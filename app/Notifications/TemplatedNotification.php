<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every real notification this project sends (Implementation_plan.md
 * S2.11) — content is never hard-coded in the notification class itself, it
 * comes from an active notification_templates row per (event_key, channel,
 * locale), resolved at send time so copy can change without a deploy.
 */
abstract class TemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('emails');
    }

    abstract public function eventKey(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function data(object $notifiable): array;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = NotificationTemplate::resolve($this->eventKey(), 'mail', $this->resolveLocale($notifiable));
        $rendered = $template->render($this->data($notifiable));

        $message = (new MailMessage)->subject($rendered['subject'] ?? '');

        foreach (preg_split('/\R/', trim($rendered['body'])) as $line) {
            if ($line !== '') {
                $message->line($line);
            }
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey(),
            'data' => $this->data($notifiable),
        ];
    }

    protected function resolveLocale(object $notifiable): string
    {
        return $notifiable->locale ?? 'en';
    }
}
