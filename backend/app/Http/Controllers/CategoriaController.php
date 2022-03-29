<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\FsUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CategoriaController extends Controller
{
    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    /**
     * @todo INNER JOIN - TiposDoc ON Cat.IdCategoria = TipoDoc.IdCategoria
     */
    public function index()
    {
        $query = Categoria::query()->where('Baja', false);

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        if ($this->req->input('PF')) {
            $query->where('PF', 1);
        }
        if ($this->req->input('PJ')) {
            $query->where('PJ', 1);
        }
        if ($this->req->input('MAQ')) {
            $query->where('MAQ', 1);
        }
        if ($this->req->input('VEH')) {
            $query->where('VEH', 1);
        }
        if ($this->req->input('VIS')) {
            $query->where('VIS', 1);
        }

        if ($this->req->input('SoloParaContratista', 'false') === 'true') {
            $query->where('ContratistaDisponible', '1');
        }

        $data = $query->orderBy('Descripcion')->get();

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            return $this->export($data->toArray(), $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Categorias-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Descripción',
            'PF' => 'Personas Fisicas',
            'PJ' => 'Empresas',
            'MAQ' => 'Máquinas',
            'VEH' => 'Vehículos',
            'VIS' => 'Visitantes',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Categoria::with(['Accesos'])->where('IdCategoria', $id)->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('La categoría no existe');
        }
        $entity->load('Accesos');
        return $entity;
    }

    /**
     * @todo Definir Usuario y Empresa
     */
    public function create()
    {
        return DB::transaction(function () {
            Categoria::exigirArgs($this->req->all(), ['Descripcion', 'IdDisenhoMatricula']);

            self::comprobarContratistaDisponible(null, $this->req->all());

            /**
            * @var Categoria
            */
            $entity = Categoria::query()->where('Descripcion', $this->req->input("Descripcion"))->where('Baja', false)->first();

            if (isset($entity)) {
                throw new ConflictHttpException('Ya existe una categoría con la misma descripción ' . $this->req->input("Descripcion"));
            }

            $entity = new Categoria($this->req->all());
            $entity->IdCategoria = Categoria::getNextId();

            $entity->IdDisenhoMatricula = $this->req->input('IdDisenhoMatricula');
            $entity->Sige = $this->req->input('SincSIGE');

            if (!empty($this->req->input('PF')) || !empty($this->req->input('VIS'))) {
                if (is_array($accesos = $this->req->input('Accesos'))) {
                    $entity->Accesos()->sync(array_map(function ($acceso) { return $acceso['IdAcceso']; }, $accesos));
                }
            }

            $entity->Baja = false;

            $entity->save();
            $entity->load('Accesos');
            return $entity->refresh();
        });
    }

    public function update($id)
    {
        return DB::transaction(function () use($id) {

            Categoria::exigirArgs($this->req->all(), ['Descripcion', 'IdDisenhoMatricula']);

            self::comprobarContratistaDisponible($id, $this->req->all());
            
            $entity = Categoria::find($id);
            
            if (!isset($entity)) {
                throw new NotFoundHttpException('Categoria no encontrada');
            }

            $entity->Descripcion = Categoria::query()->where('IdCategoria', '!=', $id)->where('Descripcion', $this->req->input("Descripcion"))->where('Baja', false)->first();

            if (isset($entity->Descripcion)) {
                throw new ConflictHttpException('Ya existe una categoría con la misma descripción ' . $this->req->input("Descripcion"));
            }

            $entity->fill($this->req->all());
            $entity->IdDisenhoMatricula = $this->req->input('IdDisenhoMatricula');
            $entity->Sige = $this->req->input('SincSIGE');
            
            if (!empty($this->req->input('PF')) || !empty($this->req->input('VIS'))) {
                if (is_array($accesos = $this->req->input('Accesos'))) {
                    $entity->Accesos()->sync(array_map(function ($acceso) { return $acceso['IdAcceso']; }, $accesos));
                }
            }

            $entity->Baja = false;

            $entity->save();
            $entity->load('Accesos');
            return $entity;
        });
    }

    public function delete(int $id)
    {
        $tables = array("CategoriasAccesos",
                "CategoriasNiveles",
                "CategoriasNivelesESP",
                "CategoriasNivelesRestricciones",
                "CategoriasNivelesRestriccionesESP",
                "EmpresasSectoresAutorizantes",
                //"Eventos", 
                //"EventosDuplicados",
                "HISTMaquinas",
                "HISTPersonas",
                "HISTVehiculos",
                "Maquinas",
                "MaquinasTransac",
                "Personas",
                "PersonasFisicasTransac",
                "TiposDocEmpCategorias",
                "TiposDocMaqCategorias",
                "TiposDocPFCategorias",
                "TiposDocVehicCategorias",
                "TMPPresPersonas",
                "Vehiculos",
                "VehiculosTransac"
        );
        foreach($tables as $table) {
            $result = DB::select('SELECT IdCategoria FROM "' . $table . '" where IdCategoria = :IdCategoria', ['IdCategoria' => $id]);
            if ($result) {
                throw new ConflictHttpException('No se puede dar de baja una categoría que esta siendo usada');
            }
        }

        $entity = Categoria::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('No se pudo encontrar el identificador de la Categoria');
        }
        
        $entity->Baja = true;
        $entity->save();
    }

    public function sincConSige()
    {
        $entity = new \stdClass;
        $entity->SincConSIGE = Categoria::sincConSIGE($this->req->input('IdCategoria')) ? 1 : 0;
        return $entity;
    }

    public function asociaTarjeta()
    {
        $response = (object)['NoAsociaTarjeta' => 0];

        $stmt = 'SELECT CASE WHEN NoAsociaTarjeta = 1 THEN 1 ELSE 0 END AS NoAsociaTarjeta FROM Categorias WHERE IdCategoria = ?';
        $result = DB::SelectOne($stmt, [$this->req->input('IdCategoria')]);
        if ($result) {
            $response->NoAsociaTarjeta = $result->NoAsociaTarjeta;
        }

        return $response;
    }

    /**
     * Comprueba si corresponde asignar una categoria como disponible para contratistas.
     * @param int|null $idCategoria
     * @param array $args
     * @return void
     */
    private static function comprobarContratistaDisponible(?int $idCategoria, array $args)
    {
        if (!empty($args['ContratistaDisponible'])) {
            $entitiesCount = 0;
            $entityName = 'especificada';

            $query = Categoria::where('ContratistaDisponible', true);
            if (isset($idCategoria)) {
                $query->where('IdCategoria', '!=', $idCategoria);
            }

            if (!empty($args['PF'])) {
                $entitiesCount++;
                $entityName = 'Persona Física';
                $query->where('PF', true);
            }
            if (!empty($args['PJ'])) {
                $entitiesCount++;
                $entityName = 'Empresa';
                $query->where('PJ', true);
            }
            if (!empty($args['MAQ'])) {
                $entitiesCount++;
                $entityName = 'Máquina';
                $query->where('MAQ', true);
            }
            if (!empty($args['VEH'])) {
                $entitiesCount++;
                $entityName = 'Vehículo';
                $query->where('VEH', true);
            }
            if (!empty($args['VIS'])) {
                $entitiesCount++;
                $entityName = 'Visitante';
                $query->where('VIS', true);
            }

            if ($entitiesCount === 0) {
                throw new HttpException(400, 'Para indicar que la categoría está disponible para los contratistas debe espeficiar a qué entidad pertenece.');
            }
            if ($entitiesCount > 1) {
                throw new HttpException(400, 'Para indicar que la categoría está disponible para los contratistas debe espeficiar que pertenece a una única entidad.');
            }

            // $recordFound = $query->first();
            // if (isset($recordFound)) {
            //     throw new HttpException(400, 'Ya existe una categoría diponible para contratistas para la entidad ' . $entityName . ' (' . $recordFound->Descripcion . ').');
            // }
        }
    }
}