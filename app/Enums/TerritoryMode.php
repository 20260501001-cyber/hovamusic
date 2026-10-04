<?php

namespace App\Enums;

enum TerritoryMode: string
{
    case Worldwide = 'worldwide';
    case Include = 'include';
    case Exclude = 'exclude';

    public function label(): string
    {
        return __('release.territory_modes.'.$this->value);
    }
}
