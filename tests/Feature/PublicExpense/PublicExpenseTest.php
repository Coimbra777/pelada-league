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
            'unique_hash' => 'participant-hash-aaa',
            'user_id' => $admin->id,
            'name' => 'Joao Admin',
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $member2 = TeamMember::create([
            'team_id' => $team->id,
            'unique_hash' => 'participant-hash-bbb',
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Churrasco',
            'total_amount' => 100.00,
            'amount_per_member' => 50.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
            'public_hash' => 'test-hash-123',
            'manage_token' => 'manage-token-secret',
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

    /**
     * @return array{0: Expense, 1: Charge}
     */
    private function createPublicExpenseWithOneParticipant500(): array
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        $member = TeamMember::create([
            'team_id' => $team->id,
            'unique_hash' => 'participant-hash-only',
            'name' => 'Unico Participante',
            'phone' => '11000000001',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Rateio',
            'total_amount' => 500.00,
            'amount_per_member' => 500.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
            'public_hash' => 'test-hash-500',
            'manage_token' => 'manage-token-secret',
        ]);

        $charge = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member->id,
            'amount' => 500.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        return [$expense, $charge];
    }

    public function test_public_expense_page_returns_data(): void
    {
        [$expense] = $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123');

        $response->assertOk()
            ->assertJsonPath('expense.description', 'Churrasco')
            ->assertJsonPath('expense.pix_key', '11999999999')
            ->assertJsonPath('expense.can_manage', false)
            ->assertJsonStructure([
                'expense' => ['id', 'description', 'total_amount', 'amount', 'amount_per_member', 'pix_key', 'pix_qr_code', 'participants', 'can_manage'],
            ]);
    }

    public function test_public_expense_with_manage_returns_members_and_can_manage(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'));

        $response->assertOk()
            ->assertJsonPath('expense.can_manage', true)
            ->assertJsonPath('expense.owner_phone', '11988887777')
            ->assertJsonCount(2, 'expense.members')
            ->assertJsonStructure([
                'expense' => ['members', 'owner_name', 'amount_per_member'],
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
            'phone' => '11000000002',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Maria Silva')
            ->assertJsonPath('members.0.unique_hash', 'participant-hash-bbb');
    }

    public function test_identify_member_by_phone(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/identify', [
            'name' => 'Qualquer',
            'phone' => '11000000002',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.name', 'Maria Silva');
    }

    public function test_identify_returns_422_when_participant_not_found(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/identify', [
            'name' => 'Pedro Novo',
            'phone' => '11999888777',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Participante nao encontrado nesta despesa.');

        $this->assertDatabaseMissing('team_members', [
            'phone' => '11999888777',
        ]);
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

        // Try again (ja em proof_sent)
        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Aguardando aprovacao do responsavel.');
    }

    public function test_second_proof_upload_is_rejected(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file]);

        $file2 = UploadedFile::fake()->create('comprovante2.jpg', 100, 'image/jpeg');
        $response = $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file2]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Comprovante ja enviado.');
    }

    public function test_participant_show_resolves_member(): void
    {
        [, , $charge2] = $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123/participants/participant-hash-bbb');

        $response->assertOk()
            ->assertJsonPath('participant.name', 'Maria Silva')
            ->assertJsonPath('charge.amount', '50.00');
    }

    public function test_public_validate_charge_with_manage_token(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file]);
        $this->postJson("/api/v1/public/charges/{$charge2->id}/mark-as-paid");

        $response = $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('charge.status', 'validated');
    }

    public function test_participate_uploads_proof_and_sets_proof_sent(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comp.jpg', 100, 'image/jpeg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/participate', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'proof_sent');

        $this->assertDatabaseHas('team_members', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ]);

        $charge = Charge::query()->where('expense_id', Expense::where('public_hash', 'test-hash-123')->value('id'))
            ->whereHas('teamMember', fn ($q) => $q->where('phone', '11000000002'))
            ->first();
        $this->assertNotNull($charge);
        $this->assertSame('proof_sent', $charge->status);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge->id,
        ]);
    }

    public function test_participate_twice_same_phone_returns_422(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $payload = [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
        ];
        $this->post('/api/v1/public/expenses/test-hash-123/participate', $payload)->assertStatus(201);

        $payload['proof'] = UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg');
        $this->post('/api/v1/public/expenses/test-hash-123/participate', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Aguardando aprovacao do responsavel.');
    }

    public function test_participate_updates_existing_member_by_phone(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comp.jpg', 100, 'image/jpeg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/participate', [
            'name' => 'Maria Atualizada',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('team_members', [
            'phone' => '11000000002',
            'name' => 'Maria Atualizada',
        ]);
    }

    public function test_public_patch_expense_with_manage_token(): void
    {
        $this->createExpenseWithCharges();
        $newDue = now()->addDays(7)->format('Y-m-d');

        $response = $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco atualizado',
            'amount' => 120,
            'due_date' => $newDue,
            'pix_key' => 'pix@novo.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('expense.description', 'Churrasco atualizado')
            ->assertJsonPath('expense.pix_key', 'pix@novo.com');

        $this->assertDatabaseHas('expenses', [
            'public_hash' => 'test-hash-123',
            'description' => 'Churrasco atualizado',
        ]);
    }

    public function test_public_patch_expense_forbidden_without_token(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123', [
            'description' => 'X',
            'amount' => 99,
            'due_date' => now()->format('Y-m-d'),
            'pix_key' => 'k',
        ])->assertForbidden();
    }

    public function test_add_public_participants_creates_charges(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco',
            'amount' => 150,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ]);

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Zeca Novo', 'phone' => '11977776666'],
            ],
        ]);

        $response->assertStatus(201);
        $expenseId = Expense::where('public_hash', 'test-hash-123')->value('id');
        $this->assertEquals(3, Charge::where('expense_id', $expenseId)->count());

        $charges = Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $this->assertEquals(150.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
    }

    public function test_add_public_participants_redistributes_full_total_evenly(): void
    {
        [$expense] = $this->createPublicExpenseWithOneParticipant500();

        $this->postJson('/api/v1/public/expenses/test-hash-500/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Segundo', 'phone' => '11977776666'],
            ],
        ])->assertStatus(201);

        $charges = Charge::where('expense_id', $expense->id)->orderBy('id')->get();
        $this->assertCount(2, $charges);
        $this->assertEquals(500.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
        $this->assertEquals(250.0, (float) $charges[0]->amount);
        $this->assertEquals(250.0, (float) $charges[1]->amount);
    }

    public function test_add_public_participants_rejects_when_payments_in_progress(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'proof_sent']);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Novo', 'phone' => '11977776666'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível redistribuir valores pois já existem pagamentos em andamento.');
    }

    public function test_add_public_participants_rejects_duplicate_phone(): void
    {
        $this->createExpenseWithCharges();
        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco',
            'amount' => 150,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ]);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Duplicado', 'phone' => '11000000002'],
            ],
        ])->assertStatus(422);
    }

    public function test_resend_link_rotates_unique_hash(): void
    {
        [, , $charge2] = $this->createExpenseWithCharges();
        $member = $charge2->teamMember;
        $old = $member->unique_hash;

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants/'.$member->id.'/resend-link', [
            'manage_token' => 'manage-token-secret',
        ])->assertOk();

        $member->refresh();
        $this->assertNotSame($old, $member->unique_hash);
    }

    public function test_resend_link_rejects_validated_participant(): void
    {
        Storage::fake('local');
        [, , $charge2] = $this->createExpenseWithCharges();
        $charge2->update(['status' => 'validated']);
        $memberId = $charge2->teamMember->id;

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants/'.$memberId.'/resend-link', [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422);
    }

    public function test_close_expense_forbidden_without_manage_token(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_close_expense_rejects_when_any_charge_not_validated(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'So e possivel finalizar quando todos os participantes estiverem com pagamento validado.');
    }

    public function test_close_expense_rejects_when_charge_is_proof_sent(): void
    {
        [, $charge1, $charge2] = $this->createExpenseWithCharges();
        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'proof_sent']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422);
    }

    public function test_close_expense_rejects_when_charge_is_rejected(): void
    {
        [, $charge1, $charge2] = $this->createExpenseWithCharges();
        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'rejected']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422);
    }

    public function test_close_expense_succeeds_when_all_charges_validated(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk()
            ->assertJsonPath('expense.status', 'closed')
            ->assertJsonPath('expense.is_closed', true);

        $this->assertDatabaseHas('expenses', [
            'public_hash' => 'test-hash-123',
            'status' => 'closed',
        ]);
    }

    public function test_close_expense_returns_422_when_already_closed(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);
        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta despesa ja foi finalizada.');
    }

    public function test_public_actions_blocked_after_expense_closed(): void
    {
        Storage::fake('local');
        [, , $charge2] = $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $msg = 'Esta despesa foi finalizada e nao aceita mais alteracoes.';

        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'X',
            'amount' => 99,
            'due_date' => now()->format('Y-m-d'),
            'pix_key' => 'k',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [['name' => 'Novo', 'phone' => '11911112222']],
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $memberId = $charge2->teamMember->id;
        $this->postJson('/api/v1/public/expenses/test-hash-123/participants/'.$memberId.'/resend-link', [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $file = UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg');
        $this->postJson("/api/v1/public/charges/{$charge2->id}/upload-proof", ['file' => $file])
            ->assertStatus(422)->assertJsonPath('message', $msg);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/reject", [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->post('/api/v1/public/expenses/test-hash-123/participate', [
            'name' => 'Visitante',
            'phone' => '11988776655',
            'proof' => UploadedFile::fake()->create('p.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonPath('message', $msg);
    }
}
