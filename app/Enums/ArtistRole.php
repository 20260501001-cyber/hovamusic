<?php

namespace App\Enums;

enum ArtistRole: string
{
    case Primary = 'primary';
    case Featuring = 'featuring';

    public function label(): string
    {
        return __('release.artist_roles.'.$this->value);
    }
}
