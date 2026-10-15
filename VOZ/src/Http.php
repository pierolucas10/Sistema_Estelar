<?php
declare(strict_types=1);

/**
 * Utilidades HTTP: HTTPS obligatorio, cabeceras de seguridad, JSON y redirecciones.
 */
final class Http
{
    public static function iniciarWeb(): void
    {
        if (!self::esHttps()) {
            header('Location: https://' . self::host() . (string) ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
        self::cabecerasSeguridad();
        Auth::iniciarSesion();
    }

    public static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        if (config('app.trust_proxy')) {
            if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
                return true;
            }
            $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
            if (str_contains($cfVisitor, '"https"')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dominio actual (validado). Si no es válido, usa el de url_sitio.
     */
    public static function host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $host)) {
            return $host;
        }
        return (string) config('app.host');
    }

    /**
     * Carpeta del sistema tal como la ve el navegador: "/VOZ", "" si está en la raíz, etc.
     * Se detecta sola, así el sistema funciona en cualquier carpeta sin configurar nada.
     */
    public static function base(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $ruta = (string) preg_replace('#/+#', '/', $ruta);
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $pos = $script !== '' ? strpos($ruta, '/' . $script) : false;
        $dir = $pos !== false ? substr($ruta, 0, $pos) : rtrim($ruta, '/');

        // Las llamadas a la API viven en /api: la base es la carpeta de arriba.
        if (basename($dir) === 'api') {
            $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
        }
        $dir = (string) preg_replace('#[^A-Za-z0-9/_\-.~]#', '', $dir);

        return $base = ($dir === '.' || $dir === '/') ? '' : $dir;
    }

    public static function cabecerasSeguridad(): void
    {
        header_remove('X-Powered-By');

        // Estelar muestra VOZ dentro de un iframe en el módulo de Leads.
        $estelar = (string) config('sso.origen_estelar');
        $marcos = preg_match('#^https://[a-z0-9.\-]+(:\d+)?$#i', $estelar) ? "'self' " . $estelar : "'self'";

        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
            . "style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data:; connect-src 'self'; media-src 'self'; object-src 'none'; "
            . "frame-ancestors {$marcos}; base-uri 'self'; form-action 'self'");
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: microphone=(self), camera=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    public static function url(string $ruta = ''): string
    {
        return self::base() . '/' . ltrim($ruta, '/');
    }

    public static function redirigir(string $ruta): never
    {
        header('Location: ' . self::url($ruta), true, 303);
        exit;
    }

    public static function json(mixed $datos, int $estado = 200): never
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $mensaje, int $estado = 400, array $extra = []): never
    {
        self::json(['ok' => false, 'error' => $mensaje] + $extra, $estado);
    }

    public static function soloMetodo(string $metodo): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== $metodo) {
            header('Allow: ' . $metodo);
            self::error('Método no permitido.', 405);
        }
    }

    public static function esPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    /**
     * Lee el cuerpo JSON de la petición (máx. 64 KB).
     */
    public static function entradaJson(): array
    {
        $tipo = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_contains(strtolower($tipo), 'application/json')) {
            self::error('Se esperaba JSON.', 415);
        }
        $cuerpo = file_get_contents('php://input', false, null, 0, 65536);
        $datos = json_decode((string) $cuerpo, true);
        if (!is_array($datos)) {
            self::error('JSON inválido.', 400);
        }
        return $datos;
    }

    /**
     * Rechaza POST que vengan de otro sitio (defensa adicional al token CSRF).
     */
    public static function verificarOrigen(): void
    {
        $origen = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        if ($origen === '') {
            return;
        }
        $hostActual = explode(':', self::host())[0];
        if (strcasecmp((string) parse_url($origen, PHP_URL_HOST), $hostActual) !== 0) {
            self::error('Origen no permitido.', 403);
        }
    }

    public static function ipCliente(): string
    {
        if (config('app.trust_proxy')) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $cab) {
                if (!empty($_SERVER[$cab])) {
                    $ip = trim(explode(',', (string) $_SERVER[$cab])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function esPeticionApi(): bool
    {
        return str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
    }

    public static function errorFatal(): never
    {
        if (!headers_sent()) {
            if (self::esPeticionApi()) {
                self::error('Ocurrió un error en el servidor. Intenta nuevamente.', 500);
            }
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Estelar</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body><main><h1>Algo salió mal</h1><p>Ocurrió un error inesperado. '
            . 'Ya quedó registrado; intenta de nuevo en unos minutos.</p></main></body></html>';
        exit;
    }
}
