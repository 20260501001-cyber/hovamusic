<?php

namespace App\Policies;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, Order $order): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, Order $order): bool
    {
        return false;
    }

    public function delete(Admin $actor, Order $order): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }

    private function allowed(Admin $actor): bool
    {
        return $actor->hasAnyRole([AdminRole::SuperAdmin->value, AdminRole::Finance->value]);
    }
}
