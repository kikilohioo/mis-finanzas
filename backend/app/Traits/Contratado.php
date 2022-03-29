<?php

namespace App\Traits;

use App\FsUtils;
use App\Models\Categoria;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

trait Contratado
{
    /**
     * Comprueba si la entidad cumple todas las condiciones
     * para ser activada.
     *
     * @param object $Args
     * @return void
     */
    public static function esActivable(object $Args)
    {
        if (isset($Args->ForzarActivacion) && ($Args->ForzarActivacion === 1 || $Args->ForzarActivacion === true)) {
            return;
        }

        $suff = static::getAttrSuffix();
        $text = ['PF' => 'La persona', 'Emp' => 'La empresa', 'Maq' => 'La máquina', 'Vehic' => 'El vehículo', 'Vis' => 'La visita'];
        $docmzclass = [
            // 'PF' => \App\Models\TipoDocumentoPF::class,
            'PF' => \App\Http\Controllers\TipoDocumentoPersonaFisicaController::class,
            'Emp' => \App\Http\Controllers\TipoDocumentoEmpresaController::class,
            'Maq' => \App\Http\Controllers\TipoDocumentoMaquinaController::class,
            'Vehic' => \App\Http\Controllers\TipoDocumentoVehiculoController::class,
        ];

        if ($suff !== 'Emp') {
            if ($suff === "PF" && FsUtils::endDay($Args->FechaVtoDoc, FsUtils::DDMMYY) < new Carbon) {
                throw new ConflictHttpException($text[$suff] . ' no se puede activar porque la fecha de vencimiento del documento es anterior al día de hoy');
            }

            if (($suff == "Vehic" || $suff == "Maq") && $Args->EnTransito == 1) {
                throw new ConflictHttpException($text[$suff] . " no se puede activar porque está en tránsito");
            }

            if (($suff != "PF" && $suff != "Vis" && empty($Args->IdEmpresa)) ||
                    ($suff == "PF" && empty($Args->Empresas)) || 
                    ($suff == "Vis" && empty($Args->IdEmpresaVisit))) {
                    throw new ConflictHttpException($text[$suff] . " no se puede activar porque no está asociad" . substr($text[$suff], -1) . " a una empresa");
                }
            else {
                $empresasHabilitadas = [];
            
                // hay empresa...
                if ($suff == "Maq" || $suff == "Vehic") {
                    $empresasHabilitadas[] = (object)[
                        'IdEmpresa' => $Args->IdEmpresa,
                    ];
                    
                    // Chequear que la entidad tenga al menos un contrato activo
                    if ($Args->Estado == 1 && count($Args->Contratos) == 0) {
                        // Compruebo si hay que ignorar la ausencia de contrato
                        if (Categoria::requiereContratoActivar($Args->IdCategoria)) {
                            throw new ConflictHttpException($text[$suff]." debe estar asociad".  substr($text[$suff], strlen($text[$suff]) - 1)." a un contrato");
                        }
                    }
                }
                else if ($suff == "Vis") {
                    $empresasHabilitadas[] = (object)[
                        'IdEmpresa' => $Args->IdEmpresaVisit,
                    ];
                }
                else if ($suff == "PF") {
                    $contratosCont = 0;

                    foreach ($Args->Empresas as $empresa) {
                        $empresa = (object)$empresa;
                        if (empty($empresa->FechaBaja) || FsUtils::fromHumanDate($empresa->FechaBaja) > new Carbon) {
                            $empresasHabilitadas[] = $empresa;
                        }
                        if (isset($empresa->Contratos)) {
                            foreach ($empresa->Contratos as $contrato) {
                                $contrato = (object)$contrato;
                                if (empty($contrato->FechaBajaContrato) || FsUtils::fromHumanDate($contrato->FechaBajaContrato) > new Carbon) {
                                    $contratosCont++;
                                }
                            }
                        }
                    }
                    
                    if (count($empresasHabilitadas) == 0) {
                        throw new ConflictHttpException($text[$suff] . " debe tener al menos una empresa habilitada asignada");
                    }

                    // Chequear que la persona tenga al menos un contrato activo
                    if ($Args->Estado == 1 && $contratosCont == 0) {
                        if (Categoria::requiereContratoActivar($Args->IdCategoria)) {
                            throw new ConflictHttpException("La persona debe estar asociada a un contrato");
                        }
                    }
                }
            }
            
            
            // Chequear que las empresas habilitadas estén activas
            foreach ($empresasHabilitadas as $empresa) {
                $IdEmpresa = FsUtils::explodeId($empresa->IdEmpresa);
                $empresa->Documento = $IdEmpresa[0];
                $empresa->IdTipoDocumento = $IdEmpresa[1];

                /**
                 * @var \App\Http\Controllers\EmpresaController $empresaController
                 */
                $empresaController = app(\App\Http\Controllers\EmpresaController::class);
                $obj = $empresaController->show($empresa->IdTipoDocumento, $empresa->Documento)->getData();
                if (empty($obj->Estado)) {
                    throw new ConflictHttpException($text[$suff] . " no se puede activar porque la empresa '" . $obj->Nombre . "' está deshabilitada");
                }
            }
            
            if (!Categoria::sincConOnGuard($Args->IdCategoria)) {
                if (empty($Args->TAG)) {
                    throw new ConflictHttpException($text[$suff] . " no se puede activar porque no tiene una matrícula asociada (TAG)");
                }
            } else if (empty($Args->Matricula)) {
                throw new ConflictHttpException($text[$suff] . " no se puede activar porque no tiene una matrícula asociada (Matricula)");
            }

            if (!empty($Args->FechaVigenciaDesde) && !empty($Args->HoraVigenciaDesde) && !empty($Args->FechaVigenciaHasta) && !empty($Args->HoraVigenciaHasta)) {
                $vigenciaDesde = FsUtils::fromHumanDatetime($Args->FechaVigenciaDesde . ' ' . $Args->HoraVigenciaDesde . ':00');
                $vigenciaHasta = FsUtils::fromHumanDatetime($Args->FechaVigenciaHasta . ' ' . $Args->HoraVigenciaHasta . ':00');
                // No se puede activar porque la fecha de activación/desactivación de la matrícula no son correctas
                if ($vigenciaHasta < $vigenciaDesde) {
                    $badVigenciaArgs = true;
                }
                else if ($vigenciaHasta < new Carbon) {
                    throw new ConflictHttpException($text[$suff] . " no se puede activar porque la fecha de desactivación de la matrícula es anterior al día de hoy");
                }
            }
            else if (!empty($Args->VigenciaDesde) && !empty($Args->VigenciaHasta)) {
                $vigenciaDesde = FsUtils::fromHumanDatetime($Args->VigenciaDesde . " 00:00:00");
                $vigenciaHasta = FsUtils::fromHumanDatetime($Args->VigenciaHasta . " 23:59:59");
                // No se puede activar porque la fecha de activación/desactivación de la matrícula no son correctas
                if ($vigenciaHasta < $vigenciaDesde) {
                    $badVigenciaArgs = true;
                }
                else if ($vigenciaHasta < new Carbon) {
                    throw new ConflictHttpException($text[$suff] . " no se puede activar porque la fecha de desactivación de la matrícula es anterior al día de hoy");
                }
            }
            else {
                $badVigenciaArgs = true;
            }

            if (isset($badVigenciaArgs)) {
                throw new ConflictHttpException($text[$suff] . " no se puede activar porque la fecha de activación/desactivación de la matrícula no es correcta");
            }
        }
        
        if (empty($Args->IdCategoria)) {
            throw new ConflictHttpException($text[$suff] . " no se puede activar porque no está asociad" . substr($text[$suff], -1) . " a una categoría");
        }

        if (!empty($Args->IdEstadoActividad)) {
            $estadoActividad = DB::SelectOne("SELECT TOP 1 Descripcion FROM EstadosActividad WHERE Desactivar = 1 AND IdEstadoActividad = ?", [$Args->IdEstadoActividad]);
            if (!!$estadoActividad) {
                throw new ConflictHttpException('No se puede activar porque tiene el estado de actividad: ' . $estadoActividad->Descripcion);
            }
        }
        
        if ($suff != "Vis") {
            $obligatorios = app($docmzclass[$suff])->index((object)[
                'IdCategoria' => $Args->IdCategoria,
                'Extranjeros' => isset($Args->Extranjero) ? $Args->Extranjero : null,
                'Obligatorio' => 1,
            ])->getData()->rows;

            if (!empty($obligatorios)) {
                if (!empty($Args->Documentos)) {
                    // Me fijo que los documentos obligatorios estén todos
                    foreach ($obligatorios as $documentoObl) {
                        $tieneDocumentoObl = false;
                        foreach ($Args->Documentos as $documento) {
                            $documento = (object)$documento;
                            $key = "IdTipoDoc" . $suff;
                            if ($documento->{$key} == $documentoObl->{$key}) {
                                $tieneDocumentoObl = true;
                                break;
                            }
                        }

                        if (!$tieneDocumentoObl) {
                            $faltanDocumentosObligatorios = true;
                            break;
                        }
                    }
                }
                else {
                    $faltanDocumentosObligatorios = true;
                }
            }

            if (isset($faltanDocumentosObligatorios)) {
                $doc = isset($documentoObl) ? $documentoObl->Nombre : "";
                $docMsg = !empty($doc) ? "el documento '" . $doc . "' es obligatorio y no está asociado a la misma" : "existen documentos obligatorios que no se han asociado a la misma";
                throw new ConflictHttpException($text[$suff] . " no se puede activar porque " . $docMsg);
            }

            if (!empty($Args->Documentos)) {
                foreach ($Args->Documentos as $documento) {
                    $documento = (object)$documento;
                    $vencimiento = FsUtils::strToDateByPattern(@$documento->Vto);
                    if (isset($vencimiento)) $vencimiento->setTime(23, 59, 59);
                    if (!empty($documento->TieneVto) && $vencimiento < new Carbon) {
                        if (isset($documento->Nombre)) {
                            throw new ConflictHttpException($text[$suff] . " no se puede activar porque el documento '" . $documento->Nombre .  "' tiene una fecha de vencimiento anterior al día de hoy");
                        } else if (isset($documento->Tipo)) {
                            throw new ConflictHttpException($text[$suff] . " no se puede activar porque el documento '" . $documento->Tipo .  "' tiene una fecha de vencimiento anterior al día de hoy");
                        }
                        throw new ConflictHttpException($text[$suff] . " no se puede activar porque un documento tiene una fecha de vencimiento anterior al día de hoy");
                    }
                }
            }
        }
    }

