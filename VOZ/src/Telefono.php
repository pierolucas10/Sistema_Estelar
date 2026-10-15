<?php
declare(strict_types=1);

/**
 * Detección de país por código, validación de longitud y formato E.164.
 * Mantener en sincronía con assets/parser.js (EstelarParser.telefono).
 *
 *   Perú (51):     celular 9 dígitos que empieza en 9 · fijo Lima 1 + 7 dígitos
 *   Colombia (57): celular 10 dígitos que empieza en 3 · fijo 10 dígitos que empieza en 60
 */
final class Telefono
{
    public const PAISES = [
        'PE' => ['codigo' => '51', 'nombre' => 'Perú', 'bandera' => '🇵🇪'],
        'CO' => ['codigo' => '57', 'nombre' => 'Colombia', 'bandera' => '🇨🇴'],
    ];

    /**
     * @param string      $entrada         Número en cualquier formato (+51 954..., 51954..., 954...)
     * @param string|null $paisConfirmado  'PE' o 'CO' si el usuario ya eligió/confirmó el país
     */
    public static function analizar(string $entrada, ?string $paisConfirmado = null): array
    {
        $digitos = (string) preg_replace('/\D+/', '', $entrada);
        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        $res = [
            'valido'     => false,
            'digitos'    => $digitos,
            'pais'       => null,
            'e164'       => null,
            'local'      => null,
            'tipo'       => null,
            'error'      => null,
            'sugerencia' => null,
        ];

        if ($digitos === '') {
            $res['error'] = 'No se detectó ningún número de teléfono.';
            return $res;
        }

        $paisConfirmado = $paisConfirmado !== null ? strtoupper(trim($paisConfirmado)) : null;
        if ($paisConfirmado !== null && !isset(self::PAISES[$paisConfirmado])) {
            $paisConfirmado = null;
        }

        if ($paisConfirmado !== null) {
            $codigo = self::PAISES[$paisConfirmado]['codigo'];
            if (!str_starts_with($digitos, $codigo)) {
                $otro = $paisConfirmado === 'PE' ? 'CO' : 'PE';
                $codigoOtro = self::PAISES[$otro]['codigo'];
                if (str_starts_with($digitos, $codigoOtro) && self::validarLocal($otro, substr($digitos, 2))['valido']) {
                    $res['pais'] = $otro;
                    $res['error'] = sprintf(
                        'El número tiene el código %s (%s) pero se eligió %s.',
                        $codigoOtro,
                        self::PAISES[$otro]['nombre'],
                        self::PAISES[$paisConfirmado]['nombre']
                    );
                    return $res;
                }
                $digitos = $codigo . $digitos;
                $res['digitos'] = $digitos;
            }
        }

        foreach (self::PAISES as $iso => $pais) {
            if (!str_starts_with($digitos, $pais['codigo'])) {
                continue;
            }
            $local = substr($digitos, 2);
            $v = self::validarLocal($iso, $local);
            $res['pais'] = $iso;
            if (!$v['valido']) {
                $res['error'] = $v['error'];
                return $res;
            }
            return array_merge($res, [
                'valido' => true,
                'local'  => $local,
                'e164'   => '+' . $digitos,
                'tipo'   => $v['tipo'],
            ]);
        }

        $res['error'] = 'Falta el código de país (51 o 57) al inicio del número.';
        $res['sugerencia'] = self::sugerirPais($digitos);
        return $res;
    }

