<?php

namespace App\Models\PCAR;

class Autorizante extends \App\Models\BaseModel
{
	protected $table = 'PCAR_Autorizantes';
	protected $primaryKey = null;

	protected $casts = [
		'IdUsuarioAutorizante' => 'string',
		'IdArea' => 'integer',
	];
	
    protected $fillable = [
		'IdUsuarioAutorizante',
        'IdArea'
	];

	public $incrementing = false;

	public function getName(): string
	{
		return sprintf('%s (%s)', $this->IdUsuarioAutorizante, $this->IdArea);
	}

	public function getKeyAlt(): ?string
	{
		return implode('-', [$this->IdUsuarioAutorizante, $this->IdArea]);
	}
}
