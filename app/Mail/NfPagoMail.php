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
            ->from(env('MAIL_NF_FROM_ADDRESS', 'naturefitnes@gmail.com'), 'Nature Fitness')
            ->subject('Confirmación Automática de Pago')
            ->view('emails.nf.pago');
    }
}
