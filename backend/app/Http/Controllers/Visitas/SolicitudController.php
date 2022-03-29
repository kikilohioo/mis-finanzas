<?php

namespace App\Http\Controllers\Visitas;

use App\FsUtils;
use App\Models\Visitas\Solicitud;
use App\Models\Visitas\Autorizante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

use App\Mails\Visitas\Solicitud\SolicitudCreadaVisitanteMail;
use App\Mails\Visitas\Solicitud\SolicitudCreadaAutorizanteMail;
use Exception;

class SolicitudController extends \App\Http\Controllers\Controller
{
    
    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function create()
    {
        $Args = $this->req->all();

        if ($Args['TipoVisita'] == 1) {
            if (strtotime ( '+3 day' , strtotime ( $Args['Desde'] ) ) < strtotime ($Args['Hasta'])) {
                throw new HttpException(400, 'Las visitas no pueden durar más de 3 días');
            }
        } else if ($Args['TipoVisita'] == 3) {
            if (strtotime ( '+14 day' , strtotime ( $Args['Desde'] ) ) < strtotime ($Args['Hasta'])) {
                throw new HttpException(400, 'Las excepciones no pueden durar más de 14 días');
            }
        }

        foreach ($Args['Personas'] as $persona) {
            if (filter_var($persona['Email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException(400, 'La dirección de correo <'.$persona['Email'].'> de '.$persona['Nombres'].' '.$persona['Apellidos'].' no es válida');
            }
        }

        $entity = DB::transaction(function () use ($Args) {
            $FechaHoraDesde = null;
            $FechaHoraHasta = null;

            if (!empty($this->req->input('Desde'))) {
                $FechaHoraDesde = (new \DateTime($Args['Desde']))->setTime(0, 0 , 0, 0)->format('Y/m/d H:i:s');
            }
            if (!empty($this->req->input('Hasta'))) {
                $FechaHoraHasta = (new \DateTime($Args['Hasta']))->setTime(23, 59, 59, 0)->format('Y/m/d H:i:s');
            }

            Solicitud::exigirArgs($this->req->all(), ['EmpresaVisitante', 'PersonaContacto', 'TelefonoContacto', 'IdArea', 'Motivo', 'TipoVisita']);
            
            $IdEmpresa = FsUtils::explodeId($Args['IdEmpresa']);

            $entity = new Solicitud($this->req->all());
            $entity->Estado = Solicitud::ESTADO_SOLICITADA;
            $entity->DocEmpresa = $IdEmpresa[0];
            $entity->TipoDocEmpresa = $IdEmpresa[1];
            $entity->FechaHoraDesde = $FechaHoraDesde;
            $entity->FechaHoraHasta = $FechaHoraHasta;
            $entity->IdUsuario = Auth::id();
            $entity->FechaHora = new \DateTime;

            $entity->save();

            //$entity->refresh();

            $id = $entity->Id;

            foreach ($Args['Personas'] as $persona) {
                $PersonaFisicaExists = [];
                
                $PersonaFisicaExists = DB::table('PersonasFisicas', 'pf')
                    ->select(['pf.*'])
                    ->join('Personas', function ($join){
                        $join->on('pf.Documento', '=','Personas.Documento')
                        ->on('Personas.IdTipoDocumento', '=', 'pf.IdTipoDocumento');
                    })
                    ->where('Personas.Baja', 0)
                    ->where('pf.Transito', 0)
                    ->where('pf.Documento', $persona['Documento'])
                    ->where('pf.IdTipoDocumento', $persona['IdTipoDocumento'])
                    ->first();
                
                if (isset($PersonaFisicaExists)) {
                    throw new HttpException(400, 'La persona con documento '.$persona['Documento'].'-'.$persona['IdTipoDocumento'].' es una persona activa en el sistema. Solicite asistencia al administrador');
                }

                DB::table('Visitas_SolicitudesPersonas')->insert([
                    'Documento' => $persona['Documento'],
                    'IdTipoDocumento' => $persona['IdTipoDocumento'],
                    'Nombres' => $persona['Nombres'],
                    'Apellidos' => $persona['Apellidos'],
                    'Email' => $persona['Email'],
                    'Id' => DB::raw('NEWID()'),
                    'IdSolicitud' => $id,
                ]);
                
            }

            return $entity->refresh();
        });
        
        foreach ($Args['Personas'] as $persona) {
            Mail::to($persona['Email'])->send(new SolicitudCreadaVisitanteMail($entity, $persona));
        }

        $autorizantes = Autorizante::getAll(['Visitas_Autorizantes.IdArea' => $Args['IdArea']]);

        foreach ($autorizantes as $autorizante) {
            if ($Args['TipoVisita'] == 1 && $autorizante['AutorizaVisitas'] || $Args['TipoVisita'] == 3 && $autorizante['AutorizaExcepciones']) {
                if ($autorizante['RecibeNotificaciones']) {
                    Mail::to($autorizante['Email'])->send(new SolicitudCreadaAutorizanteMail($entity, $autorizante, $Args['Personas']));
                }
            }
        }

        /**
         * @todo
         */
        /*if (count($_FILES)) {
            foreach ($_FILES as $k => $file) {
                $ext = array_pop(array_slice(explode('.', $file['name']), -1));
                $targetFile = strpos($k, '__persona_') === 0 ? 'personas/'.substr($k, 10).'.'.$ext : $file['name'];
                $this->fileStorage->copy($file['tmp_name'], $solicitud->id.'/'.$targetFile);
            }
        }*/

        return $entity;

    }

    public function update()
    {
        throw new HttpException(409, 'Metodo no definido.');
    }

    public function delete(string $IdUsuarioAutorizante, int $IdArea)
    {
        throw new HttpException(409, 'Meotdo no definido');
    }

}