<?php

namespace App\Http\Controllers\Visitas;

use App\FsUtils;
use App\Models\Visitas\Autorizante;
use App\Models\Visitas\VisitanteQR;
use App\Integrations\OnGuard;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

use App\Mails\Visitas\Visitante\VisitanteRechazadoSolicitanteMail;
use App\Mails\Visitas\Visitante\VisitanteNotificadoMail;
use App\Mails\Visitas\Visitante\VisitanteNotificadoSysoMail;
use App\Mails\Visitas\Visitante\VisitanteAprobadoMail;
use App\Models\Persona;
use App\Models\PersonaFisica;
use App\Models\Usuario;
use App\Models\Visitas\Visitante;
use Endroid\QrCode\QrCode;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;


class VisitanteController extends \App\Http\Controllers\Controller
{
    
    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function index()
    {
        $filters = $this->req->all();
        $cols = [
            DB::raw('vsp.*'),
            DB::raw('va.Nombre AS Area'),
            DB::raw('va.Id AS IdArea'),
            DB::raw('vsp.nombres + \' \' + vsp.apellidos AS NombreCompleto'),
        ];
        $cols = array_merge($cols, self::sqlEstado(), self::visitaCols());
        
        $query = DB::table('Visitas_Solicitudes', 'vs')
            ->join(DB::raw('Visitas_Areas va'), DB::raw('va.Id'), DB::raw('vs.IdArea'))
            ->join(DB::raw('Visitas_SolicitudesPersonas vsp'), DB::raw('vsp.IdSolicitud'), DB::raw('vs.Id'))
            ->select($cols)
            ->orderByRaw('vs.FechaHoraDesde ASC');

        if (!empty($filters['tipo'])) {
            $query->where('TipoVisita', (int)$filters['tipo']);
        }
                    
        if (array_key_exists('estado', $filters) && $filters['estado'] !== 'T') {
            if ($filters['estado'] === 'V') {
                $query->where(DB::raw('vs.FechaHoraHasta'), '<', DB::raw('GETDATE()'));
                $query->where(DB::raw('vsp.Estado'), '<>', 'R');
                $query->where(DB::raw('vsp.Estado'), '<>', 'C');
            } else if (in_array($filters['estado'], ['I', 'Z', 'A', 'R', 'C', 'O', 'P'])) {
                $query->where('vs.FechaHoraHasta', '>=', DB::raw('GETDATE()'));
                $query->where('vsp.Estado', $filters['estado']);
            } else {
                // Listo todos los permisos menos los vencidos o cerrados
                $query->where('vs.FechaHoraHasta', '>=', DB::raw('GETDATE()'));
                $query->where('vs.Estado', '<>', 'R');
                $query->where('vs.Estado', '<>', 'C');
            }
        }

        return $query->get();
    }

    public function show(string $id)
    {
        $cols = [
            DB::raw('vsp.*'),
            'vs.TipoVisita',
            DB::raw('va.Nombre AS Area'),
            DB::raw('va.Id AS IdArea'),
        ];
        $cols = array_merge($cols, self::sqlEstado(), self::visitaCols());

        $visitante = DB::table('Visitas_Solicitudes', 'vs')
            ->join(DB::raw('Visitas_Areas va'), DB::raw('va.Id'), DB::raw('vs.IdArea'))
            ->join(DB::raw('Visitas_SolicitudesPersonas vsp'), DB::raw('vsp.IdSolicitud'), DB::raw('vs.Id'))
            ->select($cols)
            ->where(DB::raw('vsp.Id'), $id)
            ->first();

        $visitante->idEmpresa = strtoupper(md5($visitante->DocEmpresa.'-'.$visitante->TipoDocEmpresa));
        $visitante->Empresa = DB::table('Empresas', 'e')
            ->join(DB::raw('Personas p'), function ($join) {
                $join->on(DB::raw('p.Documento'), DB::raw('e.Documento'))
                    ->on(DB::raw('p.IdTipoDocumento'), DB::raw('e.IdTipoDocumento'));
            })
            ->select([
                DB::raw("CONVERT(varchar(32), HashBytes('MD5', e.Documento + '-' + LTRIM(RTRIM(STR(e.IdTipoDocumento)))), 2) AS IdEmpresa"),
                DB::raw('e.*'),
            ])
            ->where(DB::raw('p.Baja'), ' 0')
            ->where(DB::raw('e.Documento'), $visitante->DocEmpresa)
            ->where(DB::raw('e.IdTipoDocumento'), $visitante->TipoDocEmpresa)
            ->first();

        return (array)$visitante;
    }

