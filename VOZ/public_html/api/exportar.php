<?php
declare(strict_types=1);

/**
 * GET — descarga CSV (UTF-8 con BOM para que Excel muestre bien las tildes).
 *   ?tipo=detalle&anio=2026&mes=10&pais=...   contactos del mes
 *   ?tipo=resumen&anio=2026&mes=10&pais=...   resumen de los últimos 12 meses
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('GET');
$usuario = Auth::requerirLogin();

$filtros = ReporteRepository::filtrosDesdeRequest($_GET, $usuario);
$tipo = ($_GET['tipo'] ?? 'detalle') === 'resumen' ? 'resumen' : 'detalle';
$separador = (string) config('csv.separador');

/** Evita que Excel interprete textos libres como fórmulas. */
$seguro = static function (?string $valor): string {
    $valor = (string) $valor;
    return ($valor !== '' && str_contains("=+-@\t\r", $valor[0])) ? "'" . $valor : $valor;
};

$sufijoPais = isset($filtros['pais']) ? '-' . strtolower($filtros['pais']) : '';
$nombre = $tipo === 'detalle'
    ? "estelar-contactos-{$filtros['periodo']}{$sufijoPais}.csv"
    : "estelar-resumen-12-meses-hasta-{$filtros['periodo']}{$sufijoPais}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");

if ($tipo === 'detalle') {
    fputcsv($out, ['pais', 'telefono_e164', 'telefono_local', 'estado', 'rubro', 'nota', 'asesor', 'fecha'], $separador, '"', '');
    foreach (ContactoRepository::recorrer($filtros) as $c) {
        fputcsv($out, [
            $c['pais'],
            $c['telefono_e164'],
            $c['telefono_local'],
            EstadoRepository::nombre($c['estado']),
            $seguro($c['rubro']),
            $seguro($c['nota']),
            $seguro($c['asesor']),
            $c['creado_en'],
        ], $separador, '"', '');
    }
} else {
    fputcsv($out, [
        'periodo', 'total', 'peru', 'colombia', 'interesado', 'medio_interesado', 'reunion',
        'no_interesado', 'otros_estados', 'conversion_pct', 'conversion_peru_pct', 'conversion_colombia_pct',
    ], $separador, '"', '');
    foreach (ReporteRepository::evolucion($filtros)['filas'] as $f) {
        fputcsv($out, [
            $f['periodo'], $f['total'], $f['PE'], $f['CO'], $f['interesado'], $f['medio_interesado'],
            $f['reunion'], $f['no_interesado'], $f['otros'], $f['conversion'], $f['conv_PE'], $f['conv_CO'],
        ], $separador, '"', '');
    }
}

fclose($out);
