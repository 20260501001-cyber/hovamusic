<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IBAN: ülkeye göre uzunluk ve ISO 13616 mod-97 kontrol hanesi.
 */
class Iban implements ValidationRule
{
    /**
     * IBAN kullanan başlıca ülkelerde toplam uzunluk.
     */
    private const LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22,
        'BR' => 29, 'CH' => 21, 'CR' => 22, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28, 'EE' => 20,
        'EG' => 29, 'ES' => 24, 'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18,
        'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30,
        'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24,
        'ME' => 22, 'MK' => 19, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28,
        'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24,
        'SM' => 27, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VG' => 24, 'XK' => 20,
    ];

    public function __construct(private readonly ?string $country = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = self::normalize((string) $value);

        if (! self::isValid($iban)) {
            $fail(__('finance.payout_page.errors.iban'));

            return;
        }

        if ($this->country !== null && $this->country !== '' && substr($iban, 0, 2) !== strtoupper($this->country)) {
            $fail(__('finance.payout_page.errors.iban_country'));
        }
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $value));
    }

    public static function isValid(string $iban): bool
    {
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $country = substr($iban, 0, 2);

        if (isset(self::LENGTHS[$country]) && strlen($iban) !== self::LENGTHS[$country]) {
            return false;
        }

        $numeric = (string) preg_replace_callback('/[A-Z]/', fn (array $m): string => (string) (ord($m[0]) - 55), substr($iban, 4).substr($iban, 0, 4));
        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