    private static function sqlEstado()
    {
        return [
            DB::raw("CASE 
                WHEN vs.FechaHoraHasta < GETDATE() THEN 'Vencido'
                WHEN vsp.Estado = 'I' THEN 'Solicitado'
                WHEN vsp.Estado = 'Z' THEN 'Autorizado' 
                WHEN vsp.Estado = 'A' THEN 'Aprobado'
                WHEN vsp.Estado = 'C' THEN 'Cerrado'
                WHEN vsp.Estado = 'O' THEN 'Notificado (inducción web)'
                WHEN vsp.Estado = 'P' THEN 'Notificado (inducción presencial)'
                WHEN vsp.Estado = 'R' THEN 'Rechazado'
            END AS Estado,
            vsp.Estado AS IdEstado"),
        ];
    }

    private static function visitaCols()
    {
        return [
            DB::raw('vs.EmpresaVisitante AS EmpresaVisitante'),
            DB::raw('vs.DocEmpresa AS DocEmpresa'),
            DB::raw('vs.TipoDocEmpresa AS TipoDocEmpresa'),
            DB::raw('vs.PersonaContacto AS PersonaContacto'),
            DB::raw('vs.TelefonoContacto AS TelefonoContacto'),
            DB::raw('vs.FechaHoraDesde AS FechaHoraDesde'),
            DB::raw('vs.FechaHoraHasta AS FechaHoraHasta'),
            DB::raw('vs.Motivo AS Motivo'),
            DB::raw('vs.Observaciones AS Observaciones'),
            DB::raw('vs.ComentariosAprobador AS ComentariosAprobador'),
            DB::raw('vs.FechaHora AS FechaHora'),
            DB::raw('vs.IdUsuario AS IdUsuario'),
            DB::raw('vs.TipoVisita AS TipoVisita'),
            DB::raw("CASE
                    WHEN vs.TipoVisita = 1 THEN 'Visita'
                    WHEN vs.TipoVisita = 3 THEN 'Excepción'
                    ELSE ''
                END AS TipoVisitaNombre"),
        ];
    }

    public function _show($id) {
        $entity = Visitante::getByIdWithSolicitud($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Visitante no encontrado');
        }
        return $entity;
    }

    public function autorizar() {

        $Args = $this->req->all();

        $autorizados = [];
        $noAutorizados = [];
        foreach ($Args['Visitantes'] as $data) {
            $visitante = Visitante::getById($data['Id']);
            
            /**
             * @todo
             */
            //$visitante->loadFiles($this->fileStorage);

            $visitante['AutorizarComedor'] = $data['AutorizarComedor'];

            try {
                
                Visitante::validateAutorizar($visitante);
                $autorizantes = Autorizante::getAll(['Visitas_Autorizantes.IdArea' => $visitante['Solicitud']['IdArea'],
                                            'Visitas_Autorizantes.IdUsuarioAutorizante' => Auth::id()]);
                
                if (count($autorizantes) === 0) {
                    $jefeTurno = DB::select("SELECT 1 FROM UsuariosFunciones WHERE IdFuncion LIKE 'chkVISJefe' AND IdUsuario LIKE ? ", [Auth::Id()]);
                                       
                    if (!isset($jefeTurno)) {
                        throw new \Exception('No eres autorizante del área');
                    }
                }
                
                $visitante->Estado = Visitante::ESTADO_AUTORIZADO;
                $visitante->IdUsuarioAutorizante = Auth::id();
                $visitante->ObservacionesAutorizante = $Args['Comentarios'];

                $this->changeStatus($visitante);

                $autorizados[] = $this->nombreConDocumento($visitante).': Autorizado correctamente';
            } catch (\Exception $err) {
                $noAutorizados[] = $this->nombreConDocumento($visitante).': '.$err->getMessage();
            }
        }
        return [
            'autorizados' => $autorizados,
            'noAutorizados' => $noAutorizados,
        ];
    }

    private function nombreCompleto($visitante) : string
    {
        return implode(' ', [$visitante['Nombres'], $visitante['Apellidos']]);
    }

    private function nombreConDocumento($visitante) : string
    {
        return $this->nombreCompleto($visitante).' ('.$visitante['Documento'].')';
    }

    private function changeStatus($visitante) {
        $visitante['AutorizarComedor'] = !!$visitante['AutorizarComedor'] ? 1 : 0;
        
        $entity = Visitante::find($visitante['Id']);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Visita no encontrada');
        }

        $entity->Estado = $visitante['Estado'];
        $entity->ComentariosRechazo = $visitante['ComentariosRechazo'];
        $entity->ObservacionesAutorizante = $visitante['ObservacionesAutorizante'];
        $entity->ObservacionesAprobador = $visitante['ObservacionesAprobador'];
        $entity->IdUsuarioAutorizante = $visitante['IdUsuarioAutorizante'];
        $entity->IdUsuarioAprobador = $visitante['IdUsuarioAprobador'];
        $entity->AutorizarComedor = $visitante['AutorizarComedor'];
        $entity->save();

        return $entity;
    }

    public function rechazar() {

        $Args = $this->req->all();

        $rechazados = [];
        $noRechazados = [];
        foreach ($Args['Visitantes'] as $idVisitante) {
            $idVisitante = implode('', $idVisitante);
            $visitante = Visitante::getById($idVisitante);

            try {
                Visitante::validateRechazar($visitante, $Args['Comentarios']);
                
                $autorizantes = Autorizante::getAll([
                    'Visitas_Autorizantes.IdArea' => $visitante['Solicitud']['IdArea'],
                    'Visitas_Autorizantes.IdUsuarioAutorizante' => Auth::id(),
                ]);

                if (count($autorizantes) === 0) {
                    throw new \Exception('No eres autorizante del área');
                }

                $visitante->ComentariosRechazo = $Args['Comentarios'];
                $visitante->Estado = Visitante::ESTADO_RECHAZADO;
                $visitante->IdUsuarioAutorizante = Auth::id();
                $this->changeStatus($visitante);

                try {

                    Mail::to($visitante['Solicitud']['Solicitante']['Email'])->send(new VisitanteRechazadoSolicitanteMail($visitante));
                    
                    $rechazados[] = $this->nombreConDocumento($visitante).': Rechazado correctamente';
                } catch (\Exception $err) {
                    $rechazados[] = $this->nombreConDocumento($visitante).': Rechazado correctamente pero no se envió correo al solicitante';
                }
                
            } catch (\Exception $err) {
                $noRechazados[] = $this->nombreConDocumento($visitante).': '.$err->getMessage();
            }
        }
        return [
            'rechazados' => $rechazados,
            'noRechazados' => $noRechazados,
        ];
    }

    public function cerrar($id) {

        $visitante = Visitante::getById($id);
        
        try {
            Visitante::validateCerrar($visitante);

            $visitante->Estado = Visitante::ESTADO_CERRADO;
            $visitante->idUsuarioAprobador = Auth::id();
            $this->changeStatus($visitante);

            try {

                Mail::to($visitante['Solicitud']['Solicitante']['Email'])->send(new VisitanteRechazadoSolicitanteMail($visitante));

                return ['message' => 'Solicitud de visitante cerrada correctamente'];
            } catch (\Exception $err) {
                return ['message' => 'Solicitud de visitante cerrada correctamente pero no se envió correo al solicitante'];
            }
            
            return ['message' => 'Solicitud de visitante cerrada correctamente'];
        } catch (\Exception $err) {
            throw new \Exception('No se pudo cerrar la solicitud del visitante: '.$err->getMessage());
        }
    }

    public function delete($id) {

        $entity = Visitante::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Visitante no encontrado');
        }
        
        $entity->Estado = Visitante::ESTADO_CERRADO;
        $entity->save();
    }

