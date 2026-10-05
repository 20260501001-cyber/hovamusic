<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcının plan geçmişi. Gelir payı, satış ayının son günü açık olan kayıttan
 * okunur; oran kayıt anındaki değerle saklanır, plan sonradan değişse de etkilenmez.
 */
#[Fillable([
    'user_id', 'plan_id', 'subscription_id', 'plan_name', 'revenue_share_pct', 'release_limit', 'artist_limit',
    'starts_at', 'ends_at', 'reason',
])]
class PlanHistory extends Model
{
    protected $table = 'plan_history';

    protected function casts(): array
    {
        return [
            'revenue_share_pct' => 'decimal:2',
            'release_limit' => 'integer',
            'artist_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
