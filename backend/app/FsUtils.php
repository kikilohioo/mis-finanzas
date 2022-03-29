<?php

namespace App;

use Carbon\Carbon;
use \DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Pagination\Paginator;

class FsUtils
{
    public const YYMMDD = 'Y-m-d';
    public const YYMMDDHHMMSS = 'Y-m-d H:i:s';
    public const YYMMDDHHMMSSV = 'Y-m-d H:i:s.v';
    public const DDMMYY = 'd/m/Y';
    public const DDMMYYHHMMSS = 'd/m/Y H:i:s';
    public const DDMMYYHHMM = 'd/m/Y H:i';
    public const HHMMSS = 'H:i:s';
    public const HHMM = 'H:i';
    public const YYMMDD_PATTERN = '/(?<year>[\d]{4})-(?<month>[\d]{2})-(?<day>[\d]{2})/';
    public const YYMMDDHHMMSS_PATTERN = '/(?<year>[\d]{4})-(?<month>[\d]{2})-(?<day>[\d]{2})\s(?<time>.*)/';
    public const YYMMDDHHMMSSV_PATTERN = '/(?<year>[\d]{4})-(?<month>[\d]{2})-(?<day>[\d]{2})\s(?<time>.*)\.(?<millis>.*)/';
    public const DDMMYY_PATTERN = '/(?<day>[\d]{2})\/(?<month>[\d]{2})\/(?<year>[\d]{4})/';
    public const DDMMYYHHMMSS_PATTERN = '/(?<day>[\d]{2})\/(?<month>[\d]{2})\/(?<year>[\d]{4})\s(?<time>.*)/';
    public const DDMMYYHHMM_PATTERN = '/(?<day>[\d]{2})\/(?<month>[\d]{2})\/(?<year>[\d]{4})\s(?<time>[\d\:]{5})/';
    
