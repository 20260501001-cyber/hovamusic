<?php

namespace App\Domain\Media;

use RuntimeException;

class OffsetMismatch extends RuntimeException
{
    public function __construct(public readonly int $received)
    {
        parent::__construct('Parça sırası uyuşmuyor.');
    }
}
