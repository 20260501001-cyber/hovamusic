<?php

namespace App\Enums;

enum RequestType: string
{
    case Correction = 'correction';
    case Takedown = 'takedown';

    public function label(): string
    {
        return __('release.request_types.'.$this->value);
    }
}
