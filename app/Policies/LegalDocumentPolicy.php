<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\LegalDocument;

class LegalDocumentPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, LegalDocument $legalDocument): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, LegalDocument $legalDocument): bool
    {
        return $this->allowed($actor);
    }

    public function delete(Admin $actor, LegalDocument $legalDocument): bool
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
