<?php

namespace App\Policies;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\DuplicateFlag;

class DuplicateFlagPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->reviewer($actor);
    }

    public function view(Admin $actor, DuplicateFlag $flag): bool
    {
        return $this->reviewer($actor);
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, DuplicateFlag $flag): bool
    {
        return $this->reviewer($actor);
    }

    public function delete(Admin $actor, DuplicateFlag $flag): bool
    {
        return false;
    }

    private function reviewer(Admin $actor): bool
    {
        return $actor->hasAnyRole([AdminRole::SuperAdmin->value, AdminRole::ReviewEditor->value]);
    }
}
