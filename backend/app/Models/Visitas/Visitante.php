<?php

namespace App\Models\Visitas;

use App\Models\PersonaFisica;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class Visitante extends \App\Models\BaseModel
{
    const ESTADO_SOLICITADO = 'I';
    const ESTADO_AUTORIZADO = 'Z';
    const ESTADO_RECHAZADO = 'R';
    const ESTADO_VENCIDO = 'V';
    const ESTADO_CERRADO = 'C';
    const ESTADO_APROBADO = 'A';
    const ESTADO_NOTIFICADO_DISTANCIA = 'O';
    const ESTADO_NOTIFICADO_PRESENCIAL = 'P';
    
	protected $table = 'Visitas_SolicitudesPersonas';
	protected $primaryKey = "Id";
	protected $casts = [
		'Id' => 'string',
	];

	protected $fillable = [
		'Documento',
        'Nombres',
        'Apellidos',
        'Email',
	];

    /*protected $appends = [
		'PersonaFisica',
        'Persona',
	];*/

	public $incrementing = false;

    public static function getByIdWithSolicitud($id) 
	{
		return self::with(['Solicitud.Area', 'Solicitud.Solicitante'])->find($id);
    }

	public function Solicitud()
    {
        return $this->hasOne(Solicitud::class, 'Id', 'IdSolicitud');
    }

	public function Autorizante()
    {
        return $this->hasOne(Usuario::class, 'IdUsuario', 'IdUsuarioAutorizante');
    }

    public function getPersonaFisicaAttribute()
    {
        return PersonaFisica::where('Documento', $this->Documento)->where('IdTipoDocumento', $this->IdTipoDocumento)->noLock()->first();
    }

    public function getPersonaAttribute()
    {
        return Persona::where('Documento', $this->Documento)->where('IdTipoDocumento', $this->IdTipoDocumento)->first();
    }

	public static function getById($id) 
	{
		return self::with(['Solicitud.Area', 'Solicitud.Solicitante'])->noLock()->find($id);
    }

	protected function requireProperties($obj, $args = []) {
        foreach ($args as $arg) {
            if (empty($obj->{$arg})) {
                throw new \Exception('Missing required field: '.$arg);
            }
        }
    }

    private static function validate($Args){
        self::exigirArgs($Args, [  'FechaHoraDesde','FechaHoraHasta','IdArea',
                                    'EmpresaVisitante','IdUsuario','Documento','Motivo',
                                    'PersonaContacto','TelefonoContacto',
                                ]);
    }

	public static function validateAutorizar($visitante) {   
        $ExigirSolicitud = $visitante['Solicitud'];
        $ExigirSolicitud['Documento'] = $visitante['Documento'];
        
        self::validate($ExigirSolicitud);

        $fechaHoraHasta = strtotime(date_format((new \DateTime($visitante->solicitud->FechaHoraHasta)), 'Y-m-d'));
        $fechaHoraNow = strtotime(date_format((new \DateTime()), 'Y-m-d'));

		if ($fechaHoraHasta < $fechaHoraNow) {
            throw new \Exception('La solicitud se encuentra vencida');
        }

        switch ($visitante->Estado) {
            case self::ESTADO_APROBADO: throw new \Exception('El visitante ya fue aprobado');
            case self::ESTADO_AUTORIZADO: throw new \Exception('El visitante ya fue autorizado');
            case self::ESTADO_RECHAZADO: throw new \Exception('El visitante fue rechazado');
            default:
                if ($visitante->Estado !== self::ESTADO_SOLICITADO)
                    throw new \Exception('Sólo puede autorizar visitantes con estado Solicitado');
        }
    }

    public static function validateRechazar($visitante, $comentariosRechazo) {
        $ExigirSolicitud = $visitante['Solicitud'];
        $ExigirSolicitud['Documento'] = $visitante['Documento'];
        
        self::validate($ExigirSolicitud);

        if ($visitante['Estado'] !== self::ESTADO_SOLICITADO) {
            throw new \Exception('Sólo puede rechazar visitantes con estado Solicitado');
        }

        if (empty($comentariosRechazo)) {
            throw new \Exception('El campo `comentariosRechazo` es requerido');
        }
    }

    public static function validateCerrar($visitante) {
        $ExigirSolicitud = $visitante['Solicitud'];
        $ExigirSolicitud['Documento'] = $visitante['Documento'];
        self::validate($ExigirSolicitud);
    }

    public static function validateNotificar($visitante, $tipo) {
        $ExigirSolicitud = $visitante['Solicitud'];
        $ExigirSolicitud['Documento'] = $visitante['Documento'];
        
        self::validate($ExigirSolicitud);

        $fechaHoraHasta = strtotime(date_format((new \DateTime($visitante->FechaHoraHasta)), 'Y-m-d'));
        $fechaHoraNow = strtotime(date_format((new \DateTime()), 'Y-m-d'));

        if ($fechaHoraHasta < $fechaHoraNow) {
            throw new \Exception('La solicitud se encuentra vencida');
        }


        if (!in_array($tipo, ['presencial', 'distancia'])) {
            throw new \Exception('Indique un tipo de notificación correcto.');
        }

        switch ($visitante->Estado) {
            case self::ESTADO_AUTORIZADO:
            case self::ESTADO_NOTIFICADO_PRESENCIAL:
            case self::ESTADO_NOTIFICADO_DISTANCIA:
                break;
            default:
                throw new \Exception('Sólo los visitantes autorizados pueden ser notificados');
        }
    }

    public static function validateAprobar($visitante) {
        $ExigirSolicitud = $visitante['Solicitud'];
        $ExigirSolicitud['Documento'] = $visitante['Documento'];
        
        self::validate($ExigirSolicitud);

        $fechaHoraHasta = strtotime(date_format((new \DateTime($visitante->FechaHoraHasta)), 'Y-m-d'));
        $fechaHoraNow = strtotime(date_format((new \DateTime()), 'Y-m-d'));

        if ($fechaHoraHasta < $fechaHoraNow) {
            throw new \Exception('La solicitud se encuentra vencida');
        }
        
        switch ($visitante->Estado) {
            case self::ESTADO_AUTORIZADO:
            case self::ESTADO_NOTIFICADO_PRESENCIAL:
            case self::ESTADO_NOTIFICADO_DISTANCIA:
                break;
            case self::ESTADO_APROBADO:
                throw new \Exception('El visitante se encuentra aprobado');
            default:
                throw new \Exception('Sólo los visitantes autorizados pueden ser aprobados');
        }
    }
}