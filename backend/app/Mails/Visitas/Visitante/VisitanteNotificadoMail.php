<?php

namespace App\Mails\Visitas\Visitante;

use App\FsUtils;
use App\Models\Visitas\Solicitud;
use App\Models\Visitas\Visitante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VisitanteNotificadoMail extends Mailable
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
        if ($this->notificacion == 'presencial') {
            $filename = 'VisitanteNotificadoPresencial';
            $title = 'Solicitud de ingreso al complejo industrial Punta Pereira pendiente de inducción presencial';
            $subject = 'SOLICITUD DE INGRESO PENDIENTE DE INDUCCIÓN PRESENCIAL';

            return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitas.visitante.' . $filename)
            ->subject('[V&E - #'.$this->solicitud->Id.'] ' . $subject)
            ->with([ "title" => $title]);
            
        } else {
            $filename = 'VisitanteNotificadoWeb';
            $title = 'Solicitud de ingreso al complejo industrial Punta Pereira pendiente de inducción web';
            $subject = 'SOLICITUD DE INGRESO PENDIENTE DE INDUCCIÓN WEB';

            if ($this->solicitud->TipoVisita == 3) {
                $induccion = '../storage/app/static/InduccionExcepcion.pdf';
            } else {
                $induccion = '../storage/app/static/InduccionVisita.pdf';
            }

            return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitas.visitante.' . $filename)
            ->subject('[V&E - #'.$this->solicitud->Id.'] ' . $subject)
            ->with([ "title" => $title])
            ->attach($induccion , [
                'as' => 'Induccion.pdf',
                'mime' => 'application/pdf',
            ]);
            
        }

    }

}