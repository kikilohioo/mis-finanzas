<?php

namespace App\Http\Controllers;

use App\FsUtils;
use Illuminate\Http\Request;
use App\Models\Documento;

class DocumentoController extends Controller
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
        $args->AttrSuffix = $this->getAttrSuffix();
        $args->TablePreffix = $this->getTablePreffix() ?: null;

        $data = Documento::index($args);
        
        $output = $this->req->input('output', 'json');
        if ($output !== 'json') {
            return FsUtils::export($output, $data['data'], $data['headers'], $data['filename']);
        }

        // return $this->responsePaginate($data['data']);

        $page = (int)$this->req->input('page', 1);
        $paginate = FsUtils::paginateArray($data['data'], $this->req);
        return $this->responsePaginate($paginate->items(), $paginate->total(), $page);
    }
    
    public function getAttrSuffix(): string
    {
        if ($this instanceof DocumentoPersonaFisicaController) {
            return 'PF';
        } else if ($this instanceof DocumentoEmpresaController) {
            return 'Emp';
        } else if ($this instanceof DocumentoVehiculoController) {
            return 'Vehic';
        } else if ($this instanceof DocumentoMaquinaController) {
            return 'Maq';
        } else if ($this instanceof DocumentoController) {
            return '';
        }
        throw new \UnexpectedValueException('La clase ' . get_called_class() . ' no tiene sufijo definido');
    }

    public function getTablePreffix(): string
    {
        if ($this instanceof DocumentoPersonaFisicaController) {
            return 'PersonasFisicas';
        } else if ($this instanceof DocumentoEmpresaController) {
            return 'Empresas';
        } else if ($this instanceof DocumentoVehiculoController) {
            return 'Vehiculos';
        } else if ($this instanceof DocumentoMaquinaController) {
            return 'Maquinas';
        } else if ($this instanceof DocumentoController) {
            return '';
        }
        throw new \UnexpectedValueException('La clase ' . get_called_class() . ' no tiene prefijo de tabla definido');
    }
}