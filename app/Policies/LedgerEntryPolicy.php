<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\LedgerEntry;

/**
 * Bakiye defteri yalnızca okunur; kayıtlar servislerle eklenir, hiçbir zaman değiştirilmez.
 */
class LedgerEntryPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function view(Admin $actor, LedgerEntry $entry): bool
    {
        return $actor->canManageFinance();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, LedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(Admin $actor, LedgerEntry $entry): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }
}
