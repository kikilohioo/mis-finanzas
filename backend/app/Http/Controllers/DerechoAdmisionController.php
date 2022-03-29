<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\DerechoAdmision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DerechoAdmisionController extends Controller
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
     * @todo Agregar validacion para UsuarioHabilitado
     */
    public function index()
    {
        $query = DerechoAdmision::query()->where('Baja', false);

        // if ($this->req->input('mostrarEliminados', 'false') === 'false') {
        //     $query->where('Baja', false);
        // }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where(function ($query) use ($busqueda) {
                return $query->where('Documento', 'like', '%' . $busqueda . '%')
                    ->orWhere('NombreCompleto', 'like', '%' . $busqueda . '%')
                    ->orWhere('Motivo', 'like', '%' . $busqueda . '%');
            });
        }

        $data = $query->orderBy('Documento')->get();

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $filename = 'FSAcceso-Derecho-Admision-Consulta-' . date('Ymd-his');

            $headers = [
                'Documento' => 'Documento',
                'NombreCompleto' => 'Nombre',
                'FechaHora' => 'Fecha Ingreso',
                'Motivo' => 'Motivo'
            ];

            return FsUtils::export($output, $data->toArray(), $headers, $filename);
        }

        return $this->responsePaginate($data);
    }

    /**
     * @todo Agregar validacion para UsuarioHabilitado
     */
    public function show(int $id)
    {
        $entity = DerechoAdmision::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Derecho de Admisión no encontrado');
        }

        return $entity;
    }

    /**
     * @todo Agregar validacion para UsuarioHabilitado
     * @todo Agregar Select PersonasFisicas para obtener el Documento, para desactivar la Persona Fisica. wsdesactivar
     */
    public function create()
    {
        DerechoAdmision::exigirArgs($this->req->all(), ['Documento', 'NombreCompleto', 'Motivo']);
        
        $entity = new DerechoAdmision($this->req->all());
        $entity->IdDerechoAdmision = DerechoAdmision::getNextId();
        $entity->Documento = $this->req->input('Documento');
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();
        $entity->Baja = false;

        $entity->save();

        // Graba en el LogActividad un registro con el NombreCompleto + el documento

        return $entity->refresh();
    }

    /**
     * @todo Agregar validacion para UsuarioHabilitado
     */
    public function update(int $id)
    {
        DerechoAdmision::exigirArgs($this->req->all(), ['IdDerechoAdmision', 'NombreCompleto', 'Motivo']);

        $entity = DerechoAdmision::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Derecho de Admisión no encontrado');
        }
        $entity->NombreCompleto = $this->req->input('NombreCompleto');
        $entity->Motivo = $this->req->input('Motivo');
        $entity->Baja = false;
        $entity->save();

        // Graba en el LogActividad un registro con el NombreCompleto + el documento

        return $entity;
    }

    /**
     * @todo Agregar validacion para UsuarioHabilitado
     */
    public function delete(int $id)
    {
        $entity = DerechoAdmision::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Derecho de Admisión no encontrado');
        }
        
        $entity->Baja = true;
        $entity->save();
    }

    /**
     * @todo Agregar validacion para UsuarioHabilitado y la DATA.
    */
    public static function comprobarDocumento($documento) {
        $sql = DB::select("SELECT Documento, IdTipoDocumento, NombreCompleto FROM 
                        PersonasFisicas WHERE Estado = 1 AND REPLACE(REPLACE(REPLACE(REPLACE(Documento, ' ', ''), '-', ''), '_', ''), '.', '') LIKE ? ",
                        [$documento ]);

        if ($sql) {
            throw new ConflictHttpException("Listado de Personas a Desactivar" . $sql);
        }
    }

    /**
     * @todo Solo Utiliza Usuario y Empresa para ejecutar la consulta.
     */
    public static function comprobarDocumentoEnLista($documento) {
        $sql = DB::select("SELECT Documento, NombreCompleto, Motivo FROM 
                        DerechoAdmision WHERE Baja = 0 AND REPLACE(REPLACE(REPLACE(REPLACE(Documento, ' ', ''), '-', ''), '_', ''), '.', '') LIKE " . str_replace(['-', '_', '.', ' '], '', '?') . "",
                        [$documento]);
        if($sql) {
            throw new ConflictHttpException("Listado de personas con acceso restringido" . $sql);
        }
    }

    // public static function usuarioHabilitado() Comprueba si es usuario y si es administrador.
}