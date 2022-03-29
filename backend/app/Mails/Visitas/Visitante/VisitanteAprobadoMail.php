<?php

namespace App\Mails\Visitas\Visitante;

use App\FsUtils;
use App\Models\Visitas\Solicitud;
use App\Models\Visitas\Visitante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VisitanteAprobadoMail extends Mailable
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
     * @var Usuario
     */
    public $usuario;
    
    /**
     * @var string
     */
    public $tipo;
    
    /**
     * @var string
     */
    public $tipoVisita;
    
    /**
     * @var object
     */
    public $solicitante;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($tipo, $visitante, $usuario = null)
    {
        $this->solicitud = $visitante['Solicitud'];
        $this->solicitante = $visitante['Solicitud']['Solicitante'];
        $this->tipo = $tipo;
        $this->usuario = $usuario;
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
        switch ($this->tipo) {
            case 'visitante':
                $filename = 'VisitanteAprobadoVisitante';
                if ($this->visitante->Solicitud->TipoVisita == 3) {
                    $filename = 'VisitanteAprobadoExcepcion';
                }

                $pautasPdf = storage_path('app/static/PautasBasicasVisitas.pdf');
                $ingresoCalidadPdf = storage_path('app/static/IngresoEnCalidad-Dic21.pdf');
                // '../storage/app/static/PautasBasicasVisitas.pdf'

                return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->view('mails.visitas.visitante.' . $filename)
                    ->subject('[V&E - #'.$this->visitante->IdSolicitud.'] SOLICITUD DE INGRESO APROBADA')
                    ->with([ "title" => 'Solicitud de ingreso al complejo industrial Punta Pereira aprobada'])
                    ->attach($pautasPdf, [
                        'as' => 'Pautas básicas de visitas.pdf',
                        'mime' => 'application/pdf',
                    ])
                    ->attach($ingresoCalidadPdf, [
                        'as' => 'Ingreso en calidad - Dic 21.pdf',
                        'mime' => 'application/pdf',
                    ])
                    ->attach(url('/visitas/visitantes/'.$this->visitante->Id.'/pdf') , [
                        'as' => 'QR-Acceso.pdf',
                        'mime' => 'application/pdf',
                    ]);
                    
                    /*->attach('http://127.0.0.1/QR-Access.pdf' , [
                        'as' => 'QR-Acceso.pdf',
                        'mime' => 'application/pdf',
                    ]);*/

            case 'solicitante':
                return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->view('mails.visitas.visitante.VisitanteAprobadoSolicitante')
                    ->subject('[V&E - #'.$this->visitante->IdSolicitud.'] SOLICITUD DE INGRESO APROBADA')
                    ->with([ "title" => 'Solicitud de ingreso al complejo industrial Punta Pereira aprobada']);

        }

    }

}