<?php
declare(strict_types=1);

/**
 * Sesiones seguras, login con bloqueo por intentos y control de roles.
 */
final class Auth
{
    /** Hash ficticio: iguala el tiempo de respuesta cuando el correo no existe. */
    private const HASH_FICTICIO = '$2y$10$u.wL8GtFdCtS/wkAC93uKeL0Myy9EjImqIDrP6RedMxAg0eCnGgp2';
    private const MAX_INTENTOS_IP = 20;

    private static ?array $usuario = null;
    private static bool $cargado = false;

    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) (config('session.lifetime_min') * 60));

        // Sesiones en una carpeta propia fuera de public_html (aisladas de otros sitios del hosting).
        $dir = (string) config('dir.sesiones');
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            if (is_writable($dir)) {
                session_save_path($dir);
                ini_set('session.gc_probability', '1');
                ini_set('session.gc_divisor', '100');
            }
        }

        session_name('ESTELAR_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Http::base() . '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $ahora = time();
        $limite = (int) config('session.lifetime_min') * 60;
        if (isset($_SESSION['ultimo_acceso']) && ($ahora - (int) $_SESSION['ultimo_acceso']) > $limite) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['ultimo_acceso'] = $ahora;
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function intentarLogin(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = Http::ipCliente();
        $claves = [
            'usuario' => hash('sha256', 'u|' . $email . '|' . $ip),
            'ip'      => hash('sha256', 'ip|' . $ip),
        ];

        $minutos = self::minutosBloqueo($claves);
        if ($minutos > 0) {
            return [
                'ok' => false,
                'mensaje' => "Demasiados intentos fallidos. Espera {$minutos} minuto(s) y vuelve a intentarlo.",
            ];
        }

        $usuario = $email !== '' ? UsuarioRepository::porEmail($email) : null;
        $passwordOk = password_verify($password, $usuario['password_hash'] ?? self::HASH_FICTICIO);

        if (!$usuario || !$passwordOk) {
            self::registrarFallo($claves);
            return ['ok' => false, 'mensaje' => 'Correo o contraseña incorrectos.'];
        }
        if ((int) $usuario['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'Tu usuario está desactivado. Habla con el administrador.'];
        }

        self::limpiarFallos($claves['usuario']);

        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            UsuarioRepository::cambiarPassword((int) $usuario['id'], $password);
        }

        session_regenerate_id(true);
        $_SESSION = [
            'uid'           => (int) $usuario['id'],
            'login_en'      => time(),
            'ultimo_acceso' => time(),
            'csrf'          => bin2hex(random_bytes(32)),
        ];
        self::$usuario = null;
        self::$cargado = false;

        return ['ok' => true, 'mensaje' => '¡Bienvenido!'];
    }

    public static function cerrarSesion(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        self::$usuario = null;
        self::$cargado = true;
    }

    /**
     * Usuario autenticado (se relee de la BD en cada petición para respetar desactivaciones y cambios de rol).
     */
    public static function usuario(): ?array
    {
        if (!self::$cargado) {
            self::$cargado = true;
            $id = (int) ($_SESSION['uid'] ?? 0);
            if ($id > 0) {
                $u = UsuarioRepository::porId($id);
                if ($u && (int) $u['activo'] === 1) {
                    self::$usuario = $u;
                } else {
                    $_SESSION = [];
                    session_regenerate_id(true);
                }
            }
        }
        return self::$usuario;
    }

    public static function esAdmin(): bool
    {
        return (self::usuario()['rol'] ?? '') === 'admin';
    }

    public static function requerirLogin(): array
    {
        $u = self::usuario();
        if ($u === null) {
            if (Http::esPeticionApi()) {
                Http::error('Tu sesión expiró. Vuelve a iniciar sesión.', 401);
            }
            Http::redirigir('login.php');
        }
        return $u;
    }

    public static function requerirAdmin(): array
    {
        $u = self::requerirLogin();
        if ($u['rol'] !== 'admin') {
            if (Http::esPeticionApi()) {
                Http::error('Solo un administrador puede hacer esto.', 403);
            }
            Vista::flash('error', 'Esa sección es solo para administradores.');
            Http::redirigir('index.php');
        }
        return $u;
    }

    // ---------------- Control de intentos ----------------

    private static function minutosBloqueo(array $claves): int
    {
        $in = implode(',', array_fill(0, count($claves), '?'));
        $st = Database::pdo()->prepare(
            "SELECT MAX(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta)) AS seg
               FROM login_intentos
              WHERE clave IN ($in) AND bloqueado_hasta > NOW()"
        );
        $st->execute(array_values($claves));
        $seg = (int) ($st->fetchColumn() ?: 0);
        return $seg > 0 ? (int) ceil($seg / 60) : 0;
    }

    private static function registrarFallo(array $claves): void
    {
        $pdo = Database::pdo();
        $ventana = (int) config('login.bloqueo_min');
        $limites = [
            'usuario' => (int) config('login.max_intentos'),
            'ip'      => self::MAX_INTENTOS_IP,
        ];

        foreach ($claves as $tipo => $clave) {
            $pdo->prepare(
                "INSERT INTO login_intentos (clave, fallos) VALUES (?, 1)
                 ON DUPLICATE KEY UPDATE
                    fallos = IF(actualizado_en < NOW() - INTERVAL ? MINUTE, 1, fallos + 1),
                    actualizado_en = NOW()"
            )->execute([$clave, $ventana]);

            $st = $pdo->prepare('SELECT fallos FROM login_intentos WHERE clave = ?');
            $st->execute([$clave]);
            if ((int) $st->fetchColumn() >= $limites[$tipo]) {
                $pdo->prepare(
                    'UPDATE login_intentos
                        SET bloqueado_hasta = NOW() + INTERVAL ? MINUTE, fallos = 0
                      WHERE clave = ?'
                )->execute([$ventana, $clave]);
            }
        }
    }

    private static function limpiarFallos(string $clave): void
    {
        Database::pdo()->prepare('DELETE FROM login_intentos WHERE clave = ?')->execute([$clave]);
    }
}
