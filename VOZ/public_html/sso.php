<?php
declare(strict_types=1);

/**
 * Entrada desde Estelar: recibe el token firmado, abre la sesión y manda al dictado.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$resultado = Sso::ingresar(mb_substr((string) ($_GET['token'] ?? ''), 0, 2048));
if ($resultado['ok']) {
    Http::redirigir('index.php');
}

http_response_code(403);
$app = (string) config('app.nombre');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingreso · <?= e($app) ?></title>
  <link rel="icon" href="<?= Vista::asset('assets/logo.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= Vista::asset('assets/styles.css') ?>">
</head>
<body class="pagina-login">
  <main class="login">
    <section class="login__tarjeta">
      <div class="login__marca">
        <span class="login__emblema"><?= Vista::logo('logo logo--grande') ?></span>
        <h1 class="login__titulo"><?= e($app) ?></h1>
      </div>
      <div class="aviso aviso--error" role="alert"><?= e($resultado['mensaje']) ?></div>
      <?php if ((string) config('sso.url_estelar') !== ''): ?>
        <a class="btn btn--primario btn--bloque" href="<?= e((string) config('sso.url_estelar')) ?>">Reintentar</a>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
