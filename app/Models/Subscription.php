<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polar aboneliğinin yerel kopyası. Yalnızca imzası doğrulanmış webhook ile
 * oluşur ve güncellenir; ödeme sonrası geri yönlendirme bir şey değiştirmez.
 */
#[Fillable([
    'plan_id', 'provider', 'provider_id', 'provider_customer_id', 'status', 'amount', 'currency', 'started_at',
    'current_period_start', 'current_period_end', 'cancel_at_period_end', 'canceled_at', 'ended_at', 'provider_updated_at',
])]
class Subscription extends Model
{
    use HasPublicUlid;

    /**
     * Dönem sonuna kadar kullanımda sayılan durumlar (iptal edilmiş ya da ödemesi
     * gecikmiş abonelik, ödenmiş dönem bitene kadar çalışır).
     *
     * @var list<SubscriptionStatus>
     */
    public const GRACE_STATUSES = [SubscriptionStatus::PastDue, SubscriptionStatus::Canceled];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'renewal_reminded_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
            'provider_updated_at' => 'datetime',
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
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        if ($this->ended_at !== null && $this->ended_at->isPast()) {
            return false;
        }

        if (in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
            // Yenileme webhook'u gecikebilir; dönem sonundan sonra 3 gün daha kullanımda sayılır.
            return $this->current_period_end === null || $this->current_period_end->greaterThan(now()->subDays(3));
        }

        return in_array($this->status, self::GRACE_STATUSES, true)
            && $this->current_period_end !== null
            && $this->current_period_end->isFuture();
    }

    /**
     * Kullanımdaki abonelikler (veritabanı sorgusu). isActive() ile aynı kuralı izler.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();

        $query->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $now))
            ->where(function (Builder $q) use ($now): void {
                $q->where(fn (Builder $a) => $a
                    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                    ->where(fn (Builder $p) => $p->whereNull('current_period_end')->orWhere('current_period_end', '>', $now->subDays(3))))
                    ->orWhere(fn (Builder $g) => $g
                        ->whereIn('status', array_map(fn (SubscriptionStatus $s): string => $s->value, self::GRACE_STATUSES))
                        ->where('current_period_end', '>', $now));
            });
    }

    public function willRenew(): bool
    {
        return $this->isActive() && ! $this->cancel_at_period_end
            && in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true);
    }
}
