<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\User;
use App\Services\Asaas\AsaasChargeService;

class ChargeService
{
    public function __construct(private AsaasChargeService $asaasChargeService) {}

    public function createCharge(User $user, array $data): Charge
    {
        if (!$user->asaas_customer_id) {
            throw new \DomainException('User does not have an Asaas customer ID.');
        }

        $payment = $this->asaasChargeService->createPixCharge(
            $user,
            $data['amount'],
            $data['due_date'],
            $data['description'],
        );

        $qrCode = $this->asaasChargeService->getPixQrCode($payment['id']);

        return Charge::create([
            'user_id' => $user->id,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'due_date' => $data['due_date'],
            'asaas_charge_id' => $payment['id'],
            'status' => $payment['status'] ?? 'PENDING',
            'pix_qr_code' => $qrCode['encodedImage'] ?? null,
            'pix_copy_paste' => $qrCode['payload'] ?? null,
            'payment_link' => $payment['invoiceUrl'] ?? null,
        ]);
    }
}
