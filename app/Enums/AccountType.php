<?php

namespace App\Enums;

enum AccountType: string
{
    case Artist = 'artist';
    case Label = 'label';

    public function label(): string
    {
        return __('account.types.'.$this->value);
    }
}
