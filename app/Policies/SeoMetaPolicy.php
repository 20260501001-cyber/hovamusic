<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SeoMeta;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class SeoMetaPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, SeoMeta $seoMeta): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, SeoMeta $seoMeta): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, SeoMeta $seoMeta): bool
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
