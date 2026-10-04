<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SpotifyMatch;

class SpotifyMatchPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isReviewer();
    }

    public function view(Admin $actor, SpotifyMatch $match): bool
    {
        return $actor->isReviewer();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, SpotifyMatch $match): bool
    {
        return $actor->isReviewer();
    }

    public function delete(Admin $actor, SpotifyMatch $match): bool
    {
        return false;
    }
}
