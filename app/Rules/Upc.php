<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * UPC-A (12 hane) veya EAN-13 (13 hane), GTIN kontrol hanesiyle.
 */
class Upc implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = (string) $value;

        if (! preg_match('/^\d{12,13}$/', $digits)) {
            $fail(__('release.validation.upc_format', ['length' => mb_strlen($digits)]));

            return;
        }

        if (! self::hasValidCheckDigit($digits)) {
            $fail(__('release.validation.upc_check_digit'));
        }
    }

    public static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $sum = 0;

        foreach (str_split(strrev($body)) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === (int) substr($digits, -1);
    }
}
