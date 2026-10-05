<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\DataRequest;

class DataRequestPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, DataRequest $dataRequest): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, DataRequest $dataRequest): bool
    {
        return $this->allowed($actor);
    }

    public function delete(Admin $actor, DataRequest $dataRequest): bool
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
