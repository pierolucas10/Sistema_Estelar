<?php
declare(strict_types=1);

/**
 * Reportes mensuales. Todas las consultas usan la columna generada `periodo` (AAAA-MM), que está indexada.
 * Los filtros aceptados: periodo, pais, estado, rubro_id, usuario_id (ver ContactoRepository::filtros).
 *
 * Los estados son dinámicos (tabla estados). La conversión suma los estados marcados
 * como "cuenta como conversión" (por defecto: interesado, reunión y reunión pendiente).
 */
final class ReporteRepository
{
    /** Estados que siempre tienen columna propia en las tablas. */
    public const BASICOS = EstadoRepository::BASICOS;

    public static function periodo(int $anio, int $mes): string
    {
        return sprintf('%04d-%02d', $anio, $mes);
    }

    public static function desplazarPeriodo(string $periodo, int $meses): string
    {
        return (new DateTimeImmutable($periodo . '-01'))->modify(sprintf('%+d month', $meses))->format('Y-m');
    }

    /**
     * Reporte completo del mes con todas las secciones.
     */
    public static function mensual(array $filtros): array
    {
        $periodo = $filtros['periodo'];
        $anterior = self::desplazarPeriodo($periodo, -1);

        $actual = self::resumen($filtros);
        $previo = self::resumen(['periodo' => $anterior] + $filtros);

        $variacionTotal = $actual['total'] - $previo['total'];

        return [
            'periodo'   => $periodo,
            'etiqueta'  => nombre_periodo($periodo),
            'estados'   => self::estadosParaCliente(),
            'resumen'   => $actual + [
                'anterior'  => [
                    'periodo'    => $anterior,
                    'etiqueta'   => nombre_periodo($anterior),
                    'total'      => $previo['total'],
                    'conversion' => $previo['conversion'],
                ],
                'variacion' => [
                    'total'      => $variacionTotal,
                    'total_pct'  => $previo['total'] > 0 ? round($variacionTotal * 100 / $previo['total'], 1) : null,
                    'conversion' => round($actual['conversion'] - $previo['conversion'], 1),
                ],
            ],
            'paises'    => self::paises($filtros),
            'rubros'    => self::porRubro($filtros),
            'evolucion' => self::evolucion($filtros),
        ];
    }

    /** Lista de estados (incluye inactivos) con nombre, color y si cuentan como conversión. */
    private static function estadosParaCliente(): array
    {
        return array_map(static fn (array $e): array => [
            'clave'      => $e['clave'],
            'nombre'     => $e['nombre'],
            'color'      => $e['color'],
            'conversion' => $e['cuenta_conversion'] === 1,
            'basico'     => in_array($e['clave'], self::BASICOS, true),
            'activo'     => $e['activo'] === 1,
        ], EstadoRepository::todos());
    }

    public static function resumen(array $filtros): array
    {
        [$where, $params] = ContactoRepository::filtros($filtros);
        $st = Database::pdo()->prepare("SELECT c.estado, COUNT(*) AS n FROM contactos c{$where} GROUP BY c.estado");
        $st->execute($params);

        $conteos = [];
        foreach ($st->fetchAll() as $fila) {
            $conteos[$fila['estado']] = (int) $fila['n'];
        }
        return self::armarResumen($conteos);
    }

    /**
     * Arma el resumen con todos los estados activos (y los inactivos que tengan contactos).
     */
    private static function armarResumen(array $conteos): array
    {
        $total = array_sum($conteos);
        $conversion = EstadoRepository::clavesConversion();
        $porEstado = [];
        $convertidos = 0;

        foreach (EstadoRepository::todos() as $e) {
            $n = $conteos[$e['clave']] ?? 0;
            if ($e['activo'] !== 1 && $n === 0) {
                continue;
            }
            $porEstado[$e['clave']] = [
                'n'          => $n,
                'pct'        => $total > 0 ? round($n * 100 / $total, 1) : 0.0,
                'label'      => $e['nombre'],
                'color'      => $e['color'],
                'conversion' => in_array($e['clave'], $conversion, true),
            ];
        }
        // Estados que ya no existen en la tabla pero quedaron en contactos antiguos.
        foreach ($conteos as $clave => $n) {
            if (!isset($porEstado[$clave])) {
                $porEstado[$clave] = ['n' => $n, 'pct' => $total > 0 ? round($n * 100 / $total, 1) : 0.0,
                                      'label' => (string) $clave, 'color' => '#64748b', 'conversion' => false];
            }
        }
        foreach ($conteos as $clave => $n) {
            if (in_array($clave, $conversion, true)) {
                $convertidos += $n;
            }
        }

        return [
            'total'       => $total,
            'convertidos' => $convertidos,
            'por_estado'  => $porEstado,
            'conversion'  => $total > 0 ? round($convertidos * 100 / $total, 1) : 0.0,
        ];
    }

