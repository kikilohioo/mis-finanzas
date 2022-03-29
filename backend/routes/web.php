<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    $version = file_get_contents('../VERSION');
    return sprintf("%s v%s", env('APP_NAME', 'App'), $version) . (app()->environment('production') ? '' : '-' . app()->environment());
});

$router->group([], function () use ($router) {
    $router->get('/concepto', ['uses' => 'ConceptoController@index', 'as' => 'concepto.index']);
    $router->get('/concepto/{id}', ['uses' => 'ConceptoController@show', 'as' => 'concepto.show']);
    $router->post('/concepto', ['uses' => 'ConceptoController@create', 'as' => 'concepto.create']);
    $router->put('/concepto/{id}', ['uses' => 'ConceptoController@update', 'as' => 'concepto.update']);
    $router->delete('/concepto/{id}', ['uses' => 'ConceptoController@delete', 'as' => 'concepto.delete']);
});

$router->get('webservice', ['uses' => 'WebServiceController@wsdl', 'as' => 'webservice.wsdl']);
$router->post('webservice', ['uses' => 'WebServiceController@server', 'as' => 'webservice.server']);
$router->get('pcar/webservice', ['uses' => 'PCAR\WebServiceController@wsdl', 'as' => 'pcar.webservice.wsdl']);
$router->post('pcar/webservice', ['uses' => 'PCAR\WebServiceController@server', 'as' => 'pcar.webservice.server']);

$router->post('auth/login', ['uses' => 'AuthController@login', 'as' => 'auth.login']);
$router->post('auth/reset-password', ['uses' => 'AuthController@ResetPassword', 'as' => 'auth.reset-password']);
$router->post('auth/recovery-password', ['uses' => 'AuthController@RecoveryPassword', 'as' => 'auth.recovery-password']);

$router->get('visitas/visitantes/{id}/qrImage', ['uses' => 'Visitas\VisitanteController@qrImage', 'as' => 'visitante.qrImage']);
$router->get('visitas/visitantes/{id}/accessImage', ['uses' => 'Visitas\VisitanteController@accessImage', 'as' => 'visitante.accessImage']);
$router->get('visitas/visitantes/{id}/pdf', ['uses' => 'Visitas\VisitanteController@pdf', 'as' => 'visitante.pdf']);

// $router->post('ptc/permiso/marcar-cerrado-sin-ejecuccion-vencidos-todos', ['uses' => 'PTC\PermisoController@cerrarSiFechaFinPrevistaVencioTodos', 'as' => 'permiso.cerrarSiFechaFinPrevistaVencioTodos']);
// $router->post('ptc/permiso/marcar-pendiente-revalidacion-todos', ['uses' => 'PTC\PermisoController@marcarComoPendienteDeRevalidacionTodos', 'as' => 'permiso.marcarComoPendienteDeRevalidacionTodos']);
// $router->post('ptc/permiso/marcar-vencidos-todos', ['uses' => 'PTC\PermisoController@marcarComoVencidoTodos', 'as' => 'permiso.marcarComoVencidoTodos']);

$router->post('PermisoDeTrabajo/cerrarSiFechaFinPrevistaVencioTodos', ['uses' => 'PTC\PermisoController@cerrarSiFechaFinPrevistaVencioTodos', 'as' => 'permiso.cerrarSiFechaFinPrevistaVencioTodos']);
$router->post('PermisoDeTrabajo/marcarComoPendienteDeRevalidacionTodos', ['uses' => 'PTC\PermisoController@marcarComoPendienteDeRevalidacionTodos', 'as' => 'permiso.marcarComoPendienteDeRevalidacionTodos']);
$router->post('PermisoDeTrabajo/marcarComoVencidoTodos', ['uses' => 'PTC\PermisoController@marcarComoVencidoTodos', 'as' => 'permiso.marcarComoVencidoTodos']);


