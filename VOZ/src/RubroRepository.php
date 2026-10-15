<?php
declare(strict_types=1);

final class RubroRepository
{
    /** @return array<int, array{id: int, nombre: string}> */
    public static function todos(): array
    {
        return Database::pdo()->query('SELECT id, nombre FROM rubros ORDER BY nombre')->fetchAll();
    }

    public static function conConteo(): array
    {
        return Database::pdo()->query(
            'SELECT r.id, r.nombre, r.nombre_normalizado, r.creado_en, COUNT(c.id) AS contactos
               FROM rubros r
          LEFT JOIN contactos c ON c.rubro_id = r.id
           GROUP BY r.id, r.nombre, r.nombre_normalizado, r.creado_en
           ORDER BY r.nombre'
        )->fetchAll();
    }

    public static function porId(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT id, nombre FROM rubros WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Busca por nombre tolerando tildes y plural/singular.
     */
    public static function buscar(string $nombre): ?array
    {
        return Parser::buscarRubro($nombre, self::todos())['exacto'];
    }

    /**
     * Devuelve el rubro existente equivalente o lo crea. ['id','nombre','nuevo']
     */
    public static function obtenerOCrear(string $nombre): array
    {
        $nombre = self::limpiarNombre($nombre);
        $existente = self::buscar($nombre);
        if ($existente !== null) {
            return $existente + ['nuevo' => false];
        }

        try {
            Database::pdo()->prepare('INSERT INTO rubros (nombre, nombre_normalizado) VALUES (?, ?)')
                ->execute([$nombre, Parser::normalizarTexto($nombre)]);
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                $st = Database::pdo()->prepare('SELECT id, nombre FROM rubros WHERE nombre_normalizado = ?');
                $st->execute([Parser::normalizarTexto($nombre)]);
                $fila = $st->fetch();
                return ['id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'nuevo' => false];
            }
            throw $e;
        }

        return ['id' => (int) Database::pdo()->lastInsertId(), 'nombre' => $nombre, 'nuevo' => true];
    }

    public static function limpiarNombre(string $nombre): string
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', strip_tags($nombre)));
        $nombre = mb_substr($nombre, 0, 100);
        if ($nombre === '' || Parser::normalizarTexto($nombre) === '') {
            throw new InvalidArgumentException('Escribe un nombre de rubro válido.');
        }
        return Parser::capitalizar($nombre);
    }

    public static function renombrar(int $id, string $nombre): void
    {
        $nombre = self::limpiarNombre($nombre);
        $equivalente = self::buscar($nombre);
        if ($equivalente !== null && (int) $equivalente['id'] !== $id) {
            throw new InvalidArgumentException("Ya existe el rubro «{$equivalente['nombre']}». Usa «Fusionar» si son el mismo.");
        }
        try {
            Database::pdo()->prepare('UPDATE rubros SET nombre = ?, nombre_normalizado = ? WHERE id = ?')
                ->execute([$nombre, Parser::normalizarTexto($nombre), $id]);
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                throw new InvalidArgumentException('Ya existe un rubro con ese nombre.');
            }
            throw $e;
        }
    }

    /**
     * Mueve todos los contactos (e historial) del rubro origen al destino y elimina el origen.
     * Devuelve cuántos contactos se movieron.
     */
    public static function fusionar(int $origenId, int $destinoId): int
    {
        if ($origenId === $destinoId) {
            throw new InvalidArgumentException('Elige dos rubros distintos.');
        }
        if (!self::porId($origenId) || !self::porId($destinoId)) {
            throw new InvalidArgumentException('Uno de los rubros ya no existe.');
        }

        return (int) Database::transaccion(static function (PDO $pdo) use ($origenId, $destinoId): int {
            $st = $pdo->prepare('UPDATE contactos SET rubro_id = ? WHERE rubro_id = ?');
            $st->execute([$destinoId, $origenId]);
            $movidos = $st->rowCount();

            $pdo->prepare('UPDATE contactos_historial SET rubro_anterior_id = ? WHERE rubro_anterior_id = ?')
                ->execute([$destinoId, $origenId]);
            $pdo->prepare('UPDATE contactos_historial SET rubro_nuevo_id = ? WHERE rubro_nuevo_id = ?')
                ->execute([$destinoId, $origenId]);
            $pdo->prepare('DELETE FROM rubros WHERE id = ?')->execute([$origenId]);

            return $movidos;
        });
    }

    public static function eliminar(int $id): void
    {
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM contactos WHERE rubro_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new InvalidArgumentException('Ese rubro tiene contactos. Fusiónalo con otro en lugar de eliminarlo.');
        }
        Database::pdo()->prepare('DELETE FROM rubros WHERE id = ?')->execute([$id]);
    }

    /**
     * Pares de rubros que probablemente son el mismo (para sugerir fusiones).
     */
    public static function posiblesDuplicados(array $rubros): array
    {
        $pares = [];
        $n = count($rubros);
        for ($i = 0; $i < $n; $i++) {
            $a = Parser::claveRubro($rubros[$i]['nombre']);
            for ($j = $i + 1; $j < $n; $j++) {
                $b = Parser::claveRubro($rubros[$j]['nombre']);
                $tolerancia = min(strlen($a), strlen($b)) >= 8 ? 2 : 1;
                if ($a === $b || levenshtein($a, $b) <= $tolerancia) {
                    // Se sugiere conservar el que tiene más contactos.
                    [$conservar, $absorber] = (int) $rubros[$i]['contactos'] >= (int) $rubros[$j]['contactos']
                        ? [$rubros[$i], $rubros[$j]]
                        : [$rubros[$j], $rubros[$i]];
                    $pares[] = ['conservar' => $conservar, 'absorber' => $absorber];
                }
            }
        }
        return $pares;
    }
}
