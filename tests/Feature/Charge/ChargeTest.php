<?php

namespace Tests\Feature\Charge;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAsaasPaymentSuccess(): void
    {
        Http::fake([
            '*/payments/pay_abc123/pixQrCode' => Http::response([
                'encodedImage' => base64_encode('fake-qr-image'),
                'payload' => '00020126580014br.gov.bcb.pix',
            ], 200),
            '*/payments' => Http::response([
                'id' => 'pay_abc123',
                'status' => 'PENDING',
                'invoiceUrl' => 'https://sandbox.asaas.com/i/abc123',
            ], 200),
        ]);
    }

    public function test_user_can_create_pix_charge_successfully(): void
    {
        $this->fakeAsaasPaymentSuccess();

        $user = User::factory()->create(['asaas_customer_id' => 'cus_123']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/charges', [
                'description' => 'Test charge',
                'amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'charge' => [
                    'id', 'description', 'amount', 'status',
                    'due_date', 'pix_qr_code', 'pix_copy_paste',
                    'payment_link', 'paid_at', 'created_at',
                ],
            ]);
    }

    public function test_charge_is_saved_in_database(): void
    {
        $this->fakeAsaasPaymentSuccess();

        $user = User::factory()->create(['asaas_customer_id' => 'cus_123']);
        $dueDate = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/charges', [
                'description' => 'Monthly payment',
                'amount' => 100.50,
                'due_date' => $dueDate,
            ]);

        $this->assertDatabaseHas('charges', [
            'user_id' => $user->id,
            'description' => 'Monthly payment',
            'amount' => '100.50',
            'asaas_charge_id' => 'pay_abc123',
            'status' => 'PENDING',
        ]);
    }

    public function test_asaas_failure_returns_error(): void
    {
        Http::fake([
            '*/payments' => Http::response(['error' => 'Server error'], 500),
        ]);

        $user = User::factory()->create(['asaas_customer_id' => 'cus_123']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/charges', [
                'description' => 'Test charge',
                'amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(500);

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_user_without_asaas_customer_id_gets_error(): void
    {
        Http::fake();

        $user = User::factory()->create(['asaas_customer_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/charges', [
                'description' => 'Test charge',
                'amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'User does not have an Asaas customer ID.']);

        Http::assertNothingSent();
    }

    public function test_webhook_updates_charge_status(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'team_member_id' => null,
            'asaas_charge_id' => 'pay_webhook_test',
            'status' => 'PENDING',
        ]);

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_webhook_test'],
        ], ['asaas-access-token' => 'test-token']);

        $response->assertOk()
            ->assertJson(['message' => 'Webhook processed.']);

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'RECEIVED',
        ]);

        $charge->refresh();
        $this->assertNotNull($charge->paid_at);
    }

    public function test_webhook_is_idempotent(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'team_member_id' => null,
            'asaas_charge_id' => 'pay_idempotent',
            'status' => 'RECEIVED',
            'paid_at' => now()->subHour(),
        ]);

        $originalPaidAt = $charge->paid_at->toDateTimeString();

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_idempotent'],
        ], ['asaas-access-token' => 'test-token']);

        $response->assertOk()
            ->assertJson(['message' => 'Already processed.']);

        $charge->refresh();
        $this->assertEquals($originalPaidAt, $charge->paid_at->toDateTimeString());
    }

    public function test_webhook_rejects_invalid_token(): void
    {
        config()->set('services.asaas.webhook_token', 'correct-token');

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_123'],
        ], ['asaas-access-token' => 'wrong-token']);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_webhook_handles_received_in_cash(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'team_member_id' => null,
            'asaas_charge_id' => 'pay_cash',
            'status' => 'PENDING',
        ]);

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED_IN_CASH',
            'payment' => ['id' => 'pay_cash'],
        ], ['asaas-access-token' => 'test-token']);

        $response->assertOk()
            ->assertJson(['message' => 'Webhook processed.']);

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'RECEIVED_IN_CASH',
        ]);

        $charge->refresh();
        $this->assertNotNull($charge->paid_at);
    }

    public function test_webhook_handles_overdue(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'team_member_id' => null,
            'asaas_charge_id' => 'pay_overdue',
            'status' => 'PENDING',
        ]);

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'pay_overdue'],
        ], ['asaas-access-token' => 'test-token']);

        $response->assertOk();

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'OVERDUE',
        ]);

        $charge->refresh();
        $this->assertNull($charge->paid_at);
    }

    public function test_webhook_does_not_override_paid_status(): void
    {
        config()->set('services.asaas.webhook_token', 'test-token');

        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'team_member_id' => null,
            'asaas_charge_id' => 'pay_already_paid',
            'status' => 'CONFIRMED',
            'paid_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/webhooks/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'pay_already_paid'],
        ], ['asaas-access-token' => 'test-token']);

        $response->assertOk()
            ->assertJson(['message' => 'Already processed.']);

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'CONFIRMED',
        ]);
    }

    public function test_sync_updates_charge_from_asaas(): void
    {
        Http::fake([
            '*/payments/pay_sync_test' => Http::response([
                'id' => 'pay_sync_test',
                'status' => 'RECEIVED',
            ], 200),
        ]);

        $user = User::factory()->create(['asaas_customer_id' => 'cus_123']);
        $charge = Charge::factory()->create([
            'user_id' => $user->id,
            'asaas_charge_id' => 'pay_sync_test',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/charges/{$charge->id}/sync");

        $response->assertOk()
            ->assertJsonPath('charge.status', 'RECEIVED');

        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'RECEIVED',
        ]);
    }

    public function test_sync_rejects_other_users_charge(): void
    {
        Http::fake();

        $owner = User::factory()->create(['asaas_customer_id' => 'cus_owner']);
        $otherUser = User::factory()->create(['asaas_customer_id' => 'cus_other']);

        $charge = Charge::factory()->create([
            'user_id' => $owner->id,
            'asaas_charge_id' => 'pay_forbidden',
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/charges/{$charge->id}/sync");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden.']);

        Http::assertNothingSent();
    }
}
