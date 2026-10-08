<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Aviso al pagador de que uno o varios cobros han cambiado de estado. Cuando el estado pasa a
// "Cobrado" lleva ademas el recibo en PDF; en los demas estados no hay nada que adjuntar, porque
// el recibo solo documenta un cobro efectuado.
class ClaseCobroEstadoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string      $estado      Estado nuevo: Cobrado, Pendiente o Anulado.
     * @param string      $pagador     A quien va dirigido.
     * @param array       $conceptos   Lineas [alumno, importe, mes] de los cobros afectados.
     * @param float       $total       Suma de los importes.
     * @param string|null $pdf         Recibo en PDF, solo cuando el estado es Cobrado.
     * @param string|null $numeroRecibo Numero del recibo adjunto.
     */
    public function __construct(
        public readonly string $estado,
        public readonly string $pagador,
        public readonly array $conceptos,
        public readonly float $total,
        public readonly ?string $pdf = null,
        public readonly ?string $numeroRecibo = null,
    ) {}

    public function envelope(): Envelope
    {
        $marca = config('services.correo.clase_from_nombre');

        $asunto = match ($this->estado) {
            'Cobrado'  => $this->numeroRecibo
                ? "Recibo {$this->numeroRecibo} — {$marca}"
                : "Confirmación de cobro — {$marca}",
            'Anulado'  => "Cobro anulado — {$marca}",
            default    => "Cobro pendiente — {$marca}",
        };

        // El remitente solo se fija cuando hay buzon propio configurado; sin el, Laravel usa
        // MAIL_FROM_ADDRESS y el aviso sale igual.
        $from = config('services.correo.clase_from');

        return new Envelope(
            subject: $asunto,
            from: $from ? new Address($from, config('services.correo.clase_from_nombre')) : null,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.clase.cobro-estado');
    }

    public function attachments(): array
    {
        if (!$this->pdf) {
            return [];
        }

        $nombre = 'Recibo-' . str_replace('/', '-', (string) $this->numeroRecibo) . '.pdf';

        return [
            Attachment::fromData(fn() => $this->pdf, $nombre)->withMime('application/pdf'),
        ];
    }
}
