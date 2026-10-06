<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ContactMessage;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class ContactMessagePolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, ContactMessage $contactMessage): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, ContactMessage $contactMessage): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, ContactMessage $contactMessage): bool
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
