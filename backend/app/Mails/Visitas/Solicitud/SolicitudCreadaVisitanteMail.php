<?php

namespace App\Mails\Visitas\Solicitud;

use App\Models\Visitas\Solicitud;
use App\Models\Visitas\Persona;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudCreadaVisitanteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Solicitud
     */
    public $solicitud;
    
    /**
     * @var object
     */
    public $persona;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($solicitud, $persona)
    {
        $this->solicitud = $solicitud;
        $this->persona = (object)$persona;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitas.solicitud.solicitudCreadaVisitante')
            ->subject('[V&E - #'.$this->solicitud->Id.'] SOLICITUD DE INGRESO REGISTRADA');
    }
}