<?php
declare(strict_types=1);

final class ContactoRepository
{
    private const SELECT_BASE =
        'SELECT c.id, c.usuario_id, c.pais, c.telefono_e164, c.telefono_local, c.estado, c.rubro_id,
                r.nombre AS rubro, c.nota, c.texto_dictado, c.creado_en, c.actualizado_en,
                u.nombre AS asesor
           FROM contactos c
           JOIN usuarios u ON u.id = c.usuario_id
      LEFT JOIN rubros r ON r.id = c.rubro_id';

    public static function porTelefono(string $e164): ?array
    {
        $st = Database::pdo()->prepare(self::SELECT_BASE . ' WHERE c.telefono_e164 = ?');
        $st->execute([$e164]);
        return $st->fetch() ?: null;
    }

    public static function porId(int $id): ?array
    {
        $st = Database::pdo()->prepare(self::SELECT_BASE . ' WHERE c.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * @param array{pais: string, e164: string, local: string, estado: string, rubro_id: ?int, nota: ?string, texto: ?string} $d
     * @throws DuplicadoException
     */
    public static function crear(array $d, int $usuarioId): int
    {
        try {
            return (int) Database::transaccion(static function (PDO $pdo) use ($d, $usuarioId): int {
                $pdo->prepare(
                    'INSERT INTO contactos (usuario_id, pais, telefono_e164, telefono_local, estado, rubro_id, nota, texto_dictado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$usuarioId, $d['pais'], $d['e164'], $d['local'], $d['estado'], $d['rubro_id'], $d['nota'], $d['texto']]);
                $id = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO contactos_historial (contacto_id, estado_anterior, estado_nuevo, rubro_anterior_id, rubro_nuevo_id, usuario_id)
                     VALUES (?, NULL, ?, NULL, ?, ?)'
                )->execute([$id, $d['estado'], $d['rubro_id'], $usuarioId]);

                return $id;
            });
        } catch (PDOException $e) {
            if (Database::esDuplicado($e)) {
                throw new DuplicadoException(self::porTelefono($d['e164']) ?? []);
            }
            throw $e;
        }
    }

    /**
     * Actualiza estado / rubro / nota y deja rastro en el historial.
     */
    public static function actualizar(int $id, string $estado, ?int $rubroId, ?string $nota, ?string $texto, int $usuarioId): void
    {
        Database::transaccion(static function (PDO $pdo) use ($id, $estado, $rubroId, $nota, $texto, $usuarioId): void {
            $st = $pdo->prepare('SELECT estado, rubro_id, nota FROM contactos WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $actual = $st->fetch();
            if (!$actual) {
                throw new InvalidArgumentException('El contacto ya no existe.');
            }

            // Si no se dicta rubro o nota nuevos, se conservan los actuales.
            $rubroFinal = $rubroId ?? ($actual['rubro_id'] !== null ? (int) $actual['rubro_id'] : null);
            $notaFinal = $nota ?? $actual['nota'];

            $pdo->prepare(
                'UPDATE contactos SET estado = ?, rubro_id = ?, nota = ?, texto_dictado = COALESCE(?, texto_dictado) WHERE id = ?'
            )->execute([$estado, $rubroFinal, $notaFinal, $texto, $id]);

            $pdo->prepare(
                'INSERT INTO contactos_historial (contacto_id, estado_anterior, estado_nuevo, rubro_anterior_id, rubro_nuevo_id, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $actual['estado'], $estado, $actual['rubro_id'], $rubroFinal, $usuarioId]);
        });
    }

    public static function historial(int $contactoId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT h.estado_anterior, h.estado_nuevo, ra.nombre AS rubro_anterior, rn.nombre AS rubro_nuevo,
                    u.nombre AS usuario, h.creado_en
               FROM contactos_historial h
               JOIN usuarios u ON u.id = h.usuario_id
          LEFT JOIN rubros ra ON ra.id = h.rubro_anterior_id
          LEFT JOIN rubros rn ON rn.id = h.rubro_nuevo_id
              WHERE h.contacto_id = ?
           ORDER BY h.creado_en DESC, h.id DESC'
        );
        $st->execute([$contactoId]);
        return $st->fetchAll();
    }

    /**
     * Listado con filtros y paginación.
     *
     * @param array{periodo?: string, hoy?: bool, pais?: string, estado?: string, rubro_id?: int, usuario_id?: int, q?: string} $f
     */
    public static function listar(array $f, int $pagina = 1, int $porPagina = 25): array
    {
        [$where, $params] = self::filtros($f);
        $pdo = Database::pdo();

        $st = $pdo->prepare('SELECT COUNT(*) FROM contactos c' . $where);
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $porPagina = max(1, min(100, $porPagina));
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = max(1, min($pagina, $paginas));

        $st = $pdo->prepare(self::SELECT_BASE . $where . ' ORDER BY c.creado_en DESC, c.id DESC LIMIT :limite OFFSET :desde');
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $st->bindValue(':desde', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $st->execute();

        return [
            'items'      => array_map([self::class, 'presentar'], $st->fetchAll()),
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => $paginas,
            'por_pagina' => $porPagina,
        ];
    }

    /**
     * Recorre todas las filas que cumplen los filtros (para exportar CSV sin cargar todo en memoria).
     */
    public static function recorrer(array $f): Generator
    {
        [$where, $params] = self::filtros($f);
        $st = Database::pdo()->prepare(self::SELECT_BASE . $where . ' ORDER BY c.creado_en ASC, c.id ASC');
        $st->execute($params);
        while ($fila = $st->fetch()) {
            yield $fila;
        }
    }

    /** @return array{0: string, 1: array} */
    public static function filtros(array $f): array
    {
        $w = [];
        $p = [];

        if (!empty($f['hoy'])) {
            $w[] = 'c.creado_en >= :hoy_desde AND c.creado_en < :hoy_hasta';
            $p[':hoy_desde'] = date('Y-m-d 00:00:00');
            $p[':hoy_hasta'] = date('Y-m-d 00:00:00', strtotime('+1 day'));
        }
        if (!empty($f['periodo'])) {
            $w[] = 'c.periodo = :periodo';
            $p[':periodo'] = $f['periodo'];
        }
        if (!empty($f['pais'])) {
            $w[] = 'c.pais = :pais';
            $p[':pais'] = $f['pais'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'c.estado = :estado';
            $p[':estado'] = $f['estado'];
        }
        if (!empty($f['rubro_id'])) {
            if ((int) $f['rubro_id'] === -1) {
                $w[] = 'c.rubro_id IS NULL';
            } else {
                $w[] = 'c.rubro_id = :rubro_id';
                $p[':rubro_id'] = (int) $f['rubro_id'];
            }
        }
        if (!empty($f['usuario_id'])) {
            $w[] = 'c.usuario_id = :usuario_id';
            $p[':usuario_id'] = (int) $f['usuario_id'];
        }
        if (!empty($f['q'])) {
            $digitos = preg_replace('/\D+/', '', (string) $f['q']);
            if ($digitos !== '') {
                $w[] = 'c.telefono_e164 LIKE :q';
                $p[':q'] = '%' . $digitos . '%';
            }
        }

        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /**
     * Forma pública de un contacto para la API.
     */
    public static function presentar(array $c): array
    {
        $pais = Telefono::PAISES[$c['pais']] ?? null;
        return [
            'id'             => (int) $c['id'],
            'pais'           => $c['pais'],
            'pais_nombre'    => $pais['nombre'] ?? $c['pais'],
            'bandera'        => $pais['bandera'] ?? '',
            'telefono_e164'  => $c['telefono_e164'],
            'telefono_local' => $c['telefono_local'],
            'telefono_fmt'   => Telefono::formatear($c['telefono_e164']),
            'estado'         => $c['estado'],
            'estado_label'   => EstadoRepository::nombre($c['estado']),
            'estado_color'   => EstadoRepository::color($c['estado']),
            'rubro_id'       => $c['rubro_id'] !== null ? (int) $c['rubro_id'] : null,
            'rubro'          => $c['rubro'],
            'nota'           => $c['nota'],
            'asesor'         => $c['asesor'],
            'usuario_id'     => (int) $c['usuario_id'],
            'creado_en'      => $c['creado_en'],
            'fecha'          => fecha_corta($c['creado_en']),
            'hora'           => $c['creado_en'] ? date('H:i', (int) strtotime($c['creado_en'])) : '',
            'actualizado'    => fecha_corta($c['actualizado_en']),
            'tel'            => Telefono::enlaceLlamada($c['telefono_e164']),
            'whatsapp'       => Telefono::enlaceWhatsapp($c['telefono_e164']),
        ];
    }
}
