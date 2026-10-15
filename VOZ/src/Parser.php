<?php
declare(strict_types=1);

/**
 * Interpreta la frase dictada:  [CÓDIGO + NÚMERO], [ESTADO], RUBRO [NOMBRE] (, NOTA [texto])
 * Es la misma lógica que assets/parser.js: el navegador muestra la vista previa y
 * el servidor vuelve a validar. Si cambias una, cambia la otra y corre los tests.
 */
final class Parser
{
    public const ESTADOS = [
        'interesado'       => 'Interesado',
        'medio_interesado' => 'Medio interesado',
        'reunion'          => 'Reunión',
        'no_interesado'    => 'No interesado',
    ];

    /**
     * Patrones de los 4 estados básicos. Si varios coinciden gana la frase más larga
     * ("no interesado" le gana a "interesado"); los estados creados en el sistema
     * (tabla estados) se suman a esta lista con su nombre y sus palabras clave.
     */
    private const PATRONES_ESTADO = [
        'no_interesado' => [
            'no\s+(?:le\s+)?interesad[oa]s?',
            'no\s+(?:le|les)\s+interesa',
            'no\s+(?:esta|estan)\s+interesad[oa]s?',
            'sin\s+interes',
        ],
        'medio_interesado' => [
            'medio\s+interesad[oa]s?',
            'mas\s+o\s+menos',
            'tal\s+vez',
            'lo\s+pensara',
            'lo\s+va\s+a\s+pensar',
            'quizas?',
            'indecis[oa]',
        ],
        'reunion' => [
            'reunion(?:es)?',
            'agendar',
            'agendad[oa]',
            'citas?',
        ],
        'interesado' => [
            'interesad[oa]s?',
            'le\s+interesa',
        ],
    ];

    private const PALABRAS_RUBRO = ['rubro', 'sector', 'giro'];
    private const PALABRAS_NOTA = ['nota', 'observacion', 'observaciones', 'comentario'];

    private const UNIDADES = [
        'cero' => 0, 'uno' => 1, 'un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
        'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9,
    ];
    private const ESPECIALES = [
        'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14, 'quince' => 15,
        'dieciseis' => 16, 'diecisiete' => 17, 'dieciocho' => 18, 'diecinueve' => 19,
        'veinte' => 20, 'veintiuno' => 21, 'veintiun' => 21, 'veintiuna' => 21, 'veintidos' => 22,
        'veintitres' => 23, 'veinticuatro' => 24, 'veinticinco' => 25, 'veintiseis' => 26,
        'veintisiete' => 27, 'veintiocho' => 28, 'veintinueve' => 29,
    ];
    private const DECENAS = [
        'treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50, 'sesenta' => 60,
        'setenta' => 70, 'ochenta' => 80, 'noventa' => 90,
    ];
    private const CENTENAS = [
        'cien' => 100, 'ciento' => 100, 'doscientos' => 200, 'doscientas' => 200,
        'trescientos' => 300, 'trescientas' => 300, 'cuatrocientos' => 400, 'cuatrocientas' => 400,
        'quinientos' => 500, 'quinientas' => 500, 'seiscientos' => 600, 'seiscientas' => 600,
        'setecientos' => 700, 'setecientas' => 700, 'ochocientos' => 800, 'ochocientas' => 800,
        'novecientos' => 900, 'novecientas' => 900,
    ];
    private const RELLENO = [
        'el', 'la', 'es', 'numero', 'num', 'nro', 'celular', 'cel', 'codigo', 'telefono', 'movil',
        'fijo', 'whatsapp', 'de', 'del', 'pais', 'mas', 'y', 'con', 'al', 'su', 'mi',
    ];

