<?php

namespace App\Services\Asaas;

use App\Models\User;

class AsaasCustomerService
{
    public function __construct(private AsaasClient $client) {}

    public function create(User $user): string
    {
        if ($user->asaas_customer_id) {
            return $user->asaas_customer_id;
        }

        $payload = array_filter([
            'name' => $user->name,
            'email' => $user->email,
            'cpfCnpj' => $user->cpf,
            'mobilePhone' => $user->phone,
        ]);

        $response = $this->client->post('/customers', $payload);

        return $response['id'];
    }
}
