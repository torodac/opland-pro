<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NfDocumentoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function build()
    {
        $message = $this->mailer('nf')
            ->from(env('MAIL_NF_FROM_ADDRESS', 'naturefitness@opland.es'), 'Nature Fitness')
            ->subject($this->details['title'])
            ->view('emails.nf.documento');

        if (!empty($this->details['file']) && Storage::disk('public')->exists($this->details['file'])) {
            $message->attach(Storage::disk('public')->path($this->details['file']));
        }

        return $message;
    }
}
