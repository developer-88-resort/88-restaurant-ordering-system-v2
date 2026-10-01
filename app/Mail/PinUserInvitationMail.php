<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation an Admin or Staff account gets when it's created with an
 * email. They sign in with their name and a PIN, not a password, so there's
 * no activation link: the button opens the sign-in page. The starting PIN is
 * deliberately left out — whoever reads this email could otherwise sign in
 * as them — and is handed over by the manager who set it.
 */
class PinUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("You're invited to join :app", ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pin-user-invitation',
            with: [
                'url' => url(route('login', [], false)),
                'user' => $this->user,
            ],
        );
    }
}
