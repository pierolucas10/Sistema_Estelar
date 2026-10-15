<?php
declare(strict_types=1);

/**
 * Arranque común de Estelar (web y línea de comandos).
 */

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config/config.php';

spl_autoload_register(static function (string $clase): void {
    if (!preg_match('/^[A-Za-z]+$/', $clase)) {
        return;
    }
    $archivo = APP_ROOT . '/src/' . $clase . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

require APP_ROOT . '/src/funciones.php';

// Errores: nunca en pantalla, siempre al log fuera de public_html.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
$dirLogs = APP_ROOT . '/logs';
if (!is_dir($dirLogs)) {
    @mkdir($dirLogs, 0750, true);
}
ini_set('error_log', $dirLogs . '/php-error.log');

mb_internal_encoding('UTF-8');

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[Estelar] %s: %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    Http::errorFatal();
});

date_default_timezone_set((string) config('app.timezone', 'America/Bogota'));

if (PHP_SAPI !== 'cli') {
    Http::iniciarWeb();
}
