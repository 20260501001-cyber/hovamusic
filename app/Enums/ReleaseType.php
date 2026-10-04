<?php

namespace App\Enums;

enum ReleaseType: string
{
    case Single = 'single';
    case Ep = 'ep';
    case Album = 'album';

    public function label(): string
    {
        return __('release.types.'.$this->value);
    }
}
