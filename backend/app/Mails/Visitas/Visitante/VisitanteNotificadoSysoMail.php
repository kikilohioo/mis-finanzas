<?php

namespace App\Mails\Visitas\Visitante;

use App\FsUtils;
use App\Models\Visitas\Solicitud;
use App\Models\Visitas\Visitante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitanteNotificadoSysoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Solicitud
     */
    public $solicitud;
     
    /**
     * @var Visitante
     */
    public $visitante;
    
    /**
     * @var string
     */
    public $notificacion;
    
    /**
     * @var string
     */
    public $tipoVisita;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($notificacion, $visitante)
    {
        $this->solicitud = $visitante['Solicitud'];
        $this->notificacion = $notificacion;
        $this->visitante = $visitante;
        $this->tipoVisita = $visitante['Solicitud']['tipoVisita'] == 3 ? 'excepción' : 'visita';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {   
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
        ->view('mails.visitas.visitante.VisitanteNotificadoPresencialSyso')
        ->subject('[V&E - #'.$this->solicitud->Id.'] SOLICITUD DE INDUCCIÓN PRESENCIAL PARA INGRESO DE EXCEPCIÓN');
    }

}