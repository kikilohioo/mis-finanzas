<?php

namespace App\Mails\Usuario;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UsuarioCreadoMail extends Mailable
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
            ->view('mails.usuario.usuarioCreado')
            ->subject('Alta de usuario de FSAcceso');
            
    }
}