<?php

namespace App\Domain\Users;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hesabı askıya alma, banlama ve yeniden açma. Askıya alma ve banlamada sebep
 * zorunludur; her değişiklik audit log'a yazılır. Askıdaki ya da banlı kullanıcı
 * bir sonraki isteğinde oturumdan çıkarılır (EnsureAccountIsActive).
 */
class AccountStatusChanger
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function change(User $user, UserStatus $status, Admin $admin, ?string $reason = null): User
    {
        $reason = filled($reason) ? trim((string) $reason) : null;

        if ($reason === null && $status !== UserStatus::Active) {
            throw new InvalidArgumentException('Askıya alma ve banlama için sebep zorunlu.');
        }

        if ($user->status === $status) {
            return $user;
        }

        return DB::transaction(function () use ($user, $status, $admin, $reason): User {
            $old = $user->status;

            $user->forceFill([
                'status' => $status,
                'status_reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
                'status_changed_at' => now(),
            ])->save();

            if ($status !== UserStatus::Active && config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->audit->record('user.status_changed', $user, [
                'status' => ['old' => $old->value, 'new' => $status->value],
                'reason' => $reason,
            ], $admin);

            return $user;
        });
    }
}
