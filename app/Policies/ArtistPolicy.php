<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\User;

class ArtistPolicy
{
    public function update(User $user, Artist $artist): bool
    {
        return $artist->user_id === $user->id;
    }

    public function delete(User $user, Artist $artist): bool
    {
        return $artist->user_id === $user->id;
    }
}
