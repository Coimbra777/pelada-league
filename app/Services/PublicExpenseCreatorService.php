<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicExpenseCreatorService
{
    public function __construct(private ExpenseService $expenseService) {}

    /**
     * @param  array{owner_name: string, owner_phone: string, description: string, amount: float|int|string, pix_key: string, pix_qr_code: ?string, due_date: string, participants: list<array{name: string, phone: string}>}  $data
     */
    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $team = Team::create([
                'name' => 'Grupo: '.Str::limit($data['description'], 60),
                'owner_id' => null,
            ]);

            $totalAmount = (float) $data['amount'];

            $expense = Expense::create([
                'team_id' => $team->id,
                'created_by' => null,
                'owner_name' => $data['owner_name'],
                'owner_phone' => $data['owner_phone'],
                'description' => $data['description'],
                'total_amount' => $totalAmount,
                'amount_per_member' => 0,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
                'public_hash' => (string) Str::uuid(),
                'manage_token' => (string) Str::uuid(),
            ]);

            foreach ($data['participants'] as $participant) {
                $member = TeamMember::create([
                    'team_id' => $team->id,
                    'user_id' => null,
                    'name' => $participant['name'],
                    'phone' => $participant['phone'],
                    'role' => 'member',
                ]);

                Charge::create([
                    'team_member_id' => $member->id,
                    'expense_id' => $expense->id,
                    'description' => $data['description'],
                    'amount' => 0.0,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                ]);
            }

            $this->expenseService->redistributeChargeAmounts($expense);

            return $expense->load('charges.teamMember');
        });
    }
}
