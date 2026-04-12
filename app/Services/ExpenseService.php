<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
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
}
