<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('119########'),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'member',
        ];
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $user ??= User::factory()->create();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone ?? $attributes['phone'],
                'email' => $user->email,
            ];
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
