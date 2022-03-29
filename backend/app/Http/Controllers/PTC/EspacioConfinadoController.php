<?php

namespace App\Http\Controllers\PTC;

use App\FsUtils;
use App\Models\PTC\EspacioConfinado;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EspacioConfinadoController extends \App\Http\Controllers\Controller
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
            $where .= ' and PTCTanques.Nombre like :Nombre or PTCAreas.Nombre like :Nombre2';
        }
        if ($IdArea = $this->req->input('IdArea')) {
            $binding[':IdArea'] = $IdArea;
            $where .= ' and PTCTanques.IdArea = :IdArea';
        }
        
        $data = DB::select('select PTCTanques.*, PTCAreas.Nombre AS NombreArea from PTCTanques inner join PTCAreas on PTCAreas.IdArea = PTCTanques.IdArea  where 1=1 '.$where.' order by PTCTanques.Nombre, PTCAreas.Nombre', $binding);


        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'EspacioConfinado' => $item->Nombre,
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
        $filename = 'FSAcceso-Permisos-de-Trabajo-Espacios-Confinados' . date('Ymd his');
        $headers = [
            'EspacioConfinado' => 'Espacio Confinado',
            'Area' => 'Area'
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = EspacioConfinado::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Plan de bloqueo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        EspacioConfinado::exigirArgs($this->req->all(), ['Nombre', 'IdArea']);
        
        $Args = $this->req->all();

        $EspacioConfinadoEnUso = DB::select("Select top 1 1 from PTCTanques where upper(Nombre) = upper(:Nombre) and IdArea = :IdArea", 
        [":Nombre" => $Args['Nombre'], ":IdArea" => $Args['IdArea']]);

        if (!empty($EspacioConfinadoEnUso[0])) { throw new NotFoundHttpException('No se puede crear el Espacio confinado porque ya existe uno identico'); }

        $entity = new EspacioConfinado($this->req->all());
        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        EspacioConfinado::exigirArgs($this->req->all(), ['Nombre', 'IdArea']);

        $entity = EspacioConfinado::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Espacio confinado no encontrado');
        }

        $EspacioConfinadoEnUso = DB::select("Select IdTanque from PTCPTCTanques where IdTanque = :IdTanque",[":IdTanque" => $id]);

        if (isset($EspacioConfinadoEnUso[0])) {
            throw new NotFoundHttpException('Motivo: Está asociado a un Permiso de trabajo');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = EspacioConfinado::find($id);
        if (!isset($entity)) { throw new NotFoundHttpException('Espacio confinado no encontrado'); }

        $EspacioConfinadoEnUso = DB::select("Select IdTanque from PTCPTCTanques where IdTanque = :IdTanque",[":IdTanque" => $id]);

        if (isset($EspacioConfinadoEnUso[0])) {
            throw new NotFoundHttpException('Motivo: Está asociado a un Permiso de trabajo');
        }

        $entity->delete();
    }

}