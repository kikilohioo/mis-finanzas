<?php

namespace App\Http\Controllers;

use App\Models\ReporteAuto;
use App\Models\ReporteAutoNotificacion;
use DateTime;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Break_;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\FsUtils;

class ReporteAutomaticoController extends Controller
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
        $entities = ReporteAuto::select(['idReporte', 'descripcion'])->where('baja', false)->get();
        return $this->responsePaginate($entities);
    }

    public function show($id)
    {
        $entity = ReporteAuto::with(['mails'])->where('idReporte', $id)->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('No existe un reporte con ese identificador');
        }

        $entity = FsUtils::castProperties((object)$entity->toArray(), [
            'desde' => 'date:d/m/Y',
            'hasta' => 'date:d/m/Y',
        ]);

        $entity->tieneFechaHasta = !empty($entity->hasta);

        return (array)$entity;
    }

    public function create() {
        DB::transaction(function () {
            ReporteAuto::exigirArgs($this->req->all(), ['idReporte', 'descripcion', 'estado', 'desde', 'frecuencia', 'horaEjecucion', 'asunto', 'cuerpo']);
            $entity = ReporteAuto::find($this->req->input('idReporte'));

            if (isset($entity)) {
                throw new ConflictHttpException('Ya existe un reporte automático con el número de reporte ' . $entity->idReporte);
            }
            
            $entity = new ReporteAuto();
            $entity->idReporte =  $this->req->input('idReporte');
            $entity->estado =  $this->req->input('activo');

            if (empty($this->req->input('descripcion'))) {
                throw new ConflictHttpException('Descripcion es campo obligatorio');
            }
            $entity->descripcion = $this->req->input('descripcion');

            $entity->desde = DateTime::createFromFormat('d/m/Y', $this->req->input('desde'));

            if ($this->req->input('tieneFechaHasta') == true && !empty($this->req->input('hasta'))) {
                $entity->hasta = DateTime::createFromFormat('d/m/Y', $this->req->input('hasta'));
            }

            $entity->horaEjecucion = $this->req->input('horaEjecucion');
            
            if ($this->req->input('frecuencia') == 'S') {
            $entity->lunes = $this->req->input('lunes') == null ? 0 : $this->req->input('lunes');
            $entity->martes = $this->req->input('martes') == null ? 0 : $this->req->input('martes');
            $entity->miercoles = $this->req->input('miercoles') == null ? 0 : $this->req->input('miercoles');
            $entity->jueves = $this->req->input('jueves') == null ? 0 : $this->req->input('jueves');
            $entity->viernes = $this->req->input('viernes') == null ? 0 : $this->req->input('viernes');
            $entity->sabado = $this->req->input('sabado') == null ? 0 : $this->req->input('sabado');
            $entity->domingo = $this->req->input('domingo') == null ? 0 : $this->req->input('domingo');
            }
            $entity->frecuencia = $this->req->input('frecuencia') == null ? '' : $this->req->input('frecuencia');

            if (empty($this->req->input('asunto'))) {
                throw new ConflictHttpException('Asunto es campo obligatorio');
            }
            $entity->asunto = $this->req->input('asunto');

            if (empty($this->req->input('cuerpo'))) {
                throw new ConflictHttpException('Cuerpo es campo obligatorio');
            }
            $entity->cuerpo = $this->req->input('cuerpo');
            $entity->procedimiento = $this->req->input('procedimiento') == null ? null : $this->req->input('procedimiento');
            $entity->archivo = $this->req->input('archivo') == null ? null : $this->req->input('archivo');
            $entity->diaEjecucion = $this->req->input('diaEjecucion') == null ? 0 : $this->req->input('diaEjecucion');
            $entity->forzarEjecucion = $this->req->input('forzarEjecucion') == null ? 0 : $this->req->input('forzarEjecucion');
            $entity->baja = false;

            $entity->save();

            if (is_array($mails = $this->req->input('mails'))) {
                foreach($mails as $idMail => $mail) {
                    $reporteAutoMail = new ReporteAutoNotificacion();
                    $reporteAutoMail->idReporte = $entity->idReporte;
                    $reporteAutoMail->idMail = $idMail;
                    $reporteAutoMail->mail = $mail['mail'];

                    $reporteAutoMail->save();
                }
            }
        });
    }

    public function update($id) {
        DB::transaction(function () use ($id) {
            ReporteAuto::exigirArgs($this->req->all(), ['descripcion', 'estado', 'desde', 'frecuencia', 'horaEjecucion', 'asunto', 'cuerpo']);
            
            $entity = ReporteAuto::find($id);

            if (!isset($entity)) {
                throw new ConflictHttpException('Reporte automático no encontrado');
            }

            if (empty($this->req->input('descripcion'))) {
                throw new ConflictHttpException('Descripcion es campo obligatorio');
            }
            $entity->descripcion = $this->req->input('descripcion');
            $entity->estado =  $this->req->input('activo');

            $entity->desde = DateTime::createFromFormat('d/m/Y', $this->req->input('desde'));

            if ($this->req->input('tieneFechaHasta') == true && !empty($this->req->input('hasta'))) {
                $entity->hasta = DateTime::createFromFormat('d/m/Y', $this->req->input('hasta'));
            }

            $entity->horaEjecucion = $this->req->input('horaEjecucion');
            
            if ($this->req->input('frecuencia') == 'S') {
            $entity->lunes = $this->req->input('lunes') == null ? 0 : $this->req->input('lunes');
            $entity->martes = $this->req->input('martes') == null ? 0 : $this->req->input('martes');
            $entity->miercoles = $this->req->input('miercoles') == null ? 0 : $this->req->input('miercoles');
            $entity->jueves = $this->req->input('jueves') == null ? 0 : $this->req->input('jueves');
            $entity->viernes = $this->req->input('viernes') == null ? 0 : $this->req->input('viernes');
            $entity->sabado = $this->req->input('sabado') == null ? 0 : $this->req->input('sabado');
            $entity->domingo = $this->req->input('domingo') == null ? 0 : $this->req->input('domingo');
            }
            $entity->frecuencia = $this->req->input('frecuencia') == null ? '' : $this->req->input('frecuencia');

            if (empty($this->req->input('asunto'))) {
                throw new ConflictHttpException('Asunto es campo obligatorio');
            }
            $entity->asunto = $this->req->input('asunto');

            if (empty($this->req->input('cuerpo'))) {
                throw new ConflictHttpException('Cuerpo es campo obligatorio');
            }
            $entity->cuerpo = $this->req->input('cuerpo');
            $entity->procedimiento = $this->req->input('procedimiento') == null ? null : $this->req->input('procedimiento');
            $entity->archivo = $this->req->input('archivo') == null ? null : $this->req->input('archivo');
            $entity->diaEjecucion = $this->req->input('diaEjecucion') == null ? 0 : $this->req->input('diaEjecucion');
            $entity->forzarEjecucion = $this->req->input('forzarEjecucion') == null ? 0 : $this->req->input('forzarEjecucion');
            $entity->baja = false;

            $entity->save();

            if (is_array($mails = $this->req->input('mails'))) {
                $reporteAutoMail = ReporteAutoNotificacion::where('idReporte', $id);

                if(isset($reporteAutoMail)) {
                    $reporteAutoMail->delete();
                }

                foreach($mails as $idMail => $mail) {
                    $reporteAutoMail = new ReporteAutoNotificacion();
                    $reporteAutoMail->idReporte = $entity->idReporte;
                    $reporteAutoMail->idMail = $idMail;
                    $reporteAutoMail->mail = $mail['mail'];

                    $reporteAutoMail->save();
                }
            }
        });
    }
    
    public function delete($id)
    {
        $entity = ReporteAuto::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Reporte automático no encontrado');
        }
        
        $entity->baja = true;
        $entity->save();
    }
}