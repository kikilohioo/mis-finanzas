<?php 

namespace App\ExcelReaders;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class OTExcelReader
{
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

    /**
     * Devuelve una OT.
     * 
     * @return \stdClass
     */
    public function nextRow(): ?\stdClass
    {
        $i = $this->rowNumber;
        $return = new \stdClass;

        if (!empty($this->worksheet->getCellByColumnAndRow(1, $i)->getValue())) {
            $return->NroOT = $this->worksheet->getCellByColumnAndRow(1, $i)->getValue();
            $return->UbicacionTecnica = $this->worksheet->getCellByColumnAndRow(2, $i)->getValue();
            $return->NombreUbicacionTec = $this->worksheet->getCellByColumnAndRow(3, $i)->getValue();
            $return->Descripcion = $this->worksheet->getCellByColumnAndRow(4, $i)->getValue();
        }

        return $return;
    }
}