<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SWIFT/BIC: 4 harf banka, 2 harf ülke, 2 karakter yer, isteğe bağlı 3 karakter şube.
 */
class SwiftBic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(trim((string) $value)))) {
            $fail(__('finance.payout_page.errors.swift'));
        }
    }
}
