<?php

namespace App\Http\Controllers\PCAR;

use App\Mails\PCAR\Solicitud\SolicitudCreadaSolicitanteMail;
use App\Mails\PCAR\Solicitud\SolicitudCreadaAutorizanteMail;
use App\Mails\PCAR\Solicitud\SolicitudAutorizadaAutorizanteMail;
use App\Mails\PCAR\Solicitud\SolicitudAutorizadaAprobadorMail;
use App\Mails\PCAR\Solicitud\SolicitudAprobadaSolicitanteMail;
use App\Mails\PCAR\Solicitud\SolicitudAprobadaAutorizanteMail;
use App\Mails\PCAR\Solicitud\SolicitudRechazadaSolicitanteMail;
use App\Mails\PCAR\Solicitud\SolicitudRechazadaAutorizanteMail;
use App\Mails\PCAR\Solicitud\NotificacionVencimientoMail;

use App\Models\Usuario;
use App\Models\PCAR\Area;
use App\Models\PCAR\Solicitud;
use App\Models\PCAR\Autorizante;
use App\Models\PCAR\Aprobador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @todo LEVANTAR UN SERVICE SOAP Para PERMISOS SOAP SERVICES
 */
class SolicitudController extends \App\Http\Controllers\Controller
{
    /**
     * @var Request
     */
    private $req;

    /**
     * @var Usuario
     */
    private $user;

    /**
     * @var array
    */
    private $errMessages = [
        'Matricula.required' => 'Debe ingresar una Matrícula',
        'Empresa.required' => 'Debe ingresar una Empresa',
        'PersonaContacto.required' => 'Debe ingresar el nombre del responsable de la Empresa en la planta',
        'TelefonoContacto.required' => 'Debe ingresar un teléfono de contacto',
        'EmailContacto.required' => 'Campo obligatorio, debe indicar un correo electrónico válido',
        'EmailContacto.email' => 'Debe indicar un correo electrónico válido',
    ];

    public function __construct(Request $req)
    {
        $this->req = $req;
        $this->user = Auth()->user();
    }

    public function index($Args = null) {
        $query = DB::table('PCAR_Solicitudes', 'ps')
        ->join('PCAR_Areas', 'PCAR_Areas.Id', '=', 'ps.IdArea')
        ->select([
            'ps.*',
            DB::raw(
                "CASE 
                    WHEN ps.Estado = 'I' AND ps.Hasta >= GETDATE() THEN 'Solicitado'
                    WHEN ps.Estado = 'Z' AND ps.Hasta >= GETDATE() THEN 'Autorizado'
                    WHEN ps.Estado = 'A' AND ps.Hasta >= GETDATE() THEN 'Aprobado'
                    WHEN ps.Estado = 'R'  THEN 'Rechazado'
                    WHEN ps.Hasta <= GETDATE() THEN 'Vencido'
                END AS Estado"
            ),
            DB::raw('PCAR_Areas.Nombre as Area'),
        ]);

        if(isset($Args)) {
            $this->req->merge($Args);
        }

        $estado = $this->req->input('estado');
        if (!empty($estado) && $estado !== 'T') {
            if ($estado == 'A') {
                $query->where('Estado', 'A')->where('Hasta', '>=', DB::raw('GETDATE()'));
            }
            else if ($estado == 'I') {
                $query->where('Estado', 'I')->where('Hasta', '>=', DB::raw('GETDATE()'));
            }
            else if ($estado == 'Z') {
                $query->where('Estado', 'Z')->where('Hasta', '>=', DB::raw('GETDATE()'));
            }
            else if ($estado == 'R') {
                $query->where('Estado', 'R');
            }
            else if ($estado == 'V') {
                $query->where('Estado', '<>', 'R')->where('Hasta', '<', DB::raw('GETDATE()'));
            }
        }

        if (empty($this->req->input('showPastRecords') || $this->req->input('showPastRecords') === "false")) {
            $this->req->input('estado') === 'T' ? 'ps.Desde = ps.Desde' : 'ps.Desde <= GETDATE()';
            $query->where('ps.Hasta', '>=', DB::raw('GETDATE()'));
        }

        if (($hasta = $this->req->input('hasta-strict')) !== null) {
            $query->where('ps.Hasta', \DateTime::createFromFormat('Y-m-d H:i:s', $hasta));
        }

        if (($matricula = $this->req->input('matricula')) !== null) {
            $query->where('ps.Matricula', $matricula);
        }
        
        return $query->get();
    }

    public function show(int $id) {
        $entity = Solicitud::find($id);
        if(!isset($entity)) {
            throw new NotFoundHttpException('Solicitud no encontrada');
        }
        return $entity;
    }

