<?php

namespace App\Mails\PTC;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PTCFinalizarMediciones extends Mailable
{
    use Queueable, SerializesModels;
    
    /**
     * @var object
     */
    public $Args;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($Args)
    {
        $this->Args = (object)$Args;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.ptc.ptcfinalizarmediciones')
            ->subject("Permiso de Trabajo #" . $this->Args->NroPTC . " " . ($this->Args->EsDePGP ? '[PGP] ' : '') . "[Mediciones finalizadas]");
            
    }
}