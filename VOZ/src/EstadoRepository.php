<?php
declare(strict_types=1);

/**
 * Estados de contacto (tabla estados). Los 4 básicos vienen de fábrica y se pueden
 * crear más desde la pantalla de dictado o desde la página Estados.
 */
final class EstadoRepository
{
    /** Estados de fábrica: no se pueden eliminar ni desactivar. */
    public const BASICOS = ['interesado', 'reunion', 'medio_interesado', 'no_interesado'];

    /** Colores que se asignan a los estados nuevos, en rotación. */
    private const PALETA = [
        '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c',
        '#4f46e5', '#0d9488', '#a16207', '#be123c', '#475569',
    ];

    private static ?array $cache = null;

    /**
     * Todos los estados ordenados (incluye inactivos).
     *
     * @return array<int, array{id: int, clave: string, nombre: string, palabras: ?string, color: string, cuenta_conversion: int, orden: int, activo: int}>
     */
    public static function todos(): array
    {
        if (self::$cache === null) {
            self::$cache = array_map(static fn (array $e): array => [
                'id'                => (int) $e['id'],
                'clave'             => (string) $e['clave'],
                'nombre'            => (string) $e['nombre'],
                'palabras'          => $e['palabras'] !== null ? (string) $e['palabras'] : null,
                'color'             => (string) $e['color'],
                'cuenta_conversion' => (int) $e['cuenta_conversion'],
                'orden'             => (int) $e['orden'],
                'activo'            => (int) $e['activo'],
            ], Database::pdo()->query(
                'SELECT id, clave, nombre, palabras, color, cuenta_conversion, orden, activo
                   FROM estados ORDER BY orden, nombre'
            )->fetchAll());
        }
        return self::$cache;
    }

    public static function activos(): array
    {
        return array_values(array_filter(self::todos(), static fn (array $e): bool => $e['activo'] === 1));
    }

    /** @return array<string, array> clave => estado */
    public static function mapa(): array
    {
        $mapa = [];
        foreach (self::todos() as $e) {
            $mapa[$e['clave']] = $e;
        }
        return $mapa;
    }

    public static function porClave(string $clave): ?array
    {
        return self::mapa()[$clave] ?? null;
    }

    public static function nombre(string $clave): string
    {
        return self::mapa()[$clave]['nombre'] ?? (Parser::ESTADOS[$clave] ?? $clave);
    }

    public static function color(string $clave): string
    {
        return self::mapa()[$clave]['color'] ?? '#64748b';
    }

    /** Claves que cuentan como conversión (por defecto: interesado, reunión y reunión pendiente). */
    public static function clavesConversion(): array
    {
        $claves = [];
        foreach (self::todos() as $e) {
            if ($e['cuenta_conversion'] === 1) {
                $claves[] = $e['clave'];
            }
        }
        return $claves;
    }

    /** Versión pública para el navegador (parser y vista previa). */
    public static function paraCliente(): array
    {
        return array_map(static fn (array $e): array => [
            'clave'      => $e['clave'],
            'nombre'     => $e['nombre'],
            'palabras'   => $e['palabras'],
            'color'      => $e['color'],
            'conversion' => $e['cuenta_conversion'] === 1,
            'orden'      => $e['orden'],
            'basico'     => in_array($e['clave'], self::BASICOS, true),
        ], self::activos());
    }

    /**
     * Crea un estado. Si ya existe uno con el mismo nombre, lo devuelve (y lo reactiva).
     *
     * @return array estado + ['nuevo' => bool]
     */
    public static function crear(string $nombre, ?string $palabras = null, bool $conversion = false): array
    {
        [$nombre, $palabras] = self::validar($nombre, $palabras);
        $clave = self::claveDesdeNombre($nombre);

        $existente = self::porClave($clave);
        if ($existente !== null) {
            if ($existente['activo'] !== 1) {
                self::cambiarActivo($existente['id'], true);
                $existente['activo'] = 1;
            }
            return $existente + ['nuevo' => false];
        }

        $pdo = Database::pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM estados')->fetchColumn();
        $orden = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM estados')->fetchColumn();
        $color = self::PALETA[$total % count(self::PALETA)];

        try {
            $pdo->prepare(
                'INSERT INTO estados (clave, nombre, palabras, color, cuenta_conversion, orden) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$clave, $nombre, $palabras, $color, $conversion ? 1 : 0, $orden]);
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                self::$cache = null;
                return (self::porClave($clave) ?? []) + ['nuevo' => false];
            }
            throw $e;
        }

        self::$cache = null;
        return (self::porClave($clave) ?? []) + ['nuevo' => true];
    }

    public static function actualizar(int $id, string $nombre, ?string $palabras, string $color, bool $conversion): void
    {
        [$nombre, $palabras] = self::validar($nombre, $palabras);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException('Color no válido.');
        }
        Database::pdo()->prepare(
            'UPDATE estados SET nombre = ?, palabras = ?, color = ?, cuenta_conversion = ? WHERE id = ?'
        )->execute([$nombre, $palabras, strtolower($color), $conversion ? 1 : 0, $id]);
        self::$cache = null;
    }

    public static function cambiarActivo(int $id, bool $activo): void
    {
        $estado = self::porId($id);
        if ($estado && !$activo && in_array($estado['clave'], self::BASICOS, true)) {
            throw new InvalidArgumentException('Los 4 estados básicos no se pueden desactivar.');
        }
        Database::pdo()->prepare('UPDATE estados SET activo = ? WHERE id = ?')->execute([$activo ? 1 : 0, $id]);
        self::$cache = null;
    }

    /** Elimina un estado sin contactos; si tiene contactos hay que desactivarlo. */
    public static function eliminar(int $id): void
    {
        $estado = self::porId($id);
        if (!$estado) {
            return;
        }
        if (in_array($estado['clave'], self::BASICOS, true)) {
            throw new InvalidArgumentException('Los 4 estados básicos no se pueden eliminar.');
        }
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM contactos WHERE estado = ?');
        $st->execute([$estado['clave']]);
        if ((int) $st->fetchColumn() > 0) {
            throw new InvalidArgumentException("«{$estado['nombre']}» tiene contactos. Desactívalo en lugar de eliminarlo.");
        }
        Database::pdo()->prepare('DELETE FROM estados WHERE id = ?')->execute([$id]);
        self::$cache = null;
    }

    public static function porId(int $id): ?array
    {
        foreach (self::todos() as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }
        return null;
    }

    /** Cantidad de contactos por estado (para la página de administración). */
    public static function conteos(): array
    {
        $conteos = [];
        foreach (Database::pdo()->query('SELECT estado, COUNT(*) AS n FROM contactos GROUP BY estado')->fetchAll() as $f) {
            $conteos[$f['estado']] = (int) $f['n'];
        }
        return $conteos;
    }

    /** "Escribir al WhatsApp" → "escribir_al_whatsapp" */
    public static function claveDesdeNombre(string $nombre): string
    {
        $clave = str_replace(' ', '_', Parser::normalizarTexto($nombre));
        return substr($clave, 0, 40);
    }

    /** @return array{0: string, 1: ?string} */
    private static function validar(string $nombre, ?string $palabras): array
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', strip_tags($nombre)));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60 || strlen(Parser::normalizarTexto($nombre)) < 2) {
            throw new InvalidArgumentException('Escribe un nombre de estado válido (de 2 a 60 caracteres).');
        }
        $palabras = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $palabras)), " ,");
        $palabras = $palabras === '' ? null : mb_substr($palabras, 0, 255);

        return [Parser::capitalizar($nombre), $palabras];
    }
}