    // METODO DEFINIDO EN PERMISOS
    // public function show($id) {
    //     $entity = DB::table('PCAR_Solicitudes', 'ps')
    //             ->join('PCAR_Areas', 'PCAR_Areas.Id', '=', 'ps.IdArea')
    //             ->select([
    //                 'ps.*',
    //                 'PCAR_Areas.Nombre as Area'
    //             ])
    //             ->where('ps.Id', '=', $id)
    //             ->where('ps.Estado', '=', 'A')
    //             ->get();

    //     if (count($entity) <= 0) {
    //         throw new NotFoundHttpException('Permiso no encontrado');
    //     }
    //     return $entity;
    // }

    public function create() {
        $validator = Validator::make($this->req->all(), [
            'Matricula' => 'required',
            'Empresa' => 'required',
            'PersonaContacto' => 'required',
            'EmailContacto' => 'required|email',
            'TelefonoContacto' => 'required',
        ], $this->errMessages);

        if ($validator->fails()) {
            return $this->responseError($validator->errors()->first(), $validator->errors());
        }
        
        $entity = new Solicitud($this->req->all());
        $entity->Estado = Solicitud::ESTADO_NUEVA;
        $entity->Desde = new \DateTime(substr($this->req->input('Desde'), 0, 10).'T00:00:00.000Z');
        $entity->Hasta = new \DateTime(substr($this->req->input('Hasta'), 0, 10).'T23:59:59.000Z');
        
        $area = Area::find($this->req->input('IdArea'));

        if (!isset($area)) {
            throw new NotFoundHttpException('Área no encontrada');
        }
        $entity->IdArea = $area->Id;

        if(empty($this->req->input('Motivo'))) {
            throw new ConflictHttpException("Debe ingresar un motivo para la solicitud");
        }
        $entity->Motivo = $this->req->input('Motivo');

        $entity->Observaciones = $this->req->input('Observaciones') == "" ? null : $this->req->input('Observaciones');
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();
        $entity->load(['Usuario', 'UsuarioAutorizante', 'UsuarioAprobador']);

        $entity->save();

        Mail::to($this->user->Email)->send(new SolicitudCreadaSolicitanteMail($entity)); // email del usuario solicitante

        foreach(Autorizante::where('IdArea', $entity->IdArea)->get() as $autorizante) {
            $usuarioAutorizante = Usuario::find($autorizante->IdUsuarioAutorizante);
            Mail::to($usuarioAutorizante->Email)->send(new SolicitudCreadaAutorizanteMail($entity, $usuarioAutorizante)); // email del usuario autorizante
        }
    }

    public function update($id) {
        $validator = Validator::make($this->req->all(), [
            'Matricula' => 'required',
            'Empresa' => 'required',
            'PersonaContacto' => 'required',
            'EmailContacto' => 'required|email',
            'TelefonoContacto' => 'required',
        ], $this->errMessages);

        if ($validator->fails()) {
            return $this->responseError($validator->errors()->first(), $validator->errors());
        }

        $entity = Solicitud::find($id);

        if(!isset($entity)) {
            throw new NotFoundHttpException("Solicitud no encontrada");
        }

        $entity->fill($this->req->all());
        $area = Area::find($this->req->input('IdArea'));

        if (!isset($area)) {
            throw new NotFoundHttpException('Área no encontrada');
        }

        $entity->IdArea = $area->Id;
        
        if(empty($this->req->input('Motivo'))) {
            throw new ConflictHttpException("Debe ingresar un motivo para la solicitud");
        }
        $entity->Motivo = $this->req->input('Motivo');

        $entity->Observaciones = $this->req->input('Observaciones') == "" ? null : $this->req->input('Observaciones');
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();
        $entity->Desde = new \DateTime(substr($this->req->input('Desde'), 0, 10).'T00:00:00.000Z');
        $entity->Hasta = new \DateTime(substr($this->req->input('Hasta'), 0, 10).'T23:59:59.000Z');

        $entity->save();
    }

