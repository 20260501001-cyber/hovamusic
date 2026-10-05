<?php

namespace App\Console\Commands;

use App\Domain\Billing\PlanHistoryRecorder;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionRenewalReminder;
use Illuminate\Console\Command;

/**
 * Günlük abonelik kontrolü: yenileme/bitiş hatırlatması ve süresi dolan planlar.
 * Yıllık planlarda 7, aylık planlarda 3 gün önce hatırlatılır.
 */
class CheckSubscriptions extends Command
{
    protected $signature = 'hova:subscriptions';

    protected $description = 'Yaklaşan yenilemeleri hatırlatır, süresi dolan planları kapatır ve kullanıcıya bildirir.';

    public function handle(PlanHistoryRecorder $history): int
    {
        $reminded = 0;
        $ended = 0;

        Subscription::query()
            ->with(['plan', 'user'])
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->whereNull('renewal_reminded_at')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays(7)])
            ->each(function (Subscription $subscription) use (&$reminded): void {
                $days = $subscription->plan?->interval === PlanInterval::Year ? 7 : 3;

                if ($subscription->current_period_end->greaterThan(now()->addDays($days)) || $subscription->user === null) {
                    return;
                }

                $subscription->forceFill(['renewal_reminded_at' => now()])->save();
                $subscription->user->notify(new SubscriptionRenewalReminder($subscription));
                $reminded++;
            });

        Subscription::query()
            ->with(['plan', 'user'])
            ->whereNull('expiry_notified_at')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->whereNotIn('status', [SubscriptionStatus::Incomplete->value, SubscriptionStatus::IncompleteExpired->value])
            ->each(function (Subscription $subscription) use ($history, &$ended): void {
                $user = $subscription->user;

                if ($user === null || $subscription->isActive()) {
                    return;
                }

                $subscription->forceFill(['expiry_notified_at' => now()])->save();
                $history->sync($user);

                if ($user->activeSubscription() === null) {
                    $user->notify(new SubscriptionEnded($subscription));
                    $ended++;
                }
            });

        $this->components->info("Hatırlatma: {$reminded}, sona eren: {$ended}");

        return self::SUCCESS;
    }
}
