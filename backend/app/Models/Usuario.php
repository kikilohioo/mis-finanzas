<?php

namespace App\Models;

use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Usuario extends BaseModel
{
    use HasFactory;

    protected $table = 'Usuarios';
    protected $primaryKey = 'IdUsuario';
    protected $keyType = 'varchar';
    protected $hidden = [
        'Contrasenia',
    ];
    protected $fillable = [
        'IdUsuario', 
        'Nombre', 
		'Email', 
		'Estado', 
		'Gestion', 
		'ApruebaVisitas',
		'SoloLectura',
		'Administrador',
		'NuevosContratos',
		'EnTransitos',
		'UltimaEmpresaDocumento',
		'UltimaEmpresaIdTipoDocumento',
		'PTC',
		'EsContratista',
		'EsTercerizado',
		'PTCRol',
		'PTCGestion',
		'PTCAdministrador',
		'RecibeNotificaciones',
		'LDAP',
		'EstadoObservacion',
	];
    protected $casts = [
        'Estado' => 'boolean',
        'Administrador' => 'boolean',
        'Baja' => 'boolean',
        'EnTransitos' => 'boolean',
        'SoloLectura' => 'boolean',
        'Gestion' => 'boolean',
        'EsContratista' => 'boolean',
        'EsTercerizado' => 'boolean',
        'PTC' => 'boolean',
        'PTCGestion' => 'boolean',
        'PTCAdministrador' => 'boolean',
        'ApruebaVisitas' => 'boolean',
        'RecibeNotificaciones' => 'boolean',
        'LDAP' => 'boolean',
        'EsContratista' => 'boolean',
        'EsTercerizado' => 'boolean',
    ];
    public $incrementing = false;

    /**
     * Autentica un usuario por medio de su nombre de usuario
     * y su contraseña.
     *
     * @param string $username
     * @param string|null $password
     * @return self|null
     */
    public static function auth($username, $password)
    {
        if (is_numeric($username)) {
            $tmpUsername = DB::selectOne('SELECT upf.IdUsuario FROM UsuariosPersonasFisicas upf INNER JOIN PersonasFisicas pf ON pf.Documento = upf.Documento AND pf.IdTipoDocumento = upf.IdTipoDocumento WHERE pf.Matricula = ?', [$username]);
            Log::info(sprintf('Intentando iniciar sesión con una matricula (%s)', $username));
            if (isset($tmpUsername)) {
                $username = $tmpUsername->IdUsuario;
                Log::info(sprintf('Usuario %s encontrado a través de matricula', $username));
            }
        }
        /**
         * @var self $user
         */
        $user = self::with(['Funciones'])->where('IdUsuario', $username)->first();
        if (!isset($user)) {
            Log::info(sprintf('El usuario "%s" no se encuentra', $username));
            // throw new HttpException(401, 'Usuario o contraseña incorrectos'); /// Username not found
            return null;
        }
        if (!$user->isActive()) {
            Log::info(sprintf('El usuario "%s" se encuentra desactivado', $username));
            // throw new HttpException(401, 'Usuario o contraseña incorrectos'); /// User is disabled
            return null;
        }
        // if (!Hash::check($password, $user->Contrasenia)) {
        //     throw new HttpException(401, 'Usuario o contraseña incorrectos'); /// Password incorrect
        // }
        if (!isset($tmpUsername)) {
            if (md5($password) !== $user->Contrasenia) {
                Log::info(sprintf('La contraseña del usuario "%s" es incorrecta', $username));
                // throw new HttpException(401, 'Usuario o contraseña incorrectos'); /// Password incorrect
                return null;
            }
        }
        return $user;
    }

    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    public function isActive(): bool
    {
        return $this->Estado && !$this->Baja;
    }

    public function isAdmin(): bool
    {
        return $this->Administrador;
    }

    public function isGestion(): bool
    {
        return !empty($this->Gestion);
    }

    public function Funciones()
    {
        return $this->belongsToMany(Funcion::class, 'UsuariosFunciones', 'IdUsuario', 'IdFuncion');
    }

    //Metodos para PTC
    public function PTCRoles()
    {
        return $this->belongsToMany(\App\Models\PTC\Rol::class, 'PTCRolesUsuarios', 'IdUsuario', 'Codigo');
    }

    public function usuarioEsSolicitante() {
        return $this->usuarioTieneRol("SOL");
    }

    public function usuarioEsOperador() {
        return $this->usuarioTieneRol("OPR");
    }

    public function usuarioEsAprobador() {
        return $this->usuarioTieneRol("APR");
    }

    public function usuarioEsSYSO() {
        return $this->usuarioTieneRol("SSO");
    }

    public function usuarioEsEjecutante() {
        return true;
    }

    // Tiene SÓLO el rol buscado
    public function usuarioEsSoloSolicitante() {
        return $this->usuarioTieneSoloRol("SOL");
    }

    public function usuarioEsSoloOperador() {
        return $this->usuarioTieneSoloRol("OPR");
    }

    public function usuarioEsSoloAprobador() {
        return $this->usuarioTieneSoloRol("APR");
    }

    public function usuarioEsSoloSYSO() {
        return $this->usuarioTieneSoloRol("SSO");
    }

    public function usuarioEsSoloEjecutante() {
        return $this->usuarioTieneSoloRol("EJE");
    }

    public function usuarioTieneRol($rol) {
        $roles = $this->PTCRoles()->get();

        foreach ($roles as $r) {
            if (!empty($r->pivot->Codigo) && $r->pivot->Codigo == $rol) {
                return true;
            }
        }
        return false;
    }

    public function usuarioTieneSoloRol($rol) {
        $roles = $this->PTCRoles()->get();

        return $this->usuarioTieneRol($rol) && count($roles) == 1;
    }

    public function usuarioSinRol() {
        return count($this->PTCRoles()->get()) == 0;
    }
}