    public function autorizar($id) {
        $entity = Solicitud::with(['Area'])->find($id);

        if(!isset($entity)) {
            throw new NotFoundHttpException("Solicitud no encontrada");
        }
        
        Solicitud::validateAutorizar($entity);

        $autorizantes = Autorizante::where('IdArea', $entity->IdArea)->where('IdUsuarioAutorizante', Auth::id())->count();
        if ($autorizantes === 0) {
            throw new NotFoundHttpException('No eres autorizante de esta área');
        }

        $entity->Estado = Solicitud::ESTADO_AUTORIZADA;
        $entity->IdUsuarioAutorizante = Auth::id();
        $entity->load(['Usuario', 'UsuarioAutorizante', 'UsuarioAprobador']);

        $entity->save();

        foreach (Autorizante::where('IdArea', $entity->IdArea)->get() as $autorizante) {
            $usuarioAutorizante = Usuario::find($autorizante->IdUsuarioAutorizante);
            if ($usuarioAutorizante->IdUsuario != $entity->IdUsuarioAutorizante) {
                if(isset($usuarioAutorizante->Email)) {
                    Mail::to($usuarioAutorizante->Email)->send(new SolicitudAutorizadaAutorizanteMail($entity, $usuarioAutorizante)); // email del usuario autorizante
                }
            }
        }

        foreach (Aprobador::all() as $aprobador) {
            $usuarioAprobadorAutorizante = Usuario::find($aprobador->IdUsuarioAprobador);
            Mail::to($usuarioAprobadorAutorizante->Email)->send(new SolicitudAutorizadaAprobadorMail($entity, $usuarioAprobadorAutorizante)); // email del usuario aprobador
        }
    }

    public function aprobar($id) {
        $entity = Solicitud::with('Area')->find($id);

        if(!isset($entity)) {
            throw new NotFoundHttpException("Solicitud no encontrada");
        }

        Solicitud::validateAprobar($entity);

        $aprobadores = Aprobador::where('IdUsuarioAprobador', Auth::id())->count();
        if ($aprobadores === 0) {
            throw new NotFoundHttpException('No eres aprobador');
        }

        $entity->Estado = Solicitud::ESTADO_APROBADA;
        $entity->IdUsuarioAprobador = Auth::id();
        $entity->load(['Usuario', 'UsuarioAutorizante', 'UsuarioAprobador']);

        $entity->save();

        Mail::to($this->user->Email)->send(new SolicitudAprobadaSolicitanteMail($entity)); // email del usuario aprobada solicitante
        
        foreach(Autorizante::where('IdArea', $entity->IdArea)->get() as $autorizante) {
            $usuarioAutorizante = Usuario::find($autorizante->IdUsuarioAutorizante);
            Mail::to($usuarioAutorizante->Email)->send(new SolicitudAprobadaAutorizanteMail($entity, $usuarioAutorizante)); // email del usuario autorizante
        }
    }

    public function rechazar($id) {
        $entity = Solicitud::with('Area')->find($id);

        if(!isset($entity)) {
            throw new NotFoundHttpException("Solicitud no encontrada");
        }

        $entity->ComentariosAprobador = $this->req->input('ComentariosAprobador') ?? $this->req->input('comentariosAprobador');
        Solicitud::validateRechazar($entity);

        if ($entity->Estado === Solicitud::ESTADO_NUEVA) {
            $autorizantes = Autorizante::where('IdArea', $entity->IdArea)->where('IdUsuarioAutorizante', Auth::id())->count();
            if ($autorizantes === 0) {
                throw new NotFoundHttpException('No eres autorizante de esta área');
            }
        } else if ($entity->Estado === Solicitud::ESTADO_AUTORIZADA) {
            $aprobadores = Aprobador::where('IdUsuarioAprobador', Auth::id())->count();
            if ($aprobadores === 0) {
                throw new NotFoundHttpException('No eres aprobador');
            }
        }

        $entity->Estado = Solicitud::ESTADO_RECHAZADA;
        $entity->IdUsuarioAprobador = Auth::id();
        $entity->load(['Usuario', 'UsuarioAutorizante', 'UsuarioAprobador']);
        
        if (!$entity->IdUsuarioAutorizante) {
            $entity->IdUsuarioAutorizante = Auth::id();
        }

        $entity->save();
        
        Mail::to($this->user->Email)->send(new SolicitudRechazadaSolicitanteMail($entity)); // email del usuario aprobada solicitante

        foreach(Autorizante::where('IdArea', $entity->IdArea)->get() as $autorizante) {
            $usuarioAutorizante = Usuario::find($autorizante->IdUsuarioAutorizante);
            if ($usuarioAutorizante->IdUsuario != $entity->IdUsuarioAutorizante) {
                Mail::to($usuarioAutorizante->Email)->send(new SolicitudRechazadaAutorizanteMail($entity, $usuarioAutorizante)); // email del usuario autorizante
            }
        }
    }

    public function reenviar($id) {
        $entity = Solicitud::find($id);
        
        Mail::to($this->user->Email)->send(new SolicitudCreadaSolicitanteMail($entity)); // email del usuario solicitante
        
        foreach(Autorizante::where('IdArea', $entity->IdArea)->get() as $autorizante) {
            $usuarioAutorizante = Usuario::find($autorizante->IdUsuarioAutorizante);
            Mail::to($usuarioAutorizante->Email)->send(new SolicitudCreadaAutorizanteMail($entity, $usuarioAutorizante)); // email del usuario autorizante
        }
    }

