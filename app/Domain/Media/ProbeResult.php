<?php

namespace App\Domain\Media;

final readonly class ProbeResult
{
    public function __construct(
        public ?string $format,
        public ?string $codec,
        public ?int $sampleRate,
        public ?int $bitDepth,
        public ?int $channels,
        public ?int $durationMs,
        public bool $isFloat = false,
    ) {}
}
