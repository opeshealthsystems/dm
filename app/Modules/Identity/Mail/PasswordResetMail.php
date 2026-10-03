<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Password reset link. Translated with the locale set via Mail::to()->locale(). */
class PasswordResetMail extends Mailable
{
    public function __construct(public readonly string $url, public readonly int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('security.mail.reset_subject', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
