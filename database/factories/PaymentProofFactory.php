<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    protected $model = PaymentProof::class;

    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'file_path' => 'payment-proofs/1/proof.jpg',
            'original_filename' => 'comprovante.jpg',
            'mime_type' => 'image/jpeg',
            'extracted_data' => null,
            'status' => 'pending',
        ];
    }
}
