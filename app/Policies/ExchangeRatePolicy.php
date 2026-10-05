<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ExchangeRate;

/**
 * Dönem kurları: Süper Admin ve Finans.
 */
class ExchangeRatePolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function view(Admin $actor, ExchangeRate $rate): bool
    {
        return $actor->canManageFinance();
    }

    public function create(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function update(Admin $actor, ExchangeRate $rate): bool
    {
        return $actor->canManageFinance();
    }

    public function delete(Admin $actor, ExchangeRate $rate): bool
    {
        return $actor->canManageFinance();
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }
}
