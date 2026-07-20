<?php

namespace App\Support;

/**
 * Pakistani mobile number helpers.
 */
final class Phone
{
    /**
     * Accepts 03XXXXXXXXX, 3XXXXXXXXX, 923XXXXXXXXX, +923XXXXXXXXX (and common
     * separators). Returns true for a plausible PK mobile number.
     */
    public static function isValidPkMobile(?string $number): bool
    {
        return self::normalize($number) !== null;
    }

    /**
     * Normalize to E.164 (+923XXXXXXXXX) or null if it is not a PK mobile.
     */
    public static function normalize(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);

        // 0300XXXXXXX -> 300XXXXXXX
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        // 92300XXXXXXX -> 300XXXXXXX
        if (str_starts_with($digits, '92')) {
            $digits = substr($digits, 2);
        }

        // PK mobile national number: 3XXXXXXXXX (10 digits, starts with 3).
        if (preg_match('/^3\d{9}$/', $digits)) {
            return '+92'.$digits;
        }

        return null;
    }
}
