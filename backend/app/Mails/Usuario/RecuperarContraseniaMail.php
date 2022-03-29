<?php

namespace App\Mails\Usuario;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecuperarContraseniaMail extends Mailable
{
    use Queueable, SerializesModels;

    // /**
    //  * @var object
    //  */
    // public $obj;
    
    /**
     * @var Usuario
     */
    public $usuario;

    /**
     * token
     */
    public $token;
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($usuario, $token)
    {
        // $this->obj = (object)$obj;
        $this->usuario = $usuario;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // if (isset($this->usuario) && isset($this->token)) {
        $url = sprintf('%s/auth?token=%s', env('APP_URL'), $this->token);

        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->view('mails.usuario.recuperarContraseniaMail')->with('url', $url)
            ->subject('FSAcceso: Solicitud de restablecimiento de contraseña');
        // } else if (isset($this->obj)) {
        //     return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
        //         ->view('mails.usuario.recuperarContrasenia')
        //         ->subject('FSAcceso: Solicitud de restablecimiento de contraseña');
        // }
    }
}