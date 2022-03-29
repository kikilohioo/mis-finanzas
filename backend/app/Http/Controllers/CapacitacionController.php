<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Capacitacion;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CapacitacionController extends Controller
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
        $query = Capacitacion::query();

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }

        $data = $query->get();

        $output = $this->req->input('output', 'json');
        
        if ($output !== 'json') {

            $dataOutput = array_map(function($item) {
                return
                [
                    'Descripcion' => $item['Descripcion']
                ];
            },$data->toArray());
            
            $filename = 'FSAcceso-Capacitaciones-' . date('Ymd-his');

            $headers = [
                'Descripcion' => 'Descripción',
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        return $this->responsePaginate($data);
    }

    public function show(int $id)
    {
        $entity = Capacitacion::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Capacitacion no encontrada');
        }

        return $entity;
    }

    public function create()
    {
        Capacitacion::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new Capacitacion($this->req->all());
        $entity->IdCapacitacion = Capacitacion::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;
        $entity->Baja = false;

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Capacitacion::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = Capacitacion::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Capacitacion no encontrada');
        }

        $entity->fill($this->req->all());
        $entity->Baja = false;
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Capacitacion::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Capacitacion no encontrada');
        }
        
        $entity->Baja = true;

        $entity->save();
    }

    public function busqueda()
    {
        $args = $this->req->all();

        $IdEmpresaObj = [];
       
        if (!empty($args['IdEmpresa'])) {
            $IdEmpresaObj = FsUtils::explodeId($args['IdEmpresa']);
        }

        $binding = [];

        $sql = "SELECT DISTINCT dbo.Mask(p.Documento, td.Mascara, 1, 1) as Documento,
                                p.Documento,
                                p.IdTipoDocumento,
                                td.Descripcion AS TipoDocumento,
                                c.Descripcion AS Categoria,
                            cap.Descripcion AS Capacitacion,
                            pfc.FechaRealizada AS Fecha,
                            SexoDesc = CASE WHEN pf.Sexo = 1 THEN 'Masculino' WHEN pf.Sexo = 0 THEN 'Femenino'  END,
                            RTRIM(LTRIM(pf.PrimerNombre)) + ' ' + RTRIM(LTRIM(pf.SegundoNombre)) AS Nombres, 
                            RTRIM(LTRIM(pf.PrimerApellido)) + ' ' + RTRIM(LTRIM(pf.SegundoApellido)) AS Apellidos,
                            e.Nombre AS Empresa,
                            STUFF((
                                SELECT ', ' + cr.Descripcion
                                FROM PersonasFisicasCargos pfc
                                INNER JOIN Cargos cr ON cr.IdCargo = pfc.idCargo AND cr.Baja = 0
                                WHERE pfc.documento = pf.Documento AND pfc.idTipoDocumento = pf.IdTipoDocumento AND pfc.fechaHasta IS NULL
                                ORDER BY cr.Descripcion
                                FOR XML PATH ('')), 1, 2, '') AS Cargo,
                            pa.Nombre AS IdPais,
                            d.Nombre AS IdDepartamento,
                            p.Localidad,
                            pan.Nombre AS IdPaisNac,
                            dn.Nombre AS IdDepartamentoNac,
                            pf.FechaNac
            FROM Personas p
            INNER JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
            INNER JOIN TiposDocumento td ON p.IdTipoDocumento = td.IdTipoDocumento
            INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
            LEFT JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento AND pfe.FechaBaja IS NULL
            LEFT JOIN Empresas e ON e.Documento = pfe.DocEmpresa AND e.IdTipoDocumento = pfe.TipoDocEmpresa
            INNER JOIN PersonasFisicasCapacitaciones pfc ON pf.documento = pfc.documento AND pf.idTipoDocumento = pfc.idTipoDocumento
            INNER JOIN Capacitaciones cap ON pfc.idCapacitacion = cap.idCapacitacion
            LEFT JOIN Paises pa ON p.IdPais = pa.IdPais
            LEFT JOIN Departamentos d ON p.IdPais = d.IdPais AND p.IdDepartamento = d.IdDepartamento
            LEFT JOIN Paises pan ON pf.IdPaisNac = pan.IdPais
            LEFT JOIN Departamentos dn ON pf.IdPaisNac = dn.IdPais AND pf.IdDepartamentoNac = dn.IdDepartamento";

        $bs = 'p.Baja = 0';
        
        $ws = "";

        if (!empty($args)) {
            

            foreach ($args as $key => $value) {
                
                switch ($key) {
                    case 'output':
                    case 'token':
                    case 'page':
                    case 'pageSize':
                        break;
                    case 'FechaDesde':
                        $ws .= (empty($ws) ? ' WHERE ' : ' AND ') . 'pfc.FechaRealizada >= :'.$key;
                        $binding[':'.$key] = $value;
                        break;
                    case 'FechaHasta':
                        $ws .= (empty($ws) ? ' WHERE ' : ' AND ') . 'pfc.FechaRealizada <= :'.$key;
                        $binding[':'.$key] = $value;
                        break;
                    case 'IdCapacitacion':
                        $ws .= (empty($ws) ? ' WHERE ' : ' AND ') . 'pfc.' . $key . ' = :'.$key;
                        $binding[':'.$key] = $value;
                        break;
                    case 'IdCategoria':
                        $keys = 'p.' . $key;
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . $keys . ' = :'.$key;
                        $binding[':'.$key] = $value;
                        break;
                    case 'Nombre':
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . "(PF.primerNombre LIKE :Busqueda1 OR PF.segundoNombre LIKE :Busqueda2 OR PF.primerApellido LIKE :Busqueda3 OR PF.segundoApellido LIKE :Busqueda4)";
                        $binding[':Busqueda1'] = "%" . $value . "%";
                        $binding[':Busqueda2'] = "%" . $value . "%";
                        $binding[':Busqueda3'] = "%" . $value . "%";
                        $binding[':Busqueda4'] = "%" . $value . "%";
                        break;
                    case 'IdEmpresa':
                        $e = FsUtils::explodeId($value);
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . "pfe.Documento = :IdEmpresa1 AND pfe.IdTipoDocumento = :IdEmpresa2";
                        $binding[':IdEmpresa1'] = $e[0];
                        $binding[':IdEmpresa2'] = $e[1];
                        break;
                    default:
                        $k = ':'.$key;
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " LIKE " . $k;
                        $binding[$k] = '%' . $value . '%';
                        break;
                }
            }
        }

        $sql .= $ws . (empty($ws) ? " WHERE " : " AND ") . $bs;
        
        
        $UsuarioEsGestion = $this->user->isGestion();
        
        if (empty($UsuarioEsGestion)) {
            $sql .= " AND pfe.DocEmpresa = :IdEmpresaObj1 AND pfe.TipoDocEmpresa = :IdEmpresaObj2 AND pfe.FechaBaja IS NULL";
            $binding[':IdEmpresaObj1'] = $IdEmpresaObj[0];
            $binding[':IdEmpresaObj2'] = $IdEmpresaObj[1];
        }

        $sql .= " ORDER BY p.Documento";
        
        $page = (int)$this->req->input('page', 1);
        $items = DB::select($sql, $binding);

        $output = isset($args['output']);

        if ($output !== 'json' && $output == true) {

            $output = $args['output'];

            $dataOutput = array_map(function($item) {
                return [
                    'Documento' => $item->Documento,
                    'TipoDocumento' => $item->TipoDocumento,
                    'Nombres' => $item->Nombres,
                    'Apellidos' => $item->Apellidos,
                    'Capacitacion' => $item->Capacitacion,
                    'Fecha' => $item->Fecha,
                    'Empresa' => $item->Empresa,
                    'Cargo' => $item->Cargo,
                    'Pais' => $item->IdPais,
                    'Departamento' => $item->IdDepartamento,
                    'Localidad' => $item->Localidad,
                    'PaisNacimiento' => $item->IdPaisNac,
                    'FechaNacimiento' => $item->FechaNac
                ];
            },$items);

            $filename = 'FSAcceso-Capacitaciones-Consulta-' . date('Ymd his');
            
            $headers = [
                'Documento' => 'Documento',
                'TipoDocumento' => 'Tipo de documento',
                'Nombres' => 'Nombres',
                'Apellidos' => 'Apellidos',
                'Capacitacion' => 'Capacitación',
                'Fecha' => 'Fecha', 
                'Empresa' => 'Empresa',
                'Cargo' => 'Cargo',
                'Pais' => 'País',
                'Departamento' => 'Departamento',
                'Localidad' => 'Localidad',
                'PaisNacimiento' => 'País de Nacimiento',
                'FechaNacimiento' => 'Fecha de Nacimiento'
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
        
    }
}