    /**
     * Perú vs Colombia en el mismo mes (ignora el filtro de país).
     */
    public static function paises(array $filtros): array
    {
        unset($filtros['pais']);
        [$where, $params] = ContactoRepository::filtros($filtros);
        $conversion = EstadoRepository::clavesConversion();

        $st = Database::pdo()->prepare(
            "SELECT c.pais, c.rubro_id, COALESCE(r.nombre, 'Sin rubro') AS rubro, c.estado, COUNT(*) AS n
               FROM contactos c
          LEFT JOIN rubros r ON r.id = c.rubro_id
                    {$where}
           GROUP BY c.pais, c.rubro_id, r.nombre, c.estado"
        );
        $st->execute($params);

        $conteos = ['PE' => [], 'CO' => []];
        $rubros = ['PE' => [], 'CO' => []];
        foreach ($st->fetchAll() as $f) {
            $pais = $f['pais'];
            $n = (int) $f['n'];
            $conteos[$pais][$f['estado']] = ($conteos[$pais][$f['estado']] ?? 0) + $n;

            $clave = (string) ($f['rubro_id'] ?? 'sin');
            $rubros[$pais][$clave] ??= ['rubro' => $f['rubro'], 'total' => 0, 'convertidos' => 0];
            $rubros[$pais][$clave]['total'] += $n;
            if (in_array($f['estado'], $conversion, true)) {
                $rubros[$pais][$clave]['convertidos'] += $n;
            }
        }

        $res = [];
        foreach (Telefono::PAISES as $iso => $pais) {
            $lista = array_values($rubros[$iso]);
            usort($lista, static fn (array $a, array $b): int =>
                [$b['total'], $b['convertidos']] <=> [$a['total'], $a['convertidos']]);
            $top = array_map(static fn (array $r): array => [
                'rubro'      => $r['rubro'],
                'total'      => $r['total'],
                'conversion' => $r['total'] > 0 ? round($r['convertidos'] * 100 / $r['total'], 1) : 0.0,
            ], array_slice($lista, 0, 5));

            $res[$iso] = self::armarResumen($conteos[$iso]) + [
                'nombre'     => $pais['nombre'],
                'bandera'    => $pais['bandera'],
                'top_rubros' => $top,
            ];
        }
        return $res;
    }

    /**
     * Tabla por rubro y país: columnas de los 4 estados básicos + "otros" (el resto de estados).
     */
    public static function porRubro(array $filtros): array
    {
        [$where, $params] = ContactoRepository::filtros($filtros);
        $conversion = EstadoRepository::clavesConversion();

        $st = Database::pdo()->prepare(
            "SELECT COALESCE(r.nombre, 'Sin rubro') AS rubro, c.rubro_id, c.pais, c.estado, COUNT(*) AS n
               FROM contactos c
          LEFT JOIN rubros r ON r.id = c.rubro_id
                    {$where}
           GROUP BY c.rubro_id, r.nombre, c.pais, c.estado"
        );
        $st->execute($params);

        $grupos = [];
        foreach ($st->fetchAll() as $f) {
            $clave = ($f['rubro_id'] ?? 'sin') . '|' . $f['pais'];
            $grupos[$clave] ??= [
                'rubro'            => $f['rubro'],
                'rubro_id'         => $f['rubro_id'] !== null ? (int) $f['rubro_id'] : null,
                'pais'             => $f['pais'],
                'bandera'          => Telefono::PAISES[$f['pais']]['bandera'] ?? '',
                'total'            => 0,
                'interesado'       => 0,
                'medio_interesado' => 0,
                'reunion'          => 0,
                'no_interesado'    => 0,
                'otros'            => 0,
                'convertidos'      => 0,
            ];
            $n = (int) $f['n'];
            $grupos[$clave]['total'] += $n;
            if (in_array($f['estado'], self::BASICOS, true)) {
                $grupos[$clave][$f['estado']] += $n;
            } else {
                $grupos[$clave]['otros'] += $n;
            }
            if (in_array($f['estado'], $conversion, true)) {
                $grupos[$clave]['convertidos'] += $n;
            }
        }

        $filas = array_map(static function (array $g): array {
            $g['conversion'] = $g['total'] > 0 ? round($g['convertidos'] * 100 / $g['total'], 1) : 0.0;
            return $g;
        }, array_values($grupos));

        usort($filas, static fn (array $a, array $b): int =>
            [$b['conversion'], $b['total'], $a['rubro']] <=> [$a['conversion'], $a['total'], $b['rubro']]);

        return $filas;
    }

