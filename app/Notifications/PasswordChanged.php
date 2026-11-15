<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Short email after a password change (Account page or "Forgot password"), so the owner
 * notices if someone else changed it. Sent right away (not queued).
 */
class PasswordChanged extends Notification
{
    /** When the password was changed (shown in Philippine time). */
    public function __construct(public readonly Carbon $changedAt)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->changedAt->copy()->setTimezone('Asia/Manila')->format('F j, Y \a\t g:i A');

        return (new MailMessage)
            ->subject('Your RSYNC password was changed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your RSYNC password was changed on ' . $when . ' (Philippine time).')
            ->line("If this wasn't you, reset your password now:")
            ->action('Reset my password', route('password.request'))
            ->line('If you made this change, you can ignore this email.')
            ->salutation('— RSYNC Team');
    }
}
