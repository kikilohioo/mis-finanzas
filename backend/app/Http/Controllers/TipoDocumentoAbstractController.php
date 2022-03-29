<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\BaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\TipoDocumentoPF;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Models\LogAuditoria;
use App\Models\TipoDocumentoEmp;
use App\Models\TipoDocumentoMaq;
use App\Models\TipoDocumentoVehic;

abstract class TipoDocumentoAbstractController extends Controller
{
    /**
     * @var Request
     */
    private $req;

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var string
     */
    protected $tableAlias = 'td';

    /**
     * @var string
     */
    protected $keyName = '';

    /**
     * @var string
     */
    protected $modelClass;

    public function __construct(Request $req)
    {
        $this->req = $req;
        if (!empty($this->modelClass)) {
            /**
             * @var BaseModel $model
             */
            $model = new $this->modelClass;

            if (empty($this->table)) {
                $this->table = $model->getTable();
            }

            if (empty($this->keyName)) {
                $this->keyName = $model->getKeyName();
            }
        }
    }

    public function index(?object $Args = null)
    {
        $binding = [];
        if (!isset($Args)) {
            $Args = (object)$this->req->all();
        }

        $incluirExtranjeros = $this->modelClass === TipoDocumentoPF::class
            || $this->modelClass === TipoDocumentoVis::class;

        $sql = "SELECT DISTINCT $this->tableAlias.$this->keyName, $this->tableAlias.Nombre, "
            . "CASE $this->tableAlias.TieneVto WHEN 1 THEN 'Si' ELSE 'No' END AS TieneVto, "
            . "CASE $this->tableAlias.Obligatorio WHEN 1 THEN 'Si' ELSE 'No' END AS Obligatorio, "
            . "CASE $this->tableAlias.Categorizado WHEN 1 THEN 'Si' ELSE 'No' END AS Categorizado "
            . ($incluirExtranjeros ? ", CASE $this->tableAlias.Extranjeros WHEN 1 THEN 'Si' ELSE 'No' END AS Extranjeros " : "")
            . "FROM $this->table $this->tableAlias "
            . "LEFT JOIN " . $this->table . "Categorias tdc ON $this->tableAlias.$this->keyName = tdc.$this->keyName";

        if (!empty(@$Args->Extranjeros)) {
            $sql .= " WHERE ($this->tableAlias.Extranjeros = 1 OR ";
        }
        
        if (!empty($Args->IdCategoria)) {
            if (!empty(@$Args->Extranjeros)) {
                $sql .= " tdc.IdCategoria = ?) ";
            } else {
                $sql .= " WHERE tdc.IdCategoria = ? ";
            }
            $binding[] = $Args->IdCategoria;
        }

        if (!empty($Args->Obligatorio)) {
            $sql .= strpos($sql, "WHERE") !== false ? " AND " : " WHERE ";
            $sql .= "$this->tableAlias.Obligatorio = 1";
        }

        if (!empty($Args->Busqueda)) {
            $sql .= strpos($sql, "WHERE") !== false ? " AND " : " WHERE ";
            $sql .= " ($this->tableAlias.Nombre COLLATE Latin1_general_CI_AI LIKE ? COLLATE Latin1_general_CI_AI)";
            $binding[] = "%$Args->Busqueda%";
        }

        $sql .= " ORDER BY $this->tableAlias.Nombre";

        $data = DB::select($sql, $binding);

        $output = $this->req->input('output');
        
        if ($output !== 'json' && $output !== null) {
            $filename = 'FSAcceso-DocumentosPersonasFisicas-Consulta-' . date('Ymd his');
            $headers = [
                'Nombre' => 'Nombre',
                'TieneVto' => 'TieneVto',
                'Obligatorio' => 'Obligatorio',
                'Categorizado' => 'Categorizado',
                'Extranjeros' => 'Extranjeros'
            ];
            return FsUtils::export($output,$data,$headers,$filename);
        }

        return $this->responsePaginate($data);
    }

    public function show(int $id)
    {
        return $this->modelClass::with(['Categorias'])->findOrFail($id);
    }

    public function create()
    {
        DB::transaction(function ()
        {
            $this->modelClass::exigirArgs($this->req->all(), ['Nombre']);

            $id = $this->modelClass::getNextId();
            $entity = new $this->modelClass($this->req->all());
            $entity->{ $this->keyName} = $id;
            $entity->FechaHora = new \DateTime;
            $entity->IdUsuario = Auth::id();
            $entity->save();

            if (is_array($categorias = $this->req->input('Categorias'))) {
                if (count($categorias) <= 0 ) {
                    throw new ConflictHttpException("Debe especificar mínimo una categoría");
                }
                foreach ($categorias as $categoria) {
                    DB::insert("INSERT INTO " . $this->table . "Categorias ($this->keyName, IdCategoria) VALUES (" . $id . ", " . $categoria['IdCategoria'] . ")");
                }
            }
        });
    }

    public function update(int $id)
    {
        DB::transaction(function () use ($id) {
            $entity = $this->modelClass::findOrFail($id);
            $entity->fill($this->req->all());
            $entity->save();

            if (is_array($categorias = $this->req->input('Categorias'))) {
                if (count($categorias) <= 0 ) {
                    throw new ConflictHttpException("Debe especificar mínimo una categoría");
                }
                DB::delete("DELETE FROM " . $this->table . "Categorias WHERE $this->keyName = " . $id);
                foreach ($categorias as $categoria) {
                    DB::insert("INSERT INTO " . $this->table . "Categorias ($this->keyName, IdCategoria) VALUES (" . $id . ", " . $categoria['IdCategoria'] . ")");
                }
            }
        });
    }

    public function delete(int $id)
    {
        DB::transaction(function () use ($id) {
            switch ($this->modelClass) {
                case TipoDocumentoPF::class:
                    $sqlCheck = "SELECT COUNT(*) AS Cantidad FROM PersonasFisicasDocs WHERE IdTipoDocPF = ?";
                    break;
                case TipoDocumentoEmp::class:
                    $sqlCheck = "SELECT COUNT(*) AS Cantidad FROM EmpresasDocs WHERE IdTipoDocEmp = ?";
                    break;
                case TipoDocumentoVehic::class:
                    $sqlCheck = "SELECT COUNT(*) AS Cantidad FROM VehiculosDocs WHERE IdTipoDocVehic = ?";
                    break;
                case TipoDocumentoMaq::class:
                    $sqlCheck = "SELECT COUNT(*) AS Cantidad FROM MaquinasDocs WHERE IdTipoDocMaq = ?";
                    break;
                default:
                    throw new \Exception('No se encuentra definido un tipo de documento para esta solicitud');
            }

            $check = DB::selectOne($sqlCheck, [$id]);
            if ($check->Cantidad > 0) {
                throw new ConflictHttpException('El tipo de documento esta siendo utilizado');
            }

            $entity = $this->modelClass::findOrFail($id);

            DB::delete("DELETE FROM " . $this->table . "Categorias WHERE $this->keyName = ?", [$id]);
            DB::delete("DELETE FROM $this->table WHERE $this->keyName = ?", [$id]);

            LogAuditoria::log(
                Auth::id(),
                PersonaFisica::class ,
                LogAuditoria::FSA_METHOD_DELETE,
                $entity,
                $id,
                $entity->Nombre
            );
        });
    }

}