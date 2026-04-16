<?php

namespace App\Services;

use App\Helpers\ApiWhatsappHelper;
use App\Models\Charge;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private ApiWhatsappHelper $whatsappHelper) {}

    public function sendChargeNotification(TeamMember $member, Charge $charge, ?\App\Models\Expense $expense = null): void
    {
        $message = "Ola {$member->name}! Voce tem uma cobranca de R$ {$charge->amount} "
            ."com vencimento em {$charge->due_date->format('d/m/Y')}. "
            ."Descricao: {$charge->description}";

        if ($expense?->public_hash) {
            $message .= "\nAcesse o link para pagar: {$expense->getPublicUrl()}";
        }

        if (! $member->phone) {
            Log::info('No notification channel available for member (missing phone)', [
                'team_member_id' => $member->id,
                'charge_id' => $charge->id,
            ]);

            return;
        }

        $sent = $this->whatsappHelper->send($member->phone, $message);

        if (! $sent) {
            Log::info('WhatsApp notification not delivered (API disabled or failed)', [
                'team_member_id' => $member->id,
                'charge_id' => $charge->id,
            ]);
        }
    }
}
