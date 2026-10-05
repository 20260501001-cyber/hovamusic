<?php

namespace App\Domain\Finance;

use App\Rules\Isrc;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Rapor hücrelerini normalize eder: tutar (binlik/ondalık ayıracı), adet, satış ayı,
 * ISRC, UPC ve ülke.
 */
class ReportValueParser
{
    public function __construct(private readonly string $decimalSeparator = 'auto') {}

    public function amount(mixed $value): ?BigDecimal
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return BigDecimal::of($value);
        }

        $text = preg_replace('/[^\d,.\-−]/u', '', (string) $value) ?? '';
        $text = str_replace('−', '-', $text);

        if ($text === '' || $text === '-') {
            return null;
        }

        $decimal = $this->decimalSeparator === 'auto' ? $this->guessDecimal($text) : $this->decimalSeparator;
        $thousands = $decimal === ',' ? '.' : ',';
        $text = str_replace($thousands, '', $text);
        $text = str_replace($decimal, '.', $text);

        try {
            return BigDecimal::of($text);
        } catch (MathException) {
            return null;
        }
    }

    public function quantity(mixed $value): int
    {
        $amount = $this->amount($value);

        return $amount === null ? 0 : $amount->toScale(0, RoundingMode::Down)->toInt();
    }

    /**
     * Satış ayı: "2026-07", "2026-07-31", "07/2026", "Jul 2026", Excel tarih sayısı.
     */
    public function month(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        try {
            if (preg_match('/^(\d{4})[-\/.](\d{1,2})(?:[-\/.]\d{1,2})?/', $text, $m)) {
                return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
            }

            if (preg_match('/^(\d{1,2})[-\/.](\d{4})$/', $text, $m)) {
                return Carbon::create((int) $m[2], (int) $m[1], 1)->startOfDay();
            }

            if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $text, $m)) {
                return Carbon::create((int) $m[3], (int) $m[2], 1)->startOfDay();
            }

            if (preg_match('/^\d{5}(\.\d+)?$/', $text)) {
                return Carbon::create(1899, 12, 30)->addDays((int) $text)->startOfMonth();
            }

            return Carbon::parse($text)->startOfMonth();
        } catch (Throwable) {
            return null;
        }
    }

    public function isrc(mixed $value): ?string
    {
        return Isrc::normalize(is_string($value) ? $value : (string) $value);
    }

    public function upc(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // Excel UPC'yi sayı olarak saklayınca baştaki sıfır düşer; 12 haneye tamamlanır.
        return strlen($digits) < 12 ? str_pad($digits, 12, '0', STR_PAD_LEFT) : substr($digits, 0, 14);
    }

    public function text(mixed $value, int $limit = 255): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    private function guessDecimal(string $text): string
    {
        $comma = strrpos($text, ',');
        $dot = strrpos($text, '.');

        if ($comma === false) {
            return '.';
        }

        if ($dot === false) {
            // "1,234" binlik mi ondalık mı belirsiz; virgülden sonra tam üç hane ve
            // başka virgül varsa binlik sayılır.
            return preg_match('/^\-?\d{1,3}(,\d{3})+$/', $text) && substr_count($text, ',') > 1 ? '.' : ',';
        }

        return $comma > $dot ? ',' : '.';
    }
}
