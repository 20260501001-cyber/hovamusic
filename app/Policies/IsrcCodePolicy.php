<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\IsrcCode;

/**
 * ISRC kayıtları yalnızca görüntülenir; kod bir kez atanır, silinmez ve değiştirilmez.
 */
class IsrcCodePolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isReviewer();
    }

    public function view(Admin $actor, IsrcCode $code): bool
    {
        return $actor->isReviewer();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, IsrcCode $code): bool
    {
        return false;
    }

    public function delete(Admin $actor, IsrcCode $code): bool
    {
        return false;
    }
}
