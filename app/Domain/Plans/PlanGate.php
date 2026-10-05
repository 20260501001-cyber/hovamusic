<?php

namespace App\Domain\Plans;

use App\Models\Release;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Format;

/**
 * Plan ve limit kontrollerinin tek giriş noktası.
 *
 * - Gönderim ve dosya yükleme için kullanımda bir abonelik gerekir; plan bitince
 *   yeni yükleme kapanır, mevcut yayınlar yayında kalır.
 * - Yayın limiti fatura dönemine göre sayılır: dönem içinde ilk kez gönderilen her
 *   yayın (Single, EP, Albüm) 1 sayılır; reddedilse de düşmez. Düzeltme sonrası
 *   yeniden gönderim tekrar sayılmaz.
 * - Sanatçı limiti kullanıcının kayıtlı sanatçı profili sayısıdır.
 * Limit boşsa sınırsızdır.
 */
class PlanGate
{
    public function subscription(User $user): ?Subscription
    {
        return $user->activeSubscription();
    }

    public function canSubmitRelease(User $user, ?Release $release = null): GateResult
    {
        $subscription = $this->subscription($user);

        if ($subscription === null || $subscription->plan === null) {
            return GateResult::deny(__('plans.gate.no_plan_submit'), redirect: true);
        }

        if ($release?->first_submitted_at !== null) {
            return GateResult::allow();
        }

        $limit = $subscription->plan->release_limit;

        if ($limit !== null && $this->releasesUsed($user, $subscription) >= $limit) {
            return GateResult::deny(__('plans.gate.release_limit', [
                'limit' => $limit,
                'date' => $this->periodEndLabel($subscription),
            ]), redirect: true);
        }

        return GateResult::allow();
    }

    public function canUpload(User $user): GateResult
    {
        return $this->subscription($user) === null
            ? GateResult::deny(__('plans.gate.no_plan_upload'), redirect: true)
            : GateResult::allow();
    }

    public function canAddArtist(User $user): GateResult
    {
        $subscription = $this->subscription($user);

        if ($subscription === null || $subscription->plan === null) {
            return GateResult::deny(__('plans.gate.no_plan_artist'), redirect: true);
        }

        $limit = $subscription->plan->artist_limit;

        if ($limit !== null && $this->artistsUsed($user) >= $limit) {
            return GateResult::deny(__('plans.gate.artist_limit', ['limit' => $limit]), redirect: true);
        }

        return GateResult::allow();
    }

    /**
     * Plan sayfasındaki kullanım özeti.
     *
     * @return array{subscription: Subscription|null, releases_used: int, release_limit: int|null, artists_used: int, artist_limit: int|null}
     */
    public function usage(User $user): array
    {
        $subscription = $this->subscription($user);

        return [
            'subscription' => $subscription,
            'releases_used' => $subscription ? $this->releasesUsed($user, $subscription) : 0,
            'release_limit' => $subscription?->plan?->release_limit,
            'artists_used' => $this->artistsUsed($user),
            'artist_limit' => $subscription?->plan?->artist_limit,
        ];
    }

    public function releasesUsed(User $user, Subscription $subscription): int
    {
        $start = $subscription->current_period_start ?? $subscription->started_at ?? $subscription->created_at;
        $query = Release::withTrashed()->where('user_id', $user->id)->whereNotNull('first_submitted_at')
            ->where('first_submitted_at', '>=', $start);

        if ($subscription->current_period_end !== null) {
            $query->where('first_submitted_at', '<', $subscription->current_period_end);
        }

        return $query->count();
    }

    public function artistsUsed(User $user): int
    {
        return $user->artists()->count();
    }

    private function periodEndLabel(Subscription $subscription): string
    {
        return Format::longDate($subscription->current_period_end?->timezone(config('hova.display_timezone'))) ?: '—';
    }
}
