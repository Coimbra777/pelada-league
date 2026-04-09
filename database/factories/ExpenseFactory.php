<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'description' => fake()->sentence(),
            'total_amount' => fake()->randomFloat(2, 10, 5000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }
}
