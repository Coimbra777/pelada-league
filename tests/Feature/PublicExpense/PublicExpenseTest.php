<?php

namespace Tests\Feature\PublicExpense;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function createExpenseWithCharges(): array
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        $member1 = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => 'Joao Admin',
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $member2 = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'description' => 'Churrasco',
            'total_amount' => 100.00,
            'amount_per_member' => 50.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
            'public_hash' => 'test-hash-123',
        ]);

        $charge1 = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member1->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $charge2 = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member2->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        return [$expense, $charge1, $charge2, $admin, $team];
    }

    public function test_public_expense_page_returns_data(): void
    {
        [$expense] = $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123');

        $response->assertOk()
            ->assertJsonPath('expense.description', 'Churrasco')
            ->assertJsonPath('expense.pix_key', '11999999999')
            ->assertJsonStructure([
                'expense' => ['id', 'description', 'total_amount', 'pix_key', 'pix_qr_code', 'members'],
            ]);
    }

    public function test_invalid_hash_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/expenses/invalid-hash');

        $response->assertStatus(404);
    }

    public function test_identify_member_by_name(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/identify', [
            'name' => 'Maria',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Maria Silva');
    }

    public function test_identify_unknown_name_returns_404(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/identify', [
            'name' => 'Pedro',
        ]);

        $response->assertStatus(404);
    }

    public function test_upload_proof_creates_record(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');

        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['proof' => ['id', 'original_filename', 'mime_type', 'status']]);

        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge2->id,
            'original_filename' => 'comprovante.jpg',
            'status' => 'pending',
        ]);
    }

    public function test_upload_proof_rejects_invalid_file_type(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');

        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_proof_rejects_oversized_file(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_mark_as_paid_changes_status(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        // Upload proof first
        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file]);

        // Mark as paid
        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        $response->assertOk()
            ->assertJsonPath('status', 'proof_sent');

        $this->assertDatabaseHas('charges', [
            'id' => $charge2->id,
            'status' => 'proof_sent',
        ]);
    }

    public function test_mark_as_paid_fails_without_proof(): void
    {
        [, , $charge2] = $this->createExpenseWithCharges();

        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Upload a proof before marking as paid.');
    }

    public function test_mark_as_paid_fails_for_already_processed_charge(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        // Upload and mark
        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file]);
        $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        // Try again
        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Charge already processed.');
    }
}
