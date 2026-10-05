<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dönem sonu yaklaşırken: abonelik yenilenecekse yenileme, iptal edildiyse bitiş hatırlatması.
 */
class SubscriptionRenewalReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->key();

        return (new MailMessage)
            ->subject(__("notifications.plan.{$key}.subject", $this->replace()))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("notifications.plan.{$key}.line", $this->replace()))
            ->action(__("notifications.plan.{$key}.action"), route('panel.plans.index'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $key = $this->key();

        return [
            'kind' => 'plan_'.$key,
            'title' => __("notifications.plan.{$key}.subject", $this->replace()),
            'body' => __("notifications.plan.{$key}.line", $this->replace()),
            'url' => route('panel.plans.index'),
        ];
    }

    private function key(): string
    {
        return $this->subscription->cancel_at_period_end ? 'ending' : 'renewing';
    }

    /**
     * @return array<string, string>
     */
    private function replace(): array
    {
        $end = $this->subscription->current_period_end?->timezone(config('hova.display_timezone'));

        return [
            'plan' => (string) $this->subscription->plan?->name,
            'date' => $end ? Format::longDate($end) : '—',
            'amount' => $this->subscription->amount !== null ? Format::money((string) $this->subscription->amount, (string) ($this->subscription->currency ?? 'USD')) : '—',
        ];
    }
}
