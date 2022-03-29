<?php

namespace App;

use App\Models\Categoria;
use App\Models\Contrato;
use App\Models\DisenhoMatricula;
use App\Models\Empresa;
use App\Models\Vehiculo;
use GDText\Box;
use GDText\Color;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImprimirMatricula
{
    public function obtenerDatosMatricula($entity)
    {
        $idCategoria = $entity->IdCategoria;
        $categoria = Categoria::find($idCategoria);

        // PERSONA FISICA Y VISITANTE!
        if (isset($entity->IdTipoDocumento) && isset($entity->Documento))
        {
            $empresaActiva = Empresa::loadByPersonaFisica($entity->Documento, $entity->IdTipoDocumento);
            if (isset($empresaActiva) && count($empresaActiva) > 1) {
                $empresaActiva = array_filter($empresaActiva, function ($v) {
                    return empty($v->FechaBaja) || \App\FsUtils::strToDateByPattern($v->FechaBaja) > new \Carbon\Carbon;
                });
                $empresaActiva = array_values($empresaActiva);
            }

            if(!empty($empresaActiva)) {
                $contratoActivo = Contrato::obtenerContratos($entity->Documento, $entity->IdTipoDocumento, $empresaActiva[0]->DocEmpresa, $empresaActiva[0]->TipoDocEmpresa);

                if(!empty($contratoActivo)) {
                    $empresaYContrato = implode(', ', [$empresaActiva[0]->Empresa, $contratoActivo[0]->NroContrato]);
                } else {
                    $empresaYContrato = $empresaActiva[0]->Empresa;
                }
            } else {
                $empresaYContrato = "";
            }

            $cargos = implode(', ', array_map(function($v) {
                return $v->Descripcion;
            }, $entity->Cargos));

            if(strlen($cargos) > 30) {
                $cargos = substr($cargos, 0, 30) . '...';
            }

            $personaFisica = new \stdClass;
            $personaFisica->Apellidos = $entity->PrimerApellido . ' ' . $entity->SegundoApellido;
            $personaFisica->Cargo = !empty($cargos) ? $cargos : '';
            $personaFisica->Categoria = $categoria->Descripcion;
            $personaFisica->EmpresaYContrato = $empresaYContrato;
            $personaFisica->Foto = $entity->Foto;
            $personaFisica->Matricula = $entity->Matricula;
            $personaFisica->Identificador = $entity->Documento;
            $personaFisica->IdDisenho = $categoria->IdDisenhoMatricula;
            $personaFisica->Nombres = strtoupper($entity->PrimerNombre . ' ' . $entity->SegundoNombre);

            return (array)$personaFisica;
        }
        if (isset($entity->NroSerie)) {
            $maquina = new \stdClass;
            $maquina->Categoria = $categoria->Descripcion;
            $maquina->Empresa = $entity->Empresa;
            $maquina->Identificador = $entity->NroSerie;
            $maquina->IdDisenho = $categoria->IdDisenhoMatricula;
            $maquina->Marca = $entity->Marca;
            $maquina->Matricula = $entity->Matricula;
            $maquina->Modelo = $entity->Modelo;
            $maquina->Tipo = $entity->Tipo;

            return (array)$maquina;
        }
        if ($entity->Serie && $entity->Numero) {
            $vehiculo = new \stdClass;
            $vehiculo->Categoria = $categoria->Descripcion;
            $vehiculo->Empresa = $entity->Empresa;
            $vehiculo->Identificador = $entity->Serie . $entity->Numero;
            $vehiculo->IdDisenho = $categoria->IdDisenhoMatricula;
            $vehiculo->Marca = $entity->Marca;
            $vehiculo->Matricula = $entity->Matricula;
            $vehiculo->Modelo = $entity->Modelo;
            $vehiculo->Tipo = $entity->Tipo;

            return (array)$vehiculo;
        }
    }

    public function imprimir($entity)
    {
        $categoria = Categoria::find($entity->IdCategoria);

        if (!isset($categoria)) {
            throw new NotFoundHttpException('La Persona no tiene una categoria asociada');
        }

        $pathIMG = DisenhoMatricula::getPathIMG($categoria->IdDisenhoMatricula);

        $aImageData = getimagesize($pathIMG);
        \Illuminate\Support\Facades\Log::info($pathIMG, $aImageData);

        $elementsDisenho = (object)DisenhoMatricula::loadElementsDisenho($categoria->IdDisenhoMatricula);

        $imagen = imagecreatefrompng($pathIMG);
        $this->superponerElementosEnImagen($entity, $imagen, $elementsDisenho);

        return $this->imprimirEnBase64($imagen);
    }
    
    private function superponerElementosEnImagen($entity, $imagen, $elementosDisenho) {
        foreach ($elementosDisenho as $elemento) {
            $tipoElemento = ucfirst($elemento->Tipo);
            $this->{'superponer'.$tipoElemento.'EnImagen'}($entity, $imagen, $elemento);
        }
    }
    
    private function superponerImagenEnImagen($entity, $imagen, $elemento) {
        $this->establecerImagenPorDefectoSiNoHayImagen($entity, $elemento);
        
        $miniatura = $this->obtenerMiniatura($entity, $elemento);
        $anchoMiniatura = imagesx($miniatura);
        $altoMiniatura = imagesy($miniatura);

        imagecopy($imagen, $miniatura, $elemento->X, $elemento->Y, 0, 0, $anchoMiniatura, $altoMiniatura);
    }
    
    private function establecerImagenPorDefectoSiNoHayImagen($entity, $elemento) {
        $k = $elemento->Dato;
        
        if (@!file_exists($entity->{$k})) {
            $entity->{$k} = storage_path('app/static/person.jpg'); // arreglar // Arreglado
        }
    }
    
    private function obtenerMiniatura($entity, $elemento) {
        $ruta = $entity->{$elemento->Dato};
        $anchoMaximoMiniatura = $elemento->Ancho;
        $altoMaximoMiniatura = $elemento->Alto;
        
        $imagen = imagecreatefromjpeg($ruta);
        if (!$imagen) $imagen = imagecreatefrompng($ruta);
        
        $anchoImagen = imagesx($imagen);
        $altoImagen = imagesy($imagen);
        
        $tamanhoMiniatura = $this->obtenerTamanhoProporcionalDeMiniatura($anchoImagen, $altoImagen, $anchoMaximoMiniatura, $altoMaximoMiniatura);
        $miniatura = imagecreatetruecolor($tamanhoMiniatura->ancho, $tamanhoMiniatura->alto);
        imagecopyresampled($miniatura, $imagen, 0, 0, 0, 0, $tamanhoMiniatura->ancho, $tamanhoMiniatura->alto, $anchoImagen, $altoImagen);
        
        return $miniatura;
    }
    
    private function obtenerTamanhoProporcionalDeMiniatura($anchoImagen, $altoImagen, $anchoMaximoMiniatura, $altoMaximoMiniatura) {
        $contenedorEsRetrato = $anchoMaximoMiniatura < $altoMaximoMiniatura;
        if ($anchoImagen > $altoImagen) {
            if ($contenedorEsRetrato) {
                return (object)[
                    'ancho' => $anchoMaximoMiniatura,
                    'alto' => $altoImagen * ($anchoMaximoMiniatura / $anchoImagen),
                ];
            }
            return (object)[
                'ancho' => $anchoMaximoMiniatura,
                'alto' => $altoImagen * ($altoMaximoMiniatura / $anchoImagen),
            ];
        }
        else if ($anchoImagen < $altoImagen) {
            if ($contenedorEsRetrato) {
                return (object)[
                    'ancho' => $anchoMaximoMiniatura,
                    'alto' => $altoImagen * ($anchoMaximoMiniatura / $anchoImagen),
                ];
            }
            return (object)[
                'ancho' => $anchoImagen * ($anchoMaximoMiniatura / $altoImagen),
                'alto' => $altoMaximoMiniatura
            ];
        }
        else {
            return (object)[
                'ancho' => $anchoMaximoMiniatura > $altoMaximoMiniatura ? $altoMaximoMiniatura : $anchoMaximoMiniatura,
                'alto' => $anchoMaximoMiniatura > $altoMaximoMiniatura ? $altoMaximoMiniatura : $anchoMaximoMiniatura,
            ];
        }
    }

    private function superponerTextoEnImagen($entity, $imagen, $elemento) {

        $datos = $this->obtenerDatosMatricula($entity);
        $texto = $datos[$elemento->Dato];

        $this->dibujarTextoEnImagen($texto, $imagen, $elemento);
    }
    
    private function superponerTextoFijoEnImagen($entity, $imagen, $elemento) {
        $this->dibujarTextoEnImagen($elemento->Dato, $imagen, $elemento);
    }
    
    private function dibujarTextoEnImagen($texto, $imagen, $elemento) {
        \Illuminate\Support\Facades\Log::debug('ImprimirMatricula::dibujarTextoEnImagen ' . $texto, (array)$elemento);

        $anchoMaximoTexto = $elemento->Ancho;
        $altoMaximoTexto = $elemento->Alto;
        
        $fuente = storage_path('app/static/fonts/segoeui.ttf');
        
        $box = new Box($imagen);
        $box->setBox($elemento->X, $elemento->Y, $anchoMaximoTexto, $altoMaximoTexto);
        $box->setFontColor($this->obtenerColor($elemento));
        $box->setFontFace($fuente);
        //$box->setTextShadow(new Color(0, 0, 0, 50), 2, 2);
        $box->setFontSize($elemento->Tamanho);
        $box->setTextAlign($elemento->Alineacion ?? 'left', 'top');
        $box->draw($texto);
    }

    private function obtenerColor($elemento) {
        if (!empty($elemento->Color)) {
            $color = explode(',', $elemento->Color); // VER SI FUNCA
            return new Color((int)$color[0], (int)$color[1], (int)$color[2]);
        }
        else {
            return new Color(0, 0, 0);
        }
    }
    
    private function imprimirEnBase64($imagen) {
        ob_start(); // Let's start output buffering.
        imagepng($imagen); //This will normally output the image, but because of ob_start(), it won't.
        $contents = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        return 'data:image/png;base64,'.base64_encode($contents);
    }
    
}