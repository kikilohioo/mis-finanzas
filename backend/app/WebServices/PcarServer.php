<?php

namespace App\WebServices;

use App\Http\Controllers\PCAR\SolicitudController;
use stdClass;
use WSDL\Annotation\WebMethod;
use WSDL\Annotation\WebResult;
use WSDL\Annotation\WebParam;
use WSDL\Annotation\WebService;
use WSDL\Annotation\SoapBinding;

/**
 * @WebService(
 *  targetNamespace="FSAcceso/PcarServer",
 *  ns="PcarServer",
 *  location="https://fsgestion.montesdelplata.com.uy/api/pcar/webservice?wsdl"
 * )
 * @SoapBinding(use="ENCODED")
 */
class PcarServer
{

    /**
     * @var Request
     */
    private $req;

    public function __construct()
    {
        $this->req = request();
        $this->req->headers->set('X-FS-Token', env('FSA_PUBLIC_TOKEN'));
    }

     /**
     * @WebMethod
     * @WebParam(param="string $id")
     * @WebResult(param="PermisoSoapType $res")
     */
    public function getById($id)
    {
        $permiso = app(SolicitudController::class)->show($id);
        return new PermisoSoapType($permiso);
    }

    /**
     * @WebMethod
     * @WebParam(param="string $matricula")
     * @WebResult(param="PermisoSoapType[] $res")
     */
    public function getByMatricula($matricula)
    {
        $permisos = app(SolicitudController::class)->index(['matricula' => $matricula]);
        return array_map(function ($permiso) { return new PermisoSoapType($permiso); }, $permisos->toArray());
    }

}


class PermisoSoapType
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $matricula;

    /**
     * @var string
     */
    public $empresa;

    /**
     * @var string
     */
    public $personaContacto;

    /**
     * @var string
     */
    public $emailContacto;

    /**
     * @var string
     */
    public $telefonoContacto;

    /**
     * @var string
     */
    public $desde;

    /**
     * @var string
     */
    public $hasta;

    /**
     * @var string
     */
    public $area;

    /**
     * @var int
     */
    public $idArea;

    /**
     * @var int
     */
    public $idUsuarioAutorizante;

    /**
     * @var string
     */
    public $motivo;

    /**
     * @var string
     */
    public $observaciones;

    public function __construct(object $permiso)
    {
        $this->id = $permiso->Id;
        $this->matricula = $permiso->Matricula;
        $this->empresa = $permiso->Empresa;
        $this->personaContacto = $permiso->PersonaContacto;
        $this->emailContacto = $permiso->EmailContacto;
        $this->telefonoContacto = $permiso->TelefonoContacto;
        $this->desde = $permiso->Desde;
        $this->hasta = $permiso->Hasta;
        $this->area = $permiso->Area;
        $this->idArea = $permiso->IdArea;
        $this->idUsuarioAutorizante = $permiso->IdUsuarioAutorizante;
        $this->motivo = $permiso->Motivo;
        $this->observaciones = $permiso->Observaciones;
    } 
    
}