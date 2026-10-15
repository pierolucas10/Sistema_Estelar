<?php
declare(strict_types=1);

/**
 * Instalador de Estelar (solo por línea de comandos: SSH o "Terminal" de cPanel).
 *
 *   php install/install.php                 Crea tablas, rubros iniciales y el primer administrador
 *   php install/install.php --solo-admin    Solo crea otro administrador
 *   php install/install.php --verificar     Revisa requisitos y conexión, sin cambiar nada
 *
 * Datos del admin sin preguntas (útil en scripts):
 *   php install/install.php --nombre="Ana Pérez" --email=ana@empresa.com --password-file=/ruta/clave.txt
 * La contraseña nunca se escribe en el código ni en la línea de comandos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

$opciones = getopt('', ['solo-admin', 'verificar', 'nombre:', 'email:', 'password-file:']);

function salida(string $texto, string $tipo = 'info'): void
{
    $iconos = ['ok' => '✔', 'error' => '✘', 'info' => '•', 'titulo' => '⭐'];
    echo ($iconos[$tipo] ?? '•') . ' ' . $texto . PHP_EOL;
}

function preguntar(string $texto): string
{
    echo $texto;
    return trim((string) fgets(STDIN));
}

function preguntarOculto(string $texto): string
{
    echo $texto;
    $ocultar = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($ocultar) {
        shell_exec('stty -echo');
    }
    $valor = rtrim((string) fgets(STDIN), "\r\n");
    if ($ocultar) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $valor;
}

salida('Estelar · instalación', 'titulo');
echo PHP_EOL;

// 1) Requisitos
$errores = 0;
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    salida('Se necesita PHP 8.2 o superior (tienes ' . PHP_VERSION . ').', 'error');
    $errores++;
} else {
    salida('PHP ' . PHP_VERSION, 'ok');
}
foreach (['pdo_mysql', 'mbstring', 'json'] as $ext) {
    if (extension_loaded($ext)) {
        salida("Extensión {$ext}", 'ok');
    } else {
        salida("Falta la extensión {$ext}", 'error');
        $errores++;
    }
}
salida('url_sitio: ' . (config('app.url') ?: '(se detecta sola)'), 'ok');

foreach ([config('dir.logs'), config('dir.sesiones')] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        salida('Carpeta con escritura: ' . $dir, 'ok');
    } else {
        salida('No se puede escribir en ' . $dir . ' (dale permisos 750 al usuario del sitio).', 'error');
        $errores++;
    }
}

try {
    $pdo = Database::pdo();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    salida('Conexión a la base de datos (' . $version . ')', 'ok');
} catch (PDOException $e) {
    salida('No se pudo conectar a la base de datos: ' . $e->getMessage(), 'error');
    exit(1);
}

if ($errores > 0) {
    salida("Corrige los {$errores} problema(s) anteriores y vuelve a ejecutar.", 'error');
    exit(1);
}
if (isset($opciones['verificar'])) {
    salida('Verificación completa. No se hicieron cambios.', 'ok');
    exit(0);
}

// 2) Tablas y rubros
if (!isset($opciones['solo-admin'])) {
    $sql = (string) file_get_contents(__DIR__ . '/schema.sql');
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
    $sentencias = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));
    foreach ($sentencias as $sentencia) {
        $pdo->exec($sentencia);
    }
    salida('Tablas creadas/verificadas y rubros iniciales cargados.', 'ok');
}

// 3) Administrador
$hayAdmin = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn() > 0;
if ($hayAdmin && !isset($opciones['solo-admin']) && !isset($opciones['email'])) {
    salida('Ya existe al menos un administrador. Para crear otro usa --solo-admin.', 'info');
    echo PHP_EOL;
    salida('Listo. Abre tu sitio e inicia sesión.', 'titulo');
    exit(0);
}

echo PHP_EOL;
salida('Crear usuario administrador', 'titulo');
$nombre = (string) ($opciones['nombre'] ?? preguntar('  Nombre completo: '));
$email = (string) ($opciones['email'] ?? preguntar('  Correo: '));

if (isset($opciones['password-file'])) {
    $archivo = (string) $opciones['password-file'];
    if (!is_readable($archivo)) {
        salida('No se puede leer el archivo de contraseña.', 'error');
        exit(1);
    }
    $password = trim((string) file_get_contents($archivo));
} else {
    $password = preguntarOculto('  Contraseña (mínimo ' . UsuarioRepository::LONGITUD_MINIMA_PASSWORD . ' caracteres): ');
    $repetida = preguntarOculto('  Repite la contraseña: ');
    if ($password !== $repetida) {
        salida('Las contraseñas no coinciden.', 'error');
        exit(1);
    }
}

try {
    $id = UsuarioRepository::crear($nombre, $email, $password, 'admin');
} catch (InvalidArgumentException $e) {
    salida($e->getMessage(), 'error');
    exit(1);
}

salida("Administrador creado (id {$id}): {$email}", 'ok');
if (isset($opciones['password-file'])) {
    salida('Recuerda borrar el archivo de contraseña: ' . $opciones['password-file'], 'info');
}
echo PHP_EOL;
salida('Listo. Abre tu sitio e inicia sesión.', 'titulo');
