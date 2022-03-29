<?php

namespace App\Models;

use Diff\Differ\MapDiffer;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;

class LogAuditoria extends BaseModel
{
    public const FSA_USER_DEFAULT = 'fsa';
    public const FSA_MODULE_DEFAULT = 'FSAccesoLaravel';
    public const FSA_METHOD_LOGIN = 'login';
    public const FSA_METHOD_CREATE = 'alta';
    public const FSA_METHOD_UPDATE = 'modificacion';
    public const FSA_METHOD_DELETE = 'baja';
    public const FSA_METHOD_ACTIVATE = 'activar';
    public const FSA_METHOD_DESACTIVATE = 'desactivar';
    public const FSA_METHOD_APPROVE = 'aprobar';
    public const FSA_METHOD_REJECT = 'rechazar';

    protected $table = 'LogActividades';
    protected $primaryKey = 'Id';
    protected $casts = [
        'Id' => 'integer',
    ];
    protected $hidden = [
        'Token',
    ];
    protected $fillable = [
        'FechaHora',
        'IdUsuario',
        'Modulo',
        'Entidad',
        'Operacion',
        'Observacion',
        'DireccionIP',
        'EntidadId',
        'EntidadDesc',
    ];
    protected $appends = ['Diferencia'];

    /**
     * Registrar un actividad en el log de auditoría
     * @param string $idUsuario
     * @param string $entidad
     * @param string $method
     * @param mixed $observacion
     * @param string|array $entidadId
     * @param string $entidadDesc
     * @param string $modulo
     * @return bool
     */
    public static function log(
        string $idUsuario,
        string $entidad,
        string $method,
        $observacion,
        $entidadId,
        string $entidadDesc,
        string $modulo = self::FSA_MODULE_DEFAULT
    ): bool
    {
        if (is_array($entidadId)) {
            $entidadId = implode('-', $entidadId);
        }

        $entidad = str_replace(['App\\Models\\', '\\'], ['', ':'], $entidad);

        $auditoria = new LogAuditoria([
            'FechaHora' => new \DateTime,
            'IdUsuario' => $idUsuario,
            'Modulo' => $modulo,
            'Entidad' => strtolower($entidad),
            'Operacion' => ucfirst($method),
            'Observacion' => json_encode($observacion),
            'DireccionIP' => app('request')->ip(),
            'EntidadId' => $entidadId,
            'EntidadDesc' => $entidadDesc,
        ]);

        return $auditoria->save();
    }

    public function getDiferenciaAttribute()
    {
        $oldData = isset($this->Anterior) ? $this->Anterior->Observacion : '[]';
        return self::compare(null, json_decode($oldData, true), json_decode($this->Observacion, true));
    }

    public function getAnteriorAttribute(): ?self
    {
        $anterior = self::where('Entidad', $this->Entidad)
            ->where('EntidadId', $this->EntidadId)
            ->where('FechaHora', '<', $this->FechaHora)
            ->latest('FechaHora')
            ->first();

        return $anterior;
    }

    public static function compare(?string $name = null, $oldData, $currentData): array
    {
        if (!is_array($oldData) && !is_object($oldData)) {
            $oldData = [$oldData];
        }
        if (!is_array($currentData) && !is_object($currentData)) {
            $currentData = [$currentData];
        }


        $differ = new MapDiffer();
        $diffs = $differ->doDiff($oldData, $currentData);

        $result = [];

        foreach ($diffs as $key => $diff) {
            $nameAndKey = $key;
            if (isset($name)) {
                if (is_numeric($key)) {
                    $nameAndKey = sprintf('%s[%s]', $name, $key);
                } else {
                    $nameAndKey = implode('.', [$name, $key]);
                }
            }
            
            if ($diff instanceof DiffOpChange) {
                $oldValue = $diff->getOldValue();
                $newValue = $diff->getNewValue();
                if (is_array($oldValue) && is_array($newValue)) {
                    $result[] = new LogAuditoriaItemDiff(LogAuditoriaItemDiff::TYPE_DIFF, $nameAndKey, null, null, self::compare($nameAndKey, $oldValue, $newValue));
                } else if (is_object($oldValue) && is_object($newValue)) {
                    $result[] = new LogAuditoriaItemDiff(LogAuditoriaItemDiff::TYPE_CHANGE, $nameAndKey, $oldValue, $newValue);
                } else {
                    $result[] = new LogAuditoriaItemDiff(LogAuditoriaItemDiff::TYPE_CHANGE, $nameAndKey, $oldValue, $newValue);
                }
            } else if ($diff instanceof DiffOpAdd) {
                $result[] = new LogAuditoriaItemDiff(LogAuditoriaItemDiff::TYPE_ADD, $nameAndKey, null, $diff->getNewValue());
            } else if ($diff instanceof DiffOpRemove) {
                $result[] = new LogAuditoriaItemDiff(LogAuditoriaItemDiff::TYPE_REMOVE, $nameAndKey, $diff->getOldValue());
            }
        }

        return $result;
    }
}

class LogAuditoriaItemDiff
{
    public const TYPE_ADD = 'ADD';
    public const TYPE_CHANGE = 'CHANGE';
    public const TYPE_REMOVE = 'REMOVE';
    public const TYPE_DIFF = 'DIFF';

    public $type;
    public $key;
    public $oldValue;
    public $newValue;
    public $children = [];

    function __construct(string $type, string $key, $oldValue = null, $newValue = null, array $children = [])
    {
        $this->type = $type;
        $this->key = $key;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->children = $children;
    }
}