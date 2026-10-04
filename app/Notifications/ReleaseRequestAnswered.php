<?php

namespace App\Notifications;

use App\Models\ReleaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Düzeltme talebi yanıtlandığında kullanıcıya e-posta ve panel bildirimi. Kaldırma
 * talebinin sonucu yayının durum değişikliğiyle bildirilir.
 */
class ReleaseRequestAnswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReleaseRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $release = $this->request->release;
        $replace = ['title' => $release->displayTitle()];

        return (new MailMessage)
            ->subject(__('notifications.request.answered.subject', $replace))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.request.answered.line', $replace))
            ->line('**'.__('notifications.request.answered.note_label').'**')
            ->line((string) $this->request->admin_note)
            ->action(__('notifications.request.answered.action'), route('panel.releases.show', $release))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $release = $this->request->release;

        return [
            'kind' => 'release_request',
            'release' => $release->ulid,
            'title' => __('notifications.request.answered.subject', ['title' => $release->displayTitle()]),
            'body' => $this->request->admin_note,
            'url' => route('panel.releases.show', $release),
        ];
    }
}
