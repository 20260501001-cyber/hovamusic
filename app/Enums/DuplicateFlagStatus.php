<?php

namespace App\Enums;

enum DuplicateFlagStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('release.duplicate_statuses.'.$this->value);
    }
}