    /**
     * Obtiene el identificador dependiendo de la entidad
     * @param object $Args
     * @return object|null
     */
    private static function identifiersForClass(object $Args): ?object
    {
        switch (static::getAttrSuffix()) {
            case 'Emp':
            case 'PF':
            case 'Vis':
                return (object)[
                    'Documento' => $Args->Documento,
                    'IdTipoDocumento' => $Args->IdTipoDocumento,
                ];
            case 'Maq':
                return (object)[
                    'NroSerie' => $Args->NroSerie,
                ];
            case 'Vehic':
                return (object)[
                    'Serie' => $Args->Serie,
                    'Numero' => $Args->Numero,
                ];
        }

        return null;
    }

    /**
     * Registra un cambio de matricula
     * @param object $Args
     * @param string $observacion
     * @return void
     */
    public static function logCambioMatricula(object $Args, string $observaciones = ''): void
    {
        try {
            switch (self::getAttrSuffix()) {
                case 'PF':
                    $sql = 'INSERT INTO PersonasFisicasMatriculas '
                        . '(Documento, IdTipoDocumento, Matricula, Observaciones, IdUsuario, FechaHora) '
                        . 'VALUES (?, ?, ?, ?, ?, GETDATE())';
                    $binding = [
                        $Args->Documento,
                        $Args->IdTipoDocumento,
                        $Args->Matricula,
                        $observaciones,
                        Auth::id()
                    ];
                    break;
                case 'Maq':
                        $sql = 'INSERT INTO MaquinasMatriculas'
                            . '(NroSerie, Matricula, Observaciones, IdUsuario, FechaHora) '
                            . 'VALUES (?, ?, ?, ?, GETDATE())';
                        $binding = [
                            $Args->NroSerie,
                            $Args->Matricula,
                            $observaciones,
                            Auth::id()
                        ];
                    break;
                case 'Vehic':
                    $sql = 'INSERT INTO VehiculosMatriculas'
                        . '(Serie, Numero, Matricula, Observaciones, IdUsuario, FechaHora)'
                        . 'VALUES (?, ?, ?, ?, ?, GETDATE())';
                        $binding = [
                            $Args->Serie,
                            $Args->Numero,
                            $Args->Matricula,
                            $observaciones,
                            Auth::id()
                        ];
                break;
                default:
                    return;
            }
            DB::insert($sql, $binding);
        } catch (\Exception $err) {
            throw $err;
        }
    }