    public function notificar($id) {
        $visitante = Visitante::getById($id);
        $tipo = $this->req->input('Tipo');

        try {
            Visitante::validateNotificar($visitante, $tipo);

            $visitante->Estado = $tipo === 'presencial' ? (Visitante::ESTADO_NOTIFICADO_PRESENCIAL) : (Visitante::ESTADO_NOTIFICADO_DISTANCIA);
            $this->changeStatus($visitante);

            $visitante->Solicitante = Auth::Id();
            Log::info('Preparando correo con inducción para ingreso ' . $id);

            Mail::to($visitante['Email'])->send(new VisitanteNotificadoMail($tipo, $visitante));

            Log::info('Inducción enviada correctamente al ingreso ' . $id);
            
            $visitante['Solicitud']['TipoVisita'] = 3;
            if ($visitante['Solicitud']['TipoVisita'] == 3 && $tipo === 'presencial') {
                Mail::to(env('CONF_VISITANTE_SYSO'))->send(new VisitanteNotificadoSysoMail($tipo, $visitante));
            }
            
            return ['message' => 'Visitante notificado correctamente'];
        } catch (\Exception $err) {
            Log::error('ERROR al enviar inducción al ingreso ' . $id);
            throw new \Exception('No se pudo notificar al visitante: '.$err->getMessage());
        }

    }

