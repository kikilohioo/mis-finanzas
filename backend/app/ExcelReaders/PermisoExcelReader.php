<?php namespace App\ExcelReaders;


use App\Models\PTC\PTC;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PermisoExcelReader {

    const BOOL_TRUE = 'SI';
    const SOLICITADO = 'I';
    const CONTACTO_CON_ENERGIA = 1;
    const TRABAJO_EN_CALIENTE = 2;
    const ESPACIOS_CONFINADOS = 3;
    const EQUIPOS_RADIACTIVOS = 4;
    const TAREA_CON_PRODUCTOS_QUIMICOS = 5;
    const IZAJE_CARGA_PESADA = 6;
    const EXCAVACION = 7;
    const DESCARGA_DE_LOGICA = 8;
    const OTROS = 999;

    private $reader;
    private $spreadsheet;
    private $worksheet;
    
    public $rowNumber;

    public function open($filepath) {
        $this->reader = new Xlsx();
        $this->reader->setReadDataOnly(true);
        $this->spreadsheet = $this->reader->load($filepath);
        $this->worksheet = $this->spreadsheet->getActiveSheet();
        $this->rowNumber = 1;
    }

    public function hasRows() {
        return $this->rowNumber <= $this->worksheet->getHighestRow() && 
               !empty($this->worksheet->getCellByColumnAndRow(1, $this->rowNumber)->getValue());
    }

    public function nextRow($idUsuario) {
        $i = $this->rowNumber;
        $permiso = false;

        if (!empty($this->worksheet->getCellByColumnAndRow(1, $i)->getValue())) {
            $nombreArea = $this->worksheet->getCellByColumnAndRow(2, $i)->getValue();
            $area = DB::selectOne('select IdArea from PTCAreas where nombre = :nombreArea', [':nombreArea' => $nombreArea]);
            
            $nombreEmpresa = $this->worksheet->getCellByColumnAndRow(1, $i)->getValue();

            $empresa = DB::selectOne("SELECT CONCAT(e.Documento, '-', e.IdTipoDocumento) AS IdEmpresa, e.Documento, e.IdTipoDocumento, e.Nombre from Empresas e
                    inner join Personas p on p.Documento = e.Documento AND p.IdTipoDocumento = e.IdTipoDocumento
                    where p.Baja = 0 and nombre = :nombreEmpresa", [':nombreEmpresa' => $nombreEmpresa]);

            $usuarioPerteneceEmpresa = DB::Select('select * from UsuariosEmpresas where Documento = :Documento and IdTipoDocumento = :IdTipoDocumento and idUsuario = :idUsuario', [':IdTipoDocumento' => $empresa->IdTipoDocumento ,':Documento' => $empresa->Documento, ':idUsuario' => $idUsuario]);

            /*if (count($usuarioPerteneceEmpresa) == 0) {
                throw new HttpException(409, 'No puede solicitar permisos para la empresa ' . $empresa->Nombre);
            }*/

            $permiso = [];
            $permiso['idEstado'] = self::SOLICITADO;
            $permiso['NombreArea'] = $nombreArea;
            $permiso['NombreEmpresa'] = $nombreEmpresa;
            $permiso['IdEmpresa'] = $empresa->IdEmpresa;
            $permiso['IdArea'] = $area->IdArea;
            $permiso['UbicacionFuncional'] = $this->worksheet->getCellByColumnAndRow(3, $i)->getValue();
            $permiso['Descripcion'] = $this->worksheet->getCellByColumnAndRow(4, $i)->getValue();
            $permiso['EsDePGP'] = strtoupper($this->worksheet->getCellByColumnAndRow(5, $i)->getValue()) === self::BOOL_TRUE;
            $permiso['TelefonoContacto'] = $this->worksheet->getCellByColumnAndRow(7, $i)->getValue();
            $permiso['CantidadPersonas'] = $this->worksheet->getCellByColumnAndRow(8, $i)->getValue();
            $permiso['NroOT'] = $this->worksheet->getCellByColumnAndRow(9, $i)->getValue();
            $permiso['FechaHoraComienzoPrev'] = Date::excelToDateTimeObject($this->worksheet->getCellByColumnAndRow(10, $i)->getValue(), env('APP_TIMEZONE'));
            $permiso['FechaHoraFinPrev'] = Date::excelToDateTimeObject($this->worksheet->getCellByColumnAndRow(11, $i)->getValue(), env('APP_TIMEZONE'));
            $permiso['IdUsuario'] = $idUsuario;

            $permiso['RequiereBloqueo'] = 0;
            $permiso['RequiereInspeccion'] = 0;
            $permiso['RequiereDrenarPurgar'] = 0;
            $permiso['RequiereLimpieza'] = 0;
            $permiso['RequiereMedicion'] = 0;
            $permiso['PTCDocsPendientes'] = '';
            
            if ($permiso['EsDePGP']) $permiso['AnhoPGP'] = $permiso['FechaHoraComienzoPrev']->format('Y');

            if ($permiso['EsDePGP'] && (int)$permiso['AnhoPGP'] !== (int)date('Y')) {
                throw new HttpException(409, 'Sólo se permite solicitar permisos PGP para el año actual');
            }

            $permiso['PTCTipos'] = [];
            
            if (strtoupper($this->worksheet->getCellByColumnAndRow(12, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::CONTACTO_CON_ENERGIA;
                $permiso['RequiereBloqueo'] = 1;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(13, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::TRABAJO_EN_CALIENTE;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(14, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::ESPACIOS_CONFINADOS;
                $permiso['RequiereBloqueo'] = 1;
                $permiso['RequiereMedicion'] = 1;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(15, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::EQUIPOS_RADIACTIVOS;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(16, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::TAREA_CON_PRODUCTOS_QUIMICOS;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(17, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::IZAJE_CARGA_PESADA;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(18, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::EXCAVACION;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(19, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['PTCTipos'][] = self::DESCARGA_DE_LOGICA;
            }

            if (strtoupper($this->worksheet->getCellByColumnAndRow(21, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['cierreTapa'] = 1;
                $permiso['RequiereInspeccion'] = 1;
            }
            if (strtoupper($this->worksheet->getCellByColumnAndRow(22, $i)->getValue()) === self::BOOL_TRUE) {
                $permiso['AperturaTapa'] = 1;
            }

            if (!empty($this->worksheet->getCellByColumnAndRow(20, $i)->getValue())) {
                $permiso['PrimerComentario'] = $this->worksheet->getCellByColumnAndRow(20, $i)->getValue();
            }
        }

        return $permiso;
    }

}
