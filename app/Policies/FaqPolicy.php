<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Faq;

/**
 * Site içeriği: yalnızca Süper Admin.
 */
class FaqPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, Faq $faq): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, Faq $faq): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, Faq $faq): bool
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
