<?php
declare(strict_types=1);

/**
 * GET ?anio=2026&mes=10&pais=PE|CO|todos&estado=&rubro_id=&usuario_id=
 * Devuelve todas las secciones del reporte del mes en un solo JSON.
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('GET');
$usuario = Auth::requerirLogin();

$filtros = ReporteRepository::filtrosDesdeRequest($_GET, $usuario);

Http::json(['ok' => true] + ReporteRepository::mensual($filtros));
