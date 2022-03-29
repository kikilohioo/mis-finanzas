<?php

namespace App\Http\Controllers\PTC;

use App\FsUtils;
use App\Models\PTC\PlanBloqueo;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlanBloqueoController extends \App\Http\Controllers\Controller
{

    /**
     * @var Request
     */
    private $req;

    /**
     * @var Usuario
     */
    private $user;

    public function __construct(Request $req)
    {
        $this->req = $req;
        $this->user = auth()->user();
    }

    public function index()
    {
        $binding = [];
        $where = '';
        if ($busqueda = $this->req->input('Busqueda')) {
            $binding[':Nombre'] = '%' . $busqueda . '%';
            $binding[':Nombre2'] = '%' . $busqueda . '%';
            $where .= ' and PTCPlanesBloqueos.Nombre like :Nombre or PTCAreas.Nombre like :Nombre2';
        }
        if ($AnhoPGP = $this->req->input('AnhoPGP')) {
            $binding[':AnhoPGP'] = $AnhoPGP;
            $where .= ' and PTCPlanesBloqueos.AnhoPGP = :AnhoPGP';
        }
        if ($IdArea = $this->req->input('IdArea')) {
            $binding[':IdArea'] = $IdArea;
            $where .= ' and PTCPlanesBloqueos.IdArea = :IdArea';
        }
        
        $data = DB::select('select PTCPlanesBloqueos.*, PTCAreas.Nombre AS NombreArea from PTCPlanesBloqueos inner join PTCAreas on PTCAreas.IdArea = PTCPlanesBloqueos.IdArea  where 1=1 '.$where, $binding);


        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'PlanBloqueo' => $item->Nombre,
                    'AnhoPGP' => $item->AnhoPGP,
                    'Area' => $item->NombreArea,
                ];
            },$data);
            return $this->export($dataOutput, $output);
        }

        $page = (int)$this->req->input('page', 1);        
        $paginate = FsUtils::paginateArray($data, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);

    }

    private function export(array $data, string $type) {
        $filename = 'FSAcceso-Permisos-de-Trabajo-Plan-Bloqueo' . date('Ymd his');
        $headers = [
            'PlanBloqueo' => 'Plan Bloqueo',
            'AnhoPGP' => 'Año PGP',
            'Area' => 'Area'
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = PlanBloqueo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Plan de bloqueo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        PlanBloqueo::exigirArgs($this->req->all(), ['Nombre', 'AnhoPGP', 'IdArea']);
        
        $Args = $this->req->all();

        $PlanBloqueoEnUso = DB::select("Select top 1 1 from PTCPlanesBloqueos where upper(Nombre) = upper(:Nombre) and AnhoPGP = :AnhoPGP and IdArea = :IdArea", 
        [":Nombre" => $Args['Nombre'], ":AnhoPGP" => $Args['AnhoPGP'], ":IdArea" => $Args['IdArea']]);

        if (!empty($PlanBloqueoEnUso[0])) { throw new NotFoundHttpException('No se puede crear el Plan de bloqueo porque ya existe uno identico'); }

        $entity = new PlanBloqueo($this->req->all());
        $entity->IdPlanBloqueo = PlanBloqueo::getNextId();
        $entity->IdUsuario = $this->user->IdUsuario;
        $entity->FechaHora = new \DateTime;
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        throw new NotFoundHttpException('Metodo no implementado');
    }

    public function delete(int $id)
    {
        $entity = PlanBloqueo::find($id);
        if (!isset($entity)) { throw new NotFoundHttpException('Plan de bloqueo no encontrado'); }

        $PlanBloqueoEnUso = DB::select(
            "Select PlanBloqueoExistente from PTC 
                where AnhoPGP = :AnhoPGP 
                and IdArea = :IdArea 
                and (PlanBloqueoExistente = :planBloqueo
                or PlanBloqueoExistente like :planBloqueo1
                or PlanBloqueoExistente like :planBloqueo2
                or PlanBloqueoExistente like :planBloqueo3)",
        [":AnhoPGP" => $entity->AnhoPGP, ":IdArea" => $entity->IdArea, "planBloqueo" => $entity->Nombre
        , "planBloqueo1" => "%,".$entity->Nombre, "planBloqueo2" => $entity->Nombre.",%", "planBloqueo3" => "%,".$entity->Nombre.",%"]);

        if (isset($PlanBloqueoEnUso[0])) {
            throw new NotFoundHttpException('Motivo: Está asociado a un Permiso de trabajo');
        }

        $entity->delete();
    }

}