<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\PostCategory;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class PostCategoryPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, PostCategory $postCategory): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, PostCategory $postCategory): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, PostCategory $postCategory): bool
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
