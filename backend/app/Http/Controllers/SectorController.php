<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Empresa;
use App\Models\Tarjeta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SectorController extends Controller
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
        $args = (object)$this->req->all();
        $bindings = [];

        if (isset($args->IdEmpresa)) {
            $empresa = FsUtils::explodeId($args->IdEmpresa);
            $bindings = [
                ':documento' => $empresa[0],
                ':id_Tipo_documento' => $empresa[1]
            ];
        }

        $sql = "SELECT IdSector, Nombre FROM EmpresasSectores WHERE Documento = :documento AND IdTipoDocumento = :id_Tipo_documento ORDER BY Nombre";

        $sectores = DB::select($sql, $bindings);

        if (isset($sectores)) {
            $idSectores = [];
            foreach($sectores as $sector) {
                $idSectores[] = $sector->IdSector;
            }
            $args->IdSector = implode(', ', $idSectores);

            $categorias = $this->lst_sectoresPorCategoria($args);

            foreach ($sectores as $sector) {
                foreach ($categorias as $categoria) {
                    if ($sector->IdCategoria == $categoria->IdCategoria) {
                        $sector->Categorias = $categorias;
                        break;
                    }
                }
            }
            return $sectores;
        }
    }

    public function lst_sectoresPorCategoria($args)
    {
        $bindings = [];
        $sql = "SELECT esa.IdSector, esa.IdCategoria, esa.IdUsuario, cat.Descripcion
                FROM EmpresasSectoresAutorizantes esa
                INNER JOIN Categorias cat ON cat.IdCategoria = esa.IdCategoria ";

        if (!empty($args->IdSector))
        {
            $args->IdSector = explode(", ", $args->IdSector);

            $idSectores = $args->IdSector;
            $first = true;
            $i = 0;
            
            $sql .= "WHERE esa.IdSector IN (";
            foreach($idSectores as $sector) {
                $i ++;
                if (!$first) {
                    $sql .= ", ";
                } else {
                    $first = false;
                }
                $sql .= ':idSector'.$i;
                $bindings[':idSector'.$i] = $sector;
            }
            $sql .= ") ";
        }
        $sql .= "ORDER BY esa.IdCategoria";
        return DB::select($sql, $bindings);
    }
}