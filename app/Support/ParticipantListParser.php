<?php

namespace App\Support;

class ParticipantListParser
{
    /**
     * @return list<array{name: string, phone: string}>
     */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = self::parseLine($line);
            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return array{name: string, phone: string}|null
     */
    public static function parseLine(string $line): ?array
    {
        if (preg_match('/^(.+?)[\s\-–—]+([\d\s().-]+)$/u', $line, $m)) {
            $name = trim($m[1]);
            $phone = PhoneNormalizer::digits($m[2]);
            if ($name !== '' && strlen($phone) >= 10) {
                return ['name' => $name, 'phone' => $phone];
            }
        }

        return null;
    }
}
