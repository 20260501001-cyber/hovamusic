<?php

namespace App\Domain\Finance;

use App\Models\PlanHistory;
use Illuminate\Support\Carbon;

/**
 * Gelir payı: satış ayının son günü açık olan plan geçmişi kaydının oranı. O gün
 * plan yoksa o güne kadarki son planın oranı; hiç plan yoksa sonradan alınan ilk
 * planın oranı. Hiç plan geçmişi yoksa oran bilinmez (uyarı).
 */
class ShareResolver
{
    /**
     * @var array<string, string|null>
     */
    private array $cache = [];

    public function shareFor(int $userId, Carbon $salesMonth): ?string
    {
        $key = $userId.':'.$salesMonth->format('Y-m');

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $monthEnd = Carbon::parse($salesMonth->format('Y-m-01'), config('hova.display_timezone'))->endOfMonth()->utc();

        $history = PlanHistory::query()
            ->where('user_id', $userId)
            ->where('starts_at', '<=', $monthEnd)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $monthEnd))
            ->orderByDesc('starts_at')
            ->first()
            ?? PlanHistory::query()->where('user_id', $userId)->where('starts_at', '<=', $monthEnd)->orderByDesc('starts_at')->first()
            ?? PlanHistory::query()->where('user_id', $userId)->orderBy('starts_at')->first();

        return $this->cache[$key] = $history !== null ? (string) $history->revenue_share_pct : null;
    }
}
