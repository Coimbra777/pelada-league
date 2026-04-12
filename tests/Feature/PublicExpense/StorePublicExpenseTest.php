<?php

namespace Tests\Feature\PublicExpense;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePublicExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_expense_participants_and_charges(): void
    {
        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras',
            'amount' => 100,
            'pix_key' => 'ana@email.com',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'participants' => [
                ['name' => 'Beto', 'phone' => '11911112222'],
                ['name' => 'Cris', 'phone' => '11933334444'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'expense' => ['id', 'public_hash', 'manage_token', 'manage_path'],
            ]);

        $this->assertDatabaseHas('expenses', [
            'description' => 'Churras',
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
        ]);

        $this->assertEquals(2, Expense::first()->charges()->count());
    }

    public function test_accepts_participants_text_instead_of_array(): void
    {
        $text = "Ze 61911112222\nLu 61933334444";

        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Pizza',
            'amount' => 80,
            'pix_key' => 'pix',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'participants_text' => $text,
        ]);

        $response->assertCreated();
        $this->assertEquals(2, Expense::first()->charges()->count());
    }
}
