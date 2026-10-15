<?php
declare(strict_types=1);

final class UsuarioRepository
{
    public const LONGITUD_MINIMA_PASSWORD = 10;

    public static function porId(int $id): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT id, nombre, email, password_hash, rol, activo, creado_en FROM usuarios WHERE id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function porEmail(string $email): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT id, nombre, email, password_hash, rol, activo, creado_en FROM usuarios WHERE email = ?'
        );
        $st->execute([mb_strtolower(trim($email))]);
        return $st->fetch() ?: null;
    }

    public static function listar(): array
    {
        return Database::pdo()->query(
            'SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.creado_en,
                    (SELECT COUNT(*) FROM contactos c WHERE c.usuario_id = u.id) AS contactos
               FROM usuarios u
           ORDER BY u.activo DESC, u.nombre'
        )->fetchAll();
    }

    /** Lista corta para filtros de reportes. */
    public static function asesores(): array
    {
        return Database::pdo()->query(
            'SELECT id, nombre, activo FROM usuarios ORDER BY activo DESC, nombre'
        )->fetchAll();
    }

    /**
     * @throws InvalidArgumentException con un mensaje listo para mostrar.
     */
    public static function crear(string $nombre, string $email, string $password, string $rol): int
    {
        $nombre = trim($nombre);
        $email = mb_strtolower(trim($email));

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('Escribe un nombre válido (máximo 100 caracteres).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            throw new InvalidArgumentException('El correo no es válido.');
        }
        self::validarPassword($password);
        if (!in_array($rol, ['admin', 'asesor'], true)) {
            throw new InvalidArgumentException('Rol no válido.');
        }

        try {
            Database::pdo()->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)'
            )->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol]);
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                throw new InvalidArgumentException('Ya existe un usuario con ese correo.');
            }
            throw $e;
        }

        return (int) Database::pdo()->lastInsertId();
    }

    public static function validarPassword(string $password): void
    {
        if (mb_strlen($password) < self::LONGITUD_MINIMA_PASSWORD) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos ' . self::LONGITUD_MINIMA_PASSWORD . ' caracteres.'
            );
        }
        if (strlen($password) > 72) {
            throw new InvalidArgumentException('La contraseña no puede superar 72 caracteres.');
        }
    }

    public static function cambiarPassword(int $id, string $password): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function cambiarNombre(int $id, string $nombre): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')
            ->execute([mb_substr(trim($nombre), 0, 100), $id]);
    }

    public static function cambiarRol(int $id, string $rol): void
    {
        if (!in_array($rol, ['admin', 'asesor'], true)) {
            throw new InvalidArgumentException('Rol no válido.');
        }
        Database::pdo()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
    }

    public static function cambiarActivo(int $id, bool $activo): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$activo ? 1 : 0, $id]);
    }

    public static function contarAdminsActivos(): int
    {
        return (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1"
        )->fetchColumn();
    }
}
