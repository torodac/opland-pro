<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InformeFallosDiarioMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array{titulo:string, cuerpo:string}> $hallazgos */
    public function __construct(
        public readonly string $fecha,
        public readonly array $hallazgos,
    ) {}

    public function envelope(): Envelope
    {
        $n = count($this->hallazgos);
        $texto = $n === 1 ? '1 cosa que revisar' : "{$n} cosas que revisar";

        return new Envelope(
            subject: "⚠️ {$texto} el {$this->fecha} — Opland PRO",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.informe-fallos-diario');
    }
}
