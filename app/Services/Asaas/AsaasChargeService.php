<?php

namespace App\Services\Asaas;

use App\Models\User;

class AsaasChargeService
{
    public function __construct(private AsaasClient $client) {}

    public function createPixCharge(User $user, float $amount, string $dueDate, string $description): array
    {
        return $this->client->post('/payments', [
            'customer' => $user->asaas_customer_id,
            'billingType' => 'PIX',
            'value' => $amount,
            'dueDate' => $dueDate,
            'description' => $description,
        ]);
    }

    public function getPixQrCode(string $paymentId): array
    {
        return $this->client->get("/payments/{$paymentId}/pixQrCode");
    }

    public function getPayment(string $paymentId): array
    {
        return $this->client->get("/payments/{$paymentId}");
    }
}
