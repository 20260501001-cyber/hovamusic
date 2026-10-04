<?php

namespace App\Enums;

enum SpotifyMatchStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('release.spotify_match_statuses.'.$this->value);
    }
}