$router->group(['middleware' => 'auth'], function () use ($router) {
    $router->get('auth/check', ['uses' => 'AuthController@check', 'as' => 'auth.check']);

    #region Accesos 
    $router->get('accesos', ['uses' => 'AccesoController@index', 'as' => 'acceso.index']);
    $router->get('accesos/{id}', ['uses' => 'AccesoController@show', 'as' => 'acceso.show']);
    $router->post('accesos', ['uses' => 'AccesoController@create', 'as' => 'acceso.create']);
    $router->put('accesos/{id}', ['uses' => 'AccesoController@update', 'as' => 'acceso.update']);
    $router->delete('accesos/{id}', ['uses' => 'AccesoController@delete', 'as' => 'acceso.delete']);
    #endregion

    //// Sectores
    $router->get('sectores', ['uses' => 'SectorController@index', 'as' => 'sector.index']);

    #region Kiosco
    $router->get('kiosco/consulta', ['uses' => 'KioscoController@index', 'as' => 'kiosco.index']);
    #endregion


    $router->get('personas-fisicas/busqueda', ['uses' => 'PersonaFisicaController@busqueda', 'as' => 'persona-fisica.busqueda']);
    $router->get('personas-fisicas', ['uses' => 'PersonaFisicaController@index', 'as' => 'persona-fisica.index']);
    $router->get('personas-fisicas/index-no-transac', ['uses' => 'PersonaFisicaController@indexNoTransac', 'as' => 'persona-fisica.indexNoTransac']);
    $router->get('personas-fisicas/grafico', ['uses' => 'PersonaFisicaController@graficos', 'as' => 'personaFisica.graficos']);
    $router->get('personas-fisicas/grafico/nacionalesextranjerosdetalle', ['uses' => 'PersonaFisicaController@chartnacionalesextranjerosdetalle', 'as' => 'personaFisica.chartnacionalesextranjerosdetalle']);
    $router->get('personas-fisicas/grafico/porpaisdepartamentodetalle', ['uses' => 'PersonaFisicaController@chartporpaisdepartamentodetalle', 'as' => 'personaFisica.chartporpaisdepartamentodetalle']);
    $router->get('personas-fisicas/grafico/lugarnacimientodetalle', ['uses' => 'PersonaFisicaController@chartlugarnacimientodetalle', 'as' => 'personaFisica.chartlugarnacimientodetalle']);
    $router->get('personas-fisicas/grafico/habilitadosdetalle', ['uses' => 'PersonaFisicaController@charthabilitadosdetalle', 'as' => 'personaFisica.charthabilitadosdetalle']);
    $router->get('personas-fisicas/grafico/porcategoriadetalle', ['uses' => 'PersonaFisicaController@chartporcategoriadetalle', 'as' => 'personaFisica.chartporcategoriadetalle']);
    $router->get('personas-fisicas/grafico/poredaddetalle', ['uses' => 'PersonaFisicaController@chartporedaddetalle', 'as' => 'personaFisica.chartporedaddetalle']);
    $router->get('personas-fisicas/cargar-localidades/{value}', ['uses' => 'PersonaFisicaController@cargarLocalidades', 'as' => 'personaFisica.cargarLocalidades']);
    $router->get('personas-fisicas/cargar-ciudades/{value}', ['uses' => 'PersonaFisicaController@cargarCiudades', 'as' => 'personaFisica.cargarCiudades']);
    $router->get('personas-fisicas/archivo/{carpeta}/{fileName}', ['uses' => 'PersonaFisicaController@verArchivo', 'as' => 'persona-fisica.ver-archivo']);
    $router->get('personas-fisicas/ver-doc/{fileName}', ['uses' => 'PersonaFisicaController@verDoc', 'as' => 'persona-fisica.verDoc']);
    $router->get('personas-fisicas/{idTipoDocumento}/{documento}', ['uses' => 'PersonaFisicaController@show', 'as' => 'persona-fisica.show']);
    $router->post('personas-fisicas', ['uses' => 'PersonaFisicaController@create', 'as' => 'persona-fisica.create']);
    $router->put('personas-fisicas/{idTipoDocumento}/{documento}', ['uses' => 'PersonaFisicaController@update', 'as' => 'persona-fisica.update']);
    $router->delete('personas-fisicas/{idTipoDocumento}/{documento}', ['uses' => 'PersonaFisicaController@delete', 'as' => 'persona-fisica.delete']);
    $router->delete('personas-fisicas/{idTipoDocumento}/{documento}/delete-doc', ['uses' => 'PersonaFisicaController@deleteDoc', 'as' => 'persona-fisica.deleteDoc']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/activar', ['uses' => 'PersonaFisicaController@activar', 'as' => 'persona-fisica.activar']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/desactivar', ['uses' => 'PersonaFisicaController@desactivar', 'as' => 'persona-fisica.desactivar']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/rechazar', ['uses' => 'PersonaFisicaController@rechazar', 'as' => 'persona-fisica.rechazar']);
    $router->get('personas-fisicas/{idTipoDocumento}/{documento}/comprobar-identificador', ['uses' => 'PersonaFisicaController@comprobarIdentificador', 'as' => 'persona-fisica.comprobar-identificador']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/cambiar-identificador', ['uses' => 'PersonaFisicaController@cambiarIdentificador', 'as' => 'persona-fisica.cambiar-identificador']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/cambiar-matricula', ['uses' => 'PersonaFisicaController@cambiarMatricula', 'as' => 'persona-fisica.cambiar-matricula']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/create-doc', ['uses' => 'PersonaFisicaController@createDoc', 'as' => 'persona-fisica.createDoc']);
    // IMPRIMIR MATRICULAS
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/imprimir-matricula', ['uses' => 'PersonaFisicaController@imprimirMatriculaEnBase64', 'as' => 'persona-fisica.imprimir-matricula']);
    $router->post('personas-fisicas/subir-docs', ['uses' => 'PersonaFisicaController@subirDocs', 'as' => 'persona-fisica.subir-docs']);
    $router->post('personas-fisicas/{idTipoDocumento}/{documento}/subir-foto', ['uses' => 'PersonaFisicaController@subirFoto', 'as' => 'persona-fisica.subir-foto']);

    //// VISITANTES
    $router->get('visitantes/busqueda', ['uses' => 'VisitanteController@busqueda', 'as' => 'visitante.busqueda']);
    $router->get('visitantes', ['uses' => 'VisitanteController@index', 'as' => 'visitante.index']);
    $router->get('visitantes/autorizantes/{idCategoria}', ['uses' => 'VisitanteController@comboAutorizante', 'as' => 'visitante.comboAutorizante']);
    $router->get('visitantes/{idTipoDocumento}/{documento}', ['uses' => 'VisitanteController@show', 'as' => 'visitante.show']);
    $router->post('visitantes', ['uses' => 'VisitanteController@create', 'as' => 'visitante.create']);
    $router->put('visitantes/{idTipoDocumento}/{documento}', ['uses' => 'VisitanteController@update', 'as' => 'visitante.update']);
    $router->delete('visitantes/{idTipoDocumento}/{documento}', ['uses' => 'VisitanteController@delete', 'as' => 'visitante.delete']);
    $router->post('visitantes/{idTipoDocumento}/{documento}/activar', ['uses' => 'VisitanteController@activar', 'as' => 'visitante.activar']);
    $router->post('visitantes/{idTipoDocumento}/{documento}/desactivar', ['uses' => 'VisitanteController@desactivar', 'as' => 'visitante.desactivar']);
    $router->get('visitantes/{idTipoDocumento}/{documento}/comprobar-identificador', ['uses' => 'VisitanteController@comprobarIdentificador', 'as' => 'visitante.comprobar-identificador']);
    $router->post('visitantes/{idTipoDocumento}/{documento}/cambiar-identificador', ['uses' => 'VisitanteController@cambiarIdentificador', 'as' => 'visitante.cambiar-identificador']);
    $router->post('visitantes/{idTipoDocumento}/{documento}/cambiar-matricula', ['uses' => 'VisitanteController@cambiarMatricula', 'as' => 'visitante.cambiar-matricula']);
    // IMPRIMIR MATRICULAS
    $router->post('visitantes/{idTipoDocumento}/{documento}/imprimir-matricula', ['uses' => 'VisitanteController@imprimirMatriculaEnBase64', 'as' => 'visitante.imprimir-matricula']);
    //// EMPRESAS
    $router->get('empresas', ['uses' => 'EmpresaController@index', 'as' => 'empresa.index']);
    $router->get('empresas/busqueda', ['uses' => 'EmpresaController@busqueda', 'as' => 'empresa.busqueda']);
    $router->get('empresas/archivo/{carpeta}/{fileName}', ['uses' => 'EmpresaController@verArchivo', 'as' => 'empresa.ver-archivo']);
    $router->get('empresas/{idTipoDocumento}/{documento}', ['uses' => 'EmpresaController@show', 'as' => 'empresa.show']);
    $router->get('empresas/{idTipoDocumento}/{documento}/chequearIdentificador', ['uses' => 'EmpresaController@chequearIdentificador', 'as' => 'empresa.chequearIdentificador']);
    $router->post('empresas', ['uses' => 'EmpresaController@create', 'as' => 'empresa.create']);
    $router->post('empresas/{idTipoDocumento}/{documento}/activar', ['uses' => 'EmpresaController@activar', 'as' => 'empresa.activar']);
    $router->post('empresas/{idTipoDocumento}/{documento}/desactivar', ['uses' => 'EmpresaController@desactivar', 'as' => 'empresa.desactivar']);
    $router->post('empresas/{idTipoDocumento}/{documento}/cambiar-identificador', ['uses' => 'EmpresaController@cambiarIdentificador', 'as' => 'empresa.cambiarIdentificador']);
    $router->put('empresas/{idTipoDocumento}/{documento}', ['uses' => 'EmpresaController@update', 'as' => 'empresa.update']);
    $router->delete('empresas/{idTipoDocumento}/{documento}', ['uses' => 'EmpresaController@delete', 'as' => 'empresa.delete']);
    $router->get('empresas/{idTipoDocumento}/{documento}/comprobar-identificador', ['uses' => 'EmpresaController@comprobarIdentificador', 'as' => 'Empresa.comprobar-identificador']);
    $router->post('empresas/subir-docs', ['uses' => 'EmpresaController@subirDocs', 'as' => 'Empresa.subir-docs']);

    $router->post('empresas/{idTipoDocumento}/{documento}/adjuntos/{field}', ['uses' => 'EmpresaController@updateAttach', 'as' => 'Empresa.update-attach']);
    $router->delete('empresas/{idTipoDocumento}/{documento}/adjuntos/{field}', ['uses' => 'EmpresaController@deleteAttach', 'as' => 'Empresa.delete-attach']);
    $router->get('empresas/{idTipoDocumento}/{documento}/adjuntos/{field}', ['uses' => 'EmpresaController@downloadAttach', 'as' => 'Empresa.download-attach']);

    //// DOCUMENTOS
    $router->get('documentos', ['uses' => 'DocumentoController@index', 'as' => 'documento.index']);
    $router->get('documentos/busqueda', ['uses' => 'DocumentoController@index', 'as' => 'documento.busqueda']); // ver si la uso o no.
    
    #region Tipos de documentos (requisitos) 

    #region Personas Físicas 
    $router->get('tipos-documentos/personas-fisicas', ['uses' => 'TipoDocumentoPersonaFisicaController@index', 'as' => 'tipo-documento-persona-fisica.index']);
    $router->get('tipos-documentos/personas-fisicas/{id}', ['uses' => 'TipoDocumentoPersonaFisicaController@show', 'as' => 'tipo-documento-persona-fisica.show']);
    $router->post('tipos-documentos/personas-fisicas', ['uses' => 'TipoDocumentoPersonaFisicaController@create', 'as' => 'tipo-documento-persona-fisica.create']);
    $router->put('tipos-documentos/personas-fisicas/{id}', ['uses' => 'TipoDocumentoPersonaFisicaController@update', 'as' => 'tipo-documento-persona-fisica.update']);
    $router->delete('tipos-documentos/personas-fisicas/{id}', ['uses' => 'TipoDocumentoPersonaFisicaController@delete', 'as' => 'tipo-documento-persona-fisica.delete']);
    #endregion

    #region Empresas 
    $router->get('tipos-documentos/empresas', ['uses' => 'TipoDocumentoEmpresaController@index', 'as' => 'tipo-documento-empresa.index']);
    $router->get('tipos-documentos/empresas/{id}', ['uses' => 'TipoDocumentoEmpresaController@show', 'as' => 'tipo-documento-empresa.show']);
    $router->post('tipos-documentos/empresas', ['uses' => 'TipoDocumentoEmpresaController@create', 'as' => 'tipo-documento-empresa.create']);
    $router->put('tipos-documentos/empresas/{id}', ['uses' => 'TipoDocumentoEmpresaController@update', 'as' => 'tipo-documento-empresa.update']);
    $router->delete('tipos-documentos/empresas/{id}', ['uses' => 'TipoDocumentoEmpresaController@delete', 'as' => 'tipo-documento-empresa.delete']);
    #endregion

    #region Máquinas 
    $router->get('tipos-documentos/maquinas', ['uses' => 'TipoDocumentoMaquinaController@index', 'as' => 'tipo-documento-maquina.index']);
    $router->get('tipos-documentos/maquinas/{id}', ['uses' => 'TipoDocumentoMaquinaController@show', 'as' => 'tipo-documento-maquina.show']);
    $router->post('tipos-documentos/maquinas', ['uses' => 'TipoDocumentoMaquinaController@create', 'as' => 'tipo-documento-maquina.create']);
    $router->put('tipos-documentos/maquinas/{id}', ['uses' => 'TipoDocumentoMaquinaController@update', 'as' => 'tipo-documento-maquina.update']);
    $router->delete('tipos-documentos/maquinas/{id}', ['uses' => 'TipoDocumentoMaquinaController@delete', 'as' => 'tipo-documento-maquina.delete']);
    #endregion

    #region Vehículos 
    $router->get('tipos-documentos/vehiculos', ['uses' => 'TipoDocumentoVehiculoController@index', 'as' => 'tipo-documento-vehiculo.index']);
    $router->get('tipos-documentos/vehiculos/{id}', ['uses' => 'TipoDocumentoVehiculoController@show', 'as' => 'tipo-documento-vehiculo.show']);
    $router->post('tipos-documentos/vehiculos', ['uses' => 'TipoDocumentoVehiculoController@create', 'as' => 'tipo-documento-vehiculo.create']);
    $router->put('tipos-documentos/vehiculos/{id}', ['uses' => 'TipoDocumentoVehiculoController@update', 'as' => 'tipo-documento-vehiculo.update']);
    $router->delete('tipos-documentos/vehiculos/{id}', ['uses' => 'TipoDocumentoVehiculoController@delete', 'as' => 'tipo-documento-vehiculo.delete']);
    #endregion

    #region Visitantes 
    $router->get('tipos-documentos/visitantes', ['uses' => 'TipoDocumentoVisitanteController@index', 'as' => 'tipo-documento-visitante.index']);
    $router->get('tipos-documentos/visitantes/{id}', ['uses' => 'TipoDocumentoVisitanteController@show', 'as' => 'tipo-documento-visitante.show']);
    $router->post('tipos-documentos/visitantes', ['uses' => 'TipoDocumentoVisitanteController@create', 'as' => 'tipo-documento-visitante.create']);
    $router->put('tipos-documentos/visitantes/{id}', ['uses' => 'TipoDocumentoVisitanteController@update', 'as' => 'tipo-documento-visitante.update']);
    $router->delete('tipos-documentos/visitantes/{id}', ['uses' => 'TipoDocumentoVisitanteController@delete', 'as' => 'tipo-documento-visitante.delete']);
    #endregion
    
    #endregion

    //// tipo de máquina
    $router->get('tipos-maquinas', ['uses' => 'TipoMaquinaController@index', 'as' => 'tipoDeMaquina.index']);
    $router->get('tipos-maquinas/{id}', ['uses' => 'TipoMaquinaController@show', 'as' => 'tipoDeMaquina.show']);
    $router->post('tipos-maquinas', ['uses' => 'TipoMaquinaController@create', 'as' => 'tipoDeMaquina.create']);
    $router->put('tipos-maquinas/{id}', ['uses' => 'TipoMaquinaController@update', 'as' => 'tipoDeMaquina.update']);
    $router->delete('tipos-maquinas/{id}', ['uses' => 'TipoMaquinaController@delete', 'as' => 'tipoDeMaquina.delete']);

    //// marca de máquina
    $router->get('marcas-maquinas', ['uses' => 'MarcaMaquinaController@index', 'as' => 'marcaDeMaquina.index']);
    $router->get('marcas-maquinas/{id}', ['uses' => 'MarcaMaquinaController@show', 'as' => 'marcaDeMaquina.show']);
    $router->post('marcas-maquinas', ['uses' => 'MarcaMaquinaController@create', 'as' => 'marcaDeMaquina.create']);
    $router->put('marcas-maquinas/{id}', ['uses' => 'MarcaMaquinaController@update', 'as' => 'marcaDeMaquina.update']);
    $router->delete('marcas-maquinas/{id}', ['uses' => 'MarcaMaquinaController@delete', 'as' => 'marcaDeMaquina.delete']);

    //// tipo de equipo
    $router->get('tipos-equipos', ['uses' => 'TipoEquipoController@index', 'as' => 'tipoDeEquipo.index']);
    $router->get('tipos-equipos/{id}', ['uses' => 'TipoEquipoController@show', 'as' => 'tipoDeEquipo.show']);
    $router->post('tipos-equipos', ['uses' => 'TipoEquipoController@create', 'as' => 'tipoDeEquipo.create']);
    $router->put('tipos-equipos/{id}', ['uses' => 'TipoEquipoController@update', 'as' => 'tipoDeEquipo.update']);
    $router->delete('tipos-equipos/{id}', ['uses' => 'TipoEquipoController@delete', 'as' => 'tipoDeEquipo.delete']);

    //// Tarjeta
    $router->get('tarjetas', ['uses' => 'TarjetaController@index', 'as' => 'tarjeta.index']);
    $router->get('tarjetas/{codigoZk}', ['uses' => 'TarjetaController@show', 'as' => 'tarjeta.show']);
    $router->post('tarjetas', ['uses' => 'TarjetaController@create', 'as' => 'tarjeta.create']);
    $router->put('tarjetas/{codigoZk}', ['uses' => 'TarjetaController@update', 'as' => 'tarjeta.update']);
    $router->delete('tarjetas/{codigoZk}', ['uses' => 'TarjetaController@delete', 'as' => 'tarjeta.delete']);

    //// Categoría
    $router->get('categorias', ['uses' => 'CategoriaController@index', 'as' => 'categoria.index']);
    $router->get('categorias/{id}', ['uses' => 'CategoriaController@show', 'as' => 'categoria.show']);
    $router->post('categorias', ['uses' => 'CategoriaController@create', 'as' => 'categoria.create']);
    $router->put('categorias/{id}', ['uses' => 'CategoriaController@update', 'as' => 'categoria.update']);
    $router->delete('categorias/{id}', ['uses' => 'CategoriaController@delete', 'as' => 'categoria.delete']);
    $router->post('categorias/sinc-sige', ['uses' => 'CategoriaController@sincConSige', 'as' => 'categoria.sinc-sige']);
    $router->post('categorias/asocia-tarjeta', ['uses' => 'CategoriaController@asociaTarjeta', 'as' => 'categoria.asocia-tarjeta']);

    //// Derecho de Admisión
    $router->get('derecho-admision', ['uses' => 'DerechoAdmisionController@index', 'as' => 'derechoAdmision.index']);
    $router->get('derecho-admision/{id}', ['uses' => 'DerechoAdmisionController@show', 'as' => 'derechoAdmision.show']);
    $router->post('derecho-admision', ['uses' => 'DerechoAdmisionController@create', 'as' => 'derechoAdmision.create']);
    $router->put('derecho-admision/{id}', ['uses' => 'DerechoAdmisionController@update', 'as' => 'derechoAdmision.update']);
    $router->delete('derecho-admision/{id}', ['uses' => 'DerechoAdmisionController@delete', 'as' => 'derechoAdmision.delete']);

    //// Area
    $router->get('pcar/areas', ['uses' => 'PCAR\\AreaController@index', 'as' => 'area.index']);
    $router->get('pcar/areas/{id}', ['uses' => 'PCAR\\AreaController@show', 'as' => 'area.show']);
    $router->post('pcar/areas', ['uses' => 'PCAR\\AreaController@create', 'as' => 'area.create']);
    $router->put('pcar/areas/{id}', ['uses' => 'PCAR\\AreaController@update', 'as' => 'area.update']);
    $router->delete('pcar/areas/{id}', ['uses' => 'PCAR\\AreaController@delete', 'as' => 'area.delete']);

    //// Autorizante
    $router->get('pcar/autorizantes', ['uses' => 'PCAR\\AutorizanteController@index', 'as' => 'pcar/autorizante.index']);
    $router->get('pcar/autorizantes/{id}/{idArea}', ['uses' => 'PCAR\\AutorizanteController@show', 'as' => 'pcar/autorizante.show']);
    $router->post('pcar/autorizantes', ['uses' => 'PCAR\\AutorizanteController@create', 'as' => 'pcar/autorizante.create']);
    $router->put('pcar/autorizantes/{id}/{idArea}', ['uses' => 'PCAR\\AutorizanteController@update', 'as' => 'pcar/autorizante.update']);
    $router->delete('pcar/autorizantes/{id}/{idArea}', ['uses' => 'PCAR\\AutorizanteController@delete', 'as' => 'pcar/autorizante.delete']);

    //// Solicitud
    $router->get('pcar/solicitudes', ['uses' => 'PCAR\\SolicitudController@index', 'as' => 'pcar.solicitud.index']);
    $router->post('pcar/solicitudes/notifyByMail', ['uses' => 'PCAR\\SolicitudController@notifyByMail', 'as' => 'pcar.solicitud.notifyByMail']);
    $router->get('pcar/solicitudes/{id}', ['uses' => 'PCAR\\SolicitudController@show', 'as' => 'pcar.solicitud.show']);
    $router->post('pcar/solicitudes', ['uses' => 'PCAR\\SolicitudController@create', 'as' => 'pcar.solicitud.create']);
    $router->put('pcar/solicitudes/{id}', ['uses' => 'PCAR\\SolicitudController@update', 'as' => 'pcar.solicitud.update']);
    $router->delete('pcar/solicitudes/{id}', ['uses' => 'PCAR\\SolicitudController@delete', 'as' => 'pcar.solicitud.delete']);
    $router->post('pcar/solicitudes/{id}/autorizar', ['uses' => 'PCAR\\SolicitudController@autorizar', 'as' => 'pcar.solicitud.autorizar']);
    $router->post('pcar/solicitudes/{id}/aprobar', ['uses' => 'PCAR\\SolicitudController@aprobar', 'as' => 'pcar.solicitud.aprobar']);
    $router->post('pcar/solicitudes/{id}/rechazar', ['uses' => 'PCAR\\SolicitudController@rechazar', 'as' => 'pcar.solicitud.rechazar']);
    $router->post('pcar/solicitudes/{id}/reenviar', ['uses' => 'PCAR\\SolicitudController@reenviar', 'as' => 'pcar.solicitud.reenviar']);
    $router->get('pcar/consulta', ['uses' => 'PCAR\\SolicitudController@consultaAlutel', 'as' => 'pcar.solicitud.consultaAlutel']);

    //// ConsultaDeCumplimiento
    $router->get('consultas-de-cumplimientos/busqueda', ['uses' => 'ConsultaDeCumplimientoController@busqueda', 'as' => 'consultas-de-cumplimientos.busqueda']);
    $router->get('consultas-de-cumplimientos/listarTurnos', ['uses' => 'ConsultaDeCumplimientoController@listarTurnos', 'as' => 'consultas-de-cumplimientos.listarTurnos']);
    //// Ruta
    $router->get('rutas', ['uses' => 'RutaController@index', 'as' => 'ruta.index']);
    $router->get('rutas/{id}', ['uses' => 'RutaController@show', 'as' => 'ruta.show']);
    $router->post('rutas', ['uses' => 'RutaController@create', 'as' => 'ruta.create']);
    $router->put('rutas/{id}', ['uses' => 'RutaController@update', 'as' => 'ruta.update']);
    $router->delete('rutas/{id}', ['uses' => 'RutaController@delete', 'as' => 'ruta.delete']);

    //// Destino
    $router->get('destinos', ['uses' => 'OrigenDestinoController@index', 'as' => 'destino.index']);
    $router->get('destinos/{id}', ['uses' => 'OrigenDestinoController@show', 'as' => 'destino.show']);
    $router->post('destinos', ['uses' => 'OrigenDestinoController@create', 'as' => 'destino.create']);
    $router->put('destinos/{id}', ['uses' => 'OrigenDestinoController@update', 'as' => 'destino.update']);
    $router->delete('destinos/{id}', ['uses' => 'OrigenDestinoController@delete', 'as' => 'destino.delete']);

    //// Log de Auditoría
    $router->get('log-auditoria/busqueda', ['uses' => 'LogAuditoriaController@busqueda', 'as' => 'log-auditoria.busqueda']);
    $router->get('log-auditoria/{idUsuario}/{fechaHora}', ['uses' => 'LogAuditoriaController@show', 'as' => 'log-auditoria.show']);

    //// Log de Verificación
    $router->get('log-proceso/busqueda', ['uses' => 'LogProcesoController@busqueda', 'as' => 'log-proceso.busqueda']);

    //// ReporteAutomatico
    $router->get('reportes-automaticos', ['uses' => 'ReporteAutomaticoController@index', 'as' => 'reporteAutomatico.index']);
    $router->get('reportes-automaticos/{id}', ['uses' => 'ReporteAutomaticoController@show', 'as' => 'reporteAutomatico.show']);
    $router->post('reportes-automaticos', ['uses' => 'ReporteAutomaticoController@create', 'as' => 'reporteAutomatico.create']);
    $router->put('reportes-automaticos/{id}', ['uses' => 'ReporteAutomaticoController@update', 'as' => 'reporteAutomatico.update']);
    $router->delete('reportes-automaticos/{id}', ['uses' => 'ReporteAutomaticoController@delete', 'as' => 'reporteAutomatico.delete']);
    
    $router->get('tipos-vehiculos', ['uses' => 'TipoVehiculoController@index', 'as' => 'tipoVehiculo.index']);
    $router->get('tipos-vehiculos/{id}', ['uses' => 'TipoVehiculoController@show', 'as' => 'tipoVehiculo.show']);
    $router->post('tipos-vehiculos', ['uses' => 'TipoVehiculoController@create', 'as' => 'tipoVehiculo.create']);
    $router->put('tipos-vehiculos/{id}', ['uses' => 'TipoVehiculoController@update', 'as' => 'tipoVehiculo.update']);
    $router->delete('tipos-vehiculos/{id}', ['uses' => 'TipoVehiculoController@delete', 'as' => 'tipoVehiculo.delete']);

    $router->get('marcas-vehiculos', ['uses' => 'MarcaVehiculoController@index', 'as' => 'marcaVehiculo.index']);
    $router->get('marcas-vehiculos/{id}', ['uses' => 'MarcaVehiculoController@show', 'as' => 'marcaVehiculo.show']);
    $router->post('marcas-vehiculos', ['uses' => 'MarcaVehiculoController@create', 'as' => 'marcaVehiculo.create']);
    $router->put('marcas-vehiculos/{id}', ['uses' => 'MarcaVehiculoController@update', 'as' => 'marcaVehiculo.update']);
    $router->delete('marcas-vehiculos/{id}', ['uses' => 'MarcaVehiculoController@delete', 'as' => 'marcaVehiculo.delete']);

    $router->get('cargos', ['uses' => 'CargoController@index', 'as' => 'cargo.index']);
    $router->get('cargos/{id}', ['uses' => 'CargoController@show', 'as' => 'cargo.show']);
    $router->post('cargos', ['uses' => 'CargoController@create', 'as' => 'cargo.create']);
    $router->put('cargos/{id}', ['uses' => 'CargoController@update', 'as' => 'cargo.update']);
    $router->delete('cargos/{id}', ['uses' => 'CargoController@delete', 'as' => 'cargo.delete']);

    $router->get('capacitaciones', ['uses' => 'CapacitacionController@index', 'as' => 'capacitacion.index']);
    $router->get('capacitaciones/busqueda', ['uses' => 'CapacitacionController@busqueda', 'as' => 'capacitacion.busqueda']);
    $router->get('capacitaciones/{id}', ['uses' => 'CapacitacionController@show', 'as' => 'capacitacion.show']);
    $router->post('capacitaciones', ['uses' => 'CapacitacionController@create', 'as' => 'capacitacion.create']);
    $router->put('capacitaciones/{id}', ['uses' => 'CapacitacionController@update', 'as' => 'capacitacion.update']);
    $router->delete('capacitaciones/{id}', ['uses' => 'CapacitacionController@delete', 'as' => 'capacitacion.delete']);

    $router->get('matriculasBaja', ['uses' => 'MatriculaBajaController@index', 'as' => 'matriculaBaja.index']);
    $router->get('matriculasBaja/{id}', ['uses' => 'MatriculaBajaController@show', 'as' => 'matriculaBaja.show']);
    $router->post('matriculasBaja', ['uses' => 'MatriculaBajaController@create', 'as' => 'matriculaBaja.create']);
    $router->put('matriculasBaja/{id}', ['uses' => 'MatriculaBajaController@update', 'as' => 'matriculaBaja.update']);
    $router->delete('matriculasBaja/{id}', ['uses' => 'MatriculaBajaController@delete', 'as' => 'matriculaBaja.delete']);

    //// DISENHO MATRICULA
    $router->get('disenhos-matriculas', ['uses' => 'DisenhoMatriculaController@index', 'as' => 'disenho-matricula.index']);
    $router->get('disenhos-matriculas/{id}/loadDisenhoEnBase64', ['uses' => 'DisenhoMatriculaController@loadDisenhoEnBase64', 'as' => 'disenho-matricula.loadDisenhoEnBase64']);

    $router->get('equipos', ['uses' => 'EquipoController@index', 'as' => 'equipo.index']);
    $router->get('equipos/{id}', ['uses' => 'EquipoController@show', 'as' => 'equipo.show']);
    $router->post('equipos', ['uses' => 'EquipoController@create', 'as' => 'equipo.create']);
    $router->put('equipos/{id}', ['uses' => 'EquipoController@update', 'as' => 'equipo.update']);
    $router->delete('equipos/{id}', ['uses' => 'EquipoController@delete', 'as' => 'equipo.delete']);
    
    $router->get('tipos-documentos', ['uses' => 'TipoDocumentoController@index', 'as' => 'tipoDocumento.index']);
    $router->get('tipos-documentos/{id}', ['uses' => 'TipoDocumentoController@show', 'as' => 'tipoDocumento.show']);
    $router->post('tipos-documentos', ['uses' => 'TipoDocumentoController@create', 'as' => 'tipoDocumento.create']);
    $router->put('tipos-documentos/{id}', ['uses' => 'TipoDocumentoController@update', 'as' => 'tipoDocumento.update']);
    $router->delete('tipos-documentos/{id}', ['uses' => 'TipoDocumentoController@delete', 'as' => 'tipoDocumento.delete']);
    
    $router->get('estados-actividad', ['uses' => 'EstadoActividadController@index', 'as' => 'estadoActividad.index']);
    $router->get('estados-actividad/{id}', ['uses' => 'EstadoActividadController@show', 'as' => 'estadoActividad.show']);
    $router->post('estados-actividad', ['uses' => 'EstadoActividadController@create', 'as' => 'estadoActividad.create']);
    $router->put('estados-actividad/{id}', ['uses' => 'EstadoActividadController@update', 'as' => 'estadoActividad.update']);
    $router->delete('estados-actividad/{id}', ['uses' => 'EstadoActividadController@delete', 'as' => 'estadoActividad.delete']);

    $router->get('paises', ['uses' => 'PaisController@index', 'as' => 'pais.index']);
    $router->get('paises/{id}', ['uses' => 'PaisController@show', 'as' => 'pais.show']);
    $router->post('paises', ['uses' => 'PaisController@create', 'as' => 'pais.create']);
    $router->put('paises/{id}', ['uses' => 'PaisController@update', 'as' => 'pais.update']);
    $router->delete('paises/{id}', ['uses' => 'PaisController@delete', 'as' => 'pais.delete']);

    $router->get('departamentos/{idPais}', ['uses' => 'DepartamentoController@index', 'as' => 'departamento.index']);

    $router->get('contratado', ['uses' => 'ContratadoController@index', 'as' => 'contratado.index']);
    $router->get('contratado/busqueda', ['uses' => 'ContratadoController@busqueda', 'as' => 'contratado.busqueda']);
    $router->post('contratado/actualizarDocumento', ['uses' => 'ContratadoController@actualizarDocumentos', 'as' => 'contratado.actualizarDocumento']);
    $router->post('contratado/contratos', ['uses' => 'ContratadoController@contratos', 'as' => 'contratado.contratos']);
    
    $router->get('maquinas/grafico', ['uses' => 'MaquinaController@grafico', 'as' => 'Maquina.grafico']);
    $router->get('maquinas/grafico/pormarcadetalle', ['uses' => 'MaquinaController@chartpormarcadetalle', 'as' => 'maquina.chartpormarcadetalle']);
    $router->get('maquinas/grafico/portipodetalle', ['uses' => 'MaquinaController@chartportipodetalle', 'as' => 'maquina.chartportipodetalle']);
    $router->get('maquinas/grafico/habilitadosdetalle', ['uses' => 'MaquinaController@charthabilitadosdetalle', 'as' => 'maquina.charthabilitadosdetalle']);
    $router->get('maquinas/grafico/porcategoriadetalle', ['uses' => 'MaquinaController@chartporcategoriadetalle', 'as' => 'maquina.chartporcategoriadetalle']);

    $router->get('vehiculos/grafico', ['uses' => 'VehiculoController@grafico', 'as' => 'vehiculo.grafico']);
    $router->get('vehiculos/grafico/pormarcadetalle', ['uses' => 'VehiculoController@chartpormarcadetalle', 'as' => 'vehiculo.chartpormarcadetalle']);
    $router->get('vehiculos/grafico/portipodetalle', ['uses' => 'VehiculoController@chartportipodetalle', 'as' => 'vehiculo.chartportipodetalle']);
    $router->get('vehiculos/grafico/habilitadosdetalle', ['uses' => 'VehiculoController@charthabilitadosdetalle', 'as' => 'vehiculo.charthabilitadosdetalle']);
    $router->get('vehiculos/grafico/porcategoriadetalle', ['uses' => 'VehiculoController@chartporcategoriadetalle', 'as' => 'vehiculo.chartporcategoriadetalle']);
    
    $router->get('logProcesos', ['uses' => 'LogProcesoController@busqueda', 'as' => 'logProcesos.busqueda']);
    
    $router->get('eventos', ['uses' => 'EventoController@index', 'as' => 'eventos.index']);
    $router->get('eventos/busqueda', ['uses' => 'EventoController@wsbusqueda', 'as' => 'eventos.wsbusqueda']);
    
    $router->get('contrato', ['uses' => 'ContratoController@index', 'as' => 'contrato.index']);
    $router->get('contrato/listado', ['uses' => 'ContratoController@wslistado', 'as' => 'contrato.listado']);
    $router->get('contrato/arbol', ['uses' => 'ContratoController@arbol', 'as' => 'contrato.arbol']);

    $router->get('visitas/areas', ['uses' => 'Visitas\AreaController@index', 'as' => 'visita.area.index']);
    $router->get('visitas/areas/{id}', ['uses' => 'Visitas\AreaController@show', 'as' => 'visita.area.show']);
    $router->post('visitas/areas', ['uses' => 'Visitas\AreaController@create', 'as' => 'visita.area.create']);
    $router->put('visitas/areas/{id}', ['uses' => 'Visitas\AreaController@update', 'as' => 'visita.area.update']);
    $router->delete('visitas/areas/{id}', ['uses' => 'Visitas\AreaController@delete', 'as' => 'visita.area.delete']);

    $router->get('visitas/autorizantes', ['uses' => 'Visitas\AutorizanteController@index', 'as' => 'visita.autorizante.index']);
    $router->get('visitas/autorizantes/{IdUsuarioAutorizante}/{IdArea}', ['uses' => 'Visitas\AutorizanteController@show', 'as' => 'visita.autorizante.show']);
    $router->post('visitas/autorizantes', ['uses' => 'Visitas\AutorizanteController@create', 'as' => 'visita.autorizante.create']);
    $router->put('visitas/autorizantes/{IdUsuarioAutorizante}/{IdArea}', ['uses' => 'Visitas\AutorizanteController@update', 'as' => 'visita.autorizante.update']);
    $router->delete('visitas/autorizantes/{IdUsuarioAutorizante}/{IdArea}', ['uses' => 'Visitas\AutorizanteController@delete', 'as' => 'visita.autorizante.delete']);
   
    $router->post('visitas/solicitudes', ['uses' => 'Visitas\SolicitudController@create', 'as' => 'visita.solicitud.create']);
    
    $router->get('visitas/visitantes', ['uses' => 'Visitas\VisitanteController@index', 'as' => 'visita.visitante.index']);
    $router->get('visitas/visitantes/{id}', ['uses' => 'Visitas\VisitanteController@show', 'as' => 'visita.visitante.show']);
    $router->post('visitas/visitantes/autorizar', ['uses' => 'Visitas\VisitanteController@autorizar', 'as' => 'visita.visitante.autorizar']);
    $router->post('visitas/visitantes/rechazar', ['uses' => 'Visitas\VisitanteController@rechazar', 'as' => 'visita.visitante.rechazar']);
    $router->post('visitas/visitantes/{id}/cerrar', ['uses' => 'Visitas\VisitanteController@cerrar', 'as' => 'visita.visitante.cerrar']);
    $router->post('visitas/visitantes/{id}/notificar', ['uses' => 'Visitas\VisitanteController@notificar', 'as' => 'visita.visitante.notificar']);
    $router->post('visitas/visitantes/{id}/aprobar', ['uses' => 'Visitas\VisitanteController@aprobar', 'as' => 'visita.visitante.aprobar']);
    $router->delete('visitas/visitantes/{id}', ['uses' => 'Visitas\VisitanteController@delete', 'as' => 'visita.visitante.delete']);

    $router->get('usuarios/{idUsuario}', ['uses' => 'UsuarioController@show', 'as' => 'usuarios.show']);
    $router->get('usuarios', ['uses' => 'UsuarioController@index', 'as' => 'usuarios.index']);
    // $router->post('usuarios/{idUsuario}/recuperarcontrasena', ['uses' => 'UsuarioController@recuperarContrasena', 'as' => 'usuarios.recuperarContrasena']);
    $router->post('usuarios/changePassword', ['uses' => 'UsuarioController@changePassword', 'as' => 'usuarios.changePassword']);
    $router->post('usuarios/restablecerContrasena', ['uses' => 'UsuarioController@restablecerContrasena', 'as' => 'usuarios.restablecerContrasena']);
    $router->post('usuarios/cambiarIdentificador', ['uses' => 'UsuarioController@cambiarIdentificador', 'as' => 'usuarios.cambiarIdentificador']);
    $router->post('usuarios/desactivacionMasiva', ['uses' => 'UsuarioController@desactivacionMasiva', 'as' => 'usuarios.desactivacionMasiva']);
    $router->post('usuarios', ['uses' => 'UsuarioController@create', 'as' => 'usuarios.create']);
    $router->put('usuarios/{idUsuario}', ['uses' => 'UsuarioController@update', 'as' => 'usuarios.update']);
    $router->delete('usuarios/{idUsuario}', ['uses' => 'UsuarioController@delete', 'as' => 'usuarios.delete']);

    $router->get('ptc/usuarios/{idUsuario}', ['uses' => 'PTC\UsuarioController@show', 'as' => 'usuarios.show']);
    $router->get('ptc/usuarios', ['uses' => 'PTC\UsuarioController@index', 'as' => 'usuarios.index']);
    // $router->post('ptc/usuarios/{idUsuario}/recuperarcontrasena', ['uses' => 'PTC\UsuarioController@recuperarContrasena', 'as' => 'usuarios.recuperarContrasena']);
    $router->post('ptc/usuarios/changePassword', ['uses' => 'PTC\UsuarioController@changePassword', 'as' => 'usuarios.changePassword']);
    $router->post('ptc/usuarios/restablecerContrasena', ['uses' => 'PTC\UsuarioController@restablecerContrasena', 'as' => 'usuarios.restablecerContrasena']);
    $router->post('ptc/usuarios/cambiarIdentificador', ['uses' => 'PTC\UsuarioController@cambiarIdentificador', 'as' => 'usuarios.cambiarIdentificador']);
    $router->post('ptc/usuarios/desactivacionMasiva', ['uses' => 'PTC\UsuarioController@desactivacionMasiva', 'as' => 'usuarios.desactivacionMasiva']);
    $router->post('ptc/usuarios', ['uses' => 'PTC\UsuarioController@create', 'as' => 'usuarios.create']);
    $router->put('ptc/usuarios/{idUsuario}', ['uses' => 'PTC\UsuarioController@update', 'as' => 'usuarios.update']);
    $router->delete('ptc/usuarios/{idUsuario}', ['uses' => 'PTC\UsuarioController@delete', 'as' => 'usuarios.delete']);

    $router->get('funciones', ['uses' => 'FuncionController@index', 'as' => 'funciones.index']);

    $router->get('ptc/permiso/busqueda', ['uses' => 'PTC\PermisoController@busqueda', 'as' => 'permiso.busqueda']);
    $router->get('ptc/permiso/cargar-equipos', ['uses' => 'PTC\PermisoController@cargarEquipos', 'as' => 'permiso.cargarEquipos']);
    $router->get('ptc/permiso/cargar-tipos', ['uses' => 'PTC\PermisoController@cargarTipos', 'as' => 'permiso.cargarTipos']);
    $router->get('ptc/permiso/cargar-riesgos', ['uses' => 'PTC\PermisoController@cargarRiesgos', 'as' => 'permiso.cargarRiesgos']);
    $router->get('ptc/permiso/cargar-equipos-mediciones', ['uses' => 'PTC\PermisoController@cargarEquiposMediciones', 'as' => 'permiso.cargarEquiposMediciones']);
    $router->get('ptc/permiso/cargar-mediciones-gral', ['uses' => 'PTC\PermisoController@cargarMedicionesGral', 'as' => 'permiso.cargarMedicionesGral']);
    $router->get('ptc/permiso/cargar-estados-ot', ['uses' => 'PTC\PermisoController@cargarEstadosOT', 'as' => 'permiso.cargarEstadosOT']);
    $router->get('ptc/permiso/cargar-estados', ['uses' => 'PTC\PermisoController@cargarEstados', 'as' => 'permiso.cargarEstados']);
    $router->get('ptc/permiso/{nroPTC}/cargar-mediciones', ['uses' => 'PTC\PermisoController@cargarMediciones', 'as' => 'permiso.cargarMediciones']);
    $router->get('ptc/permiso/{idArea}/cargar-tanques-por-area', ['uses' => 'PTC\PermisoController@cargarTanquesPorArea', 'as' => 'permiso.cargarTanquesPorArea']);
    $router->get('ptc/permiso/{nroPTC}/cargar-tanques-por-nroptc', ['uses' => 'PTC\PermisoController@cargarTanquesPorNroPTC', 'as' => 'permiso.cargarTanquesPorNroPTC']);
    $router->get('ptc/permiso/{nroPTC}/cargar-comentarios', ['uses' => 'PTC\PermisoController@cargarComentarios', 'as' => 'permiso.cargarComentarios']);
    $router->get('ptc/permiso/{nroPTC}/cargar-ptc-a-vincular', ['uses' => 'PTC\PermisoController@cargarPTCaVincular', 'as' => 'permiso.cargarPTCaVincular']);
    $router->get('ptc/permiso/{nroPTC}/cargar-ptc-a-vincular-por-tanques/{idTanques}', ['uses' => 'PTC\PermisoController@cargarPTCaVincularPorTanques', 'as' => 'permiso.cargarPTCaVincularPorTanques']);
    $router->get('ptc/permiso/{id}/tienemediciones', ['uses' => 'PTC\PermisoController@tienemediciones', 'as' => 'permiso.tienemediciones']);
    $router->get('ptc/permiso/{id}/escritico', ['uses' => 'PTC\PermisoController@escritico', 'as' => 'permiso.escritico']);
    $router->get('ptc/permiso/{id}/tienebloqueosasociados', ['uses' => 'PTC\PermisoController@tienebloqueosasociados', 'as' => 'permiso.tienebloqueosasociados']);
    $router->get('ptc/permiso/{id}/index-docs', ['uses' => 'PTC\PermisoController@indexDocs', 'as' => 'permiso.indexDocs']);
    //$router->get('ptc/permiso/{id}/cargar-riesgos', ['uses' => 'PTC\PermisoController@cargarRiesgos', 'as' => 'permiso.cargarRiesgos']);
    $router->get('ptc/permiso', ['uses' => 'PTC\PermisoController@index', 'as' => 'permiso.index']);
    $router->get('ptc/permiso/descargar-excel-pt', ['uses' => 'PTC\PermisoController@descargarExcelPT', 'as' => 'permiso.descargarExcelPT']);
    $router->get('ptc/permiso/{id}', ['uses' => 'PTC\PermisoController@show', 'as' => 'permiso.show']);
    $router->get('ptc/permiso/{nroPTC}/exportar-pdf', ['uses' => 'PTC\PermisoController@exportarPDF', 'as' => 'permiso.exportarPDF']);
    $router->get('ptc/permiso/{nroPTC}/exportar-ots-excel', ['uses' => 'PTC\PermisoController@exportarOtsExcel', 'as' => 'permiso.exportarOtsExcel']);
    $router->get('ptc/permiso/ver-docs/{fileName}', ['uses' => 'PTC\PermisoController@verDocs', 'as' => 'permiso.verDocs']);
    $router->get('ptc/permiso/ver-doc-bloqueo/{fileName}', ['uses' => 'PTC\PermisoController@verDocBloqueo', 'as' => 'permiso.verDocBloqueo']);
    $router->post('ptc/permiso', ['uses' => 'PTC\PermisoController@create', 'as' => 'permiso.create']);
    // $router->post('ptc/permiso/createdocs', ['uses' => 'PTC\PermisoController@createDocs', 'as' => 'permiso.createDocs']);
    $router->post('ptc/permiso/importar-desde-excel', ['uses' => 'PTC\PermisoController@importarDesdeExcel', 'as' => 'permiso.importarDesdeExcel']);
    $router->post('ptc/permiso/modificacion-masiva', ['uses' => 'PTC\PermisoController@modificacionmasiva', 'as' => 'permiso.modificacionmasiva']);
    $router->post('ptc/permiso/crear-mediciones-masivas', ['uses' => 'PTC\PermisoController@crearMedicionesMasivas', 'as' => 'permiso.crearMedicionesMasivas']);
    $router->post('ptc/permiso/{nroPTC}/agregar-comentario', ['uses' => 'PTC\PermisoController@agregarComentario', 'as' => 'permiso.agregarComentario']);
    $router->post('ptc/permiso/{nroPTC}/create-docs', ['uses' => 'PTC\PermisoController@createDocs', 'as' => 'permiso.createDocsWithNroPTC']);
    $router->post('ptc/permiso/{nroPTC}/create-doc-bloqueo', ['uses' => 'PTC\PermisoController@createDocBloqueo', 'as' => 'permiso.createDocBloqueo']);
    $router->post('ptc/permiso/{nroPTC}/aprobar', ['uses' => 'PTC\PermisoController@aprobar', 'as' => 'permiso.aprobar']);
    $router->post('ptc/permiso/{nroPTC}/autorizar', ['uses' => 'PTC\PermisoController@autorizar', 'as' => 'permiso.autorizar']);
    $router->post('ptc/permiso/{nroPTC}/autorizar-o-aprobar', ['uses' => 'PTC\PermisoController@autorizaroAprobar', 'as' => 'permiso.autorizaroAprobar']);
    $router->post('ptc/permiso/{nroPTC}/finalizar-ejecucion', ['uses' => 'PTC\PermisoController@finalizarEjecucion', 'as' => 'permiso.finalizarEjecucion']);
    $router->post('ptc/permiso/{nroPTC}/ejecutar', ['uses' => 'PTC\PermisoController@ejecutar', 'as' => 'permiso.ejecutar']);
    $router->post('ptc/permiso/{nroPTC}/tomar', ['uses' => 'PTC\PermisoController@tomarpt', 'as' => 'permiso.tomarpt']);
    $router->post('ptc/permiso/{nroPTC}/finalizar-mediciones', ['uses' => 'PTC\PermisoController@finalizarmediciones', 'as' => 'permiso.finalizarmediciones']);
    $router->post('ptc/permiso/{nroPTC}/rechazar', ['uses' => 'PTC\PermisoController@rechazar', 'as' => 'permiso.rechazar']);
    $router->post('ptc/permiso/{nroPTC}/rechazar-solicitud', ['uses' => 'PTC\PermisoController@rechazarsolicitud', 'as' => 'permiso.rechazarsolicitud']);
    $router->post('ptc/permiso/{nroPTC}/rechazar-ejecucion', ['uses' => 'PTC\PermisoController@rechazarejecucion', 'as' => 'permiso.rechazarejecucion']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-bloqueo', ['uses' => 'PTC\PermisoController@establecerRequiereBloqueo', 'as' => 'permiso.establecerRequiereBloqueo']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-bloqueo-ejecutado', ['uses' => 'PTC\PermisoController@establecerRequiereBloqueoEjecutado', 'as' => 'permiso.establecerRequiereBloqueoEjecutado']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-inspeccion', ['uses' => 'PTC\PermisoController@establecerRequiereInspeccion', 'as' => 'permiso.establecerRequiereInspeccion']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-drenaje', ['uses' => 'PTC\PermisoController@establecerRequiereDrenaje', 'as' => 'permiso.establecerRequiereDrenaje']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-drenaje-ejecutado', ['uses' => 'PTC\PermisoController@establecerRequiereDrenajeEjecutado', 'as' => 'permiso.establecerRequiereDrenajeEjecutado']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-limpieza', ['uses' => 'PTC\PermisoController@establecerRequiereLimpieza', 'as' => 'permiso.establecerRequiereLimpieza']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-limpieza-ejecutado', ['uses' => 'PTC\PermisoController@establecerRequiereLimpiezaEjecutado', 'as' => 'permiso.establecerRequiereLimpiezaEjecutado']);
    $router->post('ptc/permiso/{nroPTC}/establecer-requiere-medicion', ['uses' => 'PTC\PermisoController@establecerRequiereMedicion', 'as' => 'permiso.establecerRequiereMedicion']);
    $router->post('ptc/permiso/{nroPTC}/cerrar', ['uses' => 'PTC\PermisoController@cerrar', 'as' => 'permiso.cerrar']);
    $router->post('ptc/permiso/{nroPTC}/cerrar-parcialmente', ['uses' => 'PTC\PermisoController@cerrarParcialmente', 'as' => 'permiso.cerrarParcialmente']);
    $router->post('ptc/permiso/{nroPTC}/solicitar-revalidacion', ['uses' => 'PTC\PermisoController@solicitarRevalidacion', 'as' => 'permiso.solicitarRevalidacion']);
    $router->post('ptc/permiso/{nroPTC}/revalidar', ['uses' => 'PTC\PermisoController@revalidar', 'as' => 'permiso.revalidar']);
    $router->post('ptc/permiso/{nroPTC}/importar-ots', ['uses' => 'PTC\PermisoController@importarOts', 'as' => 'permiso.importarOts']);
    $router->post('ptc/permiso/{nroPTC}/solicitar-revision', ['uses' => 'PTC\PermisoController@solicitarRevision', 'as' => 'permiso.solicitarRevision']);
    $router->post('ptc/permiso/{nroPTC}/aprobar-revision', ['uses' => 'PTC\PermisoController@aprobarRevisionLiberacion', 'as' => 'permiso.aprobarRevisionLiberacion']);
    $router->put('ptc/permiso/{id}', ['uses' => 'PTC\PermisoController@update', 'as' => 'permiso.update']);
    // $router->delete('ptc/permiso/deletedocs', ['uses' => 'PTC\PermisoController@deleteDocs', 'as' => 'permiso.deleteDocs']);
    $router->delete('ptc/permiso/{IdPTCDoc}/delete-docs', ['uses' => 'PTC\PermisoController@deleteDocs', 'as' => 'permiso.deleteDocs']);
    $router->delete('ptc/permiso/{nroPTC}/delete-doc-bloqueo', ['uses' => 'PTC\PermisoController@deleteDocBloqueo', 'as' => 'permiso.deleteDocBloqueo']);
    $router->delete('ptc/permiso/{nroPTC}', ['uses' => 'PTC\PermisoController@delete', 'as' => 'permiso.delete']);

    $router->get('ptc/area', ['uses' => 'ptc\AreaController@index', 'as' => 'area.index']);
    $router->get('ptc/area/{id}', ['uses' => 'ptc\AreaController@show', 'as' => 'area.show']);
    $router->post('ptc/area', ['uses' => 'ptc\AreaController@create', 'as' => 'area.create']);
    $router->put('ptc/area/{id}', ['uses' => 'ptc\AreaController@update', 'as' => 'area.update']);
    $router->delete('ptc/area/{id}', ['uses' => 'ptc\AreaController@delete', 'as' => 'area.delete']);

    $router->get('ptc/plan-bloqueo', ['uses' => 'ptc\planBloqueoController@index', 'as' => 'area.index']);
    $router->get('ptc/plan-bloqueo/{id}', ['uses' => 'ptc\planBloqueoController@show', 'as' => 'plan-bloqueo.show']);
    $router->post('ptc/plan-bloqueo', ['uses' => 'ptc\planBloqueoController@create', 'as' => 'plan-bloqueo.create']);
    $router->delete('ptc/plan-bloqueo/{id}', ['uses' => 'ptc\planBloqueoController@delete', 'as' => 'plan-bloqueo.delete']);

    $router->get('ptc/espacio-confinado', ['uses' => 'ptc\espacioConfinadoController@index', 'as' => 'area.index']);
    $router->get('ptc/espacio-confinado/{id}', ['uses' => 'ptc\espacioConfinadoController@show', 'as' => 'espacio-confinado.show']);
    $router->post('ptc/espacio-confinado', ['uses' => 'ptc\espacioConfinadoController@create', 'as' => 'espacio-confinado.create']);
    $router->put('ptc/espacio-confinado/{id}', ['uses' => 'ptc\espacioConfinadoController@update', 'as' => 'area.update']);
    $router->delete('ptc/espacio-confinado/{id}', ['uses' => 'ptc\espacioConfinadoController@delete', 'as' => 'espacio-confinado.delete']);

    //// Maquina
    $router->get('maquinas', ['uses' => 'MaquinaController@index', 'as' => 'maquina.index']);
    $router->get('maquinas/busqueda', ['uses' => 'MaquinaController@busqueda', 'as' => 'maquina.busqueda']);
    $router->get('maquinas/{nroSerie}', ['uses' => 'MaquinaController@show', 'as' => 'maquina.show']);
    $router->get('maquinas/ver-foto/{fileName}', ['uses' => 'MaquinaController@verFoto', 'as' => 'maquina.ver-foto']);
    $router->put('maquinas/{nroSerie}', ['uses' => 'MaquinaController@update', 'as' => 'maquina.update']);
    $router->delete('maquinas/{nroSerie}', ['uses' => 'MaquinaController@delete', 'as' => 'maquina.delete']);
    $router->post('maquinas/{nroSerie}/activar', ['uses' => 'MaquinaController@activar', 'as' => 'maquina.activar']);
    $router->post('maquinas/{nroSerie}/subir-foto', ['uses' => 'MaquinaController@subirFoto', 'as' => 'maquina.subir-foto']);
    $router->post('maquinas/{nroSerie}/desactivar', ['uses' => 'MaquinaController@desactivar', 'as' => 'maquina.desactivar']);
    $router->post('maquinas/{nroSerie}/aprobar', ['uses' => 'MaquinaController@aprobar', 'as' => 'maquina.aprobar']);
    $router->post('maquinas/{nroSerie}/rechazar', ['uses' => 'MaquinaController@rechazar', 'as' => 'maquina.rechazar']);
    $router->post('maquinas/{nroSerie}/cambiar-identificador', ['uses' => 'MaquinaController@cambiarIdentificador', 'as' => 'maquina.cambiar-identificador']);
    $router->post('maquinas/{nroSerie}/sincronizar', ['uses' => 'MaquinaController@sincronizar', 'as' => 'maquina.sincronizar']);
    $router->post('maquinas/{nroSerie}/cambiar-matricula', ['uses' => 'MaquinaController@cambiarMatricula', 'as' => 'maquina.cambiar-matricula']);
    //// IMPRIMIR MATRICULA -
    $router->post('maquinas/{nroSerie}/imprimir-matricula', ['uses' => 'MaquinaController@imprimirMatriculaEnBase64', 'as' => 'maquina.cambiar-matricula']);
    $router->post('maquinas', ['uses' => 'MaquinaController@create', 'as' => 'maquina.create']);
    $router->get('maquinas/{nroSerie}/comprobar-identificador', ['uses' => 'MaquinaController@comprobarIdentificador', 'as' => 'maquina.comprobar-identificador']);
    $router->post('maquinas/subir-docs', ['uses' => 'MaquinaController@subirDocs', 'as' => 'maquinas.subir-docs']);
    $router->get('maquinas/archivo/{carpeta}/{fileName}', ['uses' => 'MaquinaController@verArchivo', 'as' => 'maquinas.ver-archivo']);

    //// VEHICULO
    $router->get('vehiculos', ['uses' => 'VehiculoController@index', 'as' => 'vehiculo.index']);
    $router->get('vehiculos/busqueda', ['uses' => 'VehiculoController@busqueda', 'as' => 'vehiculo.busqueda']);
    $router->post('vehiculos/subir-docs', ['uses' => 'VehiculoController@subirDocs', 'as' => 'vehiculo.subir-docs']);
    $router->get('vehiculos/archivo/{carpeta}/{fileName}', ['uses' => 'VehiculoController@verArchivo', 'as' => 'vehiculo.ver-archivo']);
    $router->get('vehiculos/{serie}/{numero}', ['uses' => 'VehiculoController@show', 'as' => 'vehiculo.show']);
    $router->put('vehiculos/{serie}/{numero}', ['uses' => 'VehiculoController@update', 'as' => 'vehiculo.update']);
    $router->delete('vehiculos/{serie}/{numero}', ['uses' => 'VehiculoController@delete', 'as' => 'vehiculo.delete']);
    $router->post('vehiculos/{serie}/{numero}/activar', ['uses' => 'VehiculoController@activar', 'as' => 'vehiculo.activar']);
    $router->post('vehiculos/{serie}/{numero}/subir-foto', ['uses' => 'VehiculoController@subirFoto', 'as' => 'vehiculo.subir-foto']);
    $router->post('vehiculos/{serie}/{numero}/desactivar', ['uses' => 'VehiculoController@desactivar', 'as' => 'vehiculo.desactivar']);
    $router->post('vehiculos/{serie}/{numero}/aprobar', ['uses' => 'VehiculoController@aprobar', 'as' => 'vehiculo.aprobar']);
    $router->post('vehiculos/{serie}/{numero}/rechazar', ['uses' => 'VehiculoController@rechazar', 'as' => 'vehiculo.rechazar']);
    $router->post('vehiculos/{serie}/{numero}/cambiar-identificador', ['uses' => 'VehiculoController@cambiarIdentificador', 'as' => 'vehiculo.cambiar-identificador']);
    $router->post('vehiculos/{serie}/{numero}/sincronizar', ['uses' => 'VehiculoController@sincronizar', 'as' => 'vehiculo.sincronizar']);
    $router->post('vehiculos/{serie}/{numero}/cambiar-matricula', ['uses' => 'VehiculoController@cambiarMatricula', 'as' => 'vehiculo.cambiar-matricula']);
    //// IMPRIMIR MATRICULA -
    $router->post('vehiculos/{serie}/{numero}/imprimir-matricula', ['uses' => 'VehiculoController@imprimirMatriculaEnBase64', 'as' => 'vehiculo.cambiar-matricula']);
    $router->post('vehiculos/{serie}/{numero}/cambiar-tag', ['uses' => 'VehiculoController@cambiarTag', 'as' => 'vehiculo.cambiar-tag']);
    $router->post('vehiculos', ['uses' => 'VehiculoController@create', 'as' => 'vehiculo.create']);
    $router->get('vehiculos/{serie}/{numero}/comprobar-identificador', ['uses' => 'VehiculoController@comprobarIdentificador', 'as' => 'vehiculo.comprobar-identificador']);

    $router->get('tipos-alojamiento', ['uses' => 'TiposAlojamientoController@index', 'as' => 'tiposAlojamiento.index']);
    $router->get('tipos-alojamiento/{id}', ['uses' => 'TiposAlojamientoController@show', 'as' => 'tiposAlojamiento.show']);
    $router->post('tipos-alojamiento', ['uses' => 'TiposAlojamientoController@create', 'as' => 'tiposAlojamiento.create']);
    $router->put('tipos-alojamiento/{id}', ['uses' => 'TiposAlojamientoController@update', 'as' => 'tiposAlojamiento.update']);
    $router->delete('tipos-alojamiento/{id}', ['uses' => 'TiposAlojamientoController@delete', 'as' => 'tiposAlojamiento.delete']);

    $router->get('alojamiento', ['uses' => 'AlojamientoController@index', 'as' => 'alojamiento.index']);
    $router->get('alojamiento/{id}', ['uses' => 'AlojamientoController@show', 'as' => 'alojamiento.show']);
    $router->post('alojamiento', ['uses' => 'AlojamientoController@create', 'as' => 'alojamiento.create']);
    $router->put('alojamiento/{id}', ['uses' => 'AlojamientoController@update', 'as' => 'alojamiento.update']);
    $router->delete('alojamiento/{id}', ['uses' => 'AlojamientoController@delete', 'as' => 'alojamiento.delete']);
    
    $router->get('transportista', ['uses' => 'TransportistaController@index', 'as' => 'transportista.index']);
    $router->get('transportista/{id}', ['uses' => 'TransportistaController@show', 'as' => 'transportista.show']);
    $router->post('transportista', ['uses' => 'TransportistaController@create', 'as' => 'transportista.create']);
    $router->put('transportista/{id}', ['uses' => 'TransportistaController@update', 'as' => 'transportista.update']);
    $router->delete('transportista/{id}', ['uses' => 'TransportistaController@delete', 'as' => 'transportista.delete']);

    $router->get('seguro-salud', ['uses' => 'SeguroSaludController@index', 'as' => 'seguroSalud.index']);
    $router->get('seguro-salud/{id}', ['uses' => 'SeguroSaludController@show', 'as' => 'seguroSalud.show']);
    $router->post('seguro-salud', ['uses' => 'SeguroSaludController@create', 'as' => 'seguroSalud.create']);
    $router->put('seguro-salud/{id}', ['uses' => 'SeguroSaludController@update', 'as' => 'seguroSalud.update']);
    $router->delete('seguro-salud/{id}', ['uses' => 'SeguroSaludController@delete', 'as' => 'seguroSalud.delete']);

    $router->get('tipos-contacto', ['uses' => 'TiposContactoController@index', 'as' => 'TiposContacto.index']);
    $router->get('tipos-contacto/{id}', ['uses' => 'TiposContactoController@show', 'as' => 'TiposContacto.show']);
    $router->post('tipos-contacto', ['uses' => 'TiposContactoController@create', 'as' => 'TiposContacto.create']);
    $router->put('tipos-contacto/{id}', ['uses' => 'TiposContactoController@update', 'as' => 'TiposContacto.update']);
    $router->delete('tipos-contacto/{id}', ['uses' => 'TiposContactoController@delete', 'as' => 'TiposContacto.delete']);

    $router->get('laboratorio', ['uses' => 'LaboratorioController@index', 'as' => 'Laboratorio.index']);
});