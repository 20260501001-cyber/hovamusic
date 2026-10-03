<?php

namespace App\Models\Concerns;

use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin panelinden yapılan oluşturma, güncelleme ve silme işlemlerini
 * audit_logs tablosuna yazar. Kullanıcıların kendi işlemleri burada değil,
 * ilgili akışta (ör. yayın durum geçmişi) kaydedilir.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::writeAudit($model, 'created', $model->getAttributes()));

        static::updated(function (Model $model): void {
            $changes = collect($model->getChanges())
                ->except(['updated_at', 'remember_token', 'last_login_at', 'last_login_ip'])
                ->map(fn ($new, $key) => ['old' => $model->getOriginal($key), 'new' => $new])
                ->all();

            if ($changes !== []) {
                static::writeAudit($model, 'updated', $changes);
            }
        });

        static::deleted(fn (Model $model) => static::writeAudit($model, 'deleted', []));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected static function writeAudit(Model $model, string $event, array $changes): void
    {
        if (! auth('admin')->check()) {
            return;
        }

        app(AuditLogger::class)->record(
            $model->getTable().'.'.$event,
            $model,
            collect($changes)->except(['created_at', 'updated_at'])->all(),
        );
    }
}
