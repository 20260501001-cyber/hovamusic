<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, Admin $admin): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Admin $admin): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, Admin $admin): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($admin);
    }
}
