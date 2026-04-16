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
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('/^(.+?)[\s\-–—:,]+([\d\s().+\/-]+)$/u', $line, $m)) {
            $name = trim(preg_replace('/[.:,;\-–—]+$/u', '', trim($m[1]))) ?: trim($m[1]);
            $phone = PhoneNormalizer::digits($m[2]);
            if ($name !== '' && strlen($phone) >= 10) {
                return ['name' => $name, 'phone' => $phone];
            }
        }

        if (preg_match('/^(.+?)\s+(\(\d{2}\)[\d\s().+\/-]+|\d[\d\s().+\/-]{8,})$/u', $line, $m)) {
            $name = trim(preg_replace('/[.:,;\-–—]+$/u', '', trim($m[1]))) ?: trim($m[1]);
            $phone = PhoneNormalizer::digits($m[2]);
            if ($name !== '' && strlen($phone) >= 10) {
                return ['name' => $name, 'phone' => $phone];
            }
        }

        return null;
    }
}
