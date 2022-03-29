<?php

namespace App\Mails\Usuario;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UsuarioCreadoCambiarContraseniaMail extends Mailable
{
    use Queueable, SerializesModels;
    
    /**
     * @var object
     */
    public $Args;
    
    /**
     * token
     */
    public $token;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($Args, $token)
    {
        $this->Args = (object)$Args;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $url = sprintf('%s/auth?token=%s', env('APP_URL'), $this->token);

        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.usuario.usuarioCreadoCambiarContrasenia')->with('url', $url)
            ->subject('Alta de usuario, cambiar contraseña de FSAcceso');
            
    }
}