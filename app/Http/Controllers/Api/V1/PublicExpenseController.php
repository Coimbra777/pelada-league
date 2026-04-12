<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IdentifyMemberRequest;
use App\Http\Requests\Api\V1\StorePublicExpenseRequest;
use App\Http\Requests\Api\V1\UploadProofRequest;
use App\Http\Resources\CreatedPublicExpenseResource;
use App\Http\Resources\PaymentProofResource;
use App\Http\Resources\PublicExpenseResource;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\TeamMember;
use App\Services\PaymentProofService;
use App\Services\PublicExpenseCreatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicExpenseController extends Controller
{
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
        $expense = Expense::byHash($hash)->first();

        if (! $expense) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $expense->load('charges.teamMember');

        return response()->json([
            'expense' => new PublicExpenseResource($expense),
        ]);
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

        $validated = $request->validated();
        $name = $validated['name'];
        $phone = $validated['phone'];

        $charges = $expense->charges()
            ->whereHas('teamMember', function ($q) use ($phone) {
                $q->where('phone', $phone);
            })
            ->with('teamMember')
            ->get();

        if ($charges->isEmpty()) {
            $charges = $expense->charges()
                ->whereHas('teamMember', function ($q) use ($name) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%']);
                })
                ->with('teamMember')
                ->get();
        }

        if ($charges->isEmpty()) {
            $member = TeamMember::create([
                'team_id' => $expense->team_id,
                'user_id' => null,
                'name' => $name,
                'phone' => $phone,
                'role' => 'member',
            ]);

            $charge = Charge::create([
                'team_member_id' => $member->id,
                'expense_id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount_per_member ?? 0,
                'due_date' => $expense->due_date,
                'status' => 'pending',
            ]);

            $charge->load('teamMember');
            $charges = collect([$charge]);
        }

        return response()->json([
            'members' => $charges->map(fn ($charge) => [
                'member_id' => $charge->teamMember->id,
                'name' => $charge->teamMember->name,
                'phone' => $charge->teamMember->phone,
                'charge_id' => $charge->id,
                'amount' => $charge->amount,
                'status' => $charge->status,
            ]),
        ]);
    }

    public function uploadProof(UploadProofRequest $request, Charge $charge, PaymentProofService $service): JsonResponse
    {
        if (! $charge->expense?->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
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

        if (! $charge->paymentProofs()->exists()) {
            return response()->json(['message' => 'Upload a proof before marking as paid.'], 422);
        }

        if ($charge->status === 'proof_sent') {
            return response()->json(['message' => 'Aguardando aprovacao do responsavel.'], 422);
        }

        if (! in_array($charge->status, ['pending', 'rejected'], true)) {
            return response()->json(['message' => 'Charge already processed.'], 422);
        }

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

        if ($charge->status !== 'proof_sent') {
            return response()->json(['message' => 'Charge must have proof_sent status.'], 422);
        }

        $charge->update([
            'status' => 'validated',
            'paid_at' => now(),
        ]);

        $expenseModel = $charge->expense;
        if ($expenseModel) {
            $allValidated = $expenseModel->charges()->where('status', '!=', 'validated')->doesntExist();
            if ($allValidated) {
                $expenseModel->update(['status' => 'closed']);
            }
        }

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

        if ($charge->status !== 'proof_sent') {
            return response()->json(['message' => 'Charge must have proof_sent status.'], 422);
        }

        $charge->update(['status' => 'rejected']);

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

        if ((int) $member->team_id !== (int) $expense->team_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $charge = $expense->charges()->where('team_member_id', $member->id)->first();
        if (! $charge) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (in_array($charge->status, ['validated', 'proof_sent'], true)) {
            return response()->json(['message' => 'Participante ja enviou ou foi validado.'], 422);
        }

        $link = rtrim((string) config('app.url'), '/').'/p/'.$expense->public_hash.'/'.$member->unique_hash;
        $message = "Fala! Falta voce pagar a despesa:\n\n{$expense->description}\nValor: R$ ".number_format((float) $charge->amount, 2, ',', '.')."\n\nPague aqui:\n{$link}";

        return response()->json([
            'link' => $link,
            'message' => $message,
        ]);
    }

    private function authorizeManage(Request $request, ?Expense $expense): Expense|JsonResponse
    {
        if (! $expense || ! $expense->public_hash) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $this->authorizeManageToken($request, $expense);
    }

    private function authorizeManageToken(Request $request, Expense $expense): Expense|JsonResponse
    {
        $token = $request->input('manage_token') ?? $request->query('manage_token');
        if (! $token || ! hash_equals((string) $expense->manage_token, (string) $token)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $expense;
    }
}