    public function aprobar($id) {
        $visitante = Visitante::getById($id);

        $observaciones = $this->req->input('Observaciones');
        
        try {
            Visitante::validateAprobar($visitante);

            $visitante->Estado = Visitante::ESTADO_APROBADO;
            $visitante->IdUsuarioAprobador = Auth::Id();
            $visitante->ObservacionesAprobador = $observaciones;

            DB::transaction(function () use ($visitante, $observaciones, $id) {
                if ($this->aprobar_interno($visitante)) {
                    Log::info('Visitante aprobado ' . $id);
    
                    $visitante->ObservacionesAprobador = $observaciones;
                                    
                    // $pdfUrl = url('/visitas/visitantes/'.$visitante->Id.'/pdf');
                    // $pdfUrlPautas = url(storage_path('/app/static/PautasBasicasVisitas.pdf'));
                    
                    // Log::info('pdfUrl => ' . $pdfUrl.' '. $id);
                    // Log::info('pdfUrlPautas => ' . $pdfUrlPautas.' '. $id);
                    Log::info('Preparando correo '. $visitante['Email'], [$id]);
    
                    //Mail::to('smarenco@fusionar.com.uy')->send(new VisitanteAprobadoMail('visitante', $visitante, null));
                    Mail::to($visitante['Email'])->send(new VisitanteAprobadoMail('visitante', $visitante));
                    Log::info('Correo enviado a visitante '. $id);
                    
                    $verificador = Usuario::find($visitante->IdUsuarioAprobador);
                    
                    Log::info('Preparando correo para solicitante '. $visitante['Solicitud']['Solicitante']['Email'], [$id]);
                    
                    //Mail::to('smarenco@fusionar.com.uy')->send(new VisitanteAprobadoMail('solicitante', $visitante, $verificador));
                    Mail::to($visitante['Solicitud']['Solicitante']['Email'])->send(new VisitanteAprobadoMail('solicitante', $visitante, $verificador));
                    Log::info('Correo enviado a solicitante '. $id);
                    
                    return ['message' => 'Visitante aprobado correctamente'];
                }
                throw new HttpException(409, 'Ocurrió un error al aprobar al visitante.');
            });
        } catch (Exception $err) {
            throw new HttpException(409, 'No se pudo aprobar al visitante: '.$err->getMessage());
        }

        return [
            'message' => 'Ingreso aprobado correctamente',
        ];
    }

