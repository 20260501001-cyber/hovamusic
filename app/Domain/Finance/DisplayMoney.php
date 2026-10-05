<?php

namespace App\Domain\Finance;

use App\Models\User;
use App\Support\Format;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;

/**
 * Kullanıcı ekranındaki tutarlar: bakiye USD tutulur; kullanıcı başka para birimi
 * seçtiyse girilmiş en son kurla "yaklaşık" olarak çevrilir.
 */
final readonly class DisplayMoney
{
    private function __construct(
        public string $currency,
        private ?BigDecimal $rate,
        public ?CarbonImmutable $rateMonth,
    ) {}

    public static function for(User $user): self
    {
        $currency = $user->display_currency?->value ?? 'USD';
        $quote = $currency === 'USD' ? null : app(FxRates::class)->displayQuote($currency);

        return new self(
            $quote !== null ? $currency : 'USD',
            $quote['rate'] ?? null,
            $quote !== null ? CarbonImmutable::createFromFormat('!Y-m', $quote['period']) : null,
        );
    }

    /**
     * Kullanıcı USD dışında bir birim seçti ama kur girilmedi.
     */
    public static function missingRate(User $user): bool
    {
        $currency = $user->display_currency?->value ?? 'USD';

        return $currency !== 'USD' && self::for($user)->currency === 'USD';
    }

    public function approximate(): bool
    {
        return $this->rate !== null;
    }

    /**
     * Seçili birimde (kur yoksa USD) biçimlenmiş tutar.
     */
    public function format(BigNumber|string $usd): string
    {
        $amount = Money::of($usd instanceof BigNumber ? (string) $usd : $usd);

        if ($this->rate === null) {
            return Format::money($amount, 'USD');
        }

        return '≈ '.Format::money($amount->multipliedBy($this->rate), $this->currency);
    }

    public function usd(BigNumber|string $usd): string
    {
        return Format::money($usd instanceof BigNumber ? (string) $usd : $usd, 'USD');
    }
}
