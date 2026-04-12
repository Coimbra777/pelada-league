<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(private NotificationService $notificationService) {}

    public function createExpenseAndSplit(Team $team, User $creator, array $data): Expense
    {
        $members = $team->members()->get();

        if ($members->isEmpty()) {
            throw new \DomainException('Team has no members.');
        }

        $memberCount = $members->count();
        $totalAmount = (float) $data['total_amount'];
        $baseAmount = floor($totalAmount / $memberCount * 100) / 100;

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'description' => $data['description'],
            'total_amount' => $data['total_amount'],
            'amount_per_member' => $baseAmount,
            'due_date' => $data['due_date'],
            'pix_key' => $data['pix_key'],
            'pix_qr_code' => $data['pix_qr_code'] ?? null,
            'status' => 'open',
            'public_hash' => (string) Str::uuid(),
            'manage_token' => (string) Str::uuid(),
        ]);

        foreach ($members as $index => $member) {
            $isLast = $index === $memberCount - 1;
            $amount = $isLast
                ? round($totalAmount - ($baseAmount * ($memberCount - 1)), 2)
                : $baseAmount;

            try {
                $charge = Charge::create([
                    'team_member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'expense_id' => $expense->id,
                    'description' => $data['description'],
                    'amount' => $amount,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                ]);

                $this->notificationService->sendChargeNotification($member, $charge, $expense);
            } catch (\Throwable $e) {
                Log::error('Failed to create charge for team member', [
                    'team_member_id' => $member->id,
                    'expense_id' => $expense->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expense->load('charges.teamMember');
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        $oldTotal = (float) $expense->total_amount;
        $newTotal = (float) $data['total_amount'];

        $expense->update([
            'description' => $data['description'],
            'total_amount' => $data['total_amount'],
            'due_date' => $data['due_date'],
            'pix_key' => $data['pix_key'],
            'pix_qr_code' => $data['pix_qr_code'] ?? null,
        ]);

        $totalChanged = abs($newTotal - $oldTotal) > 0.001;

        if ($totalChanged) {
            if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
                throw new \DomainException(
                    'Nao e possivel alterar o valor total enquanto houver cobranca enviada, paga ou validada.'
                );
            }
            $this->redistributeCharges($expense->fresh());
        } else {
            $expense->charges()->update([
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
        }

        return $expense->fresh()->load('charges.teamMember');
    }

    /**
     * @param  list<array{name: string, phone: string}>  $participants  phones apenas digitos
     */
    public function addParticipantsToExpense(Team $team, Expense $expense, array $participants): Expense
    {
        if ($expense->team_id !== $team->id) {
            throw new \DomainException('Despesa nao pertence a esta equipe.');
        }

        if ($expense->charges()->where('status', '!=', 'pending')->exists()) {
            throw new \DomainException(
                'So e possivel incluir participantes enquanto todas as cobrancas estao pendentes.'
            );
        }

        $newChargeIds = [];

        foreach ($participants as $p) {
            $phone = PhoneNormalizer::digits($p['phone'] ?? '');
            $name = trim((string) ($p['name'] ?? ''));
            if ($phone === '' || strlen($phone) < 10 || $name === '') {
                continue;
            }

            $member = TeamMember::where('team_id', $team->id)
                ->get()
                ->first(fn (TeamMember $m) => PhoneNormalizer::digits((string) $m->phone) === $phone);

            if (! $member) {
                $member = TeamMember::create([
                    'team_id' => $team->id,
                    'name' => $name,
                    'phone' => $phone,
                    'role' => 'member',
                ]);
            }

            if ($expense->charges()->where('team_member_id', $member->id)->exists()) {
                continue;
            }

            $charge = Charge::create([
                'team_member_id' => $member->id,
                'user_id' => $member->user_id,
                'expense_id' => $expense->id,
                'description' => $expense->description,
                'amount' => 0,
                'due_date' => $expense->due_date,
                'status' => 'pending',
            ]);
            $newChargeIds[] = $charge->id;
        }

        if ($newChargeIds === []) {
            throw new \DomainException(
                'Nenhum participante novo: verifique os telefones ou se ja estao na despesa.'
            );
        }

        $this->redistributeCharges($expense->fresh());

        foreach ($newChargeIds as $chargeId) {
            $charge = Charge::query()->with('teamMember')->find($chargeId);
            if ($charge && $charge->teamMember) {
                try {
                    $this->notificationService->sendChargeNotification(
                        $charge->teamMember,
                        $charge->fresh(),
                        $expense->fresh()
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify new charge', [
                        'charge_id' => $chargeId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $expense->fresh()->load('charges.teamMember');
    }

    private function redistributeCharges(Expense $expense): void
    {
        $charges = $expense->charges()->orderBy('id')->get();
        $count = $charges->count();
        if ($count === 0) {
            return;
        }

        $total = (float) $expense->total_amount;
        $base = floor($total / $count * 100) / 100;

        foreach ($charges as $index => $charge) {
            $isLast = $index === $count - 1;
            $amount = $isLast
                ? round($total - ($base * ($count - 1)), 2)
                : $base;

            $charge->update([
                'amount' => $amount,
                'description' => $expense->description,
                'due_date' => $expense->due_date,
            ]);
        }

        $expense->update(['amount_per_member' => $base]);
    }
}
