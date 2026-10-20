<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Avisos de la devolución de fianzas: al huésped (importe devuelto y retención), a Borja (retención
// de reservas de Airbnb) y a Filipinas (enlace para el número de cuenta). Una sola clase con el
// asunto y la vista como datos, porque las tres dicen lo mismo con distinto texto.
class VmFianzaAvisoMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $asunto,
        public readonly string $vista,
        public readonly array $datos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: $this->vista, with: $this->datos);
    }

    /**
     * Fotos que justifican una retención. Llegan como rutas de fichero en $datos['adjuntos'], y
     * solo las que existen en disco: una ruta rota no tumba el envío.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return collect($this->datos['adjuntos'] ?? [])
            ->filter(fn(string $ruta) => is_file($ruta))
            ->map(fn(string $ruta) => Attachment::fromPath($ruta)->as(basename($ruta)))
            ->values()
            ->all();
    }
}
