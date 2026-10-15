<?php
declare(strict_types=1);

/**
 * Ingreso desde Estelar (Laravel) sin segundo login.
 *
 * Estelar firma un token corto: base64url(json) . "." . base64url(HMAC-SHA256(json_b64, secreto)).
 * El json trae: email, nombre, rol (admin|asesor), iat, exp y jti (un solo uso).
 * El usuario se busca por correo en la BD de VOZ; si no existe, se crea.
 */
final class Sso
{
    /** Vida máxima aceptada para un token, aunque Estelar diga otra cosa. */
    private const VIDA_MAXIMA_SEG = 120;

    public static function habilitado(): bool
    {
        return strlen((string) config('sso.secreto')) >= 32;
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function ingresar(string $token): array
    {
        if (!self::habilitado()) {
            return ['ok' => false, 'mensaje' => 'El ingreso desde Estelar no está configurado.'];
        }

        $datos = self::verificar($token);
        if ($datos === null) {
            return ['ok' => false, 'mensaje' => 'El enlace de ingreso no es válido o ya venció. Vuelve a abrirlo desde Estelar.'];
        }
        if (!self::marcarUsado($datos['jti'], (int) $datos['exp'])) {
            return ['ok' => false, 'mensaje' => 'Ese enlace de ingreso ya se usó. Vuelve a abrirlo desde Estelar.'];
        }

        $usuario = self::sincronizarUsuario($datos);
        if ((int) $usuario['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'Tu usuario está desactivado en Prospección por voz. Habla con el administrador.'];
        }

        session_regenerate_id(true);
        $_SESSION = [
            'uid'           => (int) $usuario['id'],
            'login_en'      => time(),
            'ultimo_acceso' => time(),
            'csrf'          => bin2hex(random_bytes(32)),
            'sso'           => true,
        ];

        return ['ok' => true, 'mensaje' => '¡Bienvenido!'];
    }

    /**
     * @return array{email: string, nombre: string, rol: string, iat: int, exp: int, jti: string}|null
     */
    private static function verificar(string $token): ?array
    {
        if ($token === '' || strlen($token) > 2048 || substr_count($token, '.') !== 1) {
            return null;
        }
        [$cuerpo, $firma] = explode('.', $token, 2);

        $esperada = self::b64url(hash_hmac('sha256', $cuerpo, (string) config('sso.secreto'), true));
        if (!hash_equals($esperada, $firma)) {
            return null;
        }

        $d = json_decode((string) self::b64urlDecodificar($cuerpo), true);
        if (!is_array($d)) {
            return null;
        }

        $ahora = time();
        $iat = (int) ($d['iat'] ?? 0);
        $exp = (int) ($d['exp'] ?? 0);
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $jti = (string) ($d['jti'] ?? '');

        if ($exp < $ahora || $iat > $ahora + 30 || $exp - $iat > self::VIDA_MAXIMA_SEG) {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            return null;
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $jti)) {
            return null;
        }

        $nombre = trim((string) ($d['nombre'] ?? ''));
        return [
            'email'  => $email,
            'nombre' => $nombre !== '' ? mb_substr($nombre, 0, 100) : $email,
            'rol'    => ($d['rol'] ?? '') === 'admin' ? 'admin' : 'asesor',
            'iat'    => $iat,
            'exp'    => $exp,
            'jti'    => $jti,
        ];
    }

    /**
     * Guarda el jti para que el mismo token no sirva dos veces.
     */
    private static function marcarUsado(string $jti, int $exp): bool
    {
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sso_usados (
               jti CHAR(32) NOT NULL PRIMARY KEY,
               expira DATETIME NOT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec('DELETE FROM sso_usados WHERE expira < NOW() - INTERVAL 1 DAY');

        try {
            $pdo->prepare('INSERT INTO sso_usados (jti, expira) VALUES (?, ?)')
                ->execute([$jti, date('Y-m-d H:i:s', $exp)]);
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                return false;
            }
            throw $e;
        }
        return true;
    }

    /**
     * Estelar manda: crea el usuario si falta y mantiene nombre y rol al día.
     */
    private static function sincronizarUsuario(array $d): array
    {
        $usuario = UsuarioRepository::porEmail($d['email']);

        if ($usuario === null) {
            try {
                // Contraseña aleatoria: este usuario entra por Estelar (el admin puede asignarle otra).
                UsuarioRepository::crear($d['nombre'], $d['email'], bin2hex(random_bytes(24)), $d['rol']);
            } catch (InvalidArgumentException $e) {
                // Carrera con otra pestaña que lo acaba de crear: se relee abajo.
                if (UsuarioRepository::porEmail($d['email']) === null) {
                    throw $e;
                }
            }
            return UsuarioRepository::porEmail($d['email']);
        }

        if ($usuario['nombre'] !== $d['nombre']) {
            UsuarioRepository::cambiarNombre((int) $usuario['id'], $d['nombre']);
        }
        $quitariaUltimoAdmin = $usuario['rol'] === 'admin' && $d['rol'] !== 'admin'
            && UsuarioRepository::contarAdminsActivos() <= 1;
        if ($usuario['rol'] !== $d['rol'] && !$quitariaUltimoAdmin) {
            UsuarioRepository::cambiarRol((int) $usuario['id'], $d['rol']);
        }

        return UsuarioRepository::porId((int) $usuario['id']);
    }

    private static function b64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    private static function b64urlDecodificar(string $texto): string|false
    {
        return base64_decode(strtr($texto, '-_', '+/'), true);
    }
}