    /**
     * @return array{valido: bool, tipo: ?string, error: ?string}
     */
    public static function validarLocal(string $pais, string $local): array
    {
        $n = strlen($local);

        if ($pais === 'PE') {
            if ($n === 0) {
                return ['valido' => false, 'tipo' => null, 'error' => 'Después del 51 falta el número.'];
            }
            if ($local[0] === '9') {
                return $n === 9
                    ? ['valido' => true, 'tipo' => 'celular', 'error' => null]
                    : ['valido' => false, 'tipo' => null,
                       'error' => "Para Perú (celular) se esperan 9 dígitos después del 51, se recibieron {$n}."];
            }
            if ($local[0] === '1') {
                return $n === 8
                    ? ['valido' => true, 'tipo' => 'fijo', 'error' => null]
                    : ['valido' => false, 'tipo' => null,
                       'error' => "Para Perú (fijo de Lima) se esperan 8 dígitos después del 51 (1 + 7), se recibieron {$n}."];
            }
            return ['valido' => false, 'tipo' => null,
                    'error' => 'Después del 51 el número debe empezar con 9 (celular) o 1 (fijo de Lima).'];
        }

        if ($pais === 'CO') {
            if ($n === 0) {
                return ['valido' => false, 'tipo' => null, 'error' => 'Después del 57 falta el número.'];
            }
            $tipo = $local[0] === '3' ? 'celular' : (str_starts_with($local, '60') ? 'fijo' : null);
            if ($tipo === null) {
                return ['valido' => false, 'tipo' => null,
                        'error' => 'Después del 57 el número debe empezar con 3 (celular) o 60 (fijo).'];
            }
            return $n === 10
                ? ['valido' => true, 'tipo' => $tipo, 'error' => null]
                : ['valido' => false, 'tipo' => null,
                   'error' => "Para Colombia se esperan 10 dígitos después del 57, se recibieron {$n}."];
        }

        return ['valido' => false, 'tipo' => null, 'error' => 'País no soportado.'];
    }

    /**
     * Sugiere el país solo cuando el formato local es inequívoco.
     */
    public static function sugerirPais(string $digitos): ?array
    {
        $n = strlen($digitos);
        $pais = null;
        $detalle = '';

        if ($n === 9 && $digitos[0] === '9') {
            $pais = 'PE';
            $detalle = 'celular de Perú (9 dígitos que empiezan en 9)';
        } elseif ($n === 10 && $digitos[0] === '3') {
            $pais = 'CO';
            $detalle = 'celular de Colombia (10 dígitos que empiezan en 3)';
        } elseif ($n === 10 && str_starts_with($digitos, '60')) {
            $pais = 'CO';
            $detalle = 'fijo de Colombia (10 dígitos que empiezan en 60)';
        }

        if ($pais === null) {
            return null;
        }

        $e164 = '+' . self::PAISES[$pais]['codigo'] . $digitos;
        return [
            'pais'    => $pais,
            'e164'    => $e164,
            'local'   => $digitos,
            'mensaje' => "Parece un {$detalle}. ¿Confirmas que es " . self::formatear($e164) . '?',
        ];
    }

    /**
     * +51 954 016 836 · +51 1 234 5678 · +57 302 242 5340
     */
    public static function formatear(?string $e164): string
    {
        $d = (string) preg_replace('/\D+/', '', (string) $e164);
        if (str_starts_with($d, '51')) {
            $l = substr($d, 2);
            if (strlen($l) === 9) {
                return '+51 ' . substr($l, 0, 3) . ' ' . substr($l, 3, 3) . ' ' . substr($l, 6);
            }
            if (strlen($l) === 8) {
                return '+51 1 ' . substr($l, 1, 3) . ' ' . substr($l, 4);
            }
        }
        if (str_starts_with($d, '57') && strlen($d) === 12) {
            $l = substr($d, 2);
            return '+57 ' . substr($l, 0, 3) . ' ' . substr($l, 3, 3) . ' ' . substr($l, 6);
        }
        return $d === '' ? '' : '+' . $d;
    }

    public static function enlaceLlamada(string $e164): string
    {
        return 'tel:+' . preg_replace('/\D+/', '', $e164);
    }

    public static function enlaceWhatsapp(string $e164): string
    {
        return 'https://wa.me/' . preg_replace('/\D+/', '', $e164);
    }
}
