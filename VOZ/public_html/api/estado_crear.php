<?php
declare(strict_types=1);

/**
 * POST JSON — crea un estado nuevo desde la pantalla de dictado (botón "+ Nuevo estado").
 * Cuerpo: { nombre, palabras?, conversion? }
 * Si ya existe un estado con ese nombre, lo devuelve en lugar de duplicarlo.
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('POST');
Auth::requerirLogin();
Csrf::verificarApi();
$in = Http::entradaJson();

try {
    $estado = EstadoRepository::crear(
        mb_substr((string) ($in['nombre'] ?? ''), 0, 80),
        isset($in['palabras']) ? mb_substr((string) $in['palabras'], 0, 300) : null,
        !empty($in['conversion'])
    );
} catch (InvalidArgumentException $e) {
    Http::error($e->getMessage(), 422);
}

Http::json([
    'ok'     => true,
    'nuevo'  => $estado['nuevo'],
    'estado' => [
        'clave'      => $estado['clave'],
        'nombre'     => $estado['nombre'],
        'palabras'   => $estado['palabras'],
        'color'      => $estado['color'],
        'conversion' => $estado['cuenta_conversion'] === 1,
        'orden'      => $estado['orden'],
        'basico'     => in_array($estado['clave'], EstadoRepository::BASICOS, true),
    ],
]);