    /**
     * Últimos 12 meses terminando en el periodo elegido.
     */
    public static function evolucion(array $filtros): array
    {
        $hasta = $filtros['periodo'];
        $desde = self::desplazarPeriodo($hasta, -11);
        $periodos = [];
        for ($i = 0; $i < 12; $i++) {
            $periodos[] = self::desplazarPeriodo($desde, $i);
        }

        unset($filtros['periodo']);
        [$where, $params] = ContactoRepository::filtros($filtros);
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'c.periodo BETWEEN :desde AND :hasta';
        $params[':desde'] = $desde;
        $params[':hasta'] = $hasta;

        $st = Database::pdo()->prepare(
            "SELECT c.periodo, c.pais, c.estado, COUNT(*) AS n FROM contactos c{$where} GROUP BY c.periodo, c.pais, c.estado"
        );
        $st->execute($params);

        $matriz = [];
        $conDatos = [];
        foreach ($st->fetchAll() as $f) {
            $matriz[$f['periodo']][$f['pais']][$f['estado']] = (int) $f['n'];
            $conDatos[$f['estado']] = true;
        }

        $conversion = EstadoRepository::clavesConversion();
        $estados = array_values(array_unique(array_merge(self::BASICOS, array_keys($conDatos))));

        $filas = [];
        foreach ($periodos as $p) {
            $fila = ['periodo' => $p, 'etiqueta' => nombre_periodo($p, true), 'total' => 0, 'otros' => 0, 'estados' => []];
            foreach ($estados as $e) {
                $fila['estados'][$e] = 0;
            }
            $convertidos = 0;
            foreach (array_keys(Telefono::PAISES) as $iso) {
                $porEstado = $matriz[$p][$iso] ?? [];
                $totalPais = array_sum($porEstado);
                $convPais = 0;
                foreach ($porEstado as $e => $n) {
                    $fila['estados'][$e] = ($fila['estados'][$e] ?? 0) + $n;
                    if (in_array($e, $conversion, true)) {
                        $convPais += $n;
                    }
                }
                $fila[$iso] = $totalPais;
                $fila['conv_' . $iso] = $totalPais > 0 ? round($convPais * 100 / $totalPais, 1) : 0.0;
                $fila['total'] += $totalPais;
                $convertidos += $convPais;
            }
            foreach (self::BASICOS as $e) {
                $fila[$e] = $fila['estados'][$e] ?? 0;
            }
            $fila['otros'] = $fila['total'] - array_sum(array_map(static fn (string $e): int => $fila[$e], self::BASICOS));
            $fila['conversion'] = $fila['total'] > 0 ? round($convertidos * 100 / $fila['total'], 1) : 0.0;
            $filas[] = $fila;
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'estados_con_datos' => array_keys($conDatos), 'filas' => $filas];
    }

    /**
     * Lee y valida los filtros de la URL. Un asesor siempre queda limitado a sus propios contactos.
     */
    public static function filtrosDesdeRequest(array $q, array $usuario): array
    {
        $anio = (int) ($q['anio'] ?? date('Y'));
        $mes = (int) ($q['mes'] ?? date('n'));
        if ($anio < 2020 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }

        $f = ['periodo' => self::periodo($anio, $mes), 'anio' => $anio, 'mes' => $mes];

        $pais = strtoupper((string) ($q['pais'] ?? 'todos'));
        if (isset(Telefono::PAISES[$pais])) {
            $f['pais'] = $pais;
        }
        $estado = (string) ($q['estado'] ?? '');
        if ($estado !== '' && EstadoRepository::porClave($estado) !== null) {
            $f['estado'] = $estado;
        }
        $rubro = (int) ($q['rubro_id'] ?? 0);
        if ($rubro > 0 || $rubro === -1) {
            $f['rubro_id'] = $rubro;
        }

        if ($usuario['rol'] === 'admin') {
            $asesor = (int) ($q['usuario_id'] ?? 0);
            if ($asesor > 0) {
                $f['usuario_id'] = $asesor;
            }
        } else {
            $f['usuario_id'] = (int) $usuario['id'];
        }

        return $f;
    }
}
