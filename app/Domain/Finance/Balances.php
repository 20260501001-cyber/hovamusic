<?php

namespace App\Domain\Finance;

use Brick\Math\BigDecimal;

final readonly class Balances
{
    public function __construct(
        public BigDecimal $available,
        public BigDecimal $blocked,
        public BigDecimal $reserved,
    ) {}

    public function total(): BigDecimal
    {
        return $this->available->plus($this->blocked)->plus($this->reserved);
    }
}
