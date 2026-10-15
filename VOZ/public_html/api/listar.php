<?php
declare(strict_types=1);

/**
 * GET — lista paginada de contactos.
 *   ?hoy=1                          registros de hoy
 *   ?anio=2026&mes=10               un mes
 *   &pais=PE|CO|todos &estado= &rubro_id= &usuario_id= (solo admin) &q=954 &pagina=1 &por_pagina=25
 * Un asesor solo ve sus propios contactos.
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('GET');
$usuario = Auth::requerirLogin();

if (!empty($_GET['hoy'])) {
    $filtros = ['hoy' => true];
    if ($usuario['rol'] !== 'admin') {
        $filtros['usuario_id'] = (int) $usuario['id'];
    }
} else {
    $filtros = ReporteRepository::filtrosDesdeRequest($_GET, $usuario);
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $filtros['q'] = mb_substr($q, 0, 20);
}

$resultado = ContactoRepository::listar(
    $filtros,
    (int) ($_GET['pagina'] ?? 1),
    (int) ($_GET['por_pagina'] ?? 25)
);

Http::json(['ok' => true] + $resultado);
