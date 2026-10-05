<?php

namespace App\Notifications;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Models\DataRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Veri talebi sonuçlandı. Dışa aktarmada indirme adresi 7 gün geçerlidir; silinen
 * hesapta bildirim yalnızca e-postayla gider.
 */
class DataRequestCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public const DOWNLOAD_DAYS = 7;

    public function __construct(public readonly DataRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->key();
        $mail = (new MailMessage)
            ->subject(__("notifications.privacy.{$key}.subject"))
            ->greeting($notifiable instanceof AnonymousNotifiable ? __('notifications.greeting_plain') : __('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("notifications.privacy.{$key}.line"));

        if (filled($this->request->admin_note)) {
            $mail->line('**'.__('notifications.privacy.note_label').'**')->line((string) $this->request->admin_note);
        }

        if ($key === 'export_ready') {
            $mail->action(__('notifications.privacy.export_ready.action'), $this->downloadUrl());
        }

        return $mail->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $key = $this->key();

        return [
            'kind' => 'data_request',
            'title' => __("notifications.privacy.{$key}.subject"),
            'body' => $this->request->admin_note,
            'url' => route('panel.account').'#veriler',
        ];
    }

    private function key(): string
    {
        if ($this->request->status === DataRequestStatus::Rejected) {
            return 'rejected';
        }

        return match ($this->request->type) {
            DataRequestType::Export => 'export_ready',
            DataRequestType::Deletion => 'deleted',
            DataRequestType::Correction => 'corrected',
        };
    }

    private function downloadUrl(): string
    {
        return URL::temporarySignedRoute('panel.privacy.download', now()->addDays(self::DOWNLOAD_DAYS), ['dataRequest' => $this->request->ulid]);
    }
}
