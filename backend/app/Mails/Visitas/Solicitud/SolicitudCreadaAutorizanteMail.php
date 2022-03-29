<?php

namespace App\Mails\Visitas\Solicitud;

use App\FsUtils;
use App\Models\Visitas\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudCreadaAutorizanteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Solicitud
     */
    public $solicitud;
    
    /**
     * @var object
     */
    public $autorizante;

    /**
     * @var string
     */
    public $tipoVisita;

    /**
     * @var object
     */
    public $personas;
    
    /**
     * @var string
     */
    public $baseUrlAutorizante;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($solicitud, $autorizante, $personas)
    {
        $this->solicitud = $solicitud;
        $this->autorizante = (object)$autorizante;
        $this->personas = (object)$personas;
        $this->tipoVisita = $solicitud['tipoVisita'] == 3 ? 'excepción' : 'visita';
        $this->baseUrlAutorizante = FsUtils::getBaseUrlByInstance(!empty($autorizante->PTC));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.visitas.solicitud.solicitudNuevaAutorizante')
            ->subject('[V&E - #'.$this->solicitud->Id.'] SOLICITUD DE INGRESO AL COMPLEJO INDUSTRIAL PUNTA PEREIRA PENDIENTE DE AUTORIZACIÓN');
    }
}