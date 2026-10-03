<?php

namespace App\Support;

class Brand
{
    /** "#1E5AA8" → "30, 90, 168" untuk CSS var --brand-rgb */
    public static function rgb(?string $hex): string
    {
        $hex = ltrim((string) $hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = '1e5aa8';
        }

        return implode(', ', array_map('hexdec', str_split($hex, 2)));
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/,.*$/', '', (string) $name)));

        return strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2))));
    }

    public static function timezoneLabel(string $tz): string
    {
        return match ($tz) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $tz,
        };
    }
}
