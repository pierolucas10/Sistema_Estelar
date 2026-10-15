<?php
declare(strict_types=1);

/**
 * GET ?telefono=+51954016836&pais=PE
 * Valida el número en el servidor y avisa si ya existe (para ofrecer "Actualizar" en la vista previa).
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('GET');
Auth::requerirLogin();

$pais = isset($_GET['pais']) && $_GET['pais'] !== '' ? (string) $_GET['pais'] : null;
$telefono = Telefono::analizar(mb_substr((string) ($_GET['telefono'] ?? ''), 0, 40), $pais);

$duplicado = null;
$historial = [];
if ($telefono['valido']) {
    $existente = ContactoRepository::porTelefono((string) $telefono['e164']);
    if ($existente !== null) {
        $duplicado = ContactoRepository::presentar($existente);
        $historial = array_map(static fn (array $h): array => [
            'estado_anterior' => $h['estado_anterior'] ? EstadoRepository::nombre($h['estado_anterior']) : null,
            'estado_nuevo'    => EstadoRepository::nombre($h['estado_nuevo']),
            'rubro_nuevo'     => $h['rubro_nuevo'],
            'usuario'         => $h['usuario'],
            'fecha'           => fecha_corta($h['creado_en']),
        ], array_slice(ContactoRepository::historial((int) $existente['id']), 0, 5));
    }
}

Http::json(['ok' => true, 'telefono' => $telefono, 'duplicado' => $duplicado, 'historial' => $historial]);
