<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Platform;

class PlatformPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Platform $platform): bool
    {
        return $actor->isSuperAdmin();
    }

    public function reorder(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, Platform $platform): bool
    {
        return $actor->isSuperAdmin() && ! $platform->releases()->exists();
    }
}
