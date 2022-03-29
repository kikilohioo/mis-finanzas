<?php

namespace App\Http\Controllers;

use App\FsUtils;
use App\Models\Equipo;
use App\Models\Acceso;
use App\Models\TipoEquipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class EquipoController extends Controller
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
        $query = Equipo::query()->with(['Acceso']);

        if ($this->req->input('MostrarEliminados', 'false') === 'false') {
            $query->where('Baja', false);
        }

        if ($busqueda = $this->req->input('Busqueda')) {
            $query->where('Nombre', 'like', '%' . $busqueda . '%');
        }

        $query->orderBy('Nombre', 'ASC');

        $data = $query->get();

        $output = $this->req->input('output', 'json');

        if ($output !== 'json') {
            $dataOutput = array_map(function($item) {
                return [
                    'Nombre' => array_key_exists('Nombre', $item) ? $item['Nombre'] : '',
                    'DireccionIP' => array_key_exists('DireccionIP', $item) ? $item['DireccionIP'] : '',
                    'Puerto' => array_key_exists('Puerto', $item) ? $item['Puerto'] : '',
                    'Acceso' => array_key_exists('Acceso', $item) ? $item['Acceso']['Descripcion'] : ''
                ];
            }, $data->toArray());

            return $this->export($dataOutput, $output);
        }
        return $this->responsePaginate($data);
    }

    private function export(array $data, string $type)
    {
        $filename = 'FSAcceso-Equipos-' . date('Ymd-his');
        $headers = [
            'Nombre' => 'Nombre',
            'DireccionIP' => 'Dirección IP',
            'Puerto' => 'Puerto',
            'Acceso' => 'Acceso',
        ];

        return FsUtils::export($type, $data, $headers, $filename);
    }

    public function show(int $id)
    {
        $entity = Equipo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Equipo no encontrado');
        }

        $entity->load('Acceso');
        return $entity;
    }

    public function create()
    {

        Equipo::exigirArgs($this->req->all(), ['Nombre', 'IdTipoEquipo', 'DireccionIP', 'IdAcceso', 'Estado']);
        
        $TipoEquipo = TipoEquipo::find($this->req->input('IdTipoEquipo'));

        if (!isset($TipoEquipo)) {
            throw new NotFoundHttpException('Tipo de equipo no encontrado');
        }

        $Acceso = Acceso::find($this->req->input('IdAcceso'));

        if (!isset($Acceso)) {
            throw new NotFoundHttpException('Acceso no encontrado');
        }

        if ( $this->req->input('Estado') === 0 ) {
            throw new NotFoundHttpException('Estado de equipo debe ser distinto a 0');
        }

        $entity = new Equipo($this->req->all());
        $entity->IdEquipo = Equipo::getNextId();
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();

        $entity->InicioAuto = $this->req->input('InicioAuto');
        $entity->TiempoDisplay = $this->req->input('TiempoDisplay');
        $entity->Cerradura = $this->req->input('Cerradura');
        $entity->IdFabricante = $this->req->input('IdFabricante');
        $entity->Modo = trim($this->req->input('Modo'));
        $entity->Comunicacion = $this->req->input('Comunicacion');
        $entity->Puerto = $this->req->input('Puerto');
        $entity->PlacaEntrada = $this->req->input('PlacaEntrada');
        $entity->CamaraEntrada = $this->req->input('CamaraEntrada');
        $entity->PlacaSalida = $this->req->input('PlacaSalida');
        $entity->CamaraSalida = $this->req->input('CamaraSalida');
        $entity->VideoEntrada = $this->req->input('VideoEntrada');
        $entity->VideoSalida = $this->req->input('VideoSalida');
        $entity->BioOnLine = $this->req->input('BioOnLine');
        $entity->AntiPassback = $this->req->input('AntiPassback');
        $entity->Grupo = $this->req->input('Grupo');
        $entity->dns = $this->req->input('dns');
        $entity->ActRemoto = $this->req->input('ActRemoto');
        $entity->TipoOpe = $this->req->input('TipoOpe');
        $entity->BorrarMarcas = $this->req->input('BorrarMarcas');
        $entity->Biometrico = $this->req->input('Biometrico');
        $entity->IdControladoraExt = $this->req->input('IdControladoraExt');
        $entity->IdLectoraExt = $this->req->input('IdLectoraExt');

        $entity->Baja = false;
       

        $entity->save();

        return $entity->refresh();
    }

    public function update(int $id)
    {
        Equipo::exigirArgs($this->req->all(), ['Nombre', 'IdTipoEquipo', 'DireccionIP', 'IdAcceso', 'Estado']);
        
        $TipoEquipo = TipoEquipo::find($this->req->input('IdTipoEquipo'));

        if (!isset($TipoEquipo)) {
            throw new NotFoundHttpException('Tipo de equipo no encontrado');
        }

        $Acceso = Acceso::find($this->req->input('IdAcceso'));

        if (!isset($Acceso)) {
            throw new NotFoundHttpException('Acceso no encontrado');
        }
        
        if ( $this->req->input('Estado') === 0 ) {
            throw new NotFoundHttpException('Estado de equipo debe ser distinto a 0');
        }

        $entity = Equipo::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('Equipo no encontrado');
        }

        $entity->fill($this->req->all());
        $entity->FechaHora = new \DateTime;
        $entity->IdUsuario = Auth::id();
        $entity->InicioAuto = $this->req->input('InicioAuto');
        $entity->TiempoDisplay = $this->req->input('TiempoDisplay');
        $entity->Cerradura = $this->req->input('Cerradura');
        $entity->IdFabricante = $this->req->input('IdFabricante');
        $entity->Modo = trim($this->req->input('Modo'));
        $entity->Comunicacion = $this->req->input('Comunicacion');
        $entity->Puerto = $this->req->input('Puerto');
        $entity->PlacaEntrada = $this->req->input('PlacaEntrada');
        $entity->CamaraEntrada = $this->req->input('CamaraEntrada');
        $entity->PlacaSalida = $this->req->input('PlacaSalida');
        $entity->CamaraSalida = $this->req->input('CamaraSalida');
        $entity->VideoEntrada = $this->req->input('VideoEntrada');
        $entity->VideoSalida = $this->req->input('VideoSalida');
        $entity->BioOnLine = $this->req->input('BioOnLine');
        $entity->AntiPassback = $this->req->input('AntiPassback');
        $entity->Grupo = $this->req->input('Grupo');
        $entity->dns = $this->req->input('dns');
        $entity->ActRemoto = $this->req->input('ActRemoto');
        $entity->TipoOpe = $this->req->input('TipoOpe');
        $entity->BorrarMarcas = $this->req->input('BorrarMarcas');
        $entity->Biometrico = $this->req->input('Biometrico');
        $entity->IdControladoraExt = $this->req->input('IdControladoraExt');
        $entity->IdLectoraExt = $this->req->input('IdLectoraExt');

        $entity->Baja = false;

        $entity->save();

        return $entity;
    }

    public function delete(int $id)
    {
        $entity = Equipo::find($id);
        
        if (!isset($entity)) {
            throw new NotFoundHttpException('Equipo no encontrado');
        }
        
        $entity->Baja = true;

        $entity->save();
    }

}