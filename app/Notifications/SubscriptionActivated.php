<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification implements ShouldQueue
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
        $replace = $this->replace();

        return (new MailMessage)
            ->subject(__('notifications.plan.activated.subject', $replace))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.plan.activated.line', $replace))
            ->action(__('notifications.plan.activated.action'), route('panel.plans.index'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'plan_activated',
            'title' => __('notifications.plan.activated.subject', $this->replace()),
            'body' => __('notifications.plan.activated.line', $this->replace()),
            'url' => route('panel.plans.index'),
        ];
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
        ];
    }
}
