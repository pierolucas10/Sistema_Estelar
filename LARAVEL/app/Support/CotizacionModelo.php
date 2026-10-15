<?php

namespace App\Support;

/**
 * Contenido base del modelo de cotización ESTELAR:
 * términos y condiciones generales y plantillas de "Descripción del sistema web".
 */
class CotizacionModelo
{
    public const TIPOS_VISITA = ['PLAN MENSUAL', 'PAGO ÚNICO', 'PLAN ANUAL', 'VISITA TÉCNICA', 'SOPORTE REMOTO'];

    /** Formas de pago rápidas. Cada parte: concepto + porcentaje (deben sumar 100). */
    public const PLANES_PAGO = [
        'unico' => ['nombre' => 'Pago único', 'partes' => [
            ['concepto' => 'Pago único al aceptar la cotización', 'porcentaje' => 100],
        ]],
        '50-50' => ['nombre' => '50% inicio · 50% final', 'partes' => [
            ['concepto' => 'Adelanto al iniciar', 'porcentaje' => 50],
            ['concepto' => 'Saldo a la entrega', 'porcentaje' => 50],
        ]],
        '40-30-30' => ['nombre' => '40% · 30% · 30%', 'partes' => [
            ['concepto' => 'Adelanto al iniciar', 'porcentaje' => 40],
            ['concepto' => 'Avance del desarrollo', 'porcentaje' => 30],
            ['concepto' => 'Saldo a la entrega', 'porcentaje' => 30],
        ]],
    ];

