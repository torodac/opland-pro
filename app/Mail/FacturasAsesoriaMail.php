<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

// Envío de facturas a la asesoría, con los documentos adjuntos en un solo correo.
//
// El remitente sale de MAIL_FROM_ADDRESS (no_reply@opland.es) y no se toca aquí: si algún día
// cambia, cambia en un sitio para toda la aplicación.
class FacturasAsesoriaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $tipo      'emitidas' | 'recibidas'
     * @param array<int, array{id:int, nombre:string, ruta:string, detalle:string}> $documentos
     */
    public function __construct(
        public readonly string $tipo,
        public readonly array  $documentos,
    ) {}

    public function envelope(): Envelope
    {
        $n     = count($this->documentos);
        $que   = $this->tipo === 'emitidas' ? 'emitidas' : 'recibidas';
        $plural = $n === 1 ? 'factura' : 'facturas';

        return new Envelope(
            subject: "Opland — {$n} {$plural} {$que} (" . now()->format('d/m/Y') . ')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.opland.facturas-asesoria');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $adjuntos = [];
        foreach ($this->documentos as $d) {
            // Se comprueba en el controlador, pero un fichero puede desaparecer entre que se
            // prepara el correo y se envía; mejor un adjunto menos que un envío fallido entero.
            if (!$d['ruta'] || !Storage::disk('public')->exists($d['ruta'])) continue;

            $adjuntos[] = Attachment::fromPath(Storage::disk('public')->path($d['ruta']))
                ->as($d['nombre']);
        }

        return $adjuntos;
    }
}
