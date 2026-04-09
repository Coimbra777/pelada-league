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

        $charge = Charge::factory()->create([
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

        $charge = Charge::factory()->create([
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
}
