<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionEnded extends Notification implements ShouldQueue
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
        $replace = ['plan' => (string) $this->subscription->plan?->name];

        return (new MailMessage)
            ->subject(__('notifications.plan.ended.subject', $replace))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.plan.ended.line', $replace))
            ->line(__('notifications.plan.ended.blocked'))
            ->action(__('notifications.plan.ended.action'), route('panel.plans.index'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $replace = ['plan' => (string) $this->subscription->plan?->name];

        return [
            'kind' => 'plan_ended',
            'title' => __('notifications.plan.ended.subject', $replace),
            'body' => __('notifications.plan.ended.line', $replace),
            'url' => route('panel.plans.index'),
        ];
    }
}
