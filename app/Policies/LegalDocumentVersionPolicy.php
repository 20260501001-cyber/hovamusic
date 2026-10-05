<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\LegalDocumentVersion;

class LegalDocumentVersionPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(Admin $actor, LegalDocumentVersion $legalDocumentVersion): bool
    {
        return $this->allowed($actor);
    }

    public function create(Admin $actor): bool
    {
        return $this->allowed($actor);
    }

    /**
     * Yayımlanmış sürüm değiştirilmez ve silinmez; onay kayıtları ona bağlıdır.
     */
    public function update(Admin $actor, LegalDocumentVersion $legalDocumentVersion): bool
    {
        return $this->allowed($actor) && $legalDocumentVersion->published_at === null;
    }

    public function delete(Admin $actor, LegalDocumentVersion $legalDocumentVersion): bool
    {
        return $this->allowed($actor) && $legalDocumentVersion->published_at === null;
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
