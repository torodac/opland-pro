<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NfPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function build()
    {
        return $this->mailer('nf')
            ->from(config('services.correo.nf_from'), 'Nature Fitness')
            ->subject('Confirmación Automática de Pago')
            ->view('emails.nf.pago');
    }
}