    private function aprobar_interno(&$visitante) {

        $visitante->IsChangeStatus = true;
        $visitante->Matricula = Visitante::getNextBetweenBadgeID('Vis');

        $visitanteCategoria = null;

        switch ((int)$visitante->Solicitud->TipoVisita) {
            case 1: $visitanteCategoria = (int)env('CONF_VISITANTE_CATEGORIA'); break;
            case 3: $visitanteCategoria = (int)env('CONF_VISITANTE_EXCEPCION_CATEGORIA'); break;
        }

        $empresaRecibeVisita = $visitante->Solicitud->Empresa;
        
        $categoria = Categoria::find($visitanteCategoria);

        $usuarioSolicitante = $visitante->Solicitud->Solicitante;

        $usuarioAutorizante = $visitante->Autorizante;

        return DB::transaction(function () use ($visitante, $visitanteCategoria, $usuarioSolicitante, $usuarioAutorizante, $empresaRecibeVisita, $categoria) {
            /**
             * @var PersonaFisica $pf
             */

            // Obtengo la persona fisica si existe en el sistema 
            $pf = $visitante->PersonaFisica;
            $p = $visitante->Persona;

            if ($pf && $p) {
                // Compruebo que la persona fisica este dada de baja y que sea visitante
                if (!$p->Baja && !$pf->Transito) {
                    throw new ConflictHttpException('La persona con documento '.$visitante->Documento.'-'.$visitante->IdTipoDocumento.' es una persona activa en el sistema. Solicite asistencia al administrador');
                }

                // Como la persona fisica existe y esta dada de baja o es ya un visitante, la actualizo
                $pf->NombreCompleto = $visitante->Nombres. ' ' .$visitante->Apellidos;
                $pf->PrimerNombre = $visitante->Nombres;
                $pf->PrimerApellido = $visitante->Apellidos;
                $pf->Transito = 1;
                $pf->Matricula = $visitante->Matricula;
                $pf->Estado = 1;
                $pf->NombreEmpresa = $visitante->Solicitud->EmpresaVisitante;
                $pf->MotivoVisita = $visitante->Solicitud->Motivo;
                $pf->NombreVisitante = $usuarioSolicitante->Nombre;
                $pf->Autorizante = $usuarioAutorizante->Nombre;
                $pf->DocEmpresa = $visitante->Solicitud->DocEmpresa;
                $pf->TipoDocEmpresa = $visitante->Solicitud->TipoDocEmpresa;
                $pf->DocEmpresaVisit = $visitante->Solicitud->DocEmpresa;
                $pf->TipoDocEmpresaVisit = $visitante->Solicitud->TipoDocEmpresa;

                $p->IdCategoria = $visitanteCategoria;
                $p->Baja = 0;
                $p->Email = $visitante->Email;

                $p->save();
                $pf->save();

            } else {
                
                // Como la persona fisica no existe, la doy de alta
                $p = new Persona;

                $p->Documento = $visitante->Documento;
                $p->IdTipoDocumento = $visitante->IdTipoDocumento;
                $p->IdCategoria = $visitanteCategoria;
                $p->Baja = 0;
                $p->Email = $visitante->Email;
                $p->FechaHora = new \DateTime;
                $p->IdUsuario = $visitante->IdUsuarioAprobador;

                $p->save();

                $pf = new PersonaFisica;

                $pf->Documento = $visitante->Documento;
                $pf->IdTipoDocumento = $visitante->IdTipoDocumento;
                $pf->VigenciaDesde = new \DateTime($visitante->Solicitud->FechaHoraDesde);
                $pf->VigenciaHasta = new \DateTime($visitante->Solicitud->FechaHoraHasta);
                $pf->NombreCompleto = $visitante->Nombres. ' ' .$visitante->Apellidos;
                $pf->PrimerNombre = $visitante->Nombres;
                $pf->PrimerApellido = $visitante->Apellidos;
                $pf->Transito = 1;
                $pf->Matricula = $visitante->Matricula;
                $pf->Estado = 1;
                $pf->NombreEmpresa = $visitante->Solicitud->EmpresaVisitante;
                $pf->MotivoVisita = $visitante->Solicitud->Motivo;
                $pf->NombreVisitante = $usuarioSolicitante->Nombre;
                $pf->Autorizante = $usuarioAutorizante->Nombre;
                $pf->DocEmpresa = $visitante->Solicitud->DocEmpresa;
                $pf->TipoDocEmpresa = $visitante->Solicitud->TipoDocEmpresa;
                $pf->DocEmpresaVisit = $visitante->Solicitud->DocEmpresa;
                $pf->TipoDocEmpresaVisit = $visitante->Solicitud->TipoDocEmpresa;

                $pf->save();
            }

            // Compruebo si esta configurado el acceso a comedor
            $idAcceso = env('CONF_VISITANTE_ACCESO_COMEDOR');
            if (is_numeric($idAcceso) && $idAcceso > 0) {
                DB::delete('Delete from PersonasFisicasAccesos where Documento = ? AND IdTipoDocumento = ? AND IdAcceso = ?', [$visitante->Documento, $visitante->IdTipoDocumento, $idAcceso]);
                if (((int)$visitante->AutorizarComedor) === 1) {
                    DB::insert('Insert into PersonasFisicasAccesos (Documento, IdTipoDocumento, IdAcceso) values(?, ?, ?)', [$visitante->Documento, $visitante->IdTipoDocumento, $idAcceso]);
                }
            }
            
            // Acutliazo la solicitud del visitante
            $this->changeStatus($visitante);

            $res = OnGuard::altaVisitante(
                $visitante->Documento,
                $visitante->Nombres,
                $visitante->Apellidos,
                $visitante->Solicitud->EmpresaVisitante, // Antes era $categoria->descripcion para indicar qué categoría era y mostrarla en lugar de la empresa a la que pertenece.
                $empresaRecibeVisita->Nombre, /// (!?)
                $visitante->Matricula,
                OnGuard::ESTADO_ACTIVO,
                $categoria->CatLenel,
                $visitante->Solicitud->FechaHoraDesde,
                $visitante->Solicitud->FechaHoraHasta
            );
            if (!$res) {
                throw new OnGuardException('Ocurrió un error al dar de alta al visitante '.$visitante->Documento);
            }

            return true;
        });
    }

