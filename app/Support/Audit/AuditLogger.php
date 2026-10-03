<?php

namespace App\Support\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    private const MASKED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'iban',
        'account_number',
        'tax_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'remember_token',
        'token',
        'secret',
    ];

    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(string $action, ?Model $subject = null, array $changes = [], ?Authenticatable $actor = null): AuditLog
    {
        $actor ??= auth('admin')->user() ?? auth('web')->user();

        [$actorType, $actorId] = match (true) {
            $actor instanceof Admin => ['admin', $actor->getKey()],
            $actor instanceof User => ['user', $actor->getKey()],
            default => ['system', null],
        };

        $request = request();

        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $this->mask($changes),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(Str::lower($key), self::MASKED_KEYS, true)) {
                $data[$key] = '[gizli]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}
