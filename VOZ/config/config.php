<?php
declare(strict_types=1);

/**
 * Configuración de Estelar.
 * Los datos se leen de config/database.php (fuera de public_html).
 * Este archivo NO se edita.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

/**
 * Acceso a la configuración: config('db.host').
 */
function config(string $clave, mixed $defecto = null): mixed
{
    static $cfg = null;

    if ($cfg === null) {
        $archivo = APP_ROOT . '/config/database.php';
        if (!is_file($archivo)) {
            throw new RuntimeException('Falta el archivo config/database.php.');
        }
        $d = require $archivo;
        if (!is_array($d)) {
            throw new RuntimeException('config/database.php debe devolver un arreglo (return [...]).');
        }

        // url_sitio es opcional: la carpeta del sistema se detecta sola (ver Http::base()).
        $url = rtrim((string) ($d['url_sitio'] ?? ''), '/');
        foreach (['db_host', 'db_nombre', 'db_usuario'] as $obligatoria) {
            if (empty($d[$obligatoria])) {
                throw new RuntimeException("Falta '{$obligatoria}' en config/database.php.");
            }
        }

        $separador = (string) ($d['csv_separador'] ?? ',');

        $cfg = [
            'app.nombre'           => (string) ($d['nombre_app'] ?? 'Estelar'),
            'app.url'              => $url,
            'app.host'             => (string) (parse_url($url, PHP_URL_HOST) ?: ''),
            'app.timezone'         => (string) ($d['zona_horaria'] ?? 'America/Bogota'),
            'app.idioma_voz'       => (string) ($d['idioma_voz'] ?? 'es-CO'),
            'app.trust_proxy'      => (bool) ($d['detras_de_proxy'] ?? false),
            'db.host'              => (string) $d['db_host'],
            'db.port'              => (int) ($d['db_puerto'] ?? 3306),
            'db.name'              => (string) $d['db_nombre'],
            'db.user'              => (string) $d['db_usuario'],
            'db.pass'              => (string) ($d['db_clave'] ?? ''),
            'session.lifetime_min' => max(15, (int) ($d['sesion_minutos'] ?? 480)),
            'login.max_intentos'   => max(3, (int) ($d['login_intentos'] ?? 5)),
            'login.bloqueo_min'    => max(1, (int) ($d['login_bloqueo_min'] ?? 15)),
            'csv.separador'        => in_array($separador, [',', ';'], true) ? $separador : ',',
            'sso.secreto'          => (string) ($d['sso_secreto'] ?? ''),
            'sso.url_estelar'      => (string) ($d['sso_url_estelar'] ?? ''),
            'sso.origen_estelar'   => rtrim((string) ($d['sso_origen_estelar'] ?? ''), '/'),
            'dir.logs'             => APP_ROOT . '/logs',
            'dir.sesiones'         => APP_ROOT . '/storage/sesiones',
        ];
    }

    return $cfg[$clave] ?? $defecto;
}
