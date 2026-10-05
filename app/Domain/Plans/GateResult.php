<?php

namespace App\Domain\Plans;

final readonly class GateResult
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public bool $redirectToPlans = false,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * @param  bool  $redirect  Kullanıcı plan sayfasına yönlendirilmeli mi
     */
    public static function deny(string $reason, bool $redirect = false): self
    {
        return new self(false, $reason, $redirect);
    }
}
