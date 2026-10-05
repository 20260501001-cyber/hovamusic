<?php

namespace App\Models;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Bakiye defteri kaydı. Yalnızca eklenir: güncelleme ve silme modelde ve MySQL'de
 * tetikleyiciyle engellenir; düzeltme ters kayıtla yapılır. Kayıtlar Ledger servisiyle yazılır.
 */
#[Fillable(['user_id', 'bucket', 'type', 'amount_usd', 'source_type', 'source_id', 'reversal_of_id', 'group_id', 'description', 'created_by_type', 'created_by_id', 'created_at'])]
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Bakiye defteri kaydı değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Bakiye defteri kaydı silinemez.'));
    }

    protected function casts(): array
    {
        return [
            'bucket' => LedgerBucket::class,
            'type' => LedgerEntryType::class,
            'amount_usd' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
