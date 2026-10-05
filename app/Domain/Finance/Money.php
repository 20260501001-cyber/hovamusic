<?php

namespace App\Domain\Finance;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * Para hesaplarında float kullanılmaz; tüm tutarlar BigDecimal'dır.
 * Defter (ledger) 6, para çekme 2, rapor satırı 10 basamak tutar.
 */
final class Money
{
    public const LEDGER_SCALE = 6;

    public const PAYOUT_SCALE = 2;

    public const LINE_SCALE = 10;

    public static function of(BigNumber|string|int|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        return BigDecimal::of($value);
    }

    public static function ledger(BigNumber|string|int|null $value): BigDecimal
    {
        return self::of($value)->toScale(self::LEDGER_SCALE, RoundingMode::HalfUp);
    }

    public static function payout(BigNumber|string|int|null $value): BigDecimal
    {
        return self::of($value)->toScale(self::PAYOUT_SCALE, RoundingMode::HalfUp);
    }

    public static function line(BigNumber|string|int|null $value): BigDecimal
    {
        return self::of($value)->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
    }
}
