<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\AuthorizesPublicExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddPublicExpenseParticipantsRequest;
use App\Http\Requests\Api\V1\IdentifyMemberRequest;
use App\Http\Requests\Api\V1\StorePublicExpenseRequest;
use App\Http\Requests\Api\V1\SubmitPublicProofRequest;
use App\Http\Requests\Api\V1\UpdatePublicExpenseRequest;
use App\Http\Requests\Api\V1\UploadProofRequest;
use App\Http\Requests\Api\V1\ValidateParticipantPublicRequest;
use App\Http\Resources\CreatedPublicExpenseResource;
use App\Http\Resources\PaymentProofResource;
use App\Http\Resources\PublicExpenseResource;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\TeamMember;
use App\Services\ExpenseService;
use App\Services\PaymentProofService;
use App\Services\PublicExpenseCreatorService;
use App\Support\ChargeStatusTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicExpenseController extends Controller
{
    use AuthorizesPublicExpense;

    public function store(StorePublicExpenseRequest $request, PublicExpenseCreatorService $creator): JsonResponse
    {
        $data = $request->validated();
        $expense = $creator->create([
            'owner_name' => $data['owner_name'],
            'owner_phone' => $data['owner_phone'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'pix_key' => $data['pix_key'],
            'pix_qr_code' => $data['pix_qr_code'] ?? null,
            'due_date' => $data['due_date'],
            'participants' => $data['participants'],
        ]);

        return response()->json([
            'expense' => new CreatedPublicExpenseResource($expense),
        ], 201);
    }

    public function show(Request $request, string $hash): JsonResponse
    {
        Log::info('Public expense lookup', ['hash' => $hash]);

        $expense = Expense::where('public_hash', $hash)
            ->with(['charges.teamMember', 'charges.paymentProofs'])
            ->firstOrFail();

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ]);
    }

    public function closeExpense(Request $request, string $hash): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $auth = $this->authorizeManageToken($request, $expense);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($expense->status === 'closed') {
            return response()->json(['message' => 'Esta despesa ja foi finalizada.'], 422);
        }

        $charges = $expense->charges()->get();
        if ($charges->isEmpty()) {
            return response()->json(['message' => 'Nao ha participantes para finalizar.'], 422);
        }

        if ($charges->contains(fn (Charge $c) => $c->status !== 'validated')) {
            return response()->json([
                'message' => 'So e possivel finalizar quando todos os participantes estiverem com pagamento validado.',
            ], 422);
        }

        $expense->update(['status' => 'closed']);
        $expense->load(['charges.teamMember', 'charges.paymentProofs']);

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ]);
    }

    public function updateExpense(UpdatePublicExpenseRequest $request, string $hash, ExpenseService $expenseService): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $auth = $this->authorizeManageToken($request, $expense);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        $data = $request->validated();
        $oldTotal = (float) $expense->total_amount;
        $totalAmount = (float) $data['amount'];
        $totalChanged = abs($totalAmount - $oldTotal) > 0.001;

        if ($totalChanged && $expense->charges()->where('status', '!=', 'pending')->exists()) {
            return response()->json([
                'message' => 'Nao e possivel alterar o valor total enquanto houver cobranca com status diferente de pendente.',
            ], 422);
        }

        $chargeCount = $expense->charges()->count();
        $amountPerMember = $chargeCount > 0
            ? floor($totalAmount / $chargeCount * 100) / 100
            : $totalAmount;

        $payload = [
            'description' => $data['description'],
            'total_amount' => $totalAmount,
            'amount_per_member' => $amountPerMember,
            'due_date' => $data['due_date'],
            'pix_key' => $data['pix_key'],
        ];
        if (array_key_exists('pix_qr_code', $data)) {
            $payload['pix_qr_code'] = $data['pix_qr_code'];
        }
        $expense->update($payload);
        $expense->refresh();

        if ($totalChanged) {
            $expenseService->redistributeChargeAmounts($expense);
        } else {
            $expense->charges()->update([
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
        }

        $expense->load(['charges.teamMember', 'charges.paymentProofs']);

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ]);
    }

    public function addParticipants(AddPublicExpenseParticipantsRequest $request, string $hash, ExpenseService $expenseService): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $auth = $this->authorizeManageToken($request, $expense);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            return response()->json([
                'message' => 'Não é possível redistribuir valores pois já existem pagamentos em andamento.',
            ], 422);
        }

        $participants = $request->input('participants', []);

        $existingPhones = $expense->charges()
            ->with('teamMember')
            ->get()
            ->map(fn (Charge $c) => preg_replace('/\D+/', '', (string) ($c->teamMember?->phone ?? '')))
            ->filter()
            ->all();

        foreach ($participants as $p) {
            $digits = $p['phone'];
            if (in_array($digits, $existingPhones, true)) {
                return response()->json([
                    'message' => 'Participante com este telefone ja existe nesta despesa.',
                ], 422);
            }
        }

        DB::transaction(function () use ($expense, $participants, $expenseService) {
            foreach ($participants as $p) {
                $member = TeamMember::create([
                    'team_id' => $expense->team_id,
                    'user_id' => null,
                    'name' => $p['name'],
                    'phone' => $p['phone'],
                    'role' => 'member',
                ]);

                Charge::create([
                    'team_member_id' => $member->id,
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => 0.0,
                    'due_date' => $expense->due_date,
                    'status' => 'pending',
                ]);
            }

            $expense->refresh();
            $expenseService->redistributeChargeAmounts($expense);
        });

        $expense->refresh()->load(['charges.teamMember', 'charges.paymentProofs']);

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ], 201);
    }

    public function validateParticipantPublic(ValidateParticipantPublicRequest $request, string $hash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        $validated = $request->validated();
        $charge = $this->findChargeForExactPublicParticipant($expense, $validated['name'], $validated['phone']);

        if ($charge === null) {
            return response()->json([
                'message' => 'Participante não encontrado nesta despesa.',
            ], 422);
        }

        $charge->refresh();

        $status = $charge->status;

        return response()->json([
            'status' => $status,
            'rejection_reason' => $charge->rejection_reason,
            'message' => $this->messageForValidateParticipantStatus($status),
            'can_submit_proof' => in_array($status, ['pending', 'rejected'], true),
        ]);
    }

    public function submitProofPublic(SubmitPublicProofRequest $request, string $hash, PaymentProofService $service): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        $validated = $request->validated();
        $charge = $this->findChargeForExactPublicParticipant($expense, $validated['name'], $validated['phone']);

        if ($charge === null) {
            return response()->json([
                'message' => 'Participante não encontrado nesta despesa.',
            ], 422);
        }

        $charge->refresh();

        if ($charge->status === 'validated') {
            return response()->json([
                'message' => 'Pagamento já confirmado.',
                'status' => 'validated',
                'rejection_reason' => null,
            ], 422);
        }

        if ($charge->status === 'proof_sent') {
            return response()->json([
                'message' => 'Comprovante já enviado.',
                'status' => 'proof_sent',
                'rejection_reason' => null,
            ], 422);
        }

        if (! in_array($charge->status, ['pending', 'rejected'], true)) {
            return response()->json([
                'message' => 'Não é possível enviar comprovante neste estado.',
                'status' => $charge->status,
                'rejection_reason' => $charge->rejection_reason,
            ], 422);
        }

        try {
            $service->uploadProof($charge, $request->file('proof'));
        } catch (\DomainException $e) {
            $fresh = $charge->fresh();

            return response()->json([
                'message' => $e->getMessage(),
                'status' => $fresh?->status ?? $charge->status,
                'rejection_reason' => $fresh?->rejection_reason,
            ], 422);
        }

        $charge->refresh();

        ChargeStatusTransition::assertTransition($charge->status, 'proof_sent');

        $charge->update(['status' => 'proof_sent']);

        $charge->refresh();

        return response()->json([
            'message' => 'Comprovante enviado. Aguardando aprovação do responsável.',
            'status' => $charge->status,
            'rejection_reason' => $charge->rejection_reason,
        ], 201);
    }

    public function showParticipant(string $hash, string $participantHash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $member = TeamMember::query()
            ->where('unique_hash', $participantHash)
            ->where('team_id', $expense->team_id)
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $charge = $expense->charges()->where('team_member_id', $member->id)->first();

        if (! $charge) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $expense->load('charges.teamMember');

        return response()->json([
            'expense' => [
                'id' => $expense->id,
                'description' => $expense->description,
                'total_amount' => $expense->total_amount,
                'amount_per_member' => $expense->amount_per_member,
                'due_date' => $expense->due_date,
                'status' => $expense->status,
                'is_closed' => $expense->status === 'closed',
                'pix_key' => $expense->pix_key,
                'pix_qr_code' => $expense->pix_qr_code,
            ],
            'participant' => [
                'id' => $member->id,
                'name' => $member->name,
                'phone' => $member->phone,
            ],
            'charge' => [
                'id' => $charge->id,
                'amount' => $charge->amount,
                'status' => $charge->status,
                'rejection_reason' => $charge->rejection_reason,
            ],
            'members' => $expense->charges->map(fn ($c) => [
                'id' => $c->teamMember?->id,
                'name' => $c->teamMember?->name,
                'phone' => $c->teamMember?->phone,
                'charge_id' => $c->id,
                'charge_status' => $c->status,
                'amount' => $c->amount,
            ]),
        ]);
    }

    public function identify(IdentifyMemberRequest $request, string $hash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        $validated = $request->validated();
        $name = $validated['name'];
        $phoneRaw = $validated['phone'];

        try {
            $charge = $this->locateParticipantChargeForPublicExpense($expense, $name, $phoneRaw);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'members' => [[
                'member_id' => $charge->teamMember?->id,
                'unique_hash' => $charge->teamMember?->unique_hash,
                'name' => $charge->teamMember?->name,
                'phone' => $charge->teamMember?->phone,
                'charge_id' => $charge->id,
                'amount' => $charge->amount,
                'status' => $charge->status,
                'rejection_reason' => $charge->rejection_reason,
            ]],
        ]);
    }

    public function uploadProof(UploadProofRequest $request, Charge $charge, PaymentProofService $service): JsonResponse
    {
        if (! $charge->expense?->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($blocked = $this->rejectIfClosed($charge->expense)) {
            return $blocked;
        }

        try {
            $proof = $service->uploadProof($charge, $request->file('file'));

            return response()->json([
                'proof' => new PaymentProofResource($proof),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function markAsPaid(Charge $charge): JsonResponse
    {
        if (! $charge->expense?->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($blocked = $this->rejectIfClosed($charge->expense)) {
            return $blocked;
        }

        if (! $charge->paymentProofs()->exists()) {
            return response()->json(['message' => 'Upload a proof before marking as paid.'], 422);
        }

        if ($charge->status === 'proof_sent') {
            return response()->json(['message' => 'Aguardando aprovacao do responsavel.'], 422);
        }

        if ($charge->status !== 'pending') {
            return response()->json([
                'message' => 'Envie um novo comprovante antes de marcar como pago (cobranca nao esta pendente).',
            ], 422);
        }

        ChargeStatusTransition::assertTransition($charge->status, 'proof_sent');

        $charge->update(['status' => 'proof_sent']);

        return response()->json([
            'message' => 'Marked as paid. Waiting for admin validation.',
            'status' => 'proof_sent',
        ]);
    }

    public function validateCharge(Request $request, Charge $charge): JsonResponse
    {
        $expense = $this->authorizeManage($request, $charge->expense);
        if ($expense instanceof JsonResponse) {
            return $expense;
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        if ($charge->status === 'validated') {
            return response()->json(['message' => 'Este pagamento ja foi validado.'], 422);
        }

        if ($charge->status !== 'proof_sent') {
            $message = match ($charge->status) {
                'rejected' => 'Comprovante rejeitado. O participante precisa enviar um novo comprovante e marcar como pago antes da validacao.',
                default => 'So e possivel validar apos o participante enviar o comprovante e marcar como pago (status aguardando aprovacao).',
            };

            return response()->json(['message' => $message], 422);
        }

        ChargeStatusTransition::assertTransition($charge->status, 'validated');

        $charge->update([
            'status' => 'validated',
            'paid_at' => now(),
            'rejection_reason' => null,
        ]);

        $expense->syncClosedStateFromCharges();

        $charge->load('teamMember');

        return response()->json([
            'charge' => [
                'id' => $charge->id,
                'status' => $charge->status,
                'member' => [
                    'id' => $charge->teamMember?->id,
                    'name' => $charge->teamMember?->name,
                ],
            ],
        ]);
    }

    public function rejectCharge(Request $request, Charge $charge): JsonResponse
    {
        $expense = $this->authorizeManage($request, $charge->expense);
        if ($expense instanceof JsonResponse) {
            return $expense;
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        if ($charge->status === 'validated') {
            return response()->json(['message' => 'Nao e possivel rejeitar um pagamento ja validado.'], 422);
        }

        if ($charge->status !== 'proof_sent') {
            $message = match ($charge->status) {
                'rejected' => 'Este comprovante ja foi rejeitado.',
                default => 'So e possivel rejeitar quando houver comprovante aguardando aprovacao.',
            };

            return response()->json(['message' => $message], 422);
        }

        ChargeStatusTransition::assertTransition($charge->status, 'rejected');

        $reasonRaw = $request->input('reason');
        $reason = is_string($reasonRaw) && trim($reasonRaw) !== ''
            ? Str::limit(trim($reasonRaw), 2000)
            : null;

        $charge->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $latestProof = $charge->latestProof();
        if ($latestProof) {
            $latestProof->update(['status' => 'rejected']);
        }

        $charge->load('teamMember');

        return response()->json([
            'charge' => [
                'id' => $charge->id,
                'status' => $charge->status,
            ],
        ]);
    }

    public function downloadProof(Request $request, Charge $charge): StreamedResponse|JsonResponse
    {
        $authorized = $this->authorizeManage($request, $charge->expense);
        if ($authorized instanceof JsonResponse) {
            return $authorized;
        }

        $proof = $charge->latestProof();
        if (! $proof) {
            return response()->json(['message' => 'No proof found.'], 404);
        }

        return Storage::disk('local')->download($proof->file_path, $proof->original_filename);
    }

    public function resendParticipantLink(Request $request, string $hash, TeamMember $member): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();
        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $auth = $this->authorizeManageToken($request, $expense);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($blocked = $this->rejectIfClosed($expense)) {
            return $blocked;
        }

        if ((int) $member->team_id !== (int) $expense->team_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $charge = $expense->charges()->where('team_member_id', $member->id)->first();
        if (! $charge) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($charge->status === 'validated') {
            return response()->json(['message' => 'Participante ja validado.'], 422);
        }

        $member->update([
            'unique_hash' => (string) Str::uuid(),
        ]);
        $member->refresh();

        $link = rtrim((string) config('app.url'), '/').'/p/'.$expense->public_hash.'/'.$member->unique_hash;
        $message = "Fala! Falta voce pagar a despesa:\n\n{$expense->description}\nValor: R$ ".number_format((float) $charge->amount, 2, ',', '.')."\n\nPague aqui:\n{$link}";

        return response()->json([
            'link' => $link,
            'message' => $message,
        ]);
    }

    /**
     * Nome (trim) e telefone (só dígitos) devem coincidir exatamente com o cadastro. Não altera dados.
     */
    private function findChargeForExactPublicParticipant(Expense $expense, string $nameInput, string $phoneRaw): ?Charge
    {
        $nameTrim = trim($nameInput);
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';

        if ($nameTrim === '' || $phoneDigits === '' || strlen($phoneDigits) < 10) {
            return null;
        }

        foreach ($expense->charges()->with('teamMember')->get() as $charge) {
            $member = $charge->teamMember;
            if ($member === null) {
                continue;
            }

            $storedDigits = preg_replace('/\D+/', '', (string) $member->phone) ?? '';
            $storedName = trim((string) $member->name);

            if ($storedDigits === $phoneDigits && $storedName === $nameTrim) {
                return $charge;
            }
        }

        return null;
    }

    private function messageForValidateParticipantStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Você ainda não enviou comprovante.',
            'proof_sent' => 'Comprovante já enviado. Aguarde aprovação.',
            'rejected' => 'Comprovante rejeitado. Envie novamente.',
            'validated' => 'Pagamento já confirmado.',
            default => 'Status desconhecido.',
        };
    }

    /**
     * Localiza cobrança por nome + telefone (mesma regra do identify), sem criar participante.
     *
     * @throws \DomainException
     */
    private function locateParticipantChargeForPublicExpense(Expense $expense, string $name, string $phoneRaw): Charge
    {
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';

        if ($phoneDigits === '' || strlen($phoneDigits) < 10) {
            throw new \DomainException('Informe um telefone valido (min. 10 digitos).');
        }

        $charges = $expense->charges()
            ->with('teamMember')
            ->get()
            ->filter(function (Charge $charge) use ($phoneDigits) {
                $m = $charge->teamMember;

                return $m && preg_replace('/\D+/', '', (string) $m->phone) === $phoneDigits;
            })
            ->values();

        if ($charges->isEmpty()) {
            $charges = $expense->charges()
                ->whereHas('teamMember', function ($q) use ($name) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%']);
                })
                ->with('teamMember')
                ->get();

            if ($charges->count() > 1 && $phoneDigits !== '') {
                $narrowed = $charges->filter(function (Charge $charge) use ($phoneDigits) {
                    $m = $charge->teamMember;

                    return $m && preg_replace('/\D+/', '', (string) $m->phone) === $phoneDigits;
                });
                if ($narrowed->isNotEmpty()) {
                    $charges = $narrowed->values();
                }
            }
        }

        if ($charges->isEmpty()) {
            throw new \DomainException('Participante não encontrado.');
        }

        return $charges->first();
    }
}
