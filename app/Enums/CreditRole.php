<?php

namespace App\Enums;

enum CreditRole: string
{
    case Lyricist = 'lyricist';
    case Composer = 'composer';
    case Producer = 'producer';

    public function label(): string
    {
        return __('release.credit_roles.'.$this->value);
    }
}
