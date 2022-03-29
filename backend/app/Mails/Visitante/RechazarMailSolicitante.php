<?php

namespace App\Mails\Visitante;

use App\Models\Usuario;
use App\Models\Visitante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RechazarMailSolicitante extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Visitante
     */
    public $visitante;

    /**
     * @var Usuario
     */
    public $usuario;

    /**
    * Create a new message instance.
    *
    * @return void
    */
    public function __construct($visitante, $usuario)
    {
        $this->visitante = $visitante;
        $this->usuario = $usuario;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitante.RechazarMailSolicitante')
            ->subject('Solicitud de visita rechazada');
    }
}