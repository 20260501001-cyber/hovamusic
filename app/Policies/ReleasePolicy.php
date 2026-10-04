<?php

namespace App\Policies;

use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\User;

/**
 * Kullanıcı yalnızca kendi yayınını görür. Taslak ve düzeltme istenen yayın
 * düzenlenebilir; yalnızca hiç gönderilmemiş taslak silinebilir.
 */
class ReleasePolicy
{
    public function view(User $user, Release $release): bool
    {
        return $release->user_id === $user->id;
    }

    public function update(User $user, Release $release): bool
    {
        return $release->user_id === $user->id && $release->isEditable();
    }

    public function delete(User $user, Release $release): bool
    {
        return $release->user_id === $user->id && $release->status === ReleaseStatus::Draft;
    }
}
