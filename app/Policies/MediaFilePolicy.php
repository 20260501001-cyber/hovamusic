<?php

namespace App\Policies;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\User;

/**
 * Dosyayı sahibi ve yayın incelemesi yapan adminler indirebilir.
 */
class MediaFilePolicy
{
    public function view(User|Admin $actor, MediaFile $media): bool
    {
        if ($actor instanceof Admin) {
            return $actor->hasAnyRole([AdminRole::SuperAdmin->value, AdminRole::ReviewEditor->value]);
        }

        return $media->user_id === $actor->id;
    }
}