    private const TILDES = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * @param array<int, array{id: int|string, nombre: string}> $rubros  Rubros existentes para buscar coincidencia
     * @param array<int, array{clave: string, nombre: string, palabras?: ?string, orden?: int}> $estados  Estados de la BD (vacío = solo los 4 básicos)
     */
    public static function analizar(string $texto, array $rubros = [], ?string $paisConfirmado = null, array $estados = []): array
    {
        $patrones = self::patronesEstado($estados);

        $original = trim((string) preg_replace('/\s+/u', ' ', $texto));

        // Normalización 1:1 por carácter para poder recortar el texto original en las mismas posiciones.
        $bajo = mb_strtolower($original, 'UTF-8');
        $bajo = (string) preg_replace('/[,.;:!?¿¡"\'()\[\]\/\\\\_+\-]/u', ' ', $bajo);
        $norm = strtr($bajo, self::TILDES);
        $norm = (string) preg_replace('/[^\x00-\x7F]/u', ' ', $norm);

        // 1) Palabras clave de segmentos: rubro y nota.
        $marcas = [];
        foreach (['rubro' => self::PALABRAS_RUBRO, 'nota' => self::PALABRAS_NOTA] as $tipo => $palabras) {
            if (preg_match('/\b(?:' . implode('|', $palabras) . ')\b/', $norm, $m, PREG_OFFSET_CAPTURE)) {
                $marcas[] = ['tipo' => $tipo, 'inicio' => $m[0][1], 'fin' => $m[0][1] + strlen($m[0][0])];
            }
        }
        usort($marcas, static fn (array $a, array $b): int => $a['inicio'] <=> $b['inicio']);

        $largo = strlen($norm);
        $nucleo = substr($norm, 0, $marcas[0]['inicio'] ?? $largo);
        $segmentos = ['rubro' => null, 'nota' => null];
        foreach ($marcas as $i => $marca) {
            $hasta = $marcas[$i + 1]['inicio'] ?? $largo;
            $segmentos[$marca['tipo']] = [
                'norm' => substr($norm, $marca['fin'], $hasta - $marca['fin']),
                'orig' => mb_substr($original, $marca['fin'], $hasta - $marca['fin']),
            ];
        }

        // 2) "más" al inicio (de "+57") no es parte del número.
        $nucleo = (string) preg_replace('/^\s*mas\s+(?!o\s+menos\b)/', ' ', $nucleo);

        // 3) Estado.
        [$estado, $inicioEstado] = self::detectarEstado($nucleo, $patrones);
        $textoNumero = $estado !== null ? substr($nucleo, 0, $inicioEstado) : $nucleo;

        // Si el estado se dijo después del rubro ("rubro salud interesado"), se busca ahí.
        if ($estado === null && $segmentos['rubro'] !== null) {
            [$estadoRubro, $inicio] = self::detectarEstado($segmentos['rubro']['norm'], $patrones);
            if ($estadoRubro !== null) {
                $estado = $estadoRubro;
                $segmentos['rubro']['orig'] = mb_substr($segmentos['rubro']['orig'], 0, $inicio);
            }
        }

        // 4) Número.
        $conversion = self::convertirNumero($textoNumero);
        $telefono = Telefono::analizar($conversion['digitos'], $paisConfirmado);

        // 5) Rubro y nota.
        $rubro = $segmentos['rubro'] !== null ? self::limpiarRubro($segmentos['rubro']['orig']) : null;
        $nota = $segmentos['nota'] !== null ? self::limpiarNota($segmentos['nota']['orig']) : null;

        $rubroId = null;
        $rubroNuevo = false;
        $rubroSimilar = null;
        if ($rubro !== null && $rubros !== []) {
            $busqueda = self::buscarRubro($rubro, $rubros);
            if ($busqueda['exacto'] !== null) {
                $rubro = $busqueda['exacto']['nombre'];
                $rubroId = (int) $busqueda['exacto']['id'];
            } else {
                $rubroNuevo = true;
                $rubroSimilar = $busqueda['similar'];
            }
        }

        $errores = [];
        $advertencias = [];
        if (!$telefono['valido']) {
            $errores[] = $telefono['error'];
        }
        if ($estado === null) {
            $errores[] = 'No se reconoció el estado. Di por ejemplo: interesado, no interesado, reunión, no contesta…';
        }
        if ($conversion['desconocidas'] !== []) {
            $advertencias[] = 'No entendí: "' . implode('", "', $conversion['desconocidas']) . '" (se ignoró).';
        }

        return [
            'valido'         => $telefono['valido'] && $estado !== null,
            'texto'          => $original,
            'numero_texto'   => trim((string) preg_replace('/\s+/', ' ', $textoNumero)),
            'digitos'        => $conversion['digitos'],
            'telefono'       => $telefono,
            'pais'           => $telefono['pais'],
            'telefono_e164'  => $telefono['e164'],
            'telefono_local' => $telefono['local'],
            'estado'         => $estado,
            'rubro'          => $rubro,
            'rubro_id'       => $rubroId,
            'rubro_nuevo'    => $rubroNuevo,
            'rubro_similar'  => $rubroSimilar,
            'nota'           => $nota,
            'errores'        => $errores,
            'advertencias'   => $advertencias,
        ];
    }

