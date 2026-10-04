<?php

namespace App\Domain\Releases;

use App\Enums\ReleaseStatus;
use RuntimeException;

class InvalidTransition extends RuntimeException
{
    public static function between(ReleaseStatus $from, ReleaseStatus $to, string $actor): self
    {
        return new self("{$actor}: {$from->value} -> {$to->value} geçişine izin yok.");
    }
}
