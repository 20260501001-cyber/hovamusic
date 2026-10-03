<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return __('account.statuses.'.$this->value);
    }
}