    public function pdf($id) {
        $visitante = Visitante::getById($id);
        
        if ($visitante->Estado !== Visitante::ESTADO_APROBADO) {
            throw new \Exception('El visitante no ha sido aprobado');
        }
        if (!$visitante->PersonaFisica) {
            throw new \Exception('El visitante no se encuentra disponible como Persona Física');
        }
        // if ((int)$visitante->PersonaFisica->Estado !== 1) {
        //     throw new \Exception('El visitante no se encuentra activo');
        // }
        if (empty($visitante->PersonaFisica->Matricula)) {
            throw new \Exception('El visitante no tiene matricula asociada');
        }
        $visitante->Matricula = $visitante->PersonaFisica->Matricula;
        
        VisitanteQR::createQrWithTemplate($visitante);
    }

    /**
     * @DoesntRequireAuth
     * @var string $id ID del visitante
     */
    public function accessImage($id) {
        $response = $this->qr($id);
        $visitante = $response['visitante'];

        if (!isset($visitante)) {
            throw new HttpException('Solicitud no encontrada');
        }

        $qr_image = imageCreateFromString(base64_decode(substr($response['qr'], 17)));
        $template_image = imagecreatefrompng(storage_path('app/static/visitas-plantilla-qr.png'));

        $qr_width = imagesx($qr_image) - 50;
        $qr_height = imagesy($qr_image) - 50;
        $template_width = imagesx($template_image);
        $template_height = imagesy($template_image);

        $qr_image = self::resizeImage($qr_image, $qr_width, $qr_height);

        imagecopy($template_image, $qr_image, (($template_width / 2) - ($qr_width / 2)), (($template_height / 2) - ($qr_height / 2)) + 20, 0, 0, $qr_width, $qr_height);
        
        $blackColor = imagecolorallocate($template_image, 0, 0, 0);
        $font = "C:\Windows\Fonts\arial.ttf";
        $tipoVisitaText = $visitante->Solicitud->TipoVisita == 3 ? 'Excepción' : 'Visita';
        $texts = [
            implode(' ', [$visitante->Nombres, $visitante->Apellidos]),
            $visitante->Documento,
            $tipoVisitaText,
        ];
        $textSize = 16;
        $textPaddingY = 25;
        $positionTextY = ($template_height / 2) + ($qr_height / 2) + 20;

        foreach ($texts as $text) {
            $bbox = imagettfbbox($textSize, 0, $font, $text);
            $bboxX = $bbox[0] + (imagesx($template_image) / 2) - ($bbox[4] / 2);
            // $positionTextX = ($template_width / 2) - (strlen($text) * 5);
            $positionTextY = $positionTextY + $textPaddingY;
            imagettftext($template_image, $textSize, 0, $bboxX, $positionTextY, $blackColor, $font, $text);
        }

        if ($visitante->AutorizarComedor == 1) { // $visitante->tipoVisita == 3 && // Permitir a Visitas y a Excepciones habilitar el Comedor.
            imagettftext($template_image, 16, 0, 75, (($template_height / 2) - ($qr_height / 2)) - 20, $blackColor, $font, "Esta credencial de acceso también podrá ser solicitada en nuestro comedor.");
        }

        header('Content-Type: image/png');
        imagepng($template_image);
    }

