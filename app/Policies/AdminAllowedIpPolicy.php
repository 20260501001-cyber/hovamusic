<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\AdminAllowedIp;

class AdminAllowedIpPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(Admin $actor, AdminAllowedIp $ip): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(Admin $actor, AdminAllowedIp $ip): bool
    {
        return $actor->isSuperAdmin();
    }
}
