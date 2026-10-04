<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ISRC: ülke (2 harf) + kayıt sahibi (3 harf/rakam) + yıl (2 rakam) + 5 rakam.
 * Tireli ya da tiresiz girilebilir; saklanırken tiresiz ve büyük harflidir.
 */
class Isrc implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalize((string) $value) === null) {
            $fail(__('release.validation.isrc_format'));
        }
    }

    public static function normalize(?string $value): ?string
    {
        $clean = strtoupper(str_replace(['-', ' '], '', (string) $value));

        return preg_match('/^[A-Z]{2}[A-Z0-9]{3}\d{2}\d{5}$/', $clean) ? $clean : null;
    }
}
