<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Asaas\AsaasChargeService;
use App\Services\Asaas\AsaasCustomerService;

class ChargeService
{
    public function __construct(
        private AsaasChargeService $asaasChargeService,
        private AsaasCustomerService $asaasCustomerService,
    ) {}

    public function createCharge(User $user, array $data): Charge
    {
        if (!$user->asaas_customer_id) {
            throw new \DomainException('User does not have an Asaas customer ID.');
        }

        $payment = $this->asaasChargeService->createPixCharge(
            $user->asaas_customer_id,
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

    public function createChargeForMember(TeamMember $member, array $data): Charge
    {
        $asaasCustomerId = $this->asaasCustomerService->createForMember($member);

        $payment = $this->asaasChargeService->createPixCharge(
            $asaasCustomerId,
            $data['amount'],
            $data['due_date'],
            $data['description'],
        );

        $qrCode = $this->asaasChargeService->getPixQrCode($payment['id']);

        return Charge::create([
            'user_id' => $member->user_id,
            'team_member_id' => $member->id,
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

    private const PAID_STATUSES = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    public function syncCharge(Charge $charge): Charge
    {
        $payment = $this->asaasChargeService->getPayment($charge->asaas_charge_id);

        $newStatus = $payment['status'] ?? $charge->status;
        $isPaid = in_array($newStatus, self::PAID_STATUSES);

        $charge->update([
            'status' => $newStatus,
            'paid_at' => $isPaid ? ($charge->paid_at ?? now()) : $charge->paid_at,
        ]);

        return $charge->refresh();
    }
}
