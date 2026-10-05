<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ReportMapping;

/**
 * Rapor sütun eşleştirme profilleri: Süper Admin ve Finans.
 */
class ReportMappingPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function view(Admin $actor, ReportMapping $mapping): bool
    {
        return $actor->canManageFinance();
    }

    public function create(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function update(Admin $actor, ReportMapping $mapping): bool
    {
        return $actor->canManageFinance();
    }

    public function delete(Admin $actor, ReportMapping $mapping): bool
    {
        return $actor->canManageFinance() && ! $mapping->is_default;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }
}
