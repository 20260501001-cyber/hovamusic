<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Para çekme talebi alındı, onaylandı, reddedildi ya da ödendi.
 */
class WithdrawalStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Kuyrukta beklerken durum değişebilir; bildirim olay anındaki durumu anlatır.
     */
    public readonly string $status;

    public function __construct(public readonly Withdrawal $withdrawal)
    {
        $this->status = $withdrawal->status->value;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->status;
        $mail = (new MailMessage)
            ->subject(__("notifications.withdrawal.{$key}.subject", $this->replace()))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("notifications.withdrawal.{$key}.line", $this->replace()));

        if ($key === 'rejected' && filled($this->withdrawal->reject_reason)) {
            $mail->line('**'.__('notifications.withdrawal.reason_label').'**')->line((string) $this->withdrawal->reject_reason);
        }

        return $mail->action(__('notifications.withdrawal.action'), route('panel.withdrawals.index'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $key = $this->status;

        return [
            'kind' => 'withdrawal',
            'title' => __("notifications.withdrawal.{$key}.subject", $this->replace()),
            'body' => $key === 'rejected' ? $this->withdrawal->reject_reason : null,
            'url' => route('panel.withdrawals.index'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replace(): array
    {
        return [
            'amount' => Format::money((string) $this->withdrawal->amount_usd),
            'paid' => $this->withdrawal->paid_amount !== null
                ? Format::money((string) $this->withdrawal->paid_amount, $this->withdrawal->payout_currency)
                : '—',
            'fee' => $this->withdrawal->fee_usd !== null ? Format::money((string) $this->withdrawal->fee_usd) : '—',
        ];
    }
}
