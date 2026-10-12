<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

// Aviso de las limpiezas de salida que no han podido atarse a su reserva.
//
// El vinculo lo pone Breezeway; cuando la tarea se crea sin enlazar, la reserva aparece en Dev.
// fianzas sin comentarios ni fotos y se decide a ciegas. Lo que se puede deducir se deduce en el
// sync; esto es lo que queda, y es lo unico que no se arregla solo.
class VmLimpiezasSinReservaMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, object> $tareas */
    public function __construct(
        public readonly Collection $tareas,
        public readonly int $ambiguas = 0,
    ) {}

    public function envelope(): Envelope
    {
        $n = $this->tareas->count();
        $texto = $n === 1 ? '1 limpieza sin reserva' : "{$n} limpiezas sin reserva";

        return new Envelope(subject: "⚠️ {$texto} — Vacation Marbella");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.vm.limpiezas-sin-reserva');
    }
}
