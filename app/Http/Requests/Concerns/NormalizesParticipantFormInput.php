<?php

namespace App\Http\Requests\Concerns;

use App\Support\ParticipantListParser;
use App\Support\PhoneNormalizer;

trait NormalizesParticipantFormInput
{
    /**
     * Une `participants` (array) e texto colado, normaliza telefones, remove duplicatas por telefone e entradas inválidas.
     *
     * @return list<array{name: string, phone: string}>
     */
    protected function buildNormalizedParticipantsList(?array $participantsInput, string $participantsText): array
    {
        $fromArray = [];
        if (is_array($participantsInput)) {
            foreach ($participantsInput as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fromArray[] = [
                    'name' => trim((string) ($p['name'] ?? '')),
                    'phone' => PhoneNormalizer::digits($p['phone'] ?? ''),
                ];
            }
        }

        $fromText = ParticipantListParser::parse($participantsText);

        $merged = [];
        $seen = [];
        foreach (array_merge($fromArray, $fromText) as $item) {
            $phone = $item['phone'] ?? '';
            $name = $item['name'] ?? '';
            if ($phone === '' || strlen($phone) < 10 || $name === '') {
                continue;
            }
            if (isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $merged[] = ['name' => $name, 'phone' => $phone];
        }

        return $merged;
    }
}