    /**
     * Términos y condiciones generales. {dias} se reemplaza por la vigencia de la cotización.
     *
     * @return array<int, array{titulo: string, texto: string}>
     */
    public static function terminos(int $dias = 5): array
    {
        $clausulas = [
            ['PRIMERO. OBJETO', 'La presente cotización tiene por objeto establecer las condiciones comerciales, técnicas y legales bajo las cuales EL PROVEEDOR pone a disposición de EL CLIENTE los sistemas empresariales, licencias de software, módulos, desarrollos, servicios profesionales, implementación, capacitación, soporte y/o mantenimiento que se describen en el documento de cotización correspondiente (en lo sucesivo, los "Servicios").'],
            ['SEGUNDO. NATURALEZA DE LA COTIZACIÓN', 'La presente cotización no constituye una oferta irrevocable ni un contrato definitivo, sino una propuesta comercial sujeta a aceptación expresa por parte de EL CLIENTE y a la disponibilidad técnica y comercial de EL PROVEEDOR. La aceptación implica la adhesión total e incondicional a los presentes Términos y Condiciones.'],
            ['TERCERO. VIGENCIA', 'La cotización tendrá una vigencia de {dias} días naturales contados a partir de la fecha de emisión. Una vez vencido dicho plazo, EL PROVEEDOR se reserva el derecho de modificar precios, alcances y condiciones sin responsabilidad alguna.'],
            ['CUARTO. PRECIO E IMPUESTOS', 'Los precios establecidos en la cotización se expresan en soles y no incluyen impuestos, derechos, contribuciones, tasas o gravámenes de cualquier naturaleza, los cuales serán cubiertos por EL CLIENTE conforme a la legislación aplicable.'],
            ['QUINTO. CONDICIONES DE PAGO', 'EL CLIENTE se obliga a pagar el precio conforme a las condiciones establecidas en la cotización. El incumplimiento en los pagos faculta a EL PROVEEDOR a suspender o cancelar los Servicios, sin que ello genere responsabilidad, penalización o indemnización alguna.'],
            ['SEXTO. ENTREGA, IMPLEMENTACIÓN Y PLAZOS', "Los plazos de entrega e implementación son estimados y comenzarán a computarse únicamente a partir de: a) La aceptación escrita de la cotización; b) La recepción del pago inicial correspondiente; y c) La entrega completa de la información requerida por parte de EL CLIENTE.\nCualquier retraso imputable a EL CLIENTE suspenderá automáticamente los plazos sin responsabilidad para EL PROVEEDOR."],
            ['SÉPTIMO. LICENCIA DE USO Y PROPIEDAD INTELECTUAL', 'EL PROVEEDOR concede a EL CLIENTE una licencia de uso exclusiva, intransferible y limitada del software objeto de la cotización. En ningún caso se entenderá que existe cesión de derechos patrimoniales. Todos los derechos de propiedad intelectual corresponden a EL PROVEEDOR o a los titulares originales del software.'],
            ['OCTAVO. PROHIBICIONES', 'EL CLIENTE se obliga a no copiar, reproducir, modificar, descompilar, realizar ingeniería inversa, sublicenciar, ceder o distribuir el software, total o parcialmente, sin autorización previa y por escrito de EL PROVEEDOR.'],
            ['NOVENO. SOPORTE Y MANTENIMIENTO', 'Los servicios de soporte técnico y mantenimiento solo se prestarán cuando se encuentren expresamente contratados. Su alcance, niveles de servicio, horarios y condiciones se establecerán en el documento correspondiente o contrato independiente.'],
            ['DÉCIMO. GARANTÍAS', 'EL PROVEEDOR garantiza que los Servicios se prestarán conforme a prácticas profesionales generalmente aceptadas. No se garantiza que el software sea ininterrumpido o libre de errores, ni que cumpla con necesidades particulares no expresamente pactadas.'],
            ['DÉCIMO PRIMERO. LIMITACIÓN DE RESPONSABILIDAD', 'EL PROVEEDOR no será responsable por daños indirectos, incidentales, especiales, punitivos o consecuenciales, incluyendo pérdida de datos, lucro cesante o interrupción del negocio. La responsabilidad total de EL PROVEEDOR se limitará, en cualquier caso, al monto efectivamente pagado por EL CLIENTE.'],
            ['DÉCIMO SEGUNDO. CONFIDENCIALIDAD', 'Las partes se obligan a mantener confidencial toda la información técnica, comercial, financiera o estratégica a la que tengan acceso con motivo de la presente cotización, aun después de concluida la relación comercial.'],
            ['DÉCIMO TERCERO. TERMINACIÓN', 'EL PROVEEDOR podrá dar por terminada la relación comercial de manera inmediata en caso de incumplimiento por parte de EL CLIENTE, sin perjuicio de exigir el pago de los montos adeudados y los daños y perjuicios correspondientes.'],
            ['DÉCIMO CUARTO. MODIFICACIONES', 'Cualquier modificación al alcance, precio o condiciones deberá constar por escrito y ser aceptada por ambas partes. Ninguna comunicación verbal tendrá validez legal.'],
            ['DÉCIMO QUINTO. CESIÓN', 'EL CLIENTE no podrá ceder los derechos y obligaciones derivados de la presente cotización sin autorización previa y por escrito de EL PROVEEDOR.'],
            ['DÉCIMO SEXTO. LEGISLACIÓN APLICABLE Y JURISDICCIÓN', 'La presente cotización se regirá e interpretará conforme a las leyes de Perú, sometiéndose las partes a la jurisdicción de los tribunales competentes de Huancayo, renunciando a cualquier otro fuero que pudiera corresponderles.'],
            ['DÉCIMO SÉPTIMO. ACEPTACIÓN', 'La firma, aceptación electrónica o confirmación expresa de la cotización implica la aceptación total de los presentes Términos y Condiciones.'],
        ];

        return array_map(fn ($c) => [
            'titulo' => $c[0],
            'texto'  => str_replace('{dias}', (string) $dias, $c[1]),
        ], $clausulas);
    }

    /** Texto plano de los términos (para el campo editable). */
    public static function terminosTexto(int $dias = 5): string
    {
        return collect(self::terminos($dias))
            ->map(fn ($c) => $c['titulo'] . "\n" . $c['texto'])
            ->implode("\n\n");
    }

    /**
     * Convierte el texto plano de términos en cláusulas (título en mayúsculas + párrafo).
     *
     * @return array<int, array{titulo: string, texto: string}>
     */
    public static function parsearTerminos(string $texto): array
    {
        $bloques = preg_split("/\n\s*\n/", trim(str_replace("\r", '', $texto)));
        return array_values(array_filter(array_map(function ($bloque) {
            $lineas = explode("\n", trim($bloque), 2);
            $primera = trim($lineas[0]);
            $esTitulo = isset($lineas[1]) && mb_strtoupper($primera) === $primera && mb_strlen($primera) <= 90;
            return [
                'titulo' => $esTitulo ? $primera : '',
                'texto'  => trim($esTitulo ? $lineas[1] : $bloque),
            ];
        }, $bloques), fn ($c) => $c['texto'] !== '' || $c['titulo'] !== ''));
    }

