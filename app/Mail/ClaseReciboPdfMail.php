<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaseReciboPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $recibo,
        public readonly string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        $num = $this->recibo->numero_factura ?? $this->recibo->numero_recibo;
        $mes = \Carbon\Carbon::parse($this->recibo->mes . '-01')->translatedFormat('F Y');
        return new Envelope(
            subject: "Factura {$num} — Academia Clase ({$mes})",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.clase.recibo', with: ['recibo' => $this->recibo]);
    }

    public function attachments(): array
    {
        $filename = 'Factura-' . str_replace('/', '-', $this->recibo->numero_factura) . '.pdf';
        return [
            Attachment::fromData(fn() => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
