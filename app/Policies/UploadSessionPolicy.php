<?php

namespace App\Policies;

use App\Models\UploadSession;
use App\Models\User;

class UploadSessionPolicy
{
    public function update(User $user, UploadSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
