<?php

namespace App\Models\PCAR;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Solicitud extends \App\Models\BaseModel
{
    const ESTADO_NUEVA = 'I';
    const ESTADO_APROBADA = 'A';
    const ESTADO_AUTORIZADA = 'Z';
    const ESTADO_RECHAZADA = 'R';

	protected $table = 'PCAR_Solicitudes';
	protected $primaryKey = 'Id';

	protected $casts = [
		'Id' => 'integer',
		'IdArea' => 'integer',
	];
	
    protected $fillable = [
        'Matricula',
        'Empresa',
        'PersonaContacto',
        'EmailContacto',
        'TelefonoContacto'
	];

    public function Area() {
        return $this->hasOne('App\Models\PCAR\Area', 'Id');
    }

    public function Usuario() {
        return $this->hasOne('App\Models\Usuario', 'IdUsuario', 'IdUsuario');
    }

    public function UsuarioAutorizante() {
        return $this->hasOne('App\Models\Usuario', 'IdUsuario', 'IdUsuarioAutorizante');
    }

    public function UsuarioAprobador() {
        return $this->hasOne('App\Models\Usuario', 'IdUsuario', 'IdUsuarioAprobador');
    }

    public static function validateAprobar($solicitud) {
        if ($solicitud->Estado == Solicitud::ESTADO_APROBADA) {
            throw new ConflictHttpException('La solicitud ya fue aprobada por el usuario ' . $solicitud->IdUsuarioAprobador);
        }
        else if ($solicitud->Estado != Solicitud::ESTADO_AUTORIZADA) {
            throw new ConflictHttpException('La solicitud no está pendiente de aprobación');
        }
    }

    public static function validateAutorizar($solicitud) {
        if ($solicitud->Estado == Solicitud::ESTADO_APROBADA) {
            throw new ConflictHttpException('La solicitud ya fue aprobada');
        }
        else if ($solicitud->Estado == Solicitud::ESTADO_AUTORIZADA) {
            throw new ConflictHttpException('La solicitud ya fue autorizada');
        }
        else if ($solicitud->Estado == Solicitud::ESTADO_RECHAZADA) {
            throw new ConflictHttpException('La solicitud fue rechazada');
        }
    }

    public static function validateRechazar($solicitud) {
        if ($solicitud->Estado == Solicitud::ESTADO_APROBADA) {
            throw new ConflictHttpException('No se puede rechazar la solicitud porque se encuentra aprobada');
        }
        else if (!in_array($solicitud->Estado, [Solicitud::ESTADO_NUEVA, Solicitud::ESTADO_AUTORIZADA])) {
            throw new ConflictHttpException('La solicitud no está pendiente de autorización o aprobación');
        }

        if (empty($solicitud->ComentariosAprobador)) {
            throw new ConflictHttpException('El campo ComentariosAprobador es requerido');
        }
    }

	public function getName(): string
	{
		return sprintf('%s (%s)', $this->Id, $this->IdArea);
	}

	public function getKeyAlt(): ?string
	{
		return implode('-', [$this->Id, $this->IdArea]);
	}
}
