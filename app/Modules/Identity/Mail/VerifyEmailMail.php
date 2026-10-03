<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail verification link (signed URL). */
class VerifyEmailMail extends Mailable
{
    public function __construct(public readonly string $url, public readonly int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('security.mail.verify_subject', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-email');
    }
}
