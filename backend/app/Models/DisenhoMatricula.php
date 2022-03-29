<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DisenhoMatricula extends BaseModel
{
	protected $table = 'DisenhoMatricula';
	
	protected $primaryKey = 'IdDisenho';

	protected $casts = [
		'IdDisenho' => 'integer',
	];
	
	protected $fillable = [
		'Nombre',
	];
	
	public $incrementing = false;

	public static function loadDisenhoEnBase64($id) {
		$entity = DisenhoMatricula::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('No hay un diseño específico para la categoría');
        }

        $path = storage_path('app/disenhoMatriculaIMG/' . $entity->RutaFondo);
        
        $dataFile = file_get_contents($path);
        
        return 'data:image/png;base64,'.base64_encode($dataFile);
	}

	public static function getPathIMG($id)
	{
		$entity = DisenhoMatricula::find($id);

        if (!isset($entity)) {
            throw new NotFoundHttpException('No hay un diseño específico para la categoría');
        }
		return storage_path('app/disenhoMatriculaIMG/' . $entity->RutaFondo);
	}

	public static function loadElementsDisenho($id) {
		// Ver posibilidad de en  vez de este metodo usar una Relation de 1 a N
		$elementDisenhoMatricula = DB::table('DisenhoMatriculaElemento', 'dme')
			->select(['dme.*'])
			->join(DB::raw('DisenhoMatricula dm'), function ($join) use ($id) {
				$join->on('dme.IdDisenho', '=','dm.IdDisenho')
				->where(DB::raw('dm.IdDisenho'), $id);
			})->get();
		return $elementDisenhoMatricula->toArray();
	}
}