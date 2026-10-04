<?php

namespace App\Notifications;

use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\ReleaseStatusLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Yayının durumu değiştiğinde kullanıcıya e-posta ve panel bildirimi.
 */
class ReleaseStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Release $release,
        public readonly ReleaseStatusLog $log,
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
        $release = $this->release->loadMissing('storeLinks.platform');
        $key = $this->messageKey();
        $replace = ['title' => $release->displayTitle()];

        $mail = (new MailMessage)
            ->subject(__("notifications.release.{$key}.subject", $replace))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__("notifications.release.{$key}.line", $replace));

        if (filled($this->log->note)) {
            $mail->line('**'.__("notifications.release.{$key}.note_label").'**')
                ->line($this->log->note);
        }

        if ($this->log->to_status === ReleaseStatus::Live && $release->storeLinks->isNotEmpty()) {
            $mail->line(__('notifications.release.live.links'));

            foreach ($release->storeLinks as $link) {
                $mail->line("[{$link->platform->name}]({$link->url})");
            }
        }

        return $mail
            ->action(__("notifications.release.{$key}.action"), $this->url())
            ->salutation(__('notifications.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $key = $this->messageKey();

        return [
            'kind' => 'release_status',
            'release' => $this->release->ulid,
            'status' => $this->log->to_status->value,
            'title' => __("notifications.release.{$key}.subject", ['title' => $this->release->displayTitle()]),
            'body' => $this->log->note,
            'url' => $this->url(),
        ];
    }

    /**
     * Kaldırma talebi reddedilip yayın önceki durumuna döndüğünde ayrı bir metin kullanılır.
     */
    private function messageKey(): string
    {
        if ($this->log->from_status === ReleaseStatus::TakedownRequested && $this->log->to_status !== ReleaseStatus::TakenDown) {
            return 'takedown_rejected';
        }

        return $this->log->to_status->value;
    }

    private function url(): string
    {
        return $this->log->to_status === ReleaseStatus::NeedsChanges
            ? route('panel.releases.edit', ['release' => $this->release->ulid, 'step' => 5])
            : route('panel.releases.show', $this->release);
    }
}
