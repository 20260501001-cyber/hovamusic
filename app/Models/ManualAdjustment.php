<?php

namespace App\Models;

use App\Enums\LedgerBucket;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'amount_usd', 'bucket', 'reason', 'ledger_entry_id', 'created_by'])]
class ManualAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'bucket' => LedgerBucket::class,
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
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
