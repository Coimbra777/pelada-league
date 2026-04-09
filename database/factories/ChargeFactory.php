<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\User;
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
            'user_id' => User::factory(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 5, 1000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'asaas_charge_id' => 'pay_' . fake()->unique()->uuid(),
            'status' => 'PENDING',
            'pix_qr_code' => null,
            'pix_copy_paste' => null,
            'payment_link' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'RECEIVED',
            'paid_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CONFIRMED',
            'paid_at' => now(),
        ]);
    }

    public function withPixData(): static
    {
        return $this->state(fn (array $attributes) => [
            'pix_qr_code' => base64_encode('fake-qr-code-image'),
            'pix_copy_paste' => '00020126580014br.gov.bcb.pix0136' . fake()->uuid(),
            'payment_link' => 'https://sandbox.asaas.com/i/' . fake()->uuid(),
        ]);
    }
}
