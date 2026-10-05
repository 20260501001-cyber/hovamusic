<?php

namespace App\Notifications;

use App\Enums\LedgerBucket;
use App\Models\ReportImport;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EarningsAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReportImport $import,
        public readonly string $amount,
        public readonly LedgerBucket $bucket,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('notifications.earnings.subject', $this->replace()))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.earnings.line', $this->replace()));

        if ($this->bucket === LedgerBucket::Blocked) {
            $mail->line(__('notifications.earnings.blocked'));
        }

        return $mail->action(__('notifications.earnings.action'), route('panel.earnings.index'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'earnings',
            'title' => __('notifications.earnings.subject', $this->replace()),
            'body' => $this->bucket === LedgerBucket::Blocked ? __('notifications.earnings.blocked') : null,
            'url' => route('panel.earnings.index'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replace(): array
    {
        return [
            'amount' => Format::money($this->amount),
            'periods' => implode(', ', $this->import->periods ?? []),
        ];
    }
}
