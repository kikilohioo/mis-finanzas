<?php

namespace App\Http\Controllers\PCAR;

use App\WebServices\PcarServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use SoapServer;
use WSDL\Annotation\BindingType;
use WSDL\Annotation\SoapBinding;
use WSDL\Builder\WSDLBuilder;
use WSDL\WSDL;

class WebServiceController extends \App\Http\Controllers\Controller
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
            return redirect(route('pcar.webservice.wsdl') . '?wsdl');
        }
        $wsdl = WSDL::fromAnnotations(PcarServer::class);
        $xml = $wsdl->create();
        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    public function server()
    {
        ini_set('soap.wsdl_cache_enabled', '0');

        Log::debug('[WS] Nueva solicitud al WebService (PCAR)', [
            'ip-address' => $_SERVER['REMOTE_ADDR'],
            'query-string' => $this->req->getQueryString(),
            'body-data' => file_get_contents('php://input')
        ]);

        $server = new SoapServer($this->req->url() . '?wsdl', [
            'uri' => 'FSAcceso/PcarServer',
            'location' => $this->req->url(),
            'style' => SOAP_RPC,
            'use' => SOAP_ENCODED,
        ]);
        
        $server->setClass(PcarServer::class);

        try {
            $server->handle();
        } catch (\Exception $ex) {
            Log::critical($ex->getMessage(), explode("\n", $ex->getTraceAsString()));
        }
    }
    
}