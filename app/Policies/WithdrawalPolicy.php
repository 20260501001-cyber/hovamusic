<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Withdrawal;

/**
 * Para çekme talepleri: Süper Admin ve Finans. Durum değişiklikleri servis kurallarıyla.
 */
class WithdrawalPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->canManageFinance();
    }

    public function view(Admin $actor, Withdrawal $withdrawal): bool
    {
        return $actor->canManageFinance();
    }

    public function create(Admin $actor): bool
    {
        return false;
    }

    public function update(Admin $actor, Withdrawal $withdrawal): bool
    {
        return $actor->canManageFinance();
    }

    public function delete(Admin $actor, Withdrawal $withdrawal): bool
    {
        return false;
    }

    public function deleteAny(Admin $actor): bool
    {
        return false;
    }
}
