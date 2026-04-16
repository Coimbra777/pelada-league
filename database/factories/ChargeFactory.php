<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'team_member_id' => TeamMember::factory(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 5, 1000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => 'pending',
            'paid_at' => null,
        ];
    }

    public function proofSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'proof_sent',
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validated',
            'paid_at' => now(),
        ]);
    }
}
