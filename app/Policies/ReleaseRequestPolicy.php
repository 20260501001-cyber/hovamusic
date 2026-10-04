<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ReleaseRequest;

class ReleaseRequestPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isReviewer();
    }

    public function view(Admin $actor, ReleaseRequest $request): bool
    {
        return $actor->isReviewer();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, ReleaseRequest $request): bool
    {
        return $actor->isReviewer();
    }

    public function delete(Admin $actor, ReleaseRequest $request): bool
    {
        return false;
    }
}
