<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(Admin $actor, AuditLog $log): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, AuditLog $log): bool
    {
        return false;
    }

    public function delete(Admin $actor, AuditLog $log): bool
    {
        return false;
    }
}
