<?php

namespace App\Services;

use App\Helpers\ApiWhatsappHelper;
use App\Models\Charge;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(private ApiWhatsappHelper $whatsappHelper) {}

    public function sendChargeNotification(TeamMember $member, Charge $charge, ?\App\Models\Expense $expense = null): void
    {
        $message = "Ola {$member->name}! Voce tem uma cobranca de R$ {$charge->amount} "
            . "com vencimento em {$charge->due_date->format('d/m/Y')}. "
            . "Descricao: {$charge->description}";

        if ($expense?->public_hash) {
            $message .= "\nAcesse o link para pagar: {$expense->getPublicUrl()}";
        }

        if ($member->phone) {
            $sent = $this->whatsappHelper->send($member->phone, $message);

            if ($sent) {
                return;
            }
        }

        if ($member->email) {
            try {
                Mail::raw($message, function ($mail) use ($member, $charge) {
                    $mail->to($member->email)
                        ->subject("Cobranca: {$charge->description}");
                });

                return;
            } catch (\Throwable $e) {
                Log::warning('Failed to send email notification', [
                    'team_member_id' => $member->id,
                    'charge_id' => $charge->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('No notification channel available for member', [
            'team_member_id' => $member->id,
            'charge_id' => $charge->id,
        ]);
    }
}
