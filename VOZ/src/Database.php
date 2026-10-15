<?php
declare(strict_types=1);

/**
 * Conexión PDO única (singleton) a MySQL / MariaDB en utf8mb4 y hora de Bogotá.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                config('db.host'),
                config('db.port'),
                config('db.name')
            );

            self::$pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Desfase fijo (-05:00): funciona aunque el servidor no tenga cargadas las zonas horarias.
            $offset = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('P');
            self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '{$offset}'");
        }

        return self::$pdo;
    }

    /**
     * Ejecuta una función dentro de una transacción.
     */
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultado = $fn($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function esDuplicado(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
