<?php

namespace App\Enums;

enum IsrcSource: string
{
    case User = 'user';
    case Hova = 'hova';
    case Admin = 'admin';

    public function label(): string
    {
        return __('release.isrc_sources.'.$this->value);
    }
}