    private static function resizeImage($src, $w, $h, $crop = false) {
        $width = imagesx($src);
        $height = imagesy($src);
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width - ($width * abs($r - $w / $h)));
            } else {
                $height = ceil($height - ($height * abs($r - $w / $h)));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w / $h > $r) {
                $newwidth = $h * $r;
                $newheight = $h;
            } else {
                $newheight = $w / $r;
                $newwidth = $w;
            }
        }
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        return $dst;
    }

    public function qr($id) {
        $visitante = Visitante::getById($id);

        if ($visitante->Estado !== Visitante::ESTADO_APROBADO) {
            throw new HttpException('El visitante no ha sido aprobado');
        }
        $personaFisica = $visitante->PersonaFisica;
        if (!$personaFisica) {
            throw new HttpException('El visitante no se encuentra disponible como Persona Física');
        }
        // if ((int)$personaFisica->Estado !== 1) {
        //     throw new HttpException('El visitante no se encuentra activo');
        // }
        if (empty($personaFisica->Matricula)) {
            throw new HttpException('El visitante no tiene matricula asociada');
        }

        $qrCode = new QrCode($personaFisica->Matricula);
        return [
            'visitante' => $visitante,
            'matricula' => $personaFisica->matricula,
            'qr' => $qrCode->getContentType().';base64,'.base64_encode($qrCode->writeString()),
        ];
    }

    /**
     * @DoesntRequireAuth
     * @var string $id ID del visitante
     */
    public function qrImage($id) {
        $response = $this->qr($id);
        header('Content-Type: image/png');
        echo base64_decode(str_replace('image/png;base64,', '', $response['qr']));
    }

}