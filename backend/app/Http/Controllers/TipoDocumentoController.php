<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TipoDocumentoController extends Controller
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
        $query = TipoDocumento::query();

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Descripcion', 'like', '%' . $busqueda . '%');
        }
        if (null !== ($tipoPersona = $this->req->input('TipoPersona'))) {
            $query->where('TipoPersona', $tipoPersona);
        }
        if (null !== ($visitas = $this->req->input('visitas'))) {
            $query->where('IdTipoDocumento', env('CONF_VISITANTE_TIPO_DOC'));
        }

        $query->orderBy('Descripcion', 'ASC');

        $data = $query->get();

        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Descripcion' => array_key_exists('Descripcion', $item) ? $item['Descripcion'] : '',
                    'Mascara' => array_key_exists('Mascara', $item) ? $item['Mascara'] : '',
                    'TipoPersona' => $item['TipoPersona'] == 0 ? 'Persona' : 'Empresa',
                ];
            }, $data->toArray());

            return $this->export($dataOutput, $output);
        }

        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-TiposDocumentos-' . date('Ymd-his');
        $headers = [
            'Descripcion' => 'Descripción',
            'Mascara' => 'Máscara',
            'TipoPersona' => 'Entidad',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = TipoDocumento::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de documento no encontrado');
        }

        return $entity;
    }

    public function create()
    {
        TipoDocumento::exigirArgs($this->req->all(), ['Descripcion']);
        
        $entity = new TipoDocumento($this->req->all());
        $entity->IdTipoDocumento = TipoDocumento::getNextId();
        $entity->IdUsuario = Auth::id();
        $entity->FechaHora = new \DateTime;

        if ($this->req->input('TipoPersona') === null) {
            throw new ConflictHttpException("Debe seleccionar una Entidad");
        }

        $entity->TipoPersona = $this->req->input('TipoPersona');
        
        
        $entity->Extranjero = $this->req->input('Extranjero');
        $entity->Mascara = $this->req->input('Mascara');

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        TipoDocumento::exigirArgs($this->req->all(), ['Descripcion']);

        $entity = TipoDocumento::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de documento no encontrado');
        }

        $entity->fill($this->req->all());

        if ($this->req->input('TipoPersona') === null) {
            throw new ConflictHttpException("Debe seleccionar una Entidad");
        }

        $entity->TipoPersona = $this->req->input('TipoPersona');
        $entity->Extranjero = $this->req->input('Extranjero');
        $entity->Mascara = $this->req->input('Mascara');
        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = TipoDocumento::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Tipo de documento no encontrado');
        }
        
        $entity->delete();
    }

    /**
     * @todo
     */

     /*public static function wsobtenermascara($Usuario, $IdEmpresa, $Args) {
        $sql = "SELECT Mascara FROM TiposDocumento WHERE IdTipoDocumento = " . $Args->IdTipoDocumento;
        return self::ejecutarSQL($Usuario, $IdEmpresa, $sql, false);
    }*/
}