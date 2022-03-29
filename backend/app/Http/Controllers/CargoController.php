<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Cargo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CargoController extends Controller
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
        
        /**
         * @todo
         */

        /*if (!empty($Args->IdPersonaFisica)) {
            $pf = fs_explode_id($Args->IdPersonaFisica);
            $sql = "SELECT c.IdCargo, c.Descripcion, pfc.fechaDesde, pfc.fechaHasta, pfc.Observaciones "
                . "FROM Cargos c INNER JOIN PersonasFisicasCargos pfc ON c.IdCargo = pfc.IdCargo "
                . "WHERE pfc.Documento = '" . $pf[0] . "' AND pfc.IdTipoDocumento = '" . $pf[1] . "'";
        } else if (!empty($Args->IdPersonaFisicaTransac)) {
            $pf = fs_explode_id($Args->IdPersonaFisicaTransac);
            $sql = "SELECT c.IdCargo, c.Descripcion, pfc.fechaDesde, pfc.fechaHasta, pfc.Observaciones "
                . "FROM Cargos c INNER JOIN PersonasFisicasTransacCargos pfc ON c.IdCargo = pfc.IdCargo "
                . "WHERE pfc.Documento = '" . $pf[0] . "' AND pfc.IdTipoDocumento = '" . $pf[1] . "'";
        } else {
            $sql = "SELECT c.IdCargo, c.Descripcion, c.FechaHora, c.IdUsuario, c.Baja
                    FROM Cargos c
                    WHERE c.Baja = 0";
            
            $sql .= mzbasico::whereFromArgs($Args, "c.", array("IdEmpresa", "TablePreffix"));
            
            if (!empty($Args->Busqueda)) {
                $sql .= " AND (c.Descripcion COLLATE Latin1_general_CI_AI LIKE '%" . $Args->Busqueda . "%' COLLATE Latin1_general_CI_AI)";
            }
            
            return self::ejecutarSQLyPaginar($Usuario, $IdEmpresa, '', $sql, $MaxFilas, $NroPagina, "Cargos", "c", "Descripcion");
        }*/

        $query = Cargo::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        $data = $query->get();

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Cargo-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Descripción',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Cargo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Cargo no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        Cargo::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new Cargo($this->req->all());
        $entity->IdCargo = Cargo::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Cargo::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = Cargo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Cargo no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Cargo::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Cargo no encontrado');
        }
        
        $entity->Baja = true;

        $entity->save();
    }

}