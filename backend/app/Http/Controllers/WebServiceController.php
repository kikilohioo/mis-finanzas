<?php

namespace App\Http\Controllers;

use App\WebServices\FsPublicServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use SoapServer;
use WSDL\Annotation\BindingType;
use WSDL\Annotation\SoapBinding;
use WSDL\Builder\WSDLBuilder;
use WSDL\WSDL;

class WebServiceController extends Controller
{
    
    /**
     * @var Request
     */
    private $req;

    public function __construct(Request $req)
    {
        $this->req = $req;
    }

    public function wsdl()
    {
        if ($this->req->input('wsdl') === null) {
            return redirect(route('webservice.wsdl') . '?wsdl');
        }
        $wsdl = WSDL::fromAnnotations(FsPublicServer::class);
        $xml = $wsdl->create();
        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    public function server()
    {
        ini_set('soap.wsdl_cache_enabled', '0');

        $id = '#' . uniqid();
        
        Log::debug('[WS] Nueva solicitud al WebService ' . $id, [
            'ip-address' => $_SERVER['REMOTE_ADDR'],
            'query-string' => $this->req->getQueryString(),
            'body-data' => file_get_contents('php://input')
        ]);

        $server = new SoapServer($this->req->url() . '?wsdl', [
            'uri' => 'FSAcceso/FsPublicServer',
            'location' => $this->req->url(),
            'style' => SOAP_RPC,
            'use' => SOAP_ENCODED,
        ]);
        
        $server->setClass(FsPublicServer::class);

        try {
            \ob_start();
            $server->handle();
            $contents = \ob_get_clean();
            
            Log::debug('[WS] Fin de la solicitud al WebService ' . $id);
            
            $response = response($contents);
            $response->header('Content-Type', 'text/xml; charset=UTF-8');
            return $response;
        } catch (\Exception $ex) {
            Log::critical('[WS] Excepción en la solicitud al WebService ' . $id . ' - ' . $ex->getMessage(), explode("\n", $ex->getTraceAsString()));
            // throw $ex;
        }
    }
    
}