    /**
     * Plantillas de "Descripción del sistema web" para empezar rápido.
     *
     * @return array<string, array{nombre: string, titulo: string, items: string[]}>
     */
    public static function plantillas(): array
    {
        return [
            'almacen' => [
                'nombre' => 'Sistema de almacén',
                'titulo' => 'IMPLEMENTACION ADMINISTRATIVA',
                'items'  => [
                    'Centralización de la información del almacén, productos, existencias, ingresos y egresos en un solo sistema, permitiendo una gestión organizada y eficiente.',
                    'Registro y administración de productos, incluyendo nombre, código, descripción, categoría, unidad de medida, precio y cantidad disponible.',
                    'Control y actualización automática del stock, reflejando las existencias disponibles después de cada ingreso o egreso de productos.',
                    'Registro de ingresos de mercadería, permitiendo documentar cantidades recibidas, fecha de ingreso, descripción, procedencia y responsable del registro.',
                    'Registro y control de egresos de mercadería, permitiendo registrar las cantidades retiradas, fecha, motivo, destino y responsable de la operación.',
                    'Consulta del stock disponible por producto, facilitando la identificación de las cantidades existentes y los productos que requieren reposición.',
                    'Sistema de alertas automáticas por stock mínimo, notificando cuando un producto alcance o se encuentre por debajo de la cantidad mínima establecida.',
                    'Notificaciones dentro del sistema para informar sobre productos con bajo stock, productos agotados y situaciones que requieran atención por parte del responsable del almacén.',
                    'Configuración de niveles mínimos de stock por producto, permitiendo establecer cantidades de referencia para prevenir el desabastecimiento.',
                    'Historial de movimientos de almacén, permitiendo consultar todos los ingresos y egresos registrados, con sus respectivas fechas, cantidades, motivos y responsables.',
                    'Búsqueda y filtrado de productos y movimientos por nombre, código, categoría, tipo de movimiento y rango de fechas, facilitando la consulta de información.',
                    'Dashboard administrativo con indicadores de productos registrados, stock disponible, ingresos, egresos, productos con bajo stock y productos agotados.',
                    'Generación de reportes de existencias y movimientos de almacén, permitiendo consultar la información según periodos, productos, categorías y tipos de movimiento.',
                    'Registro de observaciones en los movimientos de almacén, facilitando el seguimiento de incidencias, devoluciones, ajustes y otras situaciones relacionadas con los productos.',
                    'Control de ajustes de inventario para registrar diferencias entre las existencias físicas y las cantidades registradas en el sistema, conservando el historial de las modificaciones.',
                    'Gestión de usuarios y permisos de acceso, permitiendo asignar responsabilidades y restringir las operaciones según el perfil de cada usuario.',
                    'Identificación del usuario responsable de cada ingreso, egreso o ajuste, garantizando la trazabilidad de las operaciones realizadas.',
                    'Interfaz intuitiva y de fácil uso, diseñada para facilitar el registro, consulta y control de las operaciones diarias del almacén.',
                    'Sistema multidispositivo, accesible desde computadoras, tablets y dispositivos móviles mediante un navegador web.',
                    'Seguridad de la información mediante autenticación de usuarios, control de permisos y validación de las operaciones registradas.',
                    'SISTEMA ESCALABLE, desarrollado para permitir la incorporación de nuevos módulos, funcionalidades e integraciones conforme evolucionen las necesidades de la empresa.',
                    'SISTEMA PERSONALIZADO, adaptado a los procesos operativos y requerimientos específicos de la empresa, evitando funcionalidades innecesarias y priorizando un control eficiente del almacén.',
                ],
            ],
            'base' => [
                'nombre' => 'Puntos generales (cualquier sistema)',
                'titulo' => 'IMPLEMENTACION ADMINISTRATIVA',
                'items'  => [
                    'Gestión de usuarios y permisos de acceso, permitiendo asignar responsabilidades y restringir las operaciones según el perfil de cada usuario.',
                    'Dashboard administrativo con indicadores clave para la toma de decisiones.',
                    'Generación de reportes según periodos, categorías y filtros personalizados.',
                    'Interfaz intuitiva y de fácil uso, diseñada para facilitar el registro, consulta y control de las operaciones diarias.',
                    'Sistema multidispositivo, accesible desde computadoras, tablets y dispositivos móviles mediante un navegador web.',
                    'Seguridad de la información mediante autenticación de usuarios, control de permisos y validación de las operaciones registradas.',
                    'SISTEMA ESCALABLE, desarrollado para permitir la incorporación de nuevos módulos, funcionalidades e integraciones conforme evolucionen las necesidades de la empresa.',
                    'SISTEMA PERSONALIZADO, adaptado a los procesos operativos y requerimientos específicos de la empresa.',
                ],
            ],
        ];
    }
}
