<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Başlatılan ödeme oturumu. Yalnızca kullanıcıyı "ödemen işleniyor" ekranında
 * bekletmek için tutulur; aboneliği webhook açar.
 */
#[Fillable(['plan_id', 'provider_id', 'status', 'completed_at'])]
class Checkout extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
