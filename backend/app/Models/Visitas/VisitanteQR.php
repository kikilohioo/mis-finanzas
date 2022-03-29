<?php

namespace App\Models\Visitas;

use Fpdf\Fpdf;


class VisitanteQR extends Fpdf {

    public static function createQrWithTemplate(Visitante $visitante) {
        $url = url('/visitas/visitantes/'.$visitante->Id.'/accessImage');
        //$url = 'http://127.0.0.1/accessImage.png';

        $pdf = new self();
        $pdf->AddPage();
        $pdf->Image($url, null, null, 190, 260, 'PNG');
        $pdf->Output();
    }

    public static function createQr(Visitante $visitante) {
        $pdf = new self();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->Write(30, utf8_decode('Código QR para '.$visitante->Nombres. ' '. $visitante->Apellidos));
        $pdf->Ln();
        $pdf->Image(url('/visitas/visitantes/'.$visitante->Id.'/qrImage'), null, null, 0, 0, 'PNG');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Write(30, utf8_decode('Matricula: '.$visitante->Matricula));
        $pdf->Output();
    }

}