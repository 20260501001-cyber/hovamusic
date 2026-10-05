<?php

namespace App\Enums;

enum DataRequestType: string
{
    case Export = 'export';
    case Deletion = 'deletion';
    case Correction = 'correction';

    public function label(): string
    {
        return __('privacy.request_types.'.$this->value);
    }
}
