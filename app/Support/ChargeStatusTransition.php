<?php

namespace App\Support;

/**
 * Transições válidas de status de cobrança (MVP comprovante manual).
 *
 * @phpstan-type ChargeStatus 'pending'|'proof_sent'|'validated'|'rejected'
 */
class ChargeStatusTransition
{
    private const ALLOWED = [
        'pending' => ['proof_sent'],
        'proof_sent' => ['validated', 'rejected'],
        'rejected' => ['pending'],
        'validated' => [],
    ];

    public static function assertTransition(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = self::ALLOWED[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \DomainException(
                "Transicao de status invalida: {$from} -> {$to}."
            );
        }
    }

    /**
     * @param  iterable<int, \App\Models\Charge>  $charges
     */
    public static function assertAllPendingForRedistribution(iterable $charges): void
    {
        foreach ($charges as $charge) {
            if ($charge->status !== 'pending') {
                throw new \DomainException(
                    'Redistribuicao permitida apenas quando todas as cobrancas estao pendentes.'
                );
            }
        }
    }
}
