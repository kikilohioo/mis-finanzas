<?php

return [

    /*
	|--------------------------------------------------------------------------
	| Laravel CORS
	|--------------------------------------------------------------------------
	|
	| allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
	| to accept any value.
	|
	*/

    'supportsCredentials' => false,
    
    'paths' => ['*'],
    
    'allowedOrigins' => ['*'],
    
    'allowedHeaders' => ['Content-Type', 'X-FS-TOKEN'],
    
    'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    
    'exposedHeaders' => [],
    
    'maxAge' => 0,

];
