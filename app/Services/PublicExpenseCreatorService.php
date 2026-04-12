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

            $participantCount = count($data['participants']);
            $totalAmount = (float) $data['amount'];
            $baseAmount = floor($totalAmount / $participantCount * 100) / 100;

            $expense = Expense::create([
                'team_id' => $team->id,
                'created_by' => null,
                'owner_name' => $data['owner_name'],
                'owner_phone' => $data['owner_phone'],
                'description' => $data['description'],
                'total_amount' => $totalAmount,
                'amount_per_member' => $baseAmount,
                'due_date' => $data['due_date'],
                'pix_key' => $data['pix_key'],
                'pix_qr_code' => $data['pix_qr_code'] ?? null,
                'status' => 'open',
                'public_hash' => (string) Str::uuid(),
                'manage_token' => (string) Str::uuid(),
            ]);

            foreach ($data['participants'] as $index => $participant) {
                $isLast = $index === $participantCount - 1;
                $amount = $isLast
                    ? round($totalAmount - ($baseAmount * ($participantCount - 1)), 2)
                    : $baseAmount;

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
                    'amount' => $amount,
                    'due_date' => $data['due_date'],
                    'status' => 'pending',
                ]);
            }

            return $expense->load('charges.teamMember');
        });
    }
}
