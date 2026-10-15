<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (Auth::usuario()) {
    Http::redirigir('index.php');
}

$error = null;
$email = '';

if (Http::esPost()) {
    Csrf::verificarFormulario();
    $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 150);
    $password = mb_substr((string) ($_POST['password'] ?? ''), 0, 200);

    $resultado = Auth::intentarLogin($email, $password);
    if ($resultado['ok']) {
        Http::redirigir('index.php');
    }
    $error = $resultado['mensaje'];
}

$flash = Vista::flash();
$app = (string) config('app.nombre');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a2259">
  <title>Iniciar sesión · <?= e($app) ?></title>
  <link rel="icon" href="<?= Vista::asset('assets/logo.svg') ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Michroma&family=Nunito:wght@400;600;700;800;900&display=swap">
  <link rel="stylesheet" href="<?= Vista::asset('assets/styles.css') ?>">
</head>
<body class="pagina-login">
  <main class="login">
    <section class="login__tarjeta">
      <div class="login__marca">
        <span class="login__emblema"><?= Vista::logo('logo logo--grande') ?></span>
        <h1 class="login__titulo"><?= e($app) ?></h1>
        <p class="login__lema">Software empresarial</p>
        <p>Prospección por voz · Perú y Colombia</p>
      </div>

      <?php if ($error): ?>
        <div class="aviso aviso--error" role="alert"><?= e($error) ?></div>
      <?php elseif ($flash): ?>
        <div class="aviso aviso--<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensaje']) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" class="formulario" autocomplete="on">
        <?= Csrf::campo() ?>
        <label class="campo">
          <span>Correo</span>
          <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username" maxlength="150">
        </label>
        <label class="campo">
          <span>Contraseña</span>
          <input type="password" name="password" required autocomplete="current-password" maxlength="200">
        </label>
        <button type="submit" class="btn btn--primario btn--bloque">Entrar</button>
      </form>
      <?php if (Sso::habilitado() && (string) config('sso.url_estelar') !== ''): ?>
        <a class="btn btn--fantasma btn--bloque" href="<?= e((string) config('sso.url_estelar')) ?>">Entrar con mi cuenta de Estelar</a>
      <?php endif; ?>
      <p class="login__nota">Perú <span class="bandera bandera--PE" aria-hidden="true"></span> · Colombia <span class="bandera bandera--CO" aria-hidden="true"></span> — Conexión segura (HTTPS)</p>
    </section>
  </main>
  <script src="<?= Vista::asset('assets/comun.js') ?>"></script>
</body>
</html>
