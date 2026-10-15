<?php
declare(strict_types=1);

/**
 * Pruebas del parser de dictado (no necesita base de datos ni config/database.php).
 *
 *   php tests/parser_test.php
 *
 * Los casos viven en tests/casos_parser.json y también los usa tests/parser_test.js,
 * así se garantiza que el parser del navegador y el del servidor respondan igual.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/Telefono.php';
require __DIR__ . '/../src/Parser.php';

mb_internal_encoding('UTF-8');

$datos = json_decode((string) file_get_contents(__DIR__ . '/casos_parser.json'), true, 512, JSON_THROW_ON_ERROR);
$rubros = $datos['rubros'];
$casos = $datos['casos'];

$color = stream_isatty(STDOUT);
$verde = static fn (string $t): string => $color ? "\033[32m{$t}\033[0m" : $t;
$rojo = static fn (string $t): string => $color ? "\033[31m{$t}\033[0m" : $t;

/**
 * Compara el resultado con lo esperado y devuelve la lista de diferencias.
 */
function comparar(array $r, array $esperado): array
{
    $fallas = [];
    foreach ($esperado as $campo => $valor) {
        switch ($campo) {
            case 'error_contiene':
                $todos = implode(' | ', $r['errores']);
                if (!str_contains($todos, $valor)) {
                    $fallas[] = "errores debía contener \"{$valor}\", obtuvo \"{$todos}\"";
                }
                break;
            case 'sugerencia_pais':
                $obtenido = $r['telefono']['sugerencia']['pais'] ?? null;
                if ($obtenido !== $valor) {
                    $fallas[] = 'sugerencia_pais: esperado ' . var_export($valor, true) . ', obtuvo ' . var_export($obtenido, true);
                }
                break;
            default:
                $obtenido = $r[$campo] ?? null;
                if ($obtenido !== $valor) {
                    $fallas[] = "{$campo}: esperado " . var_export($valor, true) . ', obtuvo ' . var_export($obtenido, true);
                }
        }
    }
    return $fallas;
}

$ok = 0;
$fallidos = 0;

echo PHP_EOL . "⭐ Estelar · pruebas del parser (" . count($casos) . " frases)" . PHP_EOL . PHP_EOL;

foreach ($casos as $n => $caso) {
    $r = Parser::analizar($caso['frase'], $rubros, null, !empty($caso['usar_estados']) ? $datos['estados'] : []);
    $fallas = comparar($r, $caso['esperado']);
    $num = str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT);

    if ($fallas === []) {
        $ok++;
        echo $verde('  ✔ ') . "{$num}. {$caso['descripcion']}" . PHP_EOL;
    } else {
        $fallidos++;
        echo $rojo('  ✘ ') . "{$num}. {$caso['descripcion']}" . PHP_EOL;
        echo "       frase: \"{$caso['frase']}\"" . PHP_EOL;
        foreach ($fallas as $f) {
            echo "       - {$f}" . PHP_EOL;
        }
    }
}

// Pruebas extra de Telefono con país confirmado por el usuario.
$extras = [
    ['954016836', 'PE', true, '+51954016836'],
    ['3022425340', 'CO', true, '+573022425340'],
    ['+51 954 016 836', 'CO', false, null],
    ['0051954016836', null, true, '+51954016836'],
];
foreach ($extras as [$numero, $pais, $valido, $e164]) {
    $t = Telefono::analizar($numero, $pais);
    $etiqueta = "Telefono::analizar('{$numero}', " . var_export($pais, true) . ')';
    if ($t['valido'] === $valido && $t['e164'] === $e164) {
        $ok++;
        echo $verde('  ✔ ') . $etiqueta . PHP_EOL;
    } else {
        $fallidos++;
        echo $rojo('  ✘ ') . $etiqueta . ' → ' . json_encode($t, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

echo PHP_EOL . ($fallidos === 0
    ? $verde("Todo en orden: {$ok} pruebas pasaron.")
    : $rojo("{$fallidos} prueba(s) fallaron, {$ok} pasaron.")) . PHP_EOL . PHP_EOL;

exit($fallidos === 0 ? 0 : 1);
