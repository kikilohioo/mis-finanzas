<?php

namespace App\Mails\PCAR\Solicitud;

use App\Models\PCAR\Solicitud;
use App\Models\PCAR\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudAutorizadaAprobadorMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Solicitud
     */
    public $solicitud;

    /**
     * @var Usuario
     */
    public $usuarioAprobadorAutorizante;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($solicitud, $usuarioAprobadorAutorizante)
    {
        $this->solicitud = $solicitud;
        $this->usuarioAprobadorAutorizante = $usuarioAprobadorAutorizante;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.pcar.solicitud.solicitudAutorizadaAprobador')
            ->subject('[PCAR] Solicitud para circular en área restringida [AUTORIZADA]');
    }
}