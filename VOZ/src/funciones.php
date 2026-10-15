<?php
declare(strict_types=1);

/**
 * Escapa cualquier texto para imprimirlo en HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSON seguro para incrustar dentro de <script type="application/json">.
 */
function json_html(mixed $valor): string
{
    return (string) json_encode(
        $valor,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

/**
 * Fecha legible dd/mm/aaaa hh:mm.
 */
function fecha_corta(?string $fecha, bool $conHora = true): string
{
    if (!$fecha) {
        return '';
    }
    $ts = strtotime($fecha);
    return $ts ? date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '';
}

const MESES_ES = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

function nombre_periodo(string $periodo, bool $corto = false): string
{
    [$anio, $mes] = array_map('intval', explode('-', $periodo));
    $nombre = MESES_ES[$mes] ?? (string) $mes;
    return ($corto ? mb_substr($nombre, 0, 3) : $nombre) . ' ' . $anio;
}
