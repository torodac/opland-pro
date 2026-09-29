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
    /** @param array{sin_declarar:int, tablas_afectadas:int, campos_rotos:array<int,string>} $esquema */
    public function __construct(
        public readonly string $fecha,
        public readonly array $hallazgos,
        public readonly array $esquema = ['sin_declarar' => 0, 'tablas_afectadas' => 0, 'campos_rotos' => []],
    ) {}

    public function envelope(): Envelope
    {
        $n = count($this->hallazgos) + count($this->esquema['campos_rotos'] ?? []);
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
