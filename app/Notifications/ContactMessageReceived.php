<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * İletişim formu mesajı ekibin adresine gider; yanıt doğrudan gönderene yazılır.
 */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $contact) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $topic = __('site.contact.topics.'.$this->contact->topic);

        return (new MailMessage)
            ->subject(__('site.contact.mail_subject', ['topic' => $topic]))
            ->replyTo($this->contact->email, $this->contact->name)
            ->line($this->contact->name.' <'.$this->contact->email.'>')
            ->line($topic)
            ->line($this->contact->message);
    }
}
