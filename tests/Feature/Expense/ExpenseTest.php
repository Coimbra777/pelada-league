<?php

namespace Tests\Feature\Expense;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAsaasForMembers(int $count): void
    {
        $customerSequence = Http::sequence();
        for ($i = 1; $i <= $count; $i++) {
            $customerSequence->push(['id' => "cus_auto_{$i}"], 200);
        }

        $paymentSequence = Http::sequence();
        for ($i = 1; $i <= $count; $i++) {
            $paymentSequence->push([
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
            '*/customers' => $customerSequence,
            '*/payments' => $paymentSequence,
        ]);
    }

    private function createTeamWithMembers(int $memberCount): array
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        for ($i = 1; $i < $memberCount; $i++) {
            TeamMember::create([
                'team_id' => $team->id,
                'name' => "Member {$i}",
                'phone' => "1100000000" . ($i + 1),
                'role' => 'member',
            ]);
        }

        return [$team, $admin];
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

    public function test_auto_creates_asaas_customer_for_members(): void
    {
        $this->fakeAsaasForMembers(2);
        [$team, $admin] = $this->createTeamWithMembers(2);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Auto customer test',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $members = TeamMember::where('team_id', $team->id)->get();
        foreach ($members as $member) {
            $this->assertNotNull($member->asaas_customer_id);
        }
    }

    public function test_non_admin_cannot_create_expense(): void
    {
        Http::fake();

        $admin = User::factory()->create();
        $regularUser = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $regularUser->id,
            'name' => $regularUser->name,
            'phone' => '11000000002',
            'email' => $regularUser->email,
            'role' => 'member',
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Unauthorized',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_show_expense_includes_charges_with_member(): void
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
                        '*' => ['id', 'amount', 'status', 'member'],
                    ],
                ],
            ]);
    }

    public function test_single_member_expense_works(): void
    {
        $this->fakeAsaasForMembers(1);

        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'One member',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('charges', 1);
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
