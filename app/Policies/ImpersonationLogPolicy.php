<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ImpersonationLog;

class ImpersonationLogPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, ImpersonationLog $log): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, ImpersonationLog $log): bool
    {
        return false;
    }

    public function delete(Admin $actor, ImpersonationLog $log): bool
    {
        return false;
    }
}
