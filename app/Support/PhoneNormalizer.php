<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function digits(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
