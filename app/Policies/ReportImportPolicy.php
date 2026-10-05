<?php

namespace App\Policies;

use App\Enums\ReportImportStatus;
use App\Models\Admin;
use App\Models\ReportImport;

/**
 * Rapor içe aktarma: Süper Admin ve Finans. Onay, geri alma ve silme servis kurallarıyla sınırlıdır.
 */
class ReportImportPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function view(Admin $actor, ReportImport $import): bool
    {
        return $actor->canManageFinance();
    }

    public function create(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function update(Admin $actor, ReportImport $import): bool
    {
        return $actor->canManageFinance();
    }

    public function delete(Admin $actor, ReportImport $import): bool
    {
        return $actor->canManageFinance() && in_array($import->status, [ReportImportStatus::Uploaded, ReportImportStatus::Preview, ReportImportStatus::Failed], true);
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }
}
