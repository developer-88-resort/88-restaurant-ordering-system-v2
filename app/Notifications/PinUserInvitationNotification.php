<?php

namespace App\Notifications;

use App\Mail\PinUserInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PinUserInvitationNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PinUserInvitationMail
    {
        return (new PinUserInvitationMail($notifiable))->to($notifiable->email);
    }
}
