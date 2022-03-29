<?php

namespace App\Models;

class MarcaMaquina extends BaseModel
{
	protected $table = 'MarcasMaquinas';
	
	protected $primaryKey = 'IdMarcaMaq';
	
	protected $casts = [
		'IdMarcaMaq' => 'integer',
	];
	
	protected $fillable = [
		'Descripcion',
	];
	
	public $incrementing = false;
}