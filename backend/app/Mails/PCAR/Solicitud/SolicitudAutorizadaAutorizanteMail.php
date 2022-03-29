<?php

namespace App\Mails\PCAR\Solicitud;

use App\Models\PCAR\Solicitud;
use App\Models\PCAR\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudAutorizadaAutorizanteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Solicitud
     */
    public $solicitud;

    /**
     * @var Usuario
     */
    public $usuarioAutorizante;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($solicitud, $usuarioAutorizante)
    {
        $this->solicitud = $solicitud;
        $this->usuarioAutorizante = $usuarioAutorizante;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.pcar.solicitud.solicitudAutorizadaAutorizante')
            ->subject('[PCAR] Solicitud para circular en área restringida [AUTORIZADA]');
    }
}