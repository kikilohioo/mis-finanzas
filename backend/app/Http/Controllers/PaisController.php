<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Pais;
use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PaisController extends Controller
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
        $binding = [];
        $sql = "SELECT IdPais, Nombre, Zona, "
            . "Defecto = CASE WHEN IdPais IN "
            . "(SELECT Valor FROM Parametros WHERE IdParametro = 'PaisDefecto') "
            . "THEN 'Si' ELSE 'No' END FROM Paises WHERE Baja = 0";

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND Nombre LIKE :busqueda ";
            $binding[':busqueda'] = '%' . $busqueda . '%';
        }

        $sql .= "ORDER BY Nombre ASC";
        
        $data = DB::select($sql, $binding);
        $data = array_map(function ($item) {
            return FsUtils::castProperties($item, ['IdPais' => 'integer']);
        }, $data);

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            return $this->export($data, $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Paises-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
            'Defecto' => 'País por Defecto'
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Pais::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Pais no encontrado');
        }

        $entity->load('Departamentos');
        return $entity;
    }

    public function create()
    {

        return DB::transaction(function () {
           
            Pais::exigirArgs($this->req->all(), ['Nombre']);

            $entity = new Pais($this->req->all());
            $entity->IdPais = Pais::getNextId();
            $entity->IdUsuario = Auth::id();
            $entity->FechaHora = new \DateTime;
            $entity->Baja = false;
            $entity->save();

            $departamentos = $this->req->input('Departamentos');
            if (!empty($departamentos)) {
                foreach ($departamentos as $departamento) {
                    Departamento::exigirArgs($departamento, ['Nombre']);
                    $entityDepto = new Departamento($departamento);
                    $entityDepto->IdPais = $entity->IdPais;
                    $entityDepto->IdDepartamento = Departamento::getNextId('IdDepartamento', [['IdPais', $entityDepto->IdPais]]);
                    $entityDepto->FechaHora = new \DateTime;
                    $entityDepto->IdUsuario = Auth::id();
                    $entityDepto->save();
                }
            }

            $entity->load('Departamentos');
            return $entity->refresh();
        });
    }

    public function update(int $id)
    {
        DB::transaction(function () use ($id) {
            Pais::exigirArgs($this->req->all(), ['Nombre']);

            $entity = Pais::find($id);
            if (!isset($entity)) {
                throw new NotFoundHttpException('Pais no encontrado');
            }

            $entity->fill($this->req->all());
            $entity->Baja = false;
            $entity->save();

            if (is_array($departamentos = $this->req->input('Departamentos'))) {
                
                $arr_idDepartamentos = array_map(function($v) {
                    return $v['IdDepartamento'];
                }, array_filter($departamentos, function($v) {
                    return !empty($v['IdDepartamento']);
                }));

                if (!empty($arr_idDepartamentos)) {
                    $collection_departamentos = DB::table('Departamentos')->where('IdPais', $id)->whereNotIn('IdDepartamento',$arr_idDepartamentos)->get();
                    // whereNotIn No debería traerme nada si el valor existe. $arr_idDepartamentos['IdDepartamento']
                    $arr_departamentos = $collection_departamentos->all();

                    if (count($arr_departamentos) > 0) {
                        foreach ($arr_departamentos as $departament) {
                            DB::transaction(function () use ($id, $departament) {
                                DB::update('UPDATE Personas SET IdDepartamento = NULL WHERE IdPais = ? AND IdDepartamento = ?', [$id, $departament->IdDepartamento]);
                                DB::update('UPDATE Personas SET IdDepartamentoTemp = NULL WHERE IdPais = ? AND IdDepartamentoTemp = ?', [$id, $departament->IdDepartamento]);
                                DB::delete('DELETE FROM Departamentos WHERE IdPais = ? AND IdDepartamento = ?', [$id, $departament->IdDepartamento]);
                            });
                        }
                    }
                }

                if (count($departamentos) > 0) {
                    foreach ($departamentos as $departamento) {
                        if (!empty($departamento['IdDepartamento'])) {
                            $entityDepto = Departamento::where('IdPais', $id)->where('IdDepartamento', $departamento['IdDepartamento'])->first();
                            if (isset($entityDepto)) {
                                DB::update('UPDATE Departamentos SET Nombre = ? WHERE IdPais = ? AND IdDepartamento = ?', [$departamento['Nombre'], $id, $departamento['IdDepartamento']]);
                            }
                        } else {
                            Departamento::exigirArgs($departamento, ['Nombre']);
                            $entityDepto = new Departamento($departamento);
                            $entityDepto->IdPais = $entity->IdPais;
                            $entityDepto->IdDepartamento = Departamento::getNextId('IdDepartamento', [['IdPais', $entityDepto->IdPais]]);
                            $entityDepto->IdUsuario = Auth::id();
                            $entityDepto->FechaHora = new \DateTime;
                            $entityDepto->save();
                        }
                    }
                }
            }
        });
    }

    public function delete(int $id)
    {
        $entity = Pais::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Pais no encontrado');
        }
        
        $entity->Baja = true;
        $entity->FechaHoraBaja = new \DateTime;
        $entity->IdUsuarioBaja = Auth::id();
        
        $entity->save();
    }

}