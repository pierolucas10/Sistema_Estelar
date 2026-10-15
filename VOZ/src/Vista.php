<?php
declare(strict_types=1);

/**
 * Plantilla común: cabecera con navegación, mensajes flash y pie.
 */
final class Vista
{
    public static function asset(string $ruta): string
    {
        // Las páginas viven en la raíz pública, así que su carpeta es la del sitio.
        $archivo = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) . '/' . ltrim($ruta, '/');
        $version = is_file($archivo) ? (string) filemtime($archivo) : '1';
        return e($ruta . '?v=' . $version);
    }

    public static function flash(?string $tipo = null, ?string $mensaje = null): ?array
    {
        if ($tipo !== null) {
            $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => (string) $mensaje];
            return null;
        }
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    /**
     * Emblema de Estelar Software Empresarial: hexágono claro con el ojo y la estrella de 4 puntas.
     */
    public static function logo(string $clase = 'logo'): string
    {
        static $n = 0;
        $id = 'estelar-g' . (++$n);
        return '<svg class="' . e($clase) . '" viewBox="0 0 80 56" aria-hidden="true" focusable="false">'
            . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="#ffffff"/><stop offset=".55" stop-color="#dcebff"/>'
            . '<stop offset="1" stop-color="#8fbdf0"/></linearGradient></defs>'
            . '<path d="M19 5h42l14 23-14 23H19L5 28z" fill="url(#' . $id . ')" stroke="url(#' . $id . ')" '
            . 'stroke-width="7" stroke-linejoin="round"/>'
            . '<path d="M13 28C25 12 55 12 67 28 55 44 25 44 13 28z" fill="#0b2a63"/>'
            . '<path d="M22 28C30 19 42 17 52 21 44 20 34 23 29 28 34 33 44 36 52 35 42 39 30 37 22 28z" fill="#1d4f9e" opacity=".9"/>'
            . '<path d="M40 14.5l3 10.5 10.5 3-10.5 3L40 41.5 37 31l-10.5-3L37 25z" fill="#ffffff"/></svg>';
    }

    /**
     * @param string $seccion  dictar | reportes | estados | rubros | usuarios
     */
    public static function cabecera(string $titulo, string $seccion): void
    {
        $u = Auth::usuario();
        $esAdmin = ($u['rol'] ?? '') === 'admin';
        $app = (string) config('app.nombre');
        $links = ['dictar' => ['index.php', 'Dictar'], 'reportes' => ['reportes.php', 'Reportes']];
        if ($esAdmin) {
            $links['estados'] = ['estados.php', 'Estados'];
            $links['rubros'] = ['rubros.php', 'Rubros'];
            $links['usuarios'] = ['usuarios.php', 'Usuarios'];
        }
        $flash = self::flash();
        $iniciales = '';
        foreach (array_slice(preg_split('/\s+/u', trim((string) ($u['nombre'] ?? ''))) ?: [], 0, 2) as $p) {
            $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <meta name="theme-color" content="#0a2259">
  <title><?= e($titulo) ?> · <?= e($app) ?></title>
  <link rel="icon" href="<?= self::asset('assets/logo.svg') ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Michroma&family=Nunito:wght@400;600;700;800;900&display=swap">
  <link rel="stylesheet" href="<?= self::asset('assets/styles.css') ?>">
</head>
<body class="seccion-<?= e($seccion) ?>">
  <header class="barra">
    <div class="barra__contenido">
      <a class="marca" href="index.php" aria-label="<?= e($app) ?> · inicio">
        <?= self::logo() ?>
        <span class="marca__texto"><strong><?= e($app) ?></strong><small>Prospección por voz</small></span>
      </a>
      <nav class="nav" aria-label="Principal">
        <?php foreach ($links as $clave => [$href, $texto]): ?>
          <a href="<?= e($href) ?>" class="nav__link<?= $clave === $seccion ? ' is-activo' : '' ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($u): ?>
        <div class="usuario">
          <span class="usuario__avatar" aria-hidden="true"><?= e($iniciales) ?></span>
          <span class="usuario__datos"><strong><?= e($u['nombre']) ?></strong><small><?= $esAdmin ? 'Administrador' : 'Asesor' ?></small></span>
          <?php if (empty($_SESSION['sso'])): ?>
          <form method="post" action="logout.php" class="usuario__salir">
            <?= Csrf::campo() ?>
            <button type="submit" class="btn btn--fantasma btn--chico">Salir</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </header>
  <main class="contenedor">
    <?php if ($flash): ?>
      <div class="aviso aviso--<?= e($flash['tipo']) ?>" role="status" data-cerrable><?= e($flash['mensaje']) ?></div>
    <?php endif; ?>
<?php
    }

    /**
     * @param string[] $scripts  rutas locales o URLs https
     */
    public static function pie(array $scripts = []): void
    {
        ?>
  </main>
  <footer class="pie">
    <span><?= self::logo('logo logo--mini') ?> <?= e((string) config('app.nombre')) ?> · Perú <span class="bandera bandera--PE" aria-hidden="true"></span> y Colombia <span class="bandera bandera--CO" aria-hidden="true"></span> · <?= date('Y') ?></span>
  </footer>
  <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
  <script src="<?= self::asset('assets/comun.js') ?>"></script>
  <?php foreach ($scripts as $s): ?>
    <?php if (str_starts_with($s, 'https://')): ?>
  <script src="<?= e($s) ?>" crossorigin="anonymous"></script>
    <?php else: ?>
  <script src="<?= self::asset($s) ?>"></script>
    <?php endif; ?>
  <?php endforeach; ?>
</body>
</html>
<?php
    }
}
