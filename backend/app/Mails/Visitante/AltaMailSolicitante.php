<?php

namespace App\Mails\Visitante;

use App\Models\Visitante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AltaMailSolicitante extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Visitante
     */
    public $visitante;

    /**
    * Create a new message instance.
    *
    * @return void
    */
    public function __construct($visitante)
    {
        $this->visitante = $visitante;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitante.AltaMailSolicitante')
            ->subject('Solicitud de visita');
    }
}