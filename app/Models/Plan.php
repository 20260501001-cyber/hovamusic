<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\PlanInterval;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin'in tanımladığı abonelik planı. Sanatçı ve plak şirketi hesapları yalnızca
 * kendi hedef kitlesine ait planları görür. Limit boşsa sınırsızdır.
 */
#[Fillable([
    'name', 'audience', 'interval', 'price_usd', 'release_limit', 'artist_limit', 'revenue_share_pct',
    'polar_product_id', 'description', 'features', 'is_active', 'sort',
])]
class Plan extends Model
{
    use Auditable, HasPublicUlid;

    protected $attributes = [
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'audience' => AccountType::class,
            'interval' => PlanInterval::class,
            'price_usd' => 'decimal:2',
            'revenue_share_pct' => 'decimal:2',
            'release_limit' => 'integer',
            'artist_limit' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeAvailableFor(Builder $query, AccountType $audience): void
    {
        $query->where('audience', $audience)->where('is_active', true)->whereNotNull('polar_product_id')
            ->orderBy('sort')->orderBy('price_usd');
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
