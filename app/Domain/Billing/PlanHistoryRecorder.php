<?php

namespace App\Domain\Billing;

use App\Models\PlanHistory;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Plan geçmişini kullanıcının o anki aboneliğiyle eşitler: açık kayıt yoksa açar,
 * plan değiştiyse eskisini kapatıp yenisini açar, plan bittiyse kapatır. Gelir
 * payı ve limitler kayıt anındaki plandan kopyalanır.
 */
class PlanHistoryRecorder
{
    /**
     * @return string|null Ne değişti: started, changed, ended ya da null
     */
    public function sync(User $user, ?CarbonInterface $at = null): ?string
    {
        $at ??= now();
        $open = $user->planHistory()->whereNull('ends_at')->first();
        $active = $user->activeSubscription();

        if ($active === null || $active->plan === null) {
            if ($open === null) {
                return null;
            }

            $open->forceFill(['ends_at' => $this->endOf($user, $at)])->save();

            return 'ended';
        }

        if ($open !== null && $open->subscription_id === $active->id && $open->plan_id === $active->plan_id) {
            return null;
        }

        $reason = $open === null ? 'started' : 'changed';
        $open?->forceFill(['ends_at' => $at])->save();

        PlanHistory::query()->create([
            'user_id' => $user->id,
            'plan_id' => $active->plan_id,
            'subscription_id' => $active->id,
            'plan_name' => $active->plan->name,
            'revenue_share_pct' => $active->plan->revenue_share_pct,
            'release_limit' => $active->plan->release_limit,
            'artist_limit' => $active->plan->artist_limit,
            'starts_at' => $at,
            'reason' => $reason,
        ]);

        return $reason;
    }

    /**
     * Plan, son aboneliğin bittiği anda kapanır (dönem sonu ya da iptal anı).
     */
    private function endOf(User $user, CarbonInterface $fallback): CarbonInterface
    {
        /** @var Subscription|null $last */
        $last = $user->subscriptions()->getQuery()->reorder()->orderByDesc('current_period_end')->first();
        $end = $last?->ended_at ?? $last?->current_period_end;

        return $end !== null && $end->lessThan($fallback) ? Carbon::instance($end) : $fallback;
    }
}
