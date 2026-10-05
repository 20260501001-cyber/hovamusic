<?php

namespace App\Enums;

enum LedgerBucket: string
{
    case Available = 'available';
    case Blocked = 'blocked';
    case Reserved = 'reserved';

    public function label(): string
    {
        return __('finance.buckets.'.$this->value);
    }
}
