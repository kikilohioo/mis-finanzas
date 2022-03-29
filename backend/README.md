FSAcceso Backend con Laravel
===

Nueva capa lógica moderna para la plataforma FSAcceso Web oirignal.
Este proyecto no dispone de migraciones ni base de datos, debido a que es un upgrade del proyecto FSAccesoWeb.

## Requerimientos

 - Apache httpd 2.4 o superior.
 - PHP 7.4.
 - [Drivers Microsoft PHP SQL Server](https://github.com/microsoft/msphpsql/releases/latest).
 - Habilitar extensiones de PHP: `soap`.

Por más información, [consultar la documentación de Laravel 8](https://laravel.com/docs/8.x).

## Despligue de aplicación en producción

1. Clonar este repositorio.
2. Instalar dependencias con `composer build`.
3. Crear archivo `.env` tomando como base `.env.example`.
    1. Ejecutar el comando `php artisan key:generate` para generar una nueva clave de aplicación.
    2. Ejecutar el comando `openssl rand -base64 32` para generar una clave para JWT.
4. Tener en cuenta posibles migraciones dentro del directorio `./database/migrations`.

## Preparar ambiente de desarrollo

1. Clonar este repositorio.
2. Instalar dependencias con `composer install`.
3. Crear archivo `.env` tomando como base `.env.example`.
    1. Ejecutar el comando `php artisan key:generate` para generar una nueva clave de aplicación.
    2. Ejecutar el comando `openssl rand -base64 32` para generar una clave para JWT.
4. Tener en cuenta posibles migraciones dentro del directorio `./database/migrations`.
5. Puede correr este proyecto sin la necesidad de Apache, para esto, ejecutar el comando `composer start`.

---

### Notas

- Debido a la configuración regional de Microsoft SQL Server, seguramente necesite crear un usuario con la configuración `Language: English`. Esto debería solucionar cualquier problema de fechas entre Eloquent y el motor de base de datos.