    /**
     * Lista de patrones [clave, regex, orden].
     * Sin estados de la BD se usan solo los 4 básicos.
     */
    public static function patronesEstado(array $estados = []): array
    {
        $lista = [];
        if ($estados === []) {
            $orden = 0;
            foreach (self::PATRONES_ESTADO as $clave => $patrones) {
                $orden++;
                foreach ($patrones as $p) {
                    $lista[] = [$clave, $p, $orden];
                }
            }
            return $lista;
        }

        foreach ($estados as $e) {
            $clave = (string) $e['clave'];
            $orden = (int) ($e['orden'] ?? 100);
            $patrones = self::PATRONES_ESTADO[$clave] ?? [];
            $frases = isset(self::PATRONES_ESTADO[$clave]) ? [] : [(string) $e['nombre']];
            foreach (explode(',', (string) ($e['palabras'] ?? '')) as $frase) {
                $frases[] = $frase;
            }
            foreach ($frases as $frase) {
                $n = self::normalizarTexto($frase);
                if (strlen($n) >= 3) {
                    $patrones[] = str_replace(' ', '\s+', $n);
                }
            }
            foreach ($patrones as $p) {
                $lista[] = [$clave, $p, $orden];
            }
        }
        return $lista;
    }

    /**
     * Devuelve [estado|null, posición donde empieza la primera palabra de estado].
     * Si coinciden varios gana la frase más larga; a igual largo, el de menor orden.
     */
    private static function detectarEstado(string $texto, array $patrones): array
    {
        $mejor = null;
        $inicio = null;
        foreach ($patrones as [$clave, $patron, $orden]) {
            if (preg_match('/\b' . $patron . '\b/', $texto, $m, PREG_OFFSET_CAPTURE)) {
                $largo = strlen($m[0][0]);
                $inicio = $inicio === null ? $m[0][1] : min($inicio, $m[0][1]);
                if ($mejor === null || $largo > $mejor[1] || ($largo === $mejor[1] && $orden < $mejor[2])) {
                    $mejor = [$clave, $largo, $orden];
                }
            }
        }
        return [$mejor[0] ?? null, $inicio ?? strlen($texto)];
    }

    /**
     * Convierte palabras y dígitos mezclados en una cadena de dígitos.
     * "cincuenta y siete trescientos dos 2 cuatro" → "5730224"
     *
     * @return array{digitos: string, desconocidas: string[]}
     */
    public static function convertirNumero(string $texto): array
    {
        $texto = strtolower(strtr($texto, self::TILDES));
        $texto = (string) preg_replace('/(\d)([a-z])/', '$1 $2', $texto);
        $texto = (string) preg_replace('/([a-z])(\d)/', '$1 $2', $texto);
        $tokens = preg_split('/[^a-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $salida = '';
        $desconocidas = [];
        $n = count($tokens);
        $i = 0;

        while ($i < $n) {
            $t = $tokens[$i];

            if ($t === 'doble' || $t === 'triple') {
                $grupo = self::leerGrupo($tokens, $i + 1);
                if ($grupo !== null) {
                    $salida .= str_repeat($grupo[0], $t === 'doble' ? 2 : 3);
                    $i = $grupo[1];
                } else {
                    $desconocidas[] = $t;
                    $i++;
                }
                continue;
            }

            $grupo = self::leerGrupo($tokens, $i);
            if ($grupo !== null) {
                $salida .= $grupo[0];
                $i = $grupo[1];
                continue;
            }

            if (!in_array($t, self::RELLENO, true)) {
                $desconocidas[] = $t;
            }
            $i++;
        }

        return ['digitos' => $salida, 'desconocidas' => $desconocidas];
    }

    /**
     * Lee un grupo hablado (dígitos, unidad, decena, centena) desde la posición $i.
     *
     * @return array{0: string, 1: int}|null  [dígitos del grupo, siguiente posición]
     */
    private static function leerGrupo(array $tokens, int $i): ?array
    {
        $t = $tokens[$i] ?? null;
        if ($t === null) {
            return null;
        }
        if (ctype_digit($t)) {
            return [$t, $i + 1];
        }
        if (isset(self::CENTENAS[$t])) {
            $centena = self::CENTENAS[$t];
            if ($t !== 'cien') {
                $resto = self::leerMenorCien($tokens, $i + 1);
                if ($resto !== null && $resto[0] > 0) {
                    return [(string) ($centena + $resto[0]), $resto[1]];
                }
            }
            return [(string) $centena, $i + 1];
        }
        $menor = self::leerMenorCien($tokens, $i);
        return $menor !== null ? [(string) $menor[0], $menor[1]] : null;
    }

    /**
     * @return array{0: int, 1: int}|null  [valor 0-99, siguiente posición]
     */
    private static function leerMenorCien(array $tokens, int $i): ?array
    {
        $t = $tokens[$i] ?? null;
        if ($t === null) {
            return null;
        }
        if (isset(self::ESPECIALES[$t])) {
            return [self::ESPECIALES[$t], $i + 1];
        }
        if ($t === 'veinti') {
            $u = self::unidad($tokens[$i + 1] ?? null);
            if ($u !== null && $u > 0) {
                return [20 + $u, $i + 2];
            }
            return [20, $i + 1];
        }
        if (isset(self::DECENAS[$t])) {
            $decena = self::DECENAS[$t];
            if (($tokens[$i + 1] ?? null) === 'y') {
                $u = self::unidad($tokens[$i + 2] ?? null);
                if ($u !== null && $u > 0) {
                    return [$decena + $u, $i + 3];
                }
            }
            return [$decena, $i + 1];
        }
        if (isset(self::UNIDADES[$t])) {
            return [self::UNIDADES[$t], $i + 1];
        }
        return null;
    }

    private static function unidad(?string $t): ?int
    {
        if ($t === null) {
            return null;
        }
        if (isset(self::UNIDADES[$t])) {
            return self::UNIDADES[$t];
        }
        return (strlen($t) === 1 && ctype_digit($t)) ? (int) $t : null;
    }

    private static function limpiarRubro(string $texto): ?string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = (string) preg_replace('/[^\p{L}\p{N}&\s]/u', ' ', $texto);
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        $texto = (string) preg_replace('/^(?:(?:de|del|el|la|los|las|es)\s+)+/u', '', $texto);
        $texto = mb_substr($texto, 0, 100);
        return $texto === '' ? null : self::capitalizar($texto);
    }

    private static function limpiarNota(string $texto): ?string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto), " \t,.;:-");
        $texto = mb_substr($texto, 0, 255);
        return $texto === '' ? null : self::capitalizar($texto);
    }

    public static function capitalizar(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    // ---------------- Rubros ----------------

    /**
     * Minúsculas, sin tildes ni signos: "Construcción" → "construccion".
     */
    public static function normalizarTexto(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto, 'UTF-8'), self::TILDES);
        $texto = (string) preg_replace('/[^a-z0-9]+/', ' ', $texto);
        return trim($texto);
    }

    /**
     * Clave tolerante a plural/singular y género: "Restaurantes" y "restaurante" → "restaurant".
     */
    public static function claveRubro(string $texto): string
    {
        $palabras = explode(' ', self::normalizarTexto($texto));
        foreach ($palabras as &$p) {
            if (strlen($p) > 3 && str_ends_with($p, 's')) {
                $p = substr($p, 0, -1);
            }
            if (strlen($p) > 3 && in_array(substr($p, -1), ['a', 'e', 'o'], true)) {
                $p = substr($p, 0, -1);
            }
        }
        unset($p);
        return implode(' ', $palabras);
    }

    /**
     * @return array{exacto: ?array, similar: ?array}
     */
    public static function buscarRubro(string $nombre, array $rubros): array
    {
        $clave = self::claveRubro($nombre);
        if ($clave === '') {
            return ['exacto' => null, 'similar' => null];
        }

        $similar = null;
        $mejor = PHP_INT_MAX;
        foreach ($rubros as $r) {
            $otra = self::claveRubro((string) $r['nombre']);
            if ($otra === $clave) {
                return ['exacto' => ['id' => (int) $r['id'], 'nombre' => (string) $r['nombre']], 'similar' => null];
            }
            $distancia = levenshtein($clave, $otra);
            if ($distancia < $mejor) {
                $mejor = $distancia;
                $similar = ['id' => (int) $r['id'], 'nombre' => (string) $r['nombre']];
            }
        }

        $tolerancia = strlen($clave) >= 8 ? 2 : 1;
        return ['exacto' => null, 'similar' => $mejor <= $tolerancia ? $similar : null];
    }
}