    /**
     * Summary of paginateArray
     * @param array $array
     * @param Request $request
     * @return LengthAwarePaginator|Paginator
     */
    public static function paginateArray(array $array, Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = (int)$request->input('pageSize', 50);
        $offset = ($page * $perPage) - $perPage;
        if ($perPage === -1) {
            $offset = 0;
            $perPage = count($array) > 0 ? count($array) : 1;
        }
        
        return new LengthAwarePaginator(
            array_slice($array, $offset, $perPage, false),
            count($array),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
    
    public static function classToArray(object $object): array
    {
        return json_decode(json_encode($object), true);
    }
    
    public static function arrayToClass(array $array): object
    {
        return json_decode(json_encode($array));
    }
    
    public static function explodeId(string $id): array
    {
        return array_reverse(array_map('strrev', explode('-', strrev($id), 2)));
    }

    public static function unmask(string $str): string
    {
        return trim(str_replace(array('.', ',', ':', ';', '-', '/', '\\'), '', $str));
    }

    public static function fromHumanDate(string $date): Carbon
    {
        return self::strToDate($date, self::DDMMYY);
    }

    public static function fromHumanDatetime(string $date): Carbon
    {
        return self::strToDate($date, self::DDMMYYHHMMSS);
    }

    public static function toHumanDate(DateTime $date): string
    {
        return $date->format(self::DDMMYY);
    }

    public static function toHumanDatetime(DateTime $date): string
    {
        return $date->format(self::DDMMYYHHMMSS);
    }

    /**
     * Convierte a fecha una cadena de texto.
     * 
     * @param string|null $string
     * @param string|null $format
     * @param string|null $timezone
     * 
     * @return Carbon|null
     */
    public static function strToDate(?string $string = 'now', string $format = self::YYMMDDHHMMSS, ?string $timezone = null): ?Carbon
    {
        if (!isset($string)) {
            return null;
        }

        if (empty($string) || strtolower($string) === 'now') {
            return new Carbon;
        }

        $datetime = Carbon::createFromFormat($format, str_replace('"', '', $string));
        $datetime->tz($timezone ?: env('APP_TIMEZONE', 'UTC'));

        return new Carbon($datetime);
    }

    /**
     * Convierte a fecha una cadena de texto a partir de su patrón.
     * 
     * @param string|null $string
     * @param string|null $timezone
     * 
     * @return Carbon|null
     * 
     * @throws Exception En caso de que el formato de la fecha
     * no se encuentre dentro de los patrones disponibles.
     */
    public static function strToDateByPattern(?string $string = 'now', ?string $timezone = null): ?Carbon
    {
        if (!isset($string)) {
            return null;
        }

        if (empty($string) || strtolower($string) === 'now') {
            return new Carbon;
        }
        
        $format = null;

        $formats = [
            self::DDMMYYHHMM_PATTERN => self::DDMMYYHHMM,
            self::YYMMDDHHMMSSV_PATTERN => self::YYMMDDHHMMSSV,
            self::YYMMDDHHMMSS_PATTERN => self::YYMMDDHHMMSS,
            self::DDMMYYHHMMSS_PATTERN => self::DDMMYYHHMMSS,
            self::YYMMDD_PATTERN => self::YYMMDD,
            self::DDMMYY_PATTERN => self::DDMMYY,
        ];

        foreach ($formats as $pattern => $value) {
            if (!isset($format) && preg_match($pattern, $string) === 1) {
                $format = $value;
            }
        }
        
        if (!isset($format)) {
            throw new Exception('Formato de fecha no soportado para ' . $string);
        }

        $datetime = Carbon::createFromFormat($format, str_replace('"', '', $string));
        $datetime->tz($timezone ?: env('APP_TIMEZONE', 'UTC'));
        
        return new Carbon($datetime);
    }

    /**
     * Restablece la hora de una fecha a las 00:00:00.
     * 
     * @param string|Carbon|null $date
     * @param string|null $format
     * 
     * @return Carbon
     */
    public static function startDay($date = '', string $format = null)
    {
        $datetime = new Carbon;
        if (is_string($date)) {
            $datetime = self::strToDate($date, $format);
        }
        return $datetime->setHour(0)->setMinute(0)->setSecond(0)->setMillisecond(0);
    }

    /**
     * Restablece la hora de una fecha a las 23:59:59.
     * 
     * @param string|Carbon|null $date
     * @param string|null $format
     * 
     * @return Carbon
     */
    public static function endDay($date = '', string $format = null)
    {
        $datetime = new Carbon;
        if (is_string($date)) {
            $datetime = self::strToDate($date, $format);
        }
        return $datetime->setHour(23)->setMinute(59)->setSecond(59)->setMillisecond(999);
    }

    /**
     * Convierte una fecha en formato humano (DD/MM/YYYY).
     * 
     * @param \DateTime $datatime
     * 
     * @return string 
     */
    public static function humanDate(DateTime $datetime): string
    {
        return $datetime->format(self::DDMMYY);
    }

    /**
     * Convierte una fecha y hora en formato humano (DD/MM/YYYY HH24:MI:SS).
     * 
     * @param \DateTime $datatime
     * 
     * @return string 
     */
    public static function humanDatetime(DateTime $datetime): string
    {
        return $datetime->format(self::DDMMYYHHMMSS);
    }

    
    public static function getBaseUrlByInstance(bool $isPTC): string
    {
        $FS_INSTANCE = env('FS_INSTANCE');
        $FS_INSTANCE_FSA = env('FS_INSTANCE_FSA');
        $FS_INSTANCE_PTC = env('FS_INSTANCE_PTC');
        if (isset($FS_INSTANCE)) {
            if ($isPTC && isset($FS_INSTANCE_PTC) && $FS_INSTANCE_PTC !== false) {
                return $FS_INSTANCE_PTC;
            } else if (!$isPTC && isset($FS_INSTANCE_FSA) && $FS_INSTANCE_FSA !== false) {
                return $FS_INSTANCE_FSA;
            }
        } 
        
        throw new \Exception('URL no definida');
    }

    /**
     * Devuelve una array con la clausula `where` y las asignaciones.
     * @param object $filter
     * @param string $table
     * @param array $excluded
     * @return array
     * @deprecated No debería usarse éste método para nuevas implementaciones.
     */
    public static function whereFromArgs(object $filter, string $table = '', array $excluded = []): array
    {
        $sql = [];
        $binding = [];
        $preffix = ':FS_';

        if (isset($filter)) {
            foreach ($filter as $key => $value) {
                if (!in_array($key, $excluded)) {
                    switch ($key) {
                        case '_empty_':
                        case 'Busqueda':
                        case 'CustomCheckbox':
                        case 'CustomFilter':
                            break;

                        case 'IdEmpresa':
                        case 'IdPersonaFisica':
                        case 'IdPersonaFisicaTransac':
                            $id = self::explodeId($value);
                            $nameDocumento = $table . 'Documento';
                            $nameIdTipoDocumento = $table . 'IdTipoDocumento';
                            $sql[] = $nameDocumento . ' = ' . str_replace('.', '_', $preffix . $nameDocumento) . ' AND ' . $nameIdTipoDocumento . ' = ' . str_replace('.', '_', $preffix . $nameIdTipoDocumento);
                            $binding[str_replace('.', '_', $preffix . $nameDocumento)] = $id[0];
                            $binding[str_replace('.', '_', $preffix . $nameIdTipoDocumento)] = $id[1];
                            break;

                        default:
                            $nameKey = $table . $key;
                            $sql[] = $nameKey . ' = ' . str_replace('.', '_', $preffix . $nameKey);
                            $binding[str_replace('.', '_', $preffix . $nameKey)] = $value;
                            break;
                    }
                }
            }
        }

        return [implode(' AND ', $sql), $binding];
    }

    public static function castProperties(?object $object, array $casts): ?object
    {
        if (!isset($object)) {
            return null;
        }

        foreach ($object as $key => $value) {
            if (isset($value) && array_key_exists($key, $casts)) {
                $cast = $casts[$key];
                if (is_array($cast)) {
                    if (isset($object->{$key})) {
                        if (is_array($object->{$key})) {
                            foreach ($object->{$key} as $index => $element) {
                                $object->{$key}[$index] = self::castProperties($element, $cast);
                            }
                        }
                    }
                } else {
                    $format = null;
                    $formatTo = null;
                    $explode = explode(':', $cast);
                    if (count($explode) === 2) {
                        list($cast, $formatTo) = $explode;
                    } else if (count($explode) === 3) {
                        list($cast, $format, $formatTo) = $explode;
                    }

                    if (in_array($cast, ['date', 'datetime'])) {
                        if (!isset($format)) {
                            if (strlen($value) === 10) {
                                $format = self::YYMMDD;
                            } else if (strlen($value) === 19) {
                                $format = self::YYMMDDHHMMSS;
                            } else if (strlen($value) === 23) {
                                $format = self::YYMMDDHHMMSSV;
                            }
                        }
                    }

                    switch ($cast) {
                        case 'boolean':
                            $object->{$key} = (bool)$value;
                            break;

                        case 'int':
                        case 'integer':
                            $object->{$key} = (int)$value;
                            break;

                        case 'date':
                            $object->{$key} = Carbon::createFromFormat($format ?: self::YYMMDD, $value)->format($formatTo ?: self::DDMMYY);
                            break;

                        case 'datetime':
                            $object->{$key} = Carbon::createFromFormat($format ?: self::YYMMDDHHMMSSV, $value)->format($formatTo ?: self::DDMMYYHHMMSS);
                            break;
                    }
                }
            }
        }
        return $object;
    }

    /**
     * Obtener parámetros desde la base de datos.
     * @param string|null $key
     * @param bool $throw
     * @return array|string|null
     * @throws Exception En caso de no existir el párametro lanzar una excepción si es requerida.
     */
    public static function getParams(?string $key = null, bool $throw = false)
    {
        if (!isset($key)) {
            return DB::select('SELECT * FROM Parametros');
        }

        $value = null;
        $result = DB::selectOne('SELECT Valor FROM Parametros WHERE IdParametro = ?', [$key]);
        
        if (isset($result->Valor)) {
            $value = $result->Valor;
        }
        if ($throw && !isset($value)) {
            throw new Exception(sprintf('El parámetros "%s" no se encuentra en la base de datos'), $key);
        }

        return $value;
    }

    /**
     * Funcion de utilidad para exportar listados de datos en diferentes formatos.
     * 
     * @param string $type
     * @param array $data
     * @param array|null $columns
     * @param string|null $filename
     * 
     * @return null|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public static function export(string $type, array $data, $columns = null, string $filename = null)
    {
        $output = [];
        if (!isset($filename)) {
            $filename = 'fsacceso-export-' . date('Ymd-his');
        }
        foreach ($data as $item) {
            if (is_object($item)) {
                $item = (array)$item;
            }
            $tmp = [];
            foreach ($columns as $hKey => $hValue) {
                $key = is_numeric($hKey) ? $hValue : $hKey;
                $tmp[$hValue] = array_key_exists($key, $item) ? $item[$key] : '';
                if ($tmp[$hValue] === true) {
                    $tmp[$hValue] = 'Sí';
                } else if ($tmp[$hValue] === false) {
                    $tmp[$hValue] = 'No';
                }
            }
            $output[] = $tmp;
        }
        switch ($type) {
            case 'xls':
                return (new FastExcel(collect($output)))->download($filename . '.xlsx');
            case 'csv':
                return self::exportCSV($output, $columns, $filename);
            // case 'pdf':
            //     return self::exportPDF($output, $columns, $filename);
        }

        return null;
    }

    /**
     * Exportar listado como CSV.
     * 
     * @param array $data
     * @param array|null $columns
     * @param string $filename
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public static function exportCSV(array $data, $columns = null, string $filename)
    {
        $headers = [
            'Content-Type' => 'text-csv',
            'Content-Disposition' => 'attachment; filename=' . $filename . '.csv',
            'Program' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    
    public static function datetime_is_valid_time($str) {
        $t = explode(":", $str);
        
        if (count($t) == 2) { 
            $h = (int) $t[0];
            $m = (int) $t[1];
            return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59;
        }
        
        return false;
    }

    public static function datetime($date = null, $format = self::YYMMDDHHMMSS) {
        if (empty($date)) {
            $date = new DateTime();
        } 
        else if (is_string($date)) {
            self::date_check($date, $format);
            return DateTime::createFromFormat($format, $date);
        }
        
        if ($date instanceof DateTime) {
            return $date;
        }
    }

    public static function date_check($date, $format) {  
        $d = DateTime::createFromFormat($format, $date);
        if (!($d && $d->format($format) === $date)) {
            throw new HttpException(409, $date . " no es una fecha válida");
        }
    }

    public static function datetime_diff($date1, $date2, $datepart) {
        $date1 = self::datetime($date1);
        $date2 = self::datetime($date2);
        $interval = date_diff($date1, $date2);
        return $interval->format($datepart);
    }

    public static function datetime_add($date, $interval) {
        return date_add($date, date_interval_create_from_date_string($interval));
    }

}