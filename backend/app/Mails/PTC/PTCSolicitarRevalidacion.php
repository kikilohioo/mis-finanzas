<?php

namespace App\Mails\PTC;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PTCSolicitarRevalidacion extends Mailable
{
    use Queueable, SerializesModels;
    
    /**
     * @var object
     */
    public $Args;

    /**
     * @var string
     */
    public $Asunto;

    /**
     * @var string
     */
    public $Notificacion;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($Args, $Asunto, $Notificacion)
    {
        $this->Args = (object)$Args;
        $this->Asunto = $Asunto;
        $this->Notificacion = $Notificacion;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.ptc.ptcsolicitarrevalidacion')
            ->subject($this->Asunto);
            
    }
}