<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Post;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class PostPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, Post $post): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Post $post): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, Post $post): bool
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
