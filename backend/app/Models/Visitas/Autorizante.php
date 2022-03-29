<?php

namespace App\Models\Visitas;

use Illuminate\Support\Facades\DB;

class Autorizante extends \App\Models\BaseModel
{
	protected $table = 'Visitas_Autorizantes';
	protected $primaryKey = null;
	protected $casts = [
		'IdUsuarioAutorizante' => 'string',
        'IdArea' => 'integer',
        'AutorizaExcepciones' => 'boolean',
        'AutorizaVisitas' => 'boolean',
        'RecibeNotificaciones' => 'boolean',
	];
	protected $fillable = [
		'IdArea',
        'IdUsuarioAutorizante',
        'AutorizaExcepciones',
		'AutorizaVisitas',
        'RecibeNotificaciones',
	];
	public $incrementing = false;


	public static function getAll($Arrwhere = [])
	{
		$query = Autorizante::select([
			'Visitas_Autorizantes.*',
			'Visitas_Areas.Nombre AS Area',
			'Usuarios.Nombre',
			'Usuarios.Email',
			'Usuarios.PTC',
		]);
		$query->join('Visitas_Areas', 'Visitas_Autorizantes.IdArea', '=','Visitas_Areas.Id');
		$query->join('Usuarios', 'Visitas_Autorizantes.IdUsuarioAutorizante', '=','Usuarios.IdUsuario');

		foreach ($Arrwhere as $key => $value) {
			$query->where($key, $value);
		}

		return $query->get();		
	}

    public function getName(): string
	{
		return sprintf('%s (%s)', $this->IdUsuarioAutorizante, $this->IdArea);
	}

    public function getKeyAlt(): ?string
	{
		return implode('-', [$this->IdUsuarioAutorizante, $this->IdArea]);
	}
}