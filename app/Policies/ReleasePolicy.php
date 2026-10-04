<?php

namespace App\Policies;

use App\Enums\ReleaseStatus;
use App\Models\Admin;
use App\Models\Release;
use App\Models\User;

/**
 * Kullanıcı yalnızca kendi yayınını görür. Taslak ve düzeltme istenen yayın
 * düzenlenebilir; yalnızca hiç gönderilmemiş taslak silinebilir.
 *
 * Admin tarafında Süper Admin ve İnceleme Editörü her yayını görür, her durumda
 * düzenler, durumunu değiştirir ve dosyalarını indirir. Finans erişemez. Admin
 * yayın oluşturmaz ve silmez.
 */
class ReleasePolicy
{
    public function viewAny(User|Admin $actor): bool
    {
        return $actor instanceof User || $actor->isReviewer();
    }

    public function view(User|Admin $actor, Release $release): bool
    {
        return $actor instanceof Admin ? $actor->isReviewer() : $release->user_id === $actor->id;
    }

    public function create(User|Admin $actor): bool
    {
        return $actor instanceof User;
    }

    public function update(User|Admin $actor, Release $release): bool
    {
        if ($actor instanceof Admin) {
            return $actor->isReviewer();
        }

        return $release->user_id === $actor->id && $release->isEditable();
    }

    public function delete(User|Admin $actor, Release $release): bool
    {
        return $actor instanceof User && $release->user_id === $actor->id && $release->status === ReleaseStatus::Draft;
    }

    public function deleteAny(User|Admin $actor): bool
    {
        return false;
    }

    public function transition(Admin $actor, Release $release): bool
    {
        return $actor->isReviewer();
    }

    public function download(Admin $actor, Release $release): bool
    {
        return $actor->isReviewer();
    }

    /**
     * Onaylanmış, mağazalara gönderilmiş ya da yayındaki yayın için düzeltme veya kaldırma talebi.
     */
    public function request(User $user, Release $release): bool
    {
        return $release->user_id === $user->id;
    }
}
