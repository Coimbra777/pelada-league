<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_abc123'], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'cpf', 'is_active', 'created_at', 'updated_at'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_successfully(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_logout_and_token_is_invalidated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Successfully logged out.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_creates_asaas_customer(): void
    {
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_abc123'], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cpf' => '12345678901',
            'phone' => '11999999999',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'asaas_customer_id' => 'cus_abc123',
        ]);
    }

    public function test_register_succeeds_even_if_asaas_fails(): void
    {
        Http::fake([
            '*/customers' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'asaas_customer_id' => null,
        ]);
    }

    public function test_register_sends_correct_payload_to_asaas(): void
    {
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_xyz789'], 200),
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cpf' => '98765432100',
            'phone' => '21888888888',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === config('services.asaas.base_url') . '/customers'
                && $request['name'] === 'Maria Silva'
                && $request['email'] === 'maria@example.com'
                && $request['cpfCnpj'] === '98765432100'
                && $request['mobilePhone'] === '21888888888';
        });
    }

    public function test_asaas_customer_is_not_created_when_already_exists(): void
    {
        Http::fake();

        $user = User::factory()->create(['asaas_customer_id' => 'cus_existing']);

        $service = app(\App\Services\Asaas\AsaasCustomerService::class);
        $customerId = $service->create($user);

        $this->assertEquals('cus_existing', $customerId);

        Http::assertNothingSent();
    }
}
