<?php

namespace Tests\Feature\Expense;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAsaasForMembers(int $count): void
    {
        $sequence = Http::sequence();
        for ($i = 1; $i <= $count; $i++) {
            $sequence->push([
                'id' => "pay_split_{$i}",
                'status' => 'PENDING',
                'invoiceUrl' => "https://sandbox.asaas.com/i/split_{$i}",
            ], 200);
        }

        Http::fake([
            '*/payments/*/pixQrCode' => Http::response([
                'encodedImage' => base64_encode('fake-qr'),
                'payload' => '00020126580014br.gov.bcb.pix',
            ], 200),
            '*/payments' => $sequence,
        ]);
    }

    private function createTeamWithMembers(int $memberCount): array
    {
        $admin = User::factory()->create(['asaas_customer_id' => 'cus_admin']);

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);

        $members = [$admin];
        for ($i = 1; $i < $memberCount; $i++) {
            $member = User::factory()->create(['asaas_customer_id' => "cus_member_{$i}"]);
            $team->members()->attach($member->id, ['role' => 'member']);
            $members[] = $member;
        }

        return [$team, $admin, $members];
    }

    public function test_expense_splits_correctly_among_members(): void
    {
        $this->fakeAsaasForMembers(3);
        [$team, $admin] = $this->createTeamWithMembers(3);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Team dinner',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'expense' => ['id', 'description', 'total_amount', 'status', 'charges'],
            ]);

        $this->assertDatabaseHas('expenses', [
            'team_id' => $team->id,
            'total_amount' => '100.00',
            'status' => 'open',
        ]);
    }

    public function test_correct_number_of_charges_created(): void
    {
        $this->fakeAsaasForMembers(4);
        [$team, $admin] = $this->createTeamWithMembers(4);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Team event',
                'total_amount' => 200.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $this->assertDatabaseCount('charges', 4);
    }

    public function test_rounding_is_handled_correctly(): void
    {
        $this->fakeAsaasForMembers(3);
        [$team, $admin] = $this->createTeamWithMembers(3);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Split test',
                'total_amount' => 10.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $charges = \App\Models\Charge::orderBy('id')->get();
        $this->assertCount(3, $charges);

        $total = $charges->sum(fn ($c) => (float) $c->amount);
        $this->assertEquals(10.00, $total);

        $this->assertEquals('3.33', $charges[0]->amount);
        $this->assertEquals('3.33', $charges[1]->amount);
        $this->assertEquals('3.34', $charges[2]->amount);
    }

    public function test_members_without_asaas_customer_id_blocks_expense(): void
    {
        Http::fake();

        $admin = User::factory()->create(['asaas_customer_id' => 'cus_admin']);
        $memberWithId = User::factory()->create(['asaas_customer_id' => 'cus_has']);
        $memberWithout = User::factory()->create(['asaas_customer_id' => null, 'name' => 'No Asaas']);

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);
        $team->members()->attach($memberWithId->id, ['role' => 'member']);
        $team->members()->attach($memberWithout->id, ['role' => 'member']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Block test',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('No Asaas', $response->json('message'));
        $this->assertDatabaseCount('charges', 0);
        Http::assertNothingSent();
    }

    public function test_non_admin_cannot_create_expense(): void
    {
        Http::fake();

        $admin = User::factory()->create(['asaas_customer_id' => 'cus_admin']);
        $member = User::factory()->create(['asaas_customer_id' => 'cus_member']);

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Unauthorized',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_show_expense_includes_charges_with_user(): void
    {
        $this->fakeAsaasForMembers(2);
        [$team, $admin] = $this->createTeamWithMembers(2);

        $createResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Show test',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $expenseId = $createResponse->json('expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/teams/{$team->id}/expenses/{$expenseId}");

        $response->assertOk()
            ->assertJsonStructure([
                'expense' => [
                    'id', 'description', 'total_amount', 'status',
                    'charges' => [
                        '*' => ['id', 'amount', 'status', 'user'],
                    ],
                ],
            ]);
    }

    public function test_expense_fails_when_no_eligible_members(): void
    {
        Http::fake();

        $admin = User::factory()->create(['asaas_customer_id' => null]);

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $team->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'No eligible',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
    }

    public function test_webhook_updates_expense_status_to_paid(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $this->fakeAsaasForMembers(2);
        [$team, $admin] = $this->createTeamWithMembers(2);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Webhook expense test',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $expense = \App\Models\Expense::first();
        $charges = \App\Models\Charge::where('expense_id', $expense->id)->get();

        // Pay all charges via webhook
        foreach ($charges as $charge) {
            $this->postJson('/api/v1/webhooks/asaas', [
                'event' => 'PAYMENT_RECEIVED',
                'payment' => ['id' => $charge->asaas_charge_id],
            ], ['asaas-access-token' => 'test-token']);
        }

        $expense->refresh();
        $this->assertEquals('PAID', $expense->status);
    }

    public function test_webhook_sets_expense_partially_paid(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $this->fakeAsaasForMembers(2);
        [$team, $admin] = $this->createTeamWithMembers(2);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Partial test',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $expense = \App\Models\Expense::first();
        $firstCharge = \App\Models\Charge::where('expense_id', $expense->id)->first();

        // Pay only the first charge
        $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => $firstCharge->asaas_charge_id],
        ], ['asaas-access-token' => 'test-token']);

        $expense->refresh();
        $this->assertEquals('PARTIALLY_PAID', $expense->status);
    }

    public function test_dashboard_returns_financial_summary(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $this->fakeAsaasForMembers(2);
        [$team, $admin] = $this->createTeamWithMembers(2);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Dashboard test',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        // Pay one charge
        $firstCharge = \App\Models\Charge::first();
        $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => $firstCharge->asaas_charge_id],
        ], ['asaas-access-token' => 'test-token']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/teams/{$team->id}/dashboard");

        $response->assertOk()
            ->assertJsonStructure([
                'total_expenses', 'total_open', 'total_paid',
                'members_paid', 'members_pending',
            ]);

        $this->assertEquals(1, $response->json('total_expenses'));
        $this->assertEquals(1, $response->json('members_paid'));
        $this->assertEquals(1, $response->json('members_pending'));
    }
}
