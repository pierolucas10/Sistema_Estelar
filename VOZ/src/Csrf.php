<?php
declare(strict_types=1);

/**
 * Token CSRF por sesión para formularios y llamadas a la API.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valido(mixed $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf'])
            && hash_equals((string) $_SESSION['csrf'], $token);
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    /**
     * Para formularios HTML (POST clásico).
     */
    public static function verificarFormulario(): void
    {
        if (!self::valido($_POST['csrf'] ?? null)) {
            http_response_code(419);
            Vista::flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
            Http::redirigir(basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
        }
    }

    /**
     * Para la API: el token llega en la cabecera X-CSRF-Token.
     */
    public static function verificarApi(): void
    {
        Http::verificarOrigen();
        if (!self::valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Http::error('Token de seguridad inválido. Recarga la página.', 419);
        }
    }
}