    public function delete($id)
    {
        $entity = Solicitud::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Permiso no encontrado');
        }
        
        $entity->Estado = 'R';
        $entity->save();
    }

    public function notifyByMail() {
        $expireOn = 10;
        $expire = (new \DateTime())->add(new \DateInterval('P'.$expireOn.'D'))->format('Y-m-d');

        $filter = ['hasta-strict' => $expire];
        $permisos = $this->index($filter)->getData()->rows;

        $strMsg = '[' . date('Y-m-d H:i:s') . '] Permisos para notificar vencimiento de PCAR: ' . count($permisos);
        // file_put_contents('c:\\fs_pcar_notify.log', $strMsg . PHP_EOL, FILE_APPEND);

        if (isset($permisos)) {
            foreach ($permisos as &$permiso) {
                try {
                    $strMsg = '[' . date('Y-m-d H:i:s') . '] Enviando notificación de vencimiento de PCAR: ' . json_encode($permiso);
                    // file_put_contents('c:\\fs_mail.log', $strMsg . PHP_EOL, FILE_APPEND);

                    $permiso->notified = Mail::to($permiso->EmailContacto)->send(new NotificacionVencimientoMail($permiso));
                } catch (\Exception $err) {
                    $strErr = '[' . date('Y-m-d H:i:s') . '] Error al enviar correo de notificación de vencimiento de PCAR: ' . $err->getMessage() . ' - ' . json_encode($permiso);
                    // file_put_contents('c:\\fs_mail.log', $strErr . PHP_EOL, FILE_APPEND);
                }
            }
        }
        return [
            'permisos' => $permisos,
            'total' => count($permiso),
        ];
    }

    public function consultaAlutel() {
        $alutelConn = new PDO(
            'sqlsrv:server='.env('DB_ALPR_SERVER').';database='.env('DB_ALPR_SCHEMA'),
            env('DB_ALPR_USER'),
            env('DB_ALPR_PASS'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]
        );
      
        $sql = "";
        $binding = [];
        $where = [];

        $sql = "SELECT rl.*, CONVERT(varchar, FechaHora, 3) + ' ' + CONVERT(varchar, FechaHora, 8) AS FechaHoraFormato, r.PANEL_NAME AS Camara FROM (
            SELECT DATEADD(HOUR, -3, UTC_TIMESTAMP) AS FechaHora,
            CAMERA AS IdCamara,
            CARDHOLDER_NAME AS Nombre,
            CARDHOLDER_DOCUMENT_NUMBER AS Documento,
            CARDHOLDER_BADGE AS Matricula,
            PLATE_NUMBER AS SerieNumero FROM READING_LOGS
        ) AS rl INNER JOIN READERS as r ON r.CAMERA_READER = rl.IdCamara";
        // TOP 100
        
        if (array_key_exists('FechaDesde', $_GET) && array_key_exists('FechaHasta', $_GET)) {
            if (!empty($_GET['FechaDesde']) && !empty($_GET['FechaHasta'])) {
                $where[] = "FechaHora BETWEEN CONVERT(datetime, :FechaDesde, 120) AND CONVERT(datetime, :FechaHasta, 120)";
                $binding[':FechaDesde'] = $_GET['FechaDesde'] . ' 00:00:00';
                $binding[':FechaHasta'] = $_GET['FechaHasta'] . ' 23:59:59';
            }
        }
        if (array_key_exists('SerieNumero', $_GET) && !empty($_GET['SerieNumero'])) {
            $where[] = "SerieNumero LIKE :SerieNumero";
            $binding[':SerieNumero'] = '%'.$_GET['SerieNumero'].'%';
        }
        if (array_key_exists('Nombre', $_GET) && !empty($_GET['Nombre'])) {
            $where[] = "Nombre LIKE :Nombre";
            $binding[':Nombre'] = '%'.$_GET['Nombre'].'%';
        }
        if (array_key_exists('Documento', $_GET) && !empty($_GET['Documento'])) {
            $where[] = "Documento LIKE :Documento";
            $binding[':Documento'] = '%'.$_GET['Documento'].'%';
        }
        if (array_key_exists('Matricula', $_GET) && !empty($_GET['Matricula'])) {
            $where[] = "Matricula LIKE :Matricula";
            $binding[':Matricula'] = '%'.$_GET['Matricula'].'%';
        }
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY FechaHora DESC";

        $stmt = $alutelConn->prepare($sql);
        $stmt->execute($binding);
        return $stmt->fetchAll();
    }
}