<?php
declare(strict_types=1);

/**
 * POST JSON — crea o actualiza un contacto. Todo se vuelve a validar en el servidor.
 *
 * Cuerpo: { telefono, pais?, estado, rubro?, crear_rubro?, nota?, texto_dictado?, modo: "crear"|"actualizar" }
 * Respuestas: 200 ok · 409 duplicado (con los datos del existente) · 422 dato inválido
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';

Http::soloMetodo('POST');
$usuario = Auth::requerirLogin();
Csrf::verificarApi();
$in = Http::entradaJson();

$texto = mb_substr(trim((string) ($in['texto_dictado'] ?? '')), 0, 1000);
$pais = isset($in['pais']) && $in['pais'] !== '' ? (string) $in['pais'] : null;
$modo = ($in['modo'] ?? 'crear') === 'actualizar' ? 'actualizar' : 'crear';

// 1) Teléfono
$telefono = Telefono::analizar(mb_substr((string) ($in['telefono'] ?? ''), 0, 40), $pais);
if (!$telefono['valido']) {
    Http::error((string) $telefono['error'], 422, ['campo' => 'telefono', 'sugerencia' => $telefono['sugerencia']]);
}

// 2) Estado
$estado = (string) ($in['estado'] ?? '');
$estadoInfo = EstadoRepository::porClave($estado);
if ($estadoInfo === null || $estadoInfo['activo'] !== 1) {
    Http::error('Elige un estado de la lista.', 422, ['campo' => 'estado']);
}

// 3) Rubro (opcional)
$rubro = null;
$nombreRubro = trim((string) ($in['rubro'] ?? ''));
if ($nombreRubro !== '') {
    try {
        $existente = RubroRepository::buscar(RubroRepository::limpiarNombre($nombreRubro));
        if ($existente !== null) {
            $rubro = $existente + ['nuevo' => false];
        } elseif (!empty($in['crear_rubro'])) {
            $rubro = RubroRepository::obtenerOCrear($nombreRubro);
        } else {
            $similar = Parser::buscarRubro($nombreRubro, RubroRepository::todos())['similar'];
            Http::error("El rubro «{$nombreRubro}» no existe. Confirma que quieres crearlo.", 422, [
                'campo'   => 'rubro',
                'codigo'  => 'rubro_nuevo',
                'similar' => $similar,
            ]);
        }
    } catch (InvalidArgumentException $e) {
        Http::error($e->getMessage(), 422, ['campo' => 'rubro']);
    }
}

// 4) Nota
$nota = trim((string) ($in['nota'] ?? ''));
$nota = $nota === '' ? null : mb_substr(strip_tags($nota), 0, 255);

// 5) Crear o actualizar
$existente = ContactoRepository::porTelefono((string) $telefono['e164']);

if ($existente !== null && $modo !== 'actualizar') {
    Http::error('Este número ya está registrado.', 409, ['duplicado' => ContactoRepository::presentar($existente)]);
}

try {
    if ($existente !== null) {
        ContactoRepository::actualizar(
            (int) $existente['id'],
            $estado,
            $rubro['id'] ?? null,
            $nota,
            $texto !== '' ? $texto : null,
            (int) $usuario['id']
        );
        $id = (int) $existente['id'];
        $accion = 'actualizado';
    } else {
        $id = ContactoRepository::crear([
            'pais'     => (string) $telefono['pais'],
            'e164'     => (string) $telefono['e164'],
            'local'    => (string) $telefono['local'],
            'estado'   => $estado,
            'rubro_id' => $rubro['id'] ?? null,
            'nota'     => $nota,
            'texto'    => $texto !== '' ? $texto : null,
        ], (int) $usuario['id']);
        $accion = 'creado';
    }
} catch (DuplicadoException $e) {
    // Otro asesor lo guardó en el mismo instante.
    Http::error('Este número acaba de ser registrado por otra persona.', 409, [
        'duplicado' => $e->contacto ? ContactoRepository::presentar($e->contacto) : null,
    ]);
}

Http::json([
    'ok'       => true,
    'accion'   => $accion,
    'contacto' => ContactoRepository::presentar((array) ContactoRepository::porId($id)),
    'rubro'    => $rubro,
]);
