<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Plan;

class PlanPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, Plan $plan): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function update(Admin $actor, Plan $plan): bool
    {
        return $this->allowed($actor);
    }

    public function delete(Admin $actor, Plan $plan): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }

    private function allowed(Admin $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
