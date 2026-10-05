<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\TaxForm;
use App\Models\User;

/**
 * Vergi formu PDF'ini sahibi ve finans yetkilisi görebilir.
 */
class TaxFormPolicy
{
    public function view(Admin|User $actor, TaxForm $form): bool
    {
        if ($actor instanceof Admin) {
            return $actor->canManageFinance();
        }

        return $form->user_id === $actor->id;
    }
}
