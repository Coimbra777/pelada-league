<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExpenseService
{
    public function __construct(private ChargeService $chargeService) {}

    public function createExpenseAndSplit(Team $team, User $creator, array $data): Expense
    {
        $members = $team->members()->whereNotNull('asaas_customer_id')->get();

        $skipped = $team->members()->whereNull('asaas_customer_id')->get();
        foreach ($skipped as $member) {
            Log::warning('Skipping member without Asaas customer ID', [
                'user_id' => $member->id,
                'team_id' => $team->id,
            ]);
        }

        if ($members->isEmpty()) {
            throw new \DomainException('No eligible members to split expense.');
        }

        $memberCount = $members->count();
        $totalAmount = (float) $data['total_amount'];
        $baseAmount = floor($totalAmount / $memberCount * 100) / 100;

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'description' => $data['description'],
            'total_amount' => $data['total_amount'],
            'due_date' => $data['due_date'],
            'status' => 'open',
        ]);

        foreach ($members as $index => $member) {
            $isLast = $index === $memberCount - 1;
            $amount = $isLast
                ? round($totalAmount - ($baseAmount * ($memberCount - 1)), 2)
                : $baseAmount;

            try {
                $charge = $this->chargeService->createCharge($member, [
                    'description' => $data['description'],
                    'amount' => $amount,
                    'due_date' => $data['due_date'],
                ]);

                $charge->update(['expense_id' => $expense->id]);
            } catch (\Throwable $e) {
                Log::error('Failed to create charge for team member', [
                    'user_id' => $member->id,
                    'expense_id' => $expense->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expense->load('charges.user');
    }
}
