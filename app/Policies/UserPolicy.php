<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

/**
 * Admin panelindeki kullanıcı ekranları yalnızca Süper Admin içindir: liste,
 * detay, askıya alma, banlama ve kullanıcı olarak görüntüleme.
 */
class UserPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, User $user): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }

    public function impersonate(Admin $actor, User $user): bool
    {
        return $actor->isSuperAdmin() && ! $user->trashed();
    }
}
