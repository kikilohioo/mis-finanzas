<?php

namespace App\Models;

use App\Events\LogAuditoriaEvent;
use App\FsUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BaseModel extends Model
{
    public $timestamps = false;
    public $auditar = true;
    public static $snakeAttributes = false;
    protected $casts = [
        'Estado' => 'boolean',
        'Baja' => 'boolean'
    ];
    protected $dispatchesEvents = [
        'saved' => LogAuditoriaEvent::class,
    ];
    protected $fillable = ['Baja'];
    protected $hidden = ['rowguid'];

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * Obtiene el nombre de la entidad.
     *
     * @return string
     */
    public function getName(): string
    {
        $entity = $this->toArray();
        if (array_key_exists('Nombre', $entity)) {
            return $entity['Nombre'];
        } else if (array_key_exists('Descripcion', $entity)) {
            return $entity['Descripcion'];
        }
        $key = $this->getKey();
        if (is_string($key)) {
            return $key;
        }
        throw new \Exception('Debe sobreescribir el método `getName` de su modelo.');
    }

    public function getKeyAlt(): ?string
	{
		return $this->getKey();
	}

    public function isActive(): bool
    {
        return true;
    }

    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.v';
    }

    /**
     * Comprueba si el array `$Args` contiene los argumentos esperados.
     * @param array|object $Args
     * @param array $ArgsEsperados
     * @return void
     * @throws HttpException En caso de que uno o más argumentos no se encuentren.
     */
    public static function exigirArgs($Args, array $ArgsEsperados): void
    {
        if (!is_array($Args)) {
            $Args = FsUtils::classToArray($Args);
        }
        foreach ($ArgsEsperados as $ArgEsperado) {
            if (is_string($ArgEsperado)) {
                if (!isset($Args[$ArgEsperado]) || $Args[$ArgEsperado] === '' ) {
                    throw new HttpException(400, 'Campo ' . $ArgEsperado . ' no encontrado');
                }
            } else if (is_array($ArgEsperado)) {
                $allEmpty = true;
                foreach ($ArgEsperado as $ArgOpcional) {
                    $allEmpty = $allEmpty && !isset($Args[$ArgOpcional]);
                }
                if ($allEmpty) {
                    throw new HttpException(400, 'Campos ' . implode(', ', $ArgEsperado) . ' no encontrados');
                }
            }
        }
    }

    public static function getNextId(?string $field = null, ?array $where = null): int
    {
        if (!isset($field)) {
            $temp = new static;
            $field = $temp->getKeyName();
        }
        $query = static::query();

        if (isset($where)) {
            $query->where($where);
        }

        $max = $query->max($field);

        if (!isset($max) || empty($max)) {
            $max = 0;
        }

        return $max + 1;
    }

    public static function getNextBadgeID($parameter = '')
    {
        $stm = 'SELECT MAX(Matricula) AS Matricula FROM (SELECT MAX(pf.Matricula) AS Matricula FROM PersonasFisicas pf INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento WHERE p.Baja = 0 UNION ALL SELECT MAX(m.Matricula) AS Matricula FROM Maquinas m WHERE m.Baja = 0 UNION ALL SELECT MAX(v.Matricula) AS Matricula FROM Vehiculos v WHERE v.Baja = 0 UNION ALL SELECT pr.Valor AS Matricula FROM Parametros pr WHERE pr.IdParametro = :parameter) AS mm';
        $next = DB::selectOne($stm, ['parameter' => $parameter]);
        if (!isset($next) || empty($next->Matricula) || !is_numeric($next->Matricula)) {
            throw new \Exception('No se ha podido encontrar el mayor número de matricula');
        }
        return $next->Matricula + 1;
    }
    public static function getNextBetweenBadgeID($p = '')
    {
        $pMin = $p . 'MatriculaMin';
        $pMax = $p . 'MatriculaMax';
        $stm = 'SELECT MAX(Matricula) AS Matricula FROM (SELECT MAX(pf.Matricula) AS Matricula FROM PersonasFisicas pf INNER JOIN Personas p ON p.Documento = pf.Documento AND p.IdTipoDocumento = pf.IdTipoDocumento WHERE p.Baja = 0 AND Matricula < (SELECT pr.Valor FROM Parametros pr WHERE pr.IdParametro = ?) UNION ALL SELECT MAX(m.Matricula) AS Matricula FROM Maquinas m WHERE m.Baja = 0 AND Matricula < (SELECT pr.Valor FROM Parametros pr WHERE pr.IdParametro = ?) UNION ALL SELECT MAX(v.Matricula) AS Matricula FROM Vehiculos v WHERE v.Baja = 0 AND Matricula < (SELECT pr.Valor FROM Parametros pr WHERE pr.IdParametro = ?) UNION ALL SELECT pr.Valor AS Matricula FROM Parametros pr WHERE pr.IdParametro = ?) AS mm';
        $next = DB::selectOne($stm, [$pMax, $pMax, $pMax, $pMin]);
        if (!isset($next) || empty($next->Matricula) || !is_numeric($next->Matricula)) {
            throw new \Exception('No se ha podido encontrar el mayor número de matricula');
        }
        return $next->Matricula + 1;
    }

    public static function getAttrSuffix(): string
    {
        $class = new static;
        if ($class instanceof Visitante || $class instanceof TipoDocumentoVis) {
            return 'Vis'; // Vis ó PF
        } else if ($class instanceof PersonaFisica || $class instanceof TipoDocumentoPF) {
            return 'PF';
        } else if ($class instanceof Empresa || $class instanceof TipoDocumentoEmp) {
            return 'Emp';
        } else if ($class instanceof Vehiculo || $class instanceof TipoDocumentoVehic) {
            return 'Vehic';
        } else if ($class instanceof Maquina || $class instanceof TipoDocumentoMaq) {
            return 'Maq';
        } else if ($class instanceof Documento) {
            return '';
        }
        throw new \UnexpectedValueException('La clase ' . get_called_class() . ' no tiene sufijo definido');
    }

    public static function getTablePreffix(): string
    {
        $class = new static;
        if ($class instanceof PersonaFisica || $class instanceof TipoDocumentoPF) {
            return 'PersonasFisicas';
        } else if ($class instanceof Visitante || $class instanceof TipoDocumentoVis) {
            return 'PersonasFisicas';
        } else if ($class instanceof Empresa || $class instanceof TipoDocumentoEmp) {
            return 'Empresas';
        } else if ($class instanceof Vehiculo || $class instanceof TipoDocumentoVehic) {
            return 'Vehiculos';
        } else if ($class instanceof Maquina || $class instanceof TipoDocumentoMaq) {
            return 'Maquinas';
        }
        throw new \UnexpectedValueException('La clase ' . get_called_class() . ' no tiene prefijo de tabla definido');
    }

    public function scopeNoLock($query)
    {
        return $query->from(DB::raw(self::getTable() . ' with (nolock)'));
    }
}