    /**
     * Devuelve la clave primaria de Empresas
     * @param object $args
     * @param string $modifier
     * @return array
     */
    public static function explodeIdEmpresa(object $args, string $modifier = ''): array
    {
        throw new \Exception('No utilizado, probar');
        // if (isset($args->{'IdEmpresa' . $modifier})) {
        //     return FsUtils::explodeId($args->{'IdEmpresa' . $modifier});
        // }
        // return [];
    }

    public static function cambiarIdentificador(array $tables, object $args)
    {
        // Deshabilito temporalmente la comprobación de claves foráneas en las tablas
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE " . $table[0] . " NOCHECK CONSTRAINT ALL;");
        }

        // Hago el update
        foreach ($tables as $table) {
            $c = explode(', ', $table[1]);

            foreach ($c as $columns) {
                $cn = explode('|', $columns);

                // Ejecuto una sentencia SQL con los identificadores según el tipo de entidad
                switch (static::getAttrSuffix()) {
                    case 'Emp':
                    case 'PF':
                    case 'Vis':
                        DB::statement(
                            'UPDATE ' . $table[0] . ' SET ' . $cn[0] . ' = ?, ' . $cn[1] . ' = ? WHERE ' . $cn[0] . ' = ? AND ' . $cn[1] . ' = ?',
                            [
                                $args->NuevoDocumento,
                                $args->NuevoIdTipoDocumento,
                                $args->Documento,
                                $args->IdTipoDocumento,
                            ]
                        );
                        break;

                    case 'Maq':
                        DB::statement(
                            'UPDATE ' . $table[0] . ' SET ' . $cn[0] . ' = ? WHERE ' . $cn[0] . ' = ?',
                            [
                                $args->NuevoNroSerie,
                                $args->NroSerie,
                            ]
                        );
                        break;

                    case 'Vehic':
                        DB::statement(
                            'UPDATE ' . $table[0] . ' SET ' . $cn[0] . ' = ?, ' . $cn[1] . ' = ? WHERE ' . $cn[0] . ' = ? AND ' . $cn[1] . ' = ?',
                            [
                                strtoupper($args->NuevaSerie),
                                $args->NuevoNumero,
                                $args->Serie,
                                $args->Numero,
                            ]
                        );
                        break;
                }
            }
        }

        // Habilito la comprobación de claves foráneas instantaneamente
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE " . $table[0] . " CHECK CONSTRAINT ALL;");
        }
    }

    public static function comprobarIdentificador(object $args)
    {
        $transac = ['PF' => true, 'Vis' => true];
        $text = [
            'Emp' => 'una empresa',
            'PF' => 'una persona',
            'Vis' => 'un visitante',
            'Maq' => 'una máquina con el Nro de Serie ingresado',
            'Vehic' => 'un vehículo con la matrícula ingresada',
        ];

        $table = static::getTablePreffix();
        $suffix = static::getAttrSuffix();
        $isTransac = !empty($transac[$suffix]);
        $binding = [];

        if (in_array($suffix, ['PF', 'Emp', 'Vis'])) {
            $sql = "SELECT
                    p.Baja,
                    CASE
                        WHEN pf.Documento IS NOT NULL AND pf.Transito = 0 THEN 'PF'
                        WHEN pf.Documento IS NOT NULL AND pf.Transito = 1 THEN 'Vis'
                        WHEN e.Documento IS NOT NULL THEN 'Emp'
                    END AS Entidad
                FROM Personas p
                LEFT JOIN PersonasFisicas pf ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento
                LEFT JOIN Empresas e ON p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
                WHERE p.Documento = ? AND p.IdTipoDocumento = ? ";

            $binding[] = $args->Documento;
            $binding[] = $args->IdTipoDocumento;

            if ($isTransac) {
                $sql .= "AND NOT EXISTS (SELECT DISTINCT Documento, IdTipoDocumento 
                                    FROM " . $table . "Transac pft 
                                    WHERE pft.Documento = p.Documento 
                                    AND pft.IdTipoDocumento = p.IdTipoDocumento AND pft.Completada = 0) UNION ALL ";

                $sql .= "SELECT
                        1 AS Baja,
                        CASE
                            WHEN pf.Documento IS NOT NULL AND pf.Transito = 0 THEN 'PF'
                            WHEN pf.Documento IS NOT NULL AND pf.Transito = 1 THEN 'Vis'
                            WHEN e.Documento IS NOT NULL THEN 'Emp'
                        END AS Entidad
                    FROM " . $table . "Transac pft
                    LEFT JOIN PersonasFisicas pf ON pft.Documento = pf.Documento AND pft.IdTipoDocumento = pf.IdTipoDocumento
                    LEFT JOIN Empresas e ON pft.Documento = e.Documento AND pft.IdTipoDocumento = e.IdTipoDocumento
                    WHERE pft.Documento = ? 
                    AND pft.IdTipoDocumento = ? 
                    AND pft.Completada = 0";


                $binding[] = $args->Documento;
                $binding[] = $args->IdTipoDocumento;
            }

            $obj = DB::selectOne($sql, $binding);

            if (!$obj || empty($obj->Entidad)) {
                return null;
            }

            if ($obj->Entidad != $suffix) {
                if ($obj->Entidad == 'PF' && empty($obj->Baja) && !empty($obj->Estado)) {
                    return ['message' =>  'El documento ingresado está siendo utilizado por ' . $text[$obj->Entidad], 'code' => 1];
                } else {
                    return ['message' => 'Existe ' . $text[$obj->Entidad] . ' con el documento ingresado', 'code' => 3];
                }
            } else if (empty($obj->Baja)) {
                return ['message' => 'Existe ' . $text[$suffix] . ' con el documento ingresado', 'code' => 2];
            } else {
                return ['message' => 'Existe ' . $text[$suffix] . ' con el documento ingresado en el archivo de históricos', 'code' => 4];
            }
        } else if (in_array($suffix, ['Maq', 'Vehic'])) {
            if ($suffix === 'Maq') {
                $sql = "SELECT
                    m.Baja
                    FROM Maquinas m
                    WHERE m.NroSerie = ? 
                    AND NOT EXISTS (SELECT DISTINCT 
                                        NroSerie
                                    FROM MaquinasTransac mt 
                                    WHERE mt.NroSerie = m.NroSerie)

                    UNION ALL

                    SELECT
                    0 AS Baja
                    FROM MaquinasTransac mt
                    WHERE mt.NroSerie = ?";

                $binding[] = $args->NroSerie;
                $binding[] = $args->NroSerie;

            } else if ($suffix === 'Vehic') {
                $sql = "SELECT v.Baja FROM Vehiculos v WHERE v.Serie = ? AND v.Numero = ?
                    AND NOT EXISTS (SELECT DISTINCT Serie, Numero FROM VehiculosTransac vt WHERE vt.Serie = v.Serie AND vt.Numero = v.Numero)
                UNION ALL SELECT 0 AS Baja FROM VehiculosTransac vt WHERE vt.Serie = ? AND vt.Numero = ?";

                $binding[] = strtoupper($args->Serie);
                $binding[] = $args->Numero;
                $binding[] = strtoupper($args->Serie);
                $binding[] = $args->Numero;
            }

            $obj = DB::selectOne($sql, $binding);

            if (!$obj) {
                return null;
            }

            if ($obj->Baja == 0) {
                return ['message' => 'Existe ' . $text[$suffix], 'code' => 2];
            } else {
                return ['message' => 'Existe ' . $text[$suffix] . ' en el archivo de históricos', 'code' => 3];
            }
        }
    }

    public static function altaDocumentos(object $args, $reset = true, $modifier = '')
    {
        // Obtengo la clase
        $Identifiers = static::identifiersForClass($args);
        $TablePreffix = static::getTablePreffix();
        $AttrSuffix = static::getAttrSuffix();
        $carpetas = [
            // 'PF' => \App\Models\TipoDocumentoPF::class,
            'PF' => 'personas-fisicas',
            'Emp' => 'empresas',
            'Maq' => 'maquinas',
            'Vehic' => 'vehiculos',
        ];

        $binding = [];
        
        $idKeys = [];
        foreach ($Identifiers as $ak => $av) {
            $idKeys[] = $ak;
            $binding[] = $av;
        }

        $bindingDocs = $binding;
        $bindingDocsANoEliminar = $binding;

        // Si reset = true borro el contenido de las tablas cuyos identificadores coincidan con los que están en Args
        if ($reset) {
            if (!isset($args->Documentos)) {
                $args->Documentos = [];
            }
            $cantidad = count($args->Documentos);
            $i = 1;
            $valores = null;
            
            if (!empty($args->Documentos)) {
                foreach ($args->Documentos as $d) {
                    $d = (array)$d;
                    $bindingDocs[] = $d['IdTipoDoc' . $AttrSuffix];
                    if($cantidad > $i){
                        $valores .= '?,'; 
                    }else{
                        $valores .= '?'; 
                    }
                    $i++;
                }
            }

            $sqlNotIn = '';
            $sqlIn = '';
            if(!empty($valores)){
                $sqlNotIn = " and d.IdTipoDoc". $AttrSuffix. " Not in (". $valores .") ";
                $sqlIn = " and d.IdTipoDoc". $AttrSuffix. " in (". $valores .") ";
            }

            switch ($AttrSuffix) {
                case 'Emp':
                case 'PF':
                    $innerJoin = "inner join " . $TablePreffix . $modifier . "Docs d on d.nroDoc = di.nroDoc and d.Documento = di.Documento and d.IdTipoDocumento = di.IdTipoDocumento";
                    break;
                case 'Maq':
                    $innerJoin = "INNER JOIN ". $TablePreffix . $modifier . "Docs d on d.NroSerie = di.NroSerie and d.NroDoc = di.NroDoc";
                    break;
                case 'Vehic':
                    $innerJoin = "INNER JOIN ". $TablePreffix . $modifier . "Docs d on d.Numero = di.Numero and d.Serie = di.Serie and d.NroDoc = di.NroDoc";
                    break;
            }

            $sql = "select Archivo FROM " . $TablePreffix . $modifier . "DocsItems di ". $innerJoin ." WHERE " . implode(' AND ', array_map(function ($v) { return 'di.'.$v . ' = ?'; }, $idKeys)) . $sqlNotIn;
            $archivosAEliminar = DB::select($sql, $bindingDocs);

            if(!empty($archivosAEliminar)){
                foreach($archivosAEliminar as $archivoAEliminar){
                    if(!empty($archivoAEliminar)){
                        $pathName = storage_path('app/uploads/' . $carpetas[$AttrSuffix] . '/docs/'.$archivoAEliminar->Archivo);
                        if (file_exists($pathName)) unlink($pathName);
                    }
                }
            }

            $sql = "select di.NroDoc FROM " . $TablePreffix . $modifier . "DocsItems di ". $innerJoin ." WHERE " . implode(' AND ', array_map(function ($v) { return 'di.'.$v . ' = ?'; }, $idKeys)) . $sqlIn;
            $archivosANoEliminar = DB::select($sql, $bindingDocs);

            $cantidad = count($archivosANoEliminar);
            $i = 1;
            $notInNroDoc = '';
            if(!empty($archivosANoEliminar)){
                foreach($archivosANoEliminar as $nroDoc){
                    $bindingDocsANoEliminar[] = $nroDoc->NroDoc;
                    if($cantidad > $i){
                        $notInNroDoc .= '?,';
                    }else{
                        $notInNroDoc .= '?';
                    }
                    $i++;
                }
            }

            /*$sqlNotIn = '';
            if(!empty($notInNroDoc)){
                $sqlNotIn = " and NroDoc Not in (". $notInNroDoc .") ";
            }*/

            $sql = "DELETE FROM " . $TablePreffix . $modifier . "DocsItems WHERE " . implode(' AND ', array_map(function ($v) { return $v . ' = ?'; }, $idKeys));
            DB::statement($sql, $binding);

            $sql2 = "DELETE FROM " . $TablePreffix . $modifier . "Docs WHERE " . implode(' AND ', array_map(function ($v) { return $v . ' = ?'; }, $idKeys));
            DB::statement($sql2, $binding);
        }

        if (!empty($args->Documentos)) {
            // Genero las sentencias SQL para insertar cada uno de los documentos
            $NroDoc = 1;

            foreach ($args->Documentos as $d) {
                $d = (array)$d;
                $sql = "INSERT INTO " . $TablePreffix . $modifier . "Docs "
                    . "(" . implode(', ', $idKeys) . ", NroDoc, IdTipoDoc" . $AttrSuffix . ", Obligatorio, Identificador, Vto, Categoria, Observacion) "
                    . "VALUES (" . implode(', ', array_map(function ($v) { return '?'; }, $idKeys)) . ", ?, ?, ?, ?, CONVERT(DATETIME, ?, 103), ?, ?)";

                DB::statement($sql, array_merge($binding, [
                    $NroDoc,
                    $d["IdTipoDoc$AttrSuffix"],
                    $d['Obligatorio'] == true || $d['Obligatorio'] == 1 || $d['Obligatorio'] == "Si" ? 1 : 0,
                    isset($d['Identificador']) ? $d['Identificador'] : '',
                    isset($d['Vto']) ? FsUtils::strToDateByPattern($d['Vto'])->format(FsUtils::DDMMYY) : null,
                    isset($d['Categoria']) ? $d['Categoria'] : 'N/A',
                    @$d['Observacion'],
                ]));

                // Obtengo los archivos
                $ditems = [];

                foreach ($d as $key => $value) {
                    if (strpos($key, 'Archivo') === 0 && isset($value)) {
                        $nro = (int) ('0' . str_replace('Archivo', "", $key));
                        $ditems[] = array($key, $nro);
                    }
                }

                // Los meto en " . $TablePreffix . "DocsItems
                foreach ($ditems as $di) {
                    // Obtengo la extensión
                    $ext = substr($d[$di[0]], strrpos($d[$di[0]], '.') + 1);
                    // Luego el nombre de archivo
                    $filename = "";
                    foreach ($Identifiers as $a)
                        $filename .= '-' . $a;
                    // Y genero el path
                    $path = 'Documentos' . $d["IdTipoDoc$AttrSuffix"] . '-Archivo' . ($di[1] > 0 ? $di[1] : "") . $filename . '.' . $ext;
                    // Genero el INSERT
                    $sql = "INSERT INTO " . $TablePreffix . $modifier . "DocsItems "
                        . "(" . implode(', ', $idKeys) . ", NroDoc, NroItem, Archivo) "
                        . "VALUES (" . implode(', ', array_map(function ($v) { return '?'; }, $idKeys)) . ", ?, ?, ?)";

                    DB::statement($sql, array_merge($binding, [
                        $NroDoc,
                        $di[1],
                        $path
                    ]));
                }

                $NroDoc++;
            }
        }
    }

}