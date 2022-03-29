<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\BaseModel;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Sector;
use App\Models\Empresa;
use App\Models\EmpresasAlojamiento;
use App\Models\EmpresasContacto;
use App\Models\EmpresaSector;
use App\Models\EmpresasTransporte;
use App\Models\LogAuditoria;
use App\Models\Persona;
use Carbon\Carbon;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Models\TipoDocumentoEmp;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmpresaController extends Controller
{

    /**
     * @var Request
     */
    private $req;

    /**
     * @var Usuario
     */
    private $user;

    private static $availableFields = ['ProtocoloCovidArchivo'];

    public function __construct(Request $req)
    {
        $this->req = $req;
        $this->user = auth()->user();
    }

    public function index()
    {
        $binding = [];
        $sql = "SELECT e.Documento,
                dbo.Mask(e.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                CASE e.Estado
                    WHEN 1 THEN 'active'
                    ELSE 'inactive'
                END AS FsRC,
                e.IdTipoDocumento,
                e.Documento + '-' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS IdEmpresa,
                c.Descripcion AS Categoria,
                e.Nombre,
                e.Alias,
                CASE e.Estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END As Estado
        FROM Empresas e WITH(NOLOCK)
        INNER JOIN Personas p WITH(NOLOCK) ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
        INNER JOIN TiposDocumento td WITH(NOLOCK) ON p.IdTipoDocumento = td.IdTipoDocumento
        INNER JOIN Categorias c WITH(NOLOCK) ON p.IdCategoria = c.IdCategoria
        WHERE 1 = 1";

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $sql .= " AND p.Baja = 0 ";
        }

        if (null !== ($recibeVisitas = $this->req->input('recibeVisitas'))) {
            $sql .= " AND e.RecibeVisitas = :recibeVisitas ";
            $binding[':recibeVisitas'] = (int)$recibeVisitas;
        }
        if (null !== ($realizaVisitas = $this->req->input('realizaVisitas'))) {
            $sql .= " AND e.RealizaVisitas = :realizaVisitas ";
            $binding[':realizaVisitas'] = (int)$realizaVisitas;
        }

        if ((!isset($recibeVisitas) && !isset($realizaVisitas)) && !$this->user->isGestion() && $this->user->getKey() !== 'fsa_public') {
            // $ide = FsUtils::explodeId($IdEmpresa);
            // $sql .= ' AND CONVERT(varchar(32), HashBytes(\'MD5\', e.Documento + \'-\' + LTRIM(RTRIM(STR(e.IdTipoDocumento)))), 2) = \'' . $ide[0] . '\'';
            $empresa = Empresa::loadBySession($this->req);
            $sql .= " AND e.Documento = :doc_empresa AND e.IdTipoDocumento = :tipo_doc_empresa";
            $binding[':doc_empresa'] = $empresa->Documento;
            $binding[':tipo_doc_empresa'] = $empresa->IdTipoDocumento;
        }

        if ($this->req->input('EsMDP')) {
            $sql .= " AND e.MdP = 1 ";
        }

        if (null !== ($busqueda = $this->req->input('Busqueda'))) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(e.Documento, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI LIKE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(:busqueda_1, '_', ''), '-', ''), ';', ''), ',', ''), ':', ''), '.', '') COLLATE Latin1_general_CI_AI OR "
                . "e.Nombre COLLATE Latin1_general_CI_AI LIKE :busqueda_2 COLLATE Latin1_general_CI_AI OR "
                . "e.Alias COLLATE Latin1_general_CI_AI LIKE :busqueda_3 COLLATE Latin1_general_CI_AI)";
            $binding[':busqueda_1'] = '%' . $busqueda . '%';
            $binding[':busqueda_2'] = '%' . $busqueda . '%';
            $binding[':busqueda_3'] = '%' . $busqueda . '%';
        }

        // $sql .= str_replace('WHERE', 'AND', mzbasico::whereFromArgs($Args, "e.", ['EsMdP', 'TipoListado']));
        $sql .= " ORDER BY Nombre";

        $page = (int)$this->req->input('page', 1);

        $items = DB::select($sql, $binding);

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Documento' => $item->Documento,
                    'Estado' => $item->Estado,
                    'Nombre' => $item->Nombre ? $item->Nombre : '',
                    'Alias' => $item->Alias ? $item->Alias : '',
                    'Categoria' => $item->Categoria
                ];
            },$items);

            $filename = 'FSAcceso-Empresas-' . date('Ymd his');

            $headers = [
                'Documento' => 'Documento',
                'Estado' => 'Estado',
                'Nombre' => 'Nombre',
                'Alias' => 'Alias',
                'Categoria' => 'Categoría'
            ];

            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);

        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }

    public function show(int $idTipoDocumento, string $documento)
    {
        $entity = $this->show_interno($idTipoDocumento, $documento);
        if (!isset($entity)) {
            throw new NotFoundHttpException('La empresa no existe');
        }
        return $this->response($entity);
    }

    private function show_interno(int $idTipoDocumento, string $documento)
    {
        $binding = [
            ':documento' => $documento,
            ':id_tipo_documento' => $idTipoDocumento,
        ];
        $sql = "SELECT p.Baja,
                    e.Estado,
                    p.Documento,
                    dbo.Mask(p.Documento, td.Mascara, 1, 1) AS DocumentoMasked,
                    p.IdTipoDocumento,
                    td.Mascara AS MascaraTipoDocumento,
                    p.IdCategoria,
                    e.Nombre,
                    e.Alias,
                    p.IdPais,
                    p.IdDepartamento,
                    p.Ciudad,
                    p.Localidad,
                    e.Contacto,
                    e.Extranjera,
                    p.Email,
                    p.Telefono,
                    e.NroMTSS,
                    e.RealizaVisitas,
                    e.RecibeVisitas,
                    e.ExcluirR7,
                    e.ExcluirR30,
                    e.CantTrabajadores,
                    e.CantTrabajadoresAloj,
                    e.NroOrdenCompra,
                    e.ReferenteMDP,
                    e.ProtocoloCovidEstado,
                    e.ProtocoloCovidArchivo,
                    c.ContratistaDisponible AS CategoriaContratistaDisponible,
                    e.EsPGP
                FROM Personas p 
                INNER JOIN TiposDocumento td ON td.IdTipoDocumento = p.IdTipoDocumento
                INNER JOIN Empresas e ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
                INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
                WHERE e.Documento = :documento AND e.IdTipoDocumento = :id_tipo_documento";

        $entity = DB::selectOne($sql, $binding);
        
        if (isset($entity)) {
            $entity->Sectores = Sector::where('Documento', $documento)->where('IdTipoDocumento', $idTipoDocumento)->get();
            $entity->Documentos = TipoDocumentoEmp::list($documento, $idTipoDocumento);
            $entity->ContrataA = Empresa::loadContrataA($documento, $idTipoDocumento);
            $entity->ContratadaPor = Empresa::loadContratadaPor($documento, $idTipoDocumento);
            $entity->EmpresasAlojamientos = Empresa::EmpresasAlojamientos($documento, $idTipoDocumento);
            $entity->EmpresasTransportes = Empresa::EmpresasTransportes($documento, $idTipoDocumento);
            $entity->EmpresasContactos = Empresa::EmpresasContactos($documento, $idTipoDocumento);
        }

        $entity = FsUtils::castProperties($entity, Empresa::$castProperties);

        return $entity;
    }

    public function create() 
    {
        $Args = (object) $this->req->All();
        $Args->Documento = FsUtils::unmask($Args->DocumentoMasked);
        
        Empresa::exigirArgs($Args, ['Documento', 'IdTipoDocumento']);

        $obj = $this->show_interno($Args->IdTipoDocumento, $Args->Documento);

        if (empty($obj)) {
           	return DB::transaction(function () use ($Args) {
                $persona = Persona::where('Documento', $Args->Documento)
                    ->where('IdTipoDocumento', $Args->IdTipoDocumento)
                    ->get();
    
                if (!empty($persona[0])) {
                    throw new HttpException(409, "La persona ya existe");
                }
    
                $persona = new Persona((array)$Args);
                $persona->Documento = $Args->Documento;
                $persona->IdTipoDocumento = $Args->IdTipoDocumento;
                $persona->FechaHoraAlta = new Carbon;
                $persona->Baja = false;
                $persona->save();

                $entity = new Empresa((array)$Args);
                $entity->Estado = $Args->Estado;
                $entity->Documento = $Args->Documento;
                $entity->IdTipoDocumento = $Args->IdTipoDocumento;
                $entity->Nombre = strtoupper($Args->Nombre);
                $entity->Alias = $Args->Alias;
                $entity->Contacto = $Args->Contacto;
                $entity->NroMTSS = $Args->NroMTSS;
                $entity->RealizaVisitas = $Args->RealizaVisitas;
                $entity->RecibeVisitas = $Args->RecibeVisitas;
                $entity->ExcluirR7 = $Args->ExcluirR7;
                $entity->ExcluirR30 = $Args->ExcluirR30;
                $entity->Extranjera = $Args->Extranjera;
                $entity->CantTrabajadores = $Args->CantTrabajadores;
                $entity->CantTrabajadoresAloj = $Args->CantTrabajadoresAloj;

                $entity->NroOrdenCompra = $Args->NroOrdenCompra;
                $entity->ReferenteMDP = $Args->ReferenteMDP;

                $entity->EsPGP = $Args->EsPGP;

                if (!$this->user->EsContratista) {
                    $entity->ProtocoloCovidEstado = $Args->ProtocoloCovidEstado;
                }

                $entity->save();

                $this->altaSectores($Args);
                Empresa::altaDocumentos($Args);  
                $this->altaContratos($Args);
                $this->altaEstado($Args);

                /*$this->modificacionAlojamientos($Args->IdTipoDocumento, $Args->Documento, $Args->EmpresasAlojamientos);
                $this->modificacionTransportes($Args->IdTipoDocumento, $Args->Documento, $Args->EmpresasTransportes);
                $this->modificacionContactos($Args->IdTipoDocumento, $Args->Documento, $Args->EmpresasContactos);*/

                if ($Args->CrearUsuarioContratista) {
                    $args['Nombre']             = $Args->NombreContratista;
                    $args['IdUsuario']          = $Args->NombreUsuarioContratista;
                    $args['Email']              = $Args->CorreoUsuarioContratista;
                    $args['Empresas']           = [$Args->Documento.'-'.$Args->IdTipoDocumento];
                    $args['Funciones']          = ['chkAdmEmpresaContratista','chkAdmPersonas','chkAdmVehiculos'];

                    $args['EsContratista'] = true;
                    $args['CambiarContrasenia'] = true;

                    $usuarioController = app(\App\Http\Controllers\UsuarioController::class);
                    $usuarioController->create($args);
                }
                
            });
        } else {
			if ($obj->Baja == 1) {
                $this->update($Args->IdTipoDocumento, $Args->Documento);
                DB::update("UPDATE Personas SET Baja = 0 WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento",
					[':Documento' => $Args->Documento, ':IdTipoDocumento' => $Args->IdTipoDocumento]);
                return true;
            } else {
                throw new HttpException(409, "La empresa ya existe");
            }
        }
    }

	public function update(int $idTipoDocumento, string $documento)
	{
        $Args = (object)$this->req->All();
        if ($this->user->EsContratista) {
            if (!isset($Args->CantTrabajadoresAloj) || !isset($Args->CantTrabajadores)) {
                throw new HttpException(409, 'Debe indicar un valor para la cantidad de trabajadores alojados y totales.');
            } else if ($Args->CantTrabajadoresAloj > $Args->CantTrabajadores) {
                throw new HttpException(409, 'La cantidad de trabajadores alojados no puede superar la cantidad total de trabajadores.');
            }
        }

        return DB::transaction(function () use ($idTipoDocumento, $documento) {
            $Args = (object)$this->req->All();

            $bindings = [];
            $bindings[':IdCategoria'] = $Args->IdCategoria;
            $bindings[':IdPais'] = $Args->IdPais;
            $bindings[':IdDepartamento'] = $Args->IdDepartamento;
            $bindings[':Ciudad'] = @$Args->Ciudad;
            $bindings[':Localidad'] = @$Args->Localidad;
            $bindings[':Direccion'] = @$Args->Direccion;
            $bindings[':Email'] = @$Args->Email;
            $bindings[':Telefono'] = @$Args->Telefono;
            $bindings[':Documento'] = $documento;
            $bindings[':IdTipoDocumento'] = $idTipoDocumento;

            DB::update("UPDATE Personas SET IdCategoria = :IdCategoria, "
                . "IdPais = :IdPais, "
                . "IdDepartamento = :IdDepartamento, "
                . "Ciudad = :Ciudad, "
                . "Localidad = :Localidad, "
                . "Direccion = :Direccion, "
                . "Email = :Email,  "
                . "Telefono = :Telefono "
                . "WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);

            $bindings = [];
            $bindings[':Nombre'] = strtoupper($Args->Nombre);
            $bindings[':Alias'] = $Args->Alias;
            $bindings[':Contacto'] = $Args->Contacto;
            $bindings[':NroMTSS'] = $Args->NroMTSS;
            $bindings[':RealizaVisitas'] = $Args->RealizaVisitas;
            $bindings[':RecibeVisitas'] = $Args->RecibeVisitas;
            $bindings[':ExcluirR7'] = $Args->ExcluirR7;
            $bindings[':ExcluirR30'] = $Args->ExcluirR30;
            $bindings[':CantTrabajadores'] = $Args->CantTrabajadores;
            $bindings[':CantTrabajadoresAloj'] = $Args->CantTrabajadoresAloj;
            $bindings[':Documento'] = $documento;
            $bindings[':IdTipoDocumento'] = $idTipoDocumento;
            $bindings[':Extranjera'] = $Args->Extranjera;

            $bindings[':NroOrdenCompra'] = $Args->NroOrdenCompra;
            $bindings[':ReferenteMDP'] = $Args->ReferenteMDP;
            $bindings[':EsPGP'] = $Args->EsPGP;

            if (!$this->user->EsContratista) {
                $bindings[':ProtocoloCovidEstado'] = $Args->ProtocoloCovidEstado;
            }

            DB::update("UPDATE Empresas SET Nombre = :Nombre, "
                . "Alias = :Alias, "
                . "Contacto = :Contacto, "
                . "NroMTSS = :NroMTSS, "
                . "RealizaVisitas = :RealizaVisitas, "
                . "RecibeVisitas = :RecibeVisitas, "
                . "ExcluirR7 = :ExcluirR7, "
                . "ExcluirR30 = :ExcluirR30, "
                . "CantTrabajadores = :CantTrabajadores, "
                . "CantTrabajadoresAloj = :CantTrabajadoresAloj, "
                . "Extranjera = :Extranjera, "
                . "NroOrdenCompra = :NroOrdenCompra, "
                . "ReferenteMDP = :ReferenteMDP, "
                . "EsPGP = :EsPGP "
                . (!$this->user->EsContratista ? ", ProtocoloCovidEstado = :ProtocoloCovidEstado " : "")
                . "WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);

            $this->altaSectores($Args);
            Empresa::altaDocumentos($Args);
            $this->altaContratos($Args);

            $bindings = [];
            $bindings[':Documento'] = $documento;
            $bindings[':IdTipoDocumento'] = $idTipoDocumento;

            $baja = DB::selectOne("SELECT Baja FROM Personas WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);
            $respawning = !empty($baja->Baja) && $baja->Baja == 1;

            if ($respawning) {
                DB::update("UPDATE Personas SET Baja = 0 WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);
            }
            
            $this->modificacionAlojamientos($idTipoDocumento, $documento, $Args->EmpresasAlojamientos);
            $this->modificacionTransportes($idTipoDocumento, $documento, $Args->EmpresasTransportes);
            $this->modificacionContactos($idTipoDocumento, $documento, $Args->EmpresasContactos);
            $this->modificacionEstado($Args);

            if ($Args->CrearUsuarioContratista) {
                $args['Nombre']             = $Args->NombreContratista;
                $args['IdUsuario']          = $Args->NombreUsuarioContratista;
                $args['Email']              = $Args->CorreoUsuarioContratista;
                $args['Empresas']           = [$Args->Documento.'-'.$Args->IdTipoDocumento];
                $args['Funciones']          = ['chkAdmEmpresaContratista','chkAdmPersonas','chkAdmVehiculos'];

                $args['EsContratista'] = true;
                $args['CambiarContrasenia'] = true;

                $usuarioController = app(\App\Http\Controllers\UsuarioController::class);
                $usuarioController->create($args);
            }
        });
    }

    private function modificacionAlojamientos($idTipoDocumento, $documento, $Alojamientos) 
	{
        return DB::transaction(function () use ($idTipoDocumento, $documento, $Alojamientos) {
            $alojamientosConId = array_filter($Alojamientos, 
                function($alojamiento){ return isset($alojamiento['IdEmpresasAlojamiento']); }
            );

            $idsANoEliminar = array_map(function($alojamiento){ 
                return $alojamiento['IdEmpresasAlojamiento']; 
            }
            , $alojamientosConId);

            //ELIMINO TODOS MENOS LOS ID QUE ME LLEGAN.
            EmpresasAlojamiento::where('Documento', $documento)
                ->where('IdTipoDocumento', $idTipoDocumento)
                ->whereNotIn('IdEmpresasAlojamiento', $idsANoEliminar)
                ->delete();

            foreach($Alojamientos as $i => $alojamiento){

                if(isset($alojamiento['IdEmpresasAlojamiento'])){
                    $entity = EmpresasAlojamiento::where('IdEmpresasAlojamiento', $alojamiento['IdEmpresasAlojamiento'])->first();
                }else{
                    $entity = new EmpresasAlojamiento();
                    $entity->Documento              = $documento;
                    $entity->IdTipoDocumento        = $idTipoDocumento;
                }

                if (empty($alojamiento['FechaDesde']) || empty($alojamiento['FechaHasta'])) {
                    throw new HttpException(409, 'Falta periodo para el alojamiento #' . ($i + 1));
                }
               
                $entity->IdAlojamiento          = $alojamiento['IdAlojamiento'];
                $entity->IdTipoAlojamiento      = $alojamiento['IdTipoAlojamiento'];
                $entity->CantidadPersonas       = $alojamiento['CantidadPersonas'];
                $entity->FechaDesde             = FsUtils::fromHumanDate($alojamiento['FechaDesde']);
                $entity->FechaHasta             = FsUtils::fromHumanDate($alojamiento['FechaHasta']);
                $entity->TipoReserva            = $alojamiento['TipoReserva'] == 11 ? 1 : 0;
                
                if($alojamiento['TipoAlojamientoEsCasa']){
                    $entity->Direccion = $alojamiento['Direccion'];
                    $entity->Localidad = $alojamiento['Localidad'];
                    $entity->Ubicacion = $alojamiento['Ubicacion'];
                }

                $entity->save();
            }
        });
    }

    private function modificacionTransportes($idTipoDocumento, $documento, $Transportes) 
	{
        return DB::transaction(function () use ($idTipoDocumento, $documento, $Transportes) {
            $transportesConId = array_filter($Transportes, 
                function($transporte){ return isset($transporte['IdEmpresaTransporte']); }
            );

            $idsANoEliminar = array_map(function($transporte){ 
                return $transporte['IdEmpresaTransporte']; 
            }
            , $transportesConId);
            
            EmpresasTransporte::where('Documento', $documento)
                ->where('IdTipoDocumento', $idTipoDocumento)
                ->whereNotIn('IdEmpresaTransporte', $idsANoEliminar)
                ->delete();

            foreach($Transportes as $transporte){

                if(isset($transporte['IdEmpresaTransporte'])){
                    $entity = EmpresasTransporte::where('IdEmpresaTransporte', $transporte['IdEmpresaTransporte'])->first();
                }else{
                    $entity = new EmpresasTransporte();
                    $entity->Documento          = $documento;
                    $entity->IdTipoDocumento    = $idTipoDocumento;
                }
             
                $entity->Serie                  = $transporte['Serie'];
                $entity->Numero                 = $transporte['Numero'];
                $entity->DocumentoChofer1       = $transporte['DocumentoChofer1'];
                $entity->IdTipoDocumentoChofer1 = $transporte['IdTipoDocumentoChofer1'];
                $entity->DocumentoChofer2       = $transporte['DocumentoChofer2'];
                $entity->IdTipoDocumentoChofer2 = $transporte['IdTipoDocumentoChofer2'];
                $entity->TipoReserva            = $transporte['TipoReserva'] === 11 ? 1 : 0;

                $entity->save();
            }
        });
    }

    private function modificacionContactos($idTipoDocumento, $documento, $Contactos) 
	{
        return DB::transaction(function () use ($idTipoDocumento, $documento, $Contactos) {
            EmpresasContacto::where('Documento', $documento)
                ->where('IdTipoDocumento', $idTipoDocumento)
                ->delete();

            foreach($Contactos as $transporte){

                $entity = new EmpresasContacto();

                $entity->Documento      = $documento;
                $entity->IdTipoDocumento= $idTipoDocumento;
                $entity->IdTipoContacto = $transporte['IdTipoContacto'];
                $entity->Nombre         = $transporte['Nombre'];
                $entity->Celular        = $transporte['Celular'];
                $entity->Email          = $transporte['Email'];

                $entity->save();
            }

            $controlContactos = DB::selectOne(
                "if (select count(distinct IdTipoContacto) from EmpresasContactos where Documento = ? and IdTipoDocumento = ?) >= (select count(*) from TiposContacto where baja = 0) 
                    select 1 as resultado
                else
                    select 0 as resultado
                ", [$documento, $idTipoDocumento]
            );
            if ($this->user->EsContratista && $controlContactos->resultado !== "1") {
                throw new HttpException(409, 'Debe cargar un contacto de cada tipo');
            }
        });
    }

    private function altaSectores($Args) 
	{
        if (!empty($Args->Sectores)) {
            $bindingSectores = [];
            $haySectores = false;
            $IdSectorDisp = (int)DB::table('EmpresasSectores')->where("Documento", '=', $Args->Documento)->where("IdTipoDocumento", '=', $Args->IdTipoDocumento)->max("IdSector");

            $IdSectorDisp++;

            foreach ($Args->Sectores as $sector) {

                $sector = (object)$sector;

                if (empty($sector->IdSector)) {
                    $existeSector = [];
                } else {
                    $existeSector = DB::table('EmpresasSectores')
                        ->where("Documento", '=', $Args->Documento)
                        ->where("IdTipoDocumento", '=', $Args->IdTipoDocumento)
                        ->where("IdSector", '=', $sector->IdSector)
                        ->get();
                }
               
                $edit = count($existeSector) > 0;

                $IdSector = '';
                if ($edit) {
                    $binding = [];
                    $binding[':Nombre'] = $sector->Nombre;
                    $binding[':Documento'] = $Args->Documento;
                    $binding[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $binding[':IdSector'] = $sector->IdSector;

                    DB::update("UPDATE EmpresasSectores SET "
                        . "Nombre = :Nombre "
                        . "WHERE Documento = :Documento "
                        . "AND IdTipoDocumento = :IdTipoDocumento "
                        . "AND IdSector = :IdSector", $binding);

                    $IdSector = $sector->IdSector;
                } else {
                    $binding = [];
                    $binding[':Documento'] = $Args->Documento;
                    $binding[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $binding[':IdSectorDisp'] = $IdSectorDisp;
                    $binding[':Nombre'] = $sector->Nombre;
                    $binding[':FechaHora'] = new \DateTime;                    
                    $binding[':IdUsuario'] = $this->user->IdUsuario;                    

                    DB::insert("INSERT INTO EmpresasSectores (Documento, IdTipoDocumento, IdSector, Nombre, FechaHora, IdUsuario) "
                        . "VALUES (:Documento, :IdTipoDocumento, :IdSectorDisp, :Nombre, :FechaHora, :IdUsuario)", $binding);

                    $IdSector = $IdSectorDisp;
                }
                
                $haySectores = true;

                $bindingSectores[] = !empty($sector->IdSector) ? $sector->IdSector : $IdSectorDisp;

                $IdSectorDisp++;
                
                if(!empty($sector->Categorias)) {
                    $binding = [];
                    $binding[':Documento'] = $Args->Documento;
                    $binding[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $binding[':IdSector'] = $IdSector;

                    DB::delete("DELETE FROM EmpresasSectoresAutorizantes WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento AND IdSector = :IdSector", $binding);
                    
                    foreach ($sector->Categorias as $categoria) {
                        $binding = [];
                        $binding[':Documento'] = $Args->Documento;
                        $binding[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                        $binding[':IdSector'] = $IdSector;
                        $binding[':IdCategoria'] = $categoria['IdCategoria'];
                        $binding[':IdUsuario'] = $categoria['IdUsuario'];

                        DB::insert("INSERT INTO EmpresasSectoresAutorizantes (Documento, IdTipoDocumento, IdSector, IdCategoria, IdUsuario) "
                            . "VALUES (:Documento, :IdTipoDocumento, :IdSector, :IdCategoria, :IdUsuario)", $binding);
                    }
                }
            }

            if ($haySectores) {
                $sql = "SELECT IdSector FROM EmpresasSectores "
                    . "WHERE IdSector NOT IN (". implode(', ', array_map(function ($v) { return '?'; }, $bindingSectores)).") "
                    . "AND Documento = ? "
                    . "AND IdTipoDocumento = ?";

                $bindingSectores[] = $Args->Documento;
                $bindingSectores[] = $Args->IdTipoDocumento;

                $sectoresParaEliminar = DB::select($sql, $bindingSectores);

                if (!empty($sectoresParaEliminar)) {
                    foreach ($sectoresParaEliminar as $sector) {
                        DB::update("UPDATE PersonasFisicas SET IdSector = NULL "
                            . "FROM PersonasFisicas pf "
                            . "INNER JOIN PersonasFisicasEmpresas pfe ON pf.Documento = pfe.Documento AND pf.IdTipoDocumento = pfe.IdTipoDocumento "
                            . "WHERE pfe.DocEmpresa = :Documento "
                            . "AND pfe.TipoDocEmpresa = :IdTipoDocumento "
                            . "AND pf.IdSector =:IdSector",
                            [
                                ':Documento' => $Args->Documento,
                                ':IdTipoDocumento' => $Args->IdTipoDocumento,
                                ':IdSector' => $sector->IdSector
                            ]);

                        DB::delete("DELETE FROM EmpresasSectores WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento AND IdSector = :IdSector",
                        [
                            ':Documento' => $Args->Documento,
                            ':IdTipoDocumento' => $Args->IdTipoDocumento,
                            ':IdSector' => $sector->IdSector
                        ]);
                    }
                }
            }
        } else {
            DB::delete("DELETE FROM EmpresasSectores WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento",[":Documento" => $Args->Documento, ":IdTipoDocumento" => $Args->IdTipoDocumento]);
            DB::delete("DELETE FROM EmpresasSectoresAutorizantes WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento",[":Documento" => $Args->Documento, ":IdTipoDocumento" => $Args->IdTipoDocumento]);
        }
    }

    private function altaContratos($Args) 
	{
        if (!empty($Args->ContrataA)) {
            $bindingContratos = [];
            $hayContratos = "";
            foreach ($Args->ContrataA as $contrato) {

                $contrato = (object) $contrato;

                $IdEmpresaContratoObj = FsUtils::explodeId($contrato->IdEmpresa);
                $bindings = [];
                $bindings[':Documento'] = $Args->Documento;
                $bindings[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                $bindings[':IdEmpresaContratoObj0'] = $IdEmpresaContratoObj[0];
                $bindings[':IdEmpresaContratoObj1'] = $IdEmpresaContratoObj[1];
                $bindings[':NroContrato'] = $contrato->NroContrato;

                $edit = !empty(DB::select("SELECT Documento, IdTipoDocumento
                                            FROM EmpresasContratos
                                            WHERE Documento = :Documento
                                            AND IdTipoDocumento = :IdTipoDocumento
                                            AND DocEmpCont = :IdEmpresaContratoObj0
                                            AND IdTipoDocCont = :IdEmpresaContratoObj1
                                            AND NroContrato = :NroContrato",$bindings));
                if ($edit) {

                    $bindings = [];
                    $bindings[':VigenciaDesde'] = $contrato->VigenciaDesde. " 00:00:00";
                    $bindings[':VigenciaHasta'] = $contrato->VigenciaHasta. " 00:00:00";
                    $bindings[':Documento'] = $Args->Documento;
                    $bindings[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $bindings[':IdEmpresaContratoObj0'] = $IdEmpresaContratoObj[0];
                    $bindings[':IdEmpresaContratoObj1'] = $IdEmpresaContratoObj[1];
                    $bindings[':NroContrato'] = $contrato->NroContrato;

                    DB::update("UPDATE EmpresasContratos SET
                                FechaDesde = CONVERT(date, :VigenciaDesde, 103),
                                FechaHasta = CONVERT(date, :VigenciaHasta, 103)
                                WHERE Documento = :Documento
                                AND IdTipoDocumento = :IdTipoDocumento
                                AND DocEmpCont = :IdEmpresaContratoObj0
                                AND IdTipoDocCont = :IdEmpresaContratoObj1
                                AND NroContrato = :NroContrato",$bindings);
                } 
                else {
                    $bindings = [];
                    $bindings[':VigenciaDesde'] = $contrato->VigenciaDesde. " 00:00:00";
                    $bindings[':VigenciaHasta'] = $contrato->VigenciaHasta. " 00:00:00";
                    $bindings[':Documento'] = $Args->Documento;
                    $bindings[':IdTipoDocumento'] = $Args->IdTipoDocumento;
                    $bindings[':IdEmpresaContratoObj0'] = $IdEmpresaContratoObj[0];
                    $bindings[':IdEmpresaContratoObj1'] = $IdEmpresaContratoObj[1];
                    $bindings[':NroContrato'] = $contrato->NroContrato;
                    $bindings[':IdUsuario'] = $this->user->IdUsuario;
                    $bindings[':FechaAlta'] = new \DateTime;

                   DB::insert("INSERT INTO EmpresasContratos (Documento, IdTipoDocumento, DocEmpCont, IdTipoDocCont, NroContrato,
                                                            FechaDesde, FechaHasta, IdUsuarioAlta, FechaHoraAlta) 
                                                    VALUES (:Documento, :IdTipoDocumento, :IdEmpresaContratoObj0, :IdEmpresaContratoObj1, :NroContrato,
													CONVERT(date, :VigenciaDesde, 103), CONVERT(date, :VigenciaHasta, 103), :IdUsuario, :FechaAlta)", $bindings);
                }

                $hayContratos = true;
                $bindingContratos[] = $contrato->IdEmpresa . "-" . $contrato->NroContrato;
            }

            if (!empty($hayContratos)) {
				$sql = "SELECT Documento, IdTipoDocumento, DocEmpCont, IdTipoDocCont, NroContrato
						FROM EmpresasContratos
						WHERE DocEmpCont + '-' + LTRIM(RTRIM(STR(IdTipoDocCont))) + '-' + NroContrato NOT IN (". implode(', ', array_map(function ($v) { return '?'; }, $bindingContratos)).")
						AND   Documento = ?
						AND IdTipoDocumento = ?";

				$bindingContratos[] = $Args->Documento;
                $bindingContratos[] = $Args->IdTipoDocumento;

                $contratosParaEliminar = DB::select($sql,$bindingContratos);
                
                if (!empty($contratosParaEliminar)) {
                    foreach ($contratosParaEliminar as $contrato) {
                        // Desactivar las entidades que quedarán sin contratos

						$bindings = [];
						$bindings[':DocEmpCont'] = $contrato->DocEmpCont;
						$bindings[':IdTipoDocCont'] = $contrato->IdTipoDocCont;
						$bindings[':Documento'] = $contrato->Documento;
						$bindings[':IdTipoDocumento'] = $contrato->IdTipoDocumento;
						$bindings[':NroContrato'] = $contrato->NroContrato;
						$bindings[':DocEmpCont1'] = $contrato->DocEmpCont;
						$bindings[':IdTipoDocCont1'] = $contrato->IdTipoDocCont;
						$bindings[':Documento1'] = $contrato->Documento;
						$bindings[':IdTipoDocumento1'] = $contrato->IdTipoDocumento;
						$bindings[':NroContrato1'] = $contrato->NroContrato;

                        DB::update("UPDATE m SET m.Estado = 0 FROM Maquinas m 
                                WHERE EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpresa = :DocEmpCont AND mc.TipoDocEmpresa = :IdTipoDocCont 
											AND mc.DocEmpCont = :Documento AND mc.IdTipoDocCont = :IdTipoDocumento AND mc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpresa != :DocEmpCont1 
												AND mc.TipoDocEmpresa != :IdTipoDocCont1 AND mc.DocEmpCont != :Documento1 OR mc.IdTipoDocCont != :IdTipoDocumento1 OR mc.NroContrato != :NroContrato1) 
								AND mc.FechaAlta < GETDATE())", $bindings);
                        
                       DB::update("UPDATE p SET p.Estado = 0 FROM PersonasFisicas p 
                                WHERE EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpresa = :DocEmpCont 
											AND pfc.TipoDocEmpresa = :IdTipoDocCont AND pfc.DocEmpCont = :Documento AND pfc.IdTipoDocCont = :IdTipoDocumento AND pfc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpresa != :DocEmpCont1 
												AND pfc.TipoDocEmpresa != :IdTipoDocCont1 AND pfc.DocEmpCont != :Documento1 OR pfc.IdTipoDocCont != :IdTipoDocumento1 OR pfc.NroContrato != :NroContrato1) 
								AND pfc.FechaAlta < GETDATE())",$bindings);
                        
                       DB::update("UPDATE v SET v.Estado = 0 FROM Vehiculos v 
                                WHERE EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpresa = :DocEmpCont 
											AND vc.TipoDocEmpresa = :IdTipoDocCont AND vc.DocEmpCont = :Documento AND vc.IdTipoDocCont = :IdTipoDocumento 
											AND vc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpresa != :DocEmpCont1
												AND vc.TipoDocEmpresa != :IdTipoDocCont1 AND vc.DocEmpCont != :Documento1 OR vc.IdTipoDocCont != :IdTipoDocumento1 OR vc.NroContrato != :NroContrato1) 
								AND vc.FechaAlta < GETDATE())",$bindings);
                        

						$bindings = [];
						$bindings[':IdUsuario'] = $this->user->IdUsuario;
						$bindings[':DocEmpCont'] = $contrato->DocEmpCont;
						$bindings[':IdTipoDocCont'] = $contrato->IdTipoDocCont;
						$bindings[':Documento'] = $contrato->Documento;
						$bindings[':IdTipoDocumento'] = $contrato->IdTipoDocumento;
						$bindings[':NroContrato'] = $contrato->NroContrato;
						$bindings[':DocEmpCont1'] = $contrato->DocEmpCont;
						$bindings[':IdTipoDocCont1'] = $contrato->IdTipoDocCont;
						$bindings[':Documento1'] = $contrato->Documento;
						$bindings[':IdTipoDocumento1'] = $contrato->IdTipoDocumento;
						$bindings[':NroContrato1'] = $contrato->NroContrato;

						$bindings[':NroContratotexto'] = 'Desactivado por eliminación de contrato '.$contrato->NroContrato;

                       	DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)
                                
                                SELECT GETDATE(), :IdUsuario, 'mzmaquina', m.NroSerie, tm.Descripcion + ' ' + mm.Descripcion + ' ' + m.Modelo, '', 'FSAccesoWeb', :NroContratotexto
                                FROM Maquinas m
                                INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                                INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                                WHERE EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpresa = :DocEmpCont AND mc.TipoDocEmpresa = :IdTipoDocCont AND mc.DocEmpCont = :Documento AND mc.IdTipoDocCont = :IdTipoDocumento AND mc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpresa != :DocEmpCont1 AND mc.TipoDocEmpresa != :IdTipoDocCont1 AND mc.DocEmpCont != :Documento1 OR mc.IdTipoDocCont != :IdTipoDocumento1 OR mc.NroContrato != :NroContrato1) 
								AND mc.FechaAlta < GETDATE())",$bindings);
                                
						DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)
                                
                                SELECT GETDATE(), :IdUsuario, 'mzpersonafisica', p.Documento + '-' + LTRIM(RTRIM(STR(p.IdTipoDocumento))), p.NombreCompleto, '', 'FSAccesoWeb', :NroContratotexto
                                FROM PersonasFisicas p 
                                WHERE EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpresa = :DocEmpCont AND pfc.TipoDocEmpresa = :IdTipoDocCont AND pfc.DocEmpCont = :Documento AND pfc.IdTipoDocCont = :IdTipoDocumento AND pfc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpresa != :DocEmpCont1 AND pfc.TipoDocEmpresa != :IdTipoDocCont1 AND pfc.DocEmpCont != :Documento1 OR pfc.IdTipoDocCont != :IdTipoDocumento1 OR pfc.NroContrato != :NroContrato1) 
								AND pfc.FechaAlta < GETDATE())",$bindings);
                                
						DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)

                                SELECT GETDATE(), :IdUsuario, 'mzvehiculo', v.Serie + '-' + LTRIM(RTRIM(STR(v.Numero))), tv.Descripcion + ' ' + mv.Descripcion + ' ' + v.Modelo, '', 'FSAccesoWeb', :NroContratotexto
                                FROM Vehiculos v 
                                INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                                INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic 
                                WHERE EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpresa = :DocEmpCont AND vc.TipoDocEmpresa = :IdTipoDocCont AND vc.DocEmpCont = :Documento AND vc.IdTipoDocCont = :IdTipoDocumento AND vc.NroContrato = :NroContrato))
                                AND NOT EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpresa != :DocEmpCont1 AND vc.TipoDocEmpresa != :IdTipoDocCont1 AND vc.DocEmpCont != :Documento1 OR vc.IdTipoDocCont != :IdTipoDocumento1 OR vc.NroContrato != :NroContrato1)
								AND vc.FechaAlta < GETDATE())",$bindings);
	
						$bindings = [];
						$bindings[':Documento'] = $contrato->Documento;
						$bindings[':IdTipoDocumento'] = $contrato->IdTipoDocumento;
						$bindings[':NroContrato'] = $contrato->NroContrato;

                        // Sacar estos contratos de las relaciones con otras entidades
                        DB::delete("DELETE FROM EventosContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);

						$bindings[':DocEmpCont'] = $contrato->DocEmpCont;
						$bindings[':IdTipoDocCont'] = $contrato->IdTipoDocCont;

                        DB::delete("DELETE FROM MaquinasContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);
                        DB::delete("DELETE FROM MaquinasTransacContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);
                        DB::delete("DELETE FROM PersonasFisicasContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);
                        DB::delete("DELETE FROM PersonasFisicasTransacContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);
                        DB::delete("DELETE FROM VehiculosContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);
                        DB::delete("DELETE FROM VehiculosTransacContratos WHERE DocEmpresa = :DocEmpCont AND TipoDocEmpresa =:IdTipoDocCont AND DocEmpCont = :Documento AND IdTipoDocCont =:IdTipoDocumento AND NroContrato = :NroContrato ", $bindings);

                        DB::delete("DELETE FROM EmpresasContratos
                               WHERE Documento = :Documento
                               AND IdTipoDocumento =:IdTipoDocumento
                               AND DocEmpCont = :DocEmpCont
                               AND IdTipoDocCont =:IdTipoDocCont
                               AND NroContrato = :NroContrato ", $bindings);
                    }
                }
            }
        } else {
			$bindings = [];
			$bindings[':Documento'] = $Args->Documento;
			$bindings[':IdTipoDocumento'] = $Args->IdTipoDocumento;
			$bindings[':Documento1'] = $Args->Documento;
			$bindings[':IdTipoDocumento1'] = $Args->IdTipoDocumento;
						
            // Desactivar las entidades que quedarán sin contratos
            DB::update("UPDATE m SET m.Estado = 0 
                      FROM Maquinas m 
                      WHERE EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpCont = :Documento AND mc.IdTipoDocCont = :IdTipoDocumento))
                      AND NOT EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpCont != :Documento1 OR mc.IdTipoDocCont != :IdTipoDocumento1) AND mc.FechaAlta < GETDATE())", $bindings);
            
            DB::update("UPDATE p SET p.Estado = 0 
                      FROM PersonasFisicas p 
                      WHERE EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpCont = :Documento AND pfc.IdTipoDocCont = :IdTipoDocumento))
                      AND NOT EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpCont != :Documento1 OR pfc.IdTipoDocCont != :IdTipoDocumento1) AND pfc.FechaAlta < GETDATE())", $bindings);
            
            DB::update("UPDATE v SET v.Estado = 0 
                      FROM Vehiculos v 
                      WHERE EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpCont = :Documento AND vc.IdTipoDocCont = :IdTipoDocumento))
                      AND NOT EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpCont != :Documento1 OR vc.IdTipoDocCont != :IdTipoDocumento1) AND vc.FechaAlta < GETDATE())", $bindings);
            
			$bindings[':IdUsuario'] = $this->user->IdUsuario;

            DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)
                                
                        SELECT GETDATE(), :IdUsuario, 'mzmaquina', m.NroSerie, tm.Descripcion + ' ' + mm.Descripcion + ' ' + m.Modelo, '', 'FSAccesoWeb', 'Desactivado por eliminación de contratos'
                        FROM Maquinas m
                        INNER JOIN TiposMaquinas tm ON tm.IdTipoMaquina = m.IdTipoMaq
                        INNER JOIN MarcasMaquinas mm ON mm.IdMarcaMaq = m.IdMarcaMaq
                        WHERE EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpCont = :Documento AND mc.IdTipoDocCont = :IdTipoDocumento))
                        AND NOT EXISTS(SELECT 1 FROM MaquinasContratos mc WHERE mc.NroSerie = m.NroSerie AND (mc.DocEmpCont != :Documento1 OR mc.IdTipoDocCont != :IdTipoDocumento1) 
						AND mc.FechaAlta < GETDATE())", $bindings);

			DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)

                        SELECT GETDATE(), :IdUsuario, 'mzpersonafisica', p.Documento + '-' + LTRIM(RTRIM(STR(p.IdTipoDocumento))), p.NombreCompleto, '', 'FSAccesoWeb', 'Desactivado por eliminación de contratos'
                        FROM PersonasFisicas p 
                        WHERE EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpCont = :Documento AND pfc.IdTipoDocCont = :IdTipoDocumento))
                        AND NOT EXISTS(SELECT 1 FROM PersonasFisicasContratos pfc WHERE pfc.Documento = p.Documento AND pfc.IdTipoDocumento = p.IdTipoDocumento AND (pfc.DocEmpCont != :Documento1 OR pfc.IdTipoDocCont != :IdTipoDocumento1) 
						AND pfc.FechaAlta < GETDATE())", $bindings);

            DB::insert("INSERT INTO LogActividades (FechaHora, IdUsuario, Entidad, EntidadId, EntidadDesc, Observacion, Modulo, Operacion)

                        SELECT GETDATE(), :IdUsuario, 'mzvehiculo', v.Serie + '-' + LTRIM(RTRIM(STR(v.Numero))), tv.Descripcion + ' ' + mv.Descripcion + ' ' + v.Modelo, '', 'FSAccesoWeb', 'Desactivado por eliminación de contratos'
                        FROM Vehiculos v 
                        INNER JOIN TiposVehiculos tv ON tv.IdTipoVehiculo = v.IdTipoVehiculo
                        INNER JOIN MarcasVehiculos mv ON mv.IdMarcaVehic = v.IdMarcaVehic 
                        WHERE EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpCont = :Documento AND vc.IdTipoDocCont = :IdTipoDocumento))
                        AND NOT EXISTS(SELECT 1 FROM VehiculosContratos vc WHERE vc.Serie = v.Serie AND vc.Numero = v.Numero AND (vc.DocEmpCont != :Documento1 OR vc.IdTipoDocCont != :IdTipoDocumento1) 
						AND vc.FechaAlta < GETDATE())", $bindings);

            
			$bindings = [];
			$bindings[':Documento'] = $Args->Documento;
			$bindings[':IdTipoDocumento'] = $Args->IdTipoDocumento;

            // Sacar los contratos de esta empresa de las relaciones con otras entidades
            DB::delete("DELETE FROM EventosContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM MaquinasContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM MaquinasTransacContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM PersonasFisicasContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM PersonasFisicasTransacContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM VehiculosContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);
            DB::delete("DELETE FROM VehiculosTransacContratos WHERE DocEmpCont = :Documento AND IdTipoDocCont = :IdTipoDocumento", $bindings);

            DB::delete("DELETE FROM EmpresasContratos WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);
        }
    }
    
	private function altaEstado($Args) 
	{
        return $this->amEstado($Args, "Alta");
    }
    
    private function modificacionEstado($Args) 
	{
        return $this->amEstado($Args, "Modificación");
    }
    
    private function amEstado($Args, $Accion) 
	{
        try {
            if ((empty($Args->Estado) && $this->desactivar_interno($Args)) || 
                ($Args->Estado == 1 && $this->activar_interno($Args))) {

				// LogAuditoria::log(
				// 	$this->user->IdUsuario,
				// 	Empresa::class,
				// 	$Accion,
				// 	$Args,
				// 	$Args->Documento . "-" . $Args->IdTipoDocumento,
				// 	$Args->Nombre. ' ('.$Args->Documento . "-" . $Args->IdTipoDocumento.')'
                // );
                return true;
            }
        } catch (Exception $ex) {
            // Este catch agarra las excepciones generadas por wsactivar_interno
            $Args->Estado = 0;
			// LogAuditoria::log(
			// 	$this->user->IdUsuario,
			// 	Empresa::class,
			// 	$Accion,
			// 	$Args,
			// 	$Args->Documento . "-" . $Args->IdTipoDocumento,
			// 	$Args->Nombre. ' ('.$Args->Documento . "-" . $Args->IdTipoDocumento.')');

            throw new HttpException(409, $ex->getMessage());
        }
    }

	private function desactivar_interno($Args) 
	{
        return DB::transaction(function () use ($Args) {
            $empresa = Empresa::where('Documento', '=', $Args->Documento)
                ->where('IdTipoDocumento', '=', $Args->IdTipoDocumento)
                ->firstOrFail();

            $empresa->Estado = 0;
            $empresa->save();

            $bindings = [
                ':Documento' => $Args->Documento,
                ':IdTipoDocumento' => $Args->IdTipoDocumento,
            ];
            
            DB::update("UPDATE m SET m.Estado = 0 FROM Maquinas m WHERE m.DocEmpresa = :Documento AND m.TipoDocEmp = :IdTipoDocumento", $bindings);
            
            DB::update("UPDATE pf SET pf.Estado = 0 FROM PersonasFisicas pf "
                . "INNER JOIN PersonasFisicasEmpresas pfe ON pfe.Documento = pf.Documento AND pfe.IdTipoDocumento = pf.IdTipoDocumento "
                . "WHERE pfe.DocEmpresa = :Documento AND pfe.TipoDocEmpresa = :IdTipoDocumento", $bindings);
            
            DB::update("UPDATE v SET v.Estado = 0 FROM Vehiculos v WHERE v.DocEmpresa = :Documento AND v.TipoDocEmp = :IdTipoDocumento", $bindings);

            return true;
        });
    }

	private function activar_interno($Args)
	{
        Empresa::exigirArgs($Args, ['Documento', 'IdTipoDocumento', 'Estado']);

        Empresa::esActivable($Args);

        $empresa = Empresa::where('Documento', $Args->Documento)
            ->where('IdTipoDocumento', $Args->IdTipoDocumento)
            ->firstOrFail();

        $empresa->Estado = 1;
        $empresa->save();

        return true;
    }

    public function delete(int $idTipoDocumento, string $documento) 
    {
        DB::transaction(function () use ($idTipoDocumento, $documento){
            
            $bindings = [];
            $bindings[':IdUsuario'] = $this->user->IdUsuario;
            $bindings[':Documento'] = $documento;
            $bindings[':IdTipoDocumento'] = $idTipoDocumento;
            $bindings[':FechaHoy'] = new \DateTime;

            DB::update("UPDATE Personas SET Baja = 1, FechaHoraBaja = :FechaHoy, IdUsuarioBaja = :IdUsuario "
                . "WHERE Documento = :Documento AND IdTipoDocumento = :IdTipoDocumento", $bindings);

            DB::update("UPDATE Empresas SET Estado = 0 WHERE Documento = ? AND IdTipoDocumento = ?", [$bindings[':Documento'], $bindings[':IdTipoDocumento']]);

            DB::update("UPDATE PersonasFisicas SET Estado = 0 WHERE DocEmpresa = ? AND TipoDocEmpresa = ?", [$bindings[':Documento'], $bindings[':IdTipoDocumento']]);

            DB::update("UPDATE Maquinas SET Estado = 0 WHERE DocEmpresa = ? AND TipoDocEmp = ?", [$bindings[':Documento'], $bindings[':IdTipoDocumento']]);

            DB::update("UPDATE Vehiculos SET Estado = 0 WHERE DocEmpresa = ? AND TipoDocEmp = ?", [$bindings[':Documento'], $bindings[':IdTipoDocumento']]);

            // TODO: Quitar empresa del resto de las relaciones

            // $bindings = [];
            // $bindings[':IdTipoDocumento'] = $idTipoDocumento;
            // $bindings[':Documento'] = $documento;

            // DB::update("UPDATE Maquinas SET DocEmpresa = NULL, TipoDocEmp = NULL 
            //     WHERE DocEmpresa = :Documento AND TipoDocEmp = :IdTipoDocumento",$bindings);

            // $bindings[':IdUsuario'] = $this->user->IdUsuario;

            // DB::update("INSERT INTO MaquinasContratosBajas (DocEmpCont, DocEmpresa, FechaBaja, FechaHoraBaja, IdTipoDocCont,
            //             IdUsuarioBaja, Matricula, NroContrato, NroSerie, TipoDocEmpresa)
            //             SELECT 
            //                 mc.DocEmpCont, mc.DocEmpresa, GETDATE(), GETDATE(), mc.IdTipoDocCont,
            //                 :IdUsuario, m.Matricula, mc.NroContrato, mc.NroSerie, mc.TipoDocEmpresa
            //             FROM MaquinasContratos mc
            //             INNER JOIN EmpresasContratos ec ON ec.DocEmpCont = mc.DocEmpCont AND ec.IdTipoDocCont = mc.IdTipoDocCont
            //             INNER JOIN Maquinas m ON mc.NroSerie = m.NroSerie
            //             WHERE mc.DocEmpCont = :Documento AND mc.IdTipoDocCont = :IdTipoDocumento",$bindings);

            // $bindings = [];
            // $bindings[':IdTipoDocumento'] = $idTipoDocumento;
            // $bindings[':Documento'] = $documento;

            // DB::update("UPDATE Vehiculos SET DocEmpresa = NULL, TipoDocEmp = NULL 
            //             WHERE DocEmpresa = :Documento AND TipoDocEmp = :IdTipoDocumento",$bindings);
        });
        
        LogAuditoria::log(
            $this->user->IdUsuario,
            Empresa::class,
            LogAuditoria::FSA_METHOD_DELETE,
            [$documento, $idTipoDocumento],
            $documento . "-" . $idTipoDocumento,
            "(".$documento . "-" . $idTipoDocumento.")");
    }

    public function desactivar(int $idTipoDocumento, string $documento) 
    {
        $detalle = $this->show_interno($idTipoDocumento, $documento);
        if ($this->desactivar_interno($detalle)) {
            LogAuditoria::log(
                $this->user->IdUsuario,
                "PersonaFisica",
                LogAuditoria::FSA_METHOD_DESACTIVATE,
                $detalle,
                $documento . "-" . $idTipoDocumento,
                $detalle->Nombre . " (".$documento . "-" . $idTipoDocumento.")");

        }
    }

    public function activar(int $idTipoDocumento, string $documento) 
    {
        $detalle = $this->show_interno($idTipoDocumento, $documento);
        if ($this->activar_interno($detalle)) {
            LogAuditoria::log(
                $this->user->IdUsuario,
                Empresa::class,
                LogAuditoria::FSA_METHOD_ACTIVATE,
                $detalle,
                $documento . "-" . $idTipoDocumento,
                $detalle->Nombre . " (".$documento . "-" . $idTipoDocumento.")");
        }
    }

    public function cambiarIdentificador(int $idTipoDocumento, string $documento) 
    {
        $args = (object)$this->req->all();
        
        $entity = $this->show_interno($idTipoDocumento, $documento);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('La empresa no existe');
        }
        
        $checkEntity = $this->show_interno($args->NuevoIdTipoDocumento, $args->NuevoDocumento);

        if (isset($checkEntity)) {
            throw new ConflictHttpException('El nuevo documento se encuentra utilizado por otra empresa');
        }

        $tables = [
            ['Actividades', 'Documento|IdTipoDocumento'],
            ['AuxPersonas', 'Documento|IdTipoDocumento'],
            ['Empresas', 'Documento|IdTipoDocumento'],
            ['EmpresasContratos', 'Documento|IdTipoDocumento, DocEmpCont|IdTipoDocCont'],
            ['EmpresasContratosNumeros', 'Documento|IdTipoDocumento, DocEmpCont|IdTipoDocCont'],
            ['EmpresasDocs', 'Documento|IdTipoDocumento'],
            ['EmpresasDocsItems', 'Documento|IdTipoDocumento'],
            ['EmpresasSectores', 'Documento|IdTipoDocumento'],
            ['Eventos', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa'],
            ['EventosContratos', 'DocEmpCont|IdTipoDocCont'],
            ['EventosDuplicados', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa'],
            ['HISTEmpresas', 'Documento|IdTipoDocumento'],
            ['HISTEmpresasContratos', 'Documento|IdTipoDocumento, DocEmpCont|IdTipoDocCont'],
            ['HISTEmpresasDocs', 'Documento|IdTipoDocumento'],
            ['HISTEmpresasDocsItems', 'Documento|IdTipoDocumento'],
            ['HISTEmpresasSectores', 'Documento|IdTipoDocumento'],
            ['HISTMaquinas', 'DocEmpresa|TipoDocEmp'],
            ['HISTMaquinasActivos', 'docEmpresa|tipoDocEmpresa'],
            ['HISTPersonas', 'Documento|IdTipoDocumento'],
            ['HISTPersonasFisicasEmpresas', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa'],
            ['HISTVehiculos', 'DocEmpresa|TipoDocEmp'],
            ['HISTVehiculosActivos', 'docEmpresa|tipoDocEmpresa'],
            ['Maquinas', 'DocEmpresa|TipoDocEmp'],
            ['MaquinasContratos', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['MaquinasContratosAltas', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['MaquinasContratosBajas', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['Personas', 'Documento|IdTipoDocumento'],
            ['PersonasFisicasContratos', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['PersonasFisicasContratosAltas', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['PersonasFisicasContratosBajas', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['PersonasFisicasEmpresas', 'Documento|IdTipoDocumento, DocEmpresa|TipoDocEmpresa'],
            ['TMPPresHoras', 'Documento|IdTipoDocumento'],
            ['TMPPresPersonas', 'Documento|IdTipoDocumento'],
            ['Usuarios', 'UltimaEmpresaDocumento|UltimaEmpresaIdTipoDocumento'],
            ['UsuariosEmpresas', 'Documento|IdTipoDocumento'],
            ['Vehiculos', 'DocEmpresa|TipoDocEmp'],
            ['VehiculosContratos', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['VehiculosContratosAltas', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont'],
            ['VehiculosContratosBajas', 'DocEmpresa|TipoDocEmpresa, DocEmpCont|IdTipoDocCont']
        ];

        $args = (object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
            'NuevoDocumento' => $args->NuevoDocumento,
            'NuevoIdTipoDocumento' => $args->NuevoIdTipoDocumento,
        ];
        
        Empresa::cambiarIdentificador($tables, $args);

        LogAuditoria::log(
            Auth::id(),
            Empresa::class,
            'cambiar id',
            $args,
            implode('-', [$args->Documento, $args->IdTipoDocumento]),
            sprintf('%s (%s)', $entity->Nombre, implode('-', [$args->Documento, $args->IdTipoDocumento]))
        );
    }

    public function comprobarIdentificador(int $idTipoDocumento, string $documento)
    {
        return Empresa::comprobarIdentificador((object)[
            'Documento' => $documento,
            'IdTipoDocumento' => $idTipoDocumento,
        ]);
    }

    // public function chequearIdentificador(int $idTipoDocumento, string $documento) 
    // {
    //     $sql = "SELECT p.Baja,
    //             CASE e.Estado
    //                 WHEN 0 THEN 'inactive'
    //                 WHEN 1 THEN 'active'
    //             END AS Estado
    //             FROM Personas p
    //             INNER JOIN Empresas e ON e.Documento = p.Documento AND e.IdTipoDocumento = p.IdTipoDocumento
    //             WHERE p.Documento = :documento AND p.IdTipoDocumento = :idTipoDocumento";

    //     $obj = DB::selectOne($sql,[":documento" => $documento, ":idTipoDocumento" => $idTipoDocumento]);

    //     if (!$obj) {
    //         return true;
    //     }

    //     if ($obj->Baja == 0) {
    //         throw new HttpException(409, "El documento esta siendo utilizado");
    //     } else {
    //         throw new HttpException(409, "El documento no esta disponible");
    //     }
    // }

    public function busqueda()
    {
        $Args = (object) $this->req->All();

        $bindings = [];
        $sql = "SELECT DISTINCT 'func=AdmEmpresas|Documento=' + e.Documento + '|IdTipoDocumento=' + LTRIM(RTRIM(STR(e.IdTipoDocumento))) AS ObjUrl,
                                dbo.Mask(p.Documento, td.Mascara, 1, 1) as Documento,
                                p.IdTipoDocumento,
                                td.Descripcion AS TipoDocumento,
                                c.Descripcion AS Categoria,
                                e.Nombre,
                                e.Alias,
                                pa.Nombre AS Pais,
                                d.Nombre AS Departamento,
                                p.Ciudad,
                                p.Localidad,
                                p.Direccion,
                                p.Email,
                                (STUFF((SELECT CAST(', ' + NroContrato + ' - ' + CONVERT(VARCHAR(10), FechaHasta, 103) AS VARCHAR(MAX)) FROM EmpresasContratos ec WHERE (ec.Documento = p.Documento AND ec.IdTipoDocumento = p.IdTipoDocumento) FOR XML PATH ('')), 1, 2, '')) AS NroContrato
                FROM Personas p 
                INNER JOIN Empresas e ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento 
                INNER JOIN TiposDocumento td ON p.IdTipoDocumento = td.IdTipoDocumento
                INNER JOIN Categorias c ON p.IdCategoria = c.IdCategoria
                LEFT JOIN Paises pa ON p.IdPais = pa.IdPais
                LEFT JOIN Departamentos d ON p.IdPais = d.IdPais AND p.IdDepartamento = d.IdDepartamento ";

        $bs = 'p.Baja = 0';

        if (!empty($Args)) {
            $js = "";
            $ws = "";

            foreach ($Args as $key => $value) {
                switch ($key) {
                    case 'output':
                    case 'token':
                    case 'page':
                    case 'pageSize':
                        break;
                    case 'Baja':
                        if ($value == 1)
                            $bs = 'p.Baja IN (0, 1)';
                        break;
                        
                    case 'IdCategoria':
                    case 'IdDepartamento':
                    case 'IdPais':
                        $k = ':'.$key;
                        $bindings[$k] = $value;
                        $key = 'p.' . $key;
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " = ".$k;
                        break;

                    default:
                        $k = ':'.$key;
                        $bindings[$k] = "%" . $value . "%";
                        switch ($key) {
                            case 'Documento':
                                $key = 'p.' . $key;
                                break;

                            default:
                                $key = 'e.' . $key;
                        }
                        
                        $ws .= (empty($ws) ? " WHERE " : " AND ") . $key . " LIKE ".$k;
                        break;
                }
            }
        }

        $sql .= $js . $ws . (empty($ws) ? " WHERE " : " AND ") . $bs;

        if (empty($this->user->Gestion)) {
            /**
             * @todo
             */
            /*$ide = FsUtils::explodeId($IdEmpresa);
            $sql .= " AND e.Documento = '" . $ide[0] . "' AND e.IdTipoDocumento = " . $ide[1] . ";";*/
        }
            
        $sql .= " ORDER BY Nombre";

        $page = (int)$this->req->input('page', 1);
        $items = DB::select($sql, $bindings);

        $output = isset($Args->output);

        if ($output !== 'json' && $output == true) {

            $output = $Args->output;

            $dataOutput = array_map(function($item) {
                return [
                    'Documento' => $item->Documento,
                    'TipoDocumento' => $item->TipoDocumento,
                    'Nombre' => $item->Nombre,
                    'Categoria' => $item->Categoria,
                    'Pais' => $item->Pais,
                    'Departamento' => $item->Departamento,
                    'Ciudad' => $item->Ciudad,
                    'Localidad' => $item->Localidad,
                    'Direccion' => $item->Direccion,
                    'Email' => $item->Email
                ];
            },$items);

            $filename = 'FSAcceso-Empresas-Consulta-' . date('Ymd his');

            $headers = [
                'Documento' => 'Documento',
                'TipoDocumento' => 'Tipo de Documento',
                'Nombre' => 'Nombre',
                'Categoria' => 'Categoría',
                'Pais' => 'País',
                'Departamento' => 'Departamento',
                'Ciudad' => 'Ciudad',
                'Localidad' => 'Localidad',
                'Direccion' => 'Dirección',
                'Email' => 'Correo electrónico',
            ];
            
            return FsUtils::export($output, $dataOutput, $headers, $filename);
        }

        $paginate = FsUtils::paginateArray($items, $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);

    }

    public function subirDocs() {

        $Args = $this->req->All();
        
        $file = $this->req->file('Archivo-file');
        $file->storeAs('uploads/empresas/docs', $Args['filename']);

        return true;
    }

    public function verArchivo($carpeta, $fileName){

        $adjunto = storage_path('app/uploads/empresas/'. $carpeta .'/'.$fileName);

        $content_type = mime_content_type($adjunto);

        if (isset($adjunto)) {
            header('Content-Type: '. $content_type);
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
        
            header('Cache-Control: max-age=0');
            // If you're serving to IE 9, then the following may be needed
            header('Cache-Control: max-age=1');
            // If you're serving to IE over SSL, then the following may be needed
            header ('Expires: Mon, 03 Jan 1991 05:00:00 GMT'); // Date in the past
            header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
            header ('Pragma: public'); // HTTP/1.0
            echo file_get_contents($adjunto);
        }
    }

    public function updateAttach(int $idTipoDocumento, string $documento, string $field)
    {
        $entity = Empresa::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('No existe empresa');
        }

        if (!in_array($field, self::$availableFields)) {
            throw new BadRequestHttpException($field . ' no es un campo válido para subir archivos.');
        }

        $filename = $entity->{$field};
        $folder = implode(DIRECTORY_SEPARATOR, ['uploads', 'empresas', 'attachs']);
        $relativepath = implode(DIRECTORY_SEPARATOR, [$folder, $filename]);
        $pathname = storage_path($relativepath);
        if (file_exists($pathname)) {
            unlink($pathname);
        }

        $file = $this->req->file('file');
        $filename = implode('-', [$field, $idTipoDocumento, $documento]) . '.' . $file->getClientOriginalExtension();

        $file->storeAs($folder, $filename);

        $result = DB::update(
            'UPDATE Empresas SET ' . $field . ' = ? WHERE IdTipoDocumento = ? and Documento = ?',
            [$filename, $idTipoDocumento, $documento]
        );

        if ($result && $field === 'ProtocoloCovidArchivo') {
            $result = DB::update(
                'UPDATE Empresas SET ProtocoloCovidEstado = 0 WHERE IdTipoDocumento = ? and Documento = ?',
                [$idTipoDocumento, $documento]
            );
        }

        if (!$result) {
            throw new BadRequestHttpException('Error al guardar el archivo');
        }

        LogAuditoria::log(
            Auth::id(),
            Empresa::class,
            'update-attach',
            ['field' => $field],
            [$documento, $idTipoDocumento],
            sprintf('%s (%s-%s)', $entity->Empresa, $documento, $idTipoDocumento)
        );

        return [
            'filename' => $filename,
        ];
    }

    public function deleteAttach(int $idTipoDocumento, string $documento, string $field)
    {
        $entity = Empresa::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('No existe empresa');
        }

        if (!in_array($field, self::$availableFields)) {
            throw new BadRequestHttpException($field . ' no es un campo válido para eliminar archivos.');
        }

        $result = DB::update(
            'UPDATE Empresas SET ' . $field . ' = NULL WHERE IdTipoDocumento = ? and Documento = ?',
            [$idTipoDocumento, $documento]
        );

        if ($result && $field === 'ProtocoloCovidArchivo') {
            $result = DB::update(
                'UPDATE Empresas SET ProtocoloCovidEstado = 0 WHERE IdTipoDocumento = ? and Documento = ?',
                [$idTipoDocumento, $documento]
            );
        }

        if (!$result) {
            throw new BadRequestHttpException('Error al eliminar el archivo');
        }

        $relativepath = implode(DIRECTORY_SEPARATOR, ['app', 'uploads', 'empresas', 'attachs', $entity->{$field}]);
        $pathname = storage_path($relativepath);

        if (file_exists($pathname)) {
            unlink($pathname);
        }

        LogAuditoria::log(
            Auth::id(),
            PersonaFisica::class ,
            'delete-attach',
            ['field' => $field],
            [$documento, $idTipoDocumento],
            sprintf('%s (%s-%s)', $entity->Nombre, $documento, $idTipoDocumento)
        );
    }

    public function downloadAttach(int $idTipoDocumento, string $documento, string $field)
    {
        $entity = Empresa::where('Documento', $documento)
            ->where('IdTipoDocumento', $idTipoDocumento)
            ->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('No existe empresa');
        }

        if (!in_array($field, self::$availableFields)) {
            throw new BadRequestHttpException($field . ' no es un campo válido para descargar archivos.');
        }

        $filename = $entity->{$field};
        $relativepath = implode(DIRECTORY_SEPARATOR, ['app', 'uploads', 'empresas', 'attachs', $filename]);
        $pathname = storage_path($relativepath);

        if (!file_exists($pathname)) {
            throw new NotFoundHttpException('Archivo no encontrado');
        }

        header('Content-Type: ' . mime_content_type($pathname));
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 03 Jan 1991 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        echo file_get_contents($pathname);
    }
}