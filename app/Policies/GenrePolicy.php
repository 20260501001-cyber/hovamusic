<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Genre;

class GenrePolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Genre $genre): bool
    {
        return $actor->isSuperAdmin();
    }

    public function reorder(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Kullanılan ya da alt türü olan tür silinmez; pasife alınır.
     */
    public function delete(Admin $actor, Genre $genre): bool
    {
        return $actor->isSuperAdmin()
            && ! $genre->children()->exists()
            && ! $genre->isUsed();
    }
}
