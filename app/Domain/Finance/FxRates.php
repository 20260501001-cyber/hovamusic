<?php

namespace App\Domain\Finance;

use App\Models\ExchangeRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Admin'in girdiği dönem kurları. Rapor tutarları satış ayının kuruyla USD'ye çevrilir;
 * kullanıcı ekranındaki TRY/EUR gösterimi girilmiş en son kurla yaklaşık yapılır.
 */
class FxRates
{
    /**
     * @var array<string, BigDecimal|null>
     */
    private array $cache = [];

    /**
     * 1 birim $currency kaç USD (dönem: YYYY-MM). Kur yoksa null.
     */
    public function toUsd(string $currency, string $period): ?BigDecimal
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return BigDecimal::one();
        }

        $key = $currency.':'.$period;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $direct = ExchangeRate::query()->where('period', $period)->where('from_currency', $currency)->where('to_currency', 'USD')->value('rate');

        if ($direct !== null) {
            return $this->cache[$key] = BigDecimal::of((string) $direct);
        }

        $inverse = ExchangeRate::query()->where('period', $period)->where('from_currency', 'USD')->where('to_currency', $currency)->value('rate');

        return $this->cache[$key] = $inverse !== null && BigDecimal::of((string) $inverse)->isPositive()
            ? BigDecimal::one()->dividedBy((string) $inverse, 10, RoundingMode::HalfUp)
            : null;
    }

    /**
     * Gösterim için USD → $currency en son kur ve dönemi. Kur yoksa null (USD gösterilir).
     *
     * @return array{rate: BigDecimal, period: string}|null
     */
    public function displayQuote(string $currency): ?array
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return ['rate' => BigDecimal::one(), 'period' => now()->format('Y-m')];
        }

        $direct = ExchangeRate::query()->where('from_currency', 'USD')->where('to_currency', $currency)->orderByDesc('period')->first(['rate', 'period']);
        $inverse = ExchangeRate::query()->where('from_currency', $currency)->where('to_currency', 'USD')->orderByDesc('period')->first(['rate', 'period']);

        if ($inverse !== null && ($direct === null || $inverse->period > $direct->period) && BigDecimal::of((string) $inverse->rate)->isPositive()) {
            return ['rate' => BigDecimal::one()->dividedBy((string) $inverse->rate, 10, RoundingMode::HalfUp), 'period' => $inverse->period];
        }

        return $direct !== null ? ['rate' => BigDecimal::of((string) $direct->rate), 'period' => $direct->period] : null;
    }
}
