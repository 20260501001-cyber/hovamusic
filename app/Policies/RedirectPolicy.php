<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Redirect;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class RedirectPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, Redirect $redirect): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Redirect $redirect): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, Redirect $redirect): bool
    {
        return $actor->isSuperAdmin();
    }

    public function deleteAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function reorder(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
