<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Tarjeta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TarjetaController extends Controller
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
     * @todo Se aplica el Case en PersonasFisicas ?? 
     */
    public function index()
    {
        // CODIGO VIEJO
        // $sql = "SELECT CodigoZK, CodigoHY, CodigoPSION,
        //         Asociada = 
        //             CASE
        //                 WHEN EXISTS(SELECT * FROM PersonasFisicas pf WHERE pf.Matricula = tar.CodigoZK) THEN 'Si'
        //                 ELSE 'No'
        //             END
        //         FROM Tarjetas tar"
        $query = Tarjeta::query();

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where(function ($query) use ($busqueda) {
                return $query->where('CodigoZK', 'like', '%' . $busqueda . '%')
                    ->orWhere('CodigoHY', 'like', '%' . $busqueda . '%')
                    ->orWhere('CodigoPSION', 'like', '%' . $busqueda . '%');
            });
        }

        // $data = DB::select($sql, $bindings);
        $data = $query->get();

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {

            $dataOutput = array_map(function($item) {
                return [
                    'CodigoZK' => $item['CodigoZK'],
                    'CodigoHY' => array_key_exists('CodigoHY', $item) ? $item['CodigoHY'] : '',
                    'CodigoPSION' => array_key_exists('CodigoPSION', $item) ? $item['CodigoPSION'] : '',
                ];
            }, $data->toArray());

            return $this->export($dataOutput, $output);
        }
        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Matricula-Baja-' . date('Ymd-his');
        $headers = [
            'CodigoZK' => 'Código ZK',
            'CodigoHY' => 'Código HY',
            'CodigoPSION' => 'Código PSION'
        ];
        return FsUtils::export($type, $data, $headers, $filename);
    }

    /**
     * @todo validar usuarioHabilitado
     */
    public function show(int $codigoZk) // , int $codigoHY, string $codigoPSION
    {
        $entity = Tarjeta::find($codigoZk);
        // $entity = Tarjeta::where('CodigoZK', $codigoZk)->where('CodigoHY', $codigoHY)->where('CodigoPSION', $codigoPSION)->first();

        if (!isset($entity)) {
            throw new NotFoundHttpException('No se pudo encontrar el identificador de la tarjeta');
        }

        return $entity;
    }

    public function create()
    {
        Tarjeta::exigirArgs($this->req->all(), ['CodigoZK', 'CodigoHY', 'CodigoPSION']);

        $codigoZk = $this->req->input('CodigoZK');
        $entity = Tarjeta::find($codigoZk);

        if (isset($entity)) {
            throw new ConflictHttpException('Ya existe un registro con el codigoZK igual a ' . $codigoZk);
        }

        $entity = new Tarjeta($this->req->all());
        $entity->CodigoZK = $codigoZk;
        $entity->save();

        return $entity->refresh();
    }

    /**
     * @todo validar usuarioHabilitado
     */
    public function update(int $codigoZk)
    {
        Tarjeta::exigirArgs($this->req->all(), ['CodigoZK', 'CodigoHY', 'CodigoPSION']);

        $entity = Tarjeta::find($codigoZk);

        if (!isset($entity)) {
            throw new NotFoundHttpException('No se pudo encontrar el identificador de la tarjeta');
        }

        $entity->fill($this->req->all());
        $entity->save();

        return $entity;
    }

    /**
     * @todo validar usuarioHabilitado
     */
    public function delete(int $codigoZk)
    {
        $entity = Tarjeta::find($codigoZk);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('No se pudo encontrar el identificador de la tarjeta');
        }
        
        $entity::where('codigoZk',$codigoZk)->delete();
        $entity->save();
    }
}