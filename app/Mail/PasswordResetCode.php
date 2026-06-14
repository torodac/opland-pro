<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $link,
        public readonly string $appName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperación de contraseña — ' . $this->appName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-code');
    }
}
