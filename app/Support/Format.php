<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * tr-TR biçimleri: süre "3:42", boyut "24,6 MB", örnekleme hızı "44,1 kHz",
 * tarih metinde "14 Kasım 2026", tabloda "14.11.2026", para "$1.234,56".
 */
class Format
{
    public static function duration(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '';
        }

        $seconds = (int) round($milliseconds / 1000);

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return self::decimal($value, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public static function kiloHertz(int $hertz): string
    {
        return self::decimal($hertz / 1000, 2).' kHz';
    }

    public static function decimal(float $value, int $fraction = 1): string
    {
        $formatter = new NumberFormatter('tr_TR', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $fraction);

        return (string) $formatter->format($value);
    }

    public static function longDate(?CarbonInterface $date): string
    {
        return $date ? $date->locale('tr')->translatedFormat('j F Y') : '';
    }

    public static function shortDate(?CarbonInterface $date): string
    {
        return $date ? $date->format('d.m.Y') : '';
    }

    /**
     * Para tutarı, float'a çevrilmeden: "$1.234,56", "₺1.234,56", "€1.234,56".
     */
    public static function money(BigNumber|string|int $amount, string $currency = 'USD', int $scale = 2): string
    {
        $value = BigDecimal::of($amount)->toScale($scale, RoundingMode::HalfUp);
        $negative = $value->isNegative();
        [$integer, $fraction] = array_pad(explode('.', $value->abs()->toString(), 2), 2, '');
        $integer = strrev(implode('.', str_split(strrev($integer), 3)));
        $number = $integer.($scale > 0 ? ','.$fraction : '');
        $currency = strtoupper($currency);
        $symbol = ['USD' => '$', 'EUR' => '€', 'TRY' => '₺', 'GBP' => '£'][$currency] ?? null;

        return ($negative ? '−' : '').($symbol !== null ? $symbol.$number : $number.' '.$currency);
    }

    public static function position(int $position): string
    {
        return str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }
}
