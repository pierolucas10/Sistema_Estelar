<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

// Solo por POST con token CSRF, para que nadie pueda cerrar tu sesión con un enlace.
if (!Http::esPost() || !Csrf::valido($_POST['csrf'] ?? null)) {
    Http::redirigir('index.php');
}

Auth::cerrarSesion();
Auth::iniciarSesion();
Vista::flash('exito', 'Cerraste sesión. ¡Hasta pronto! 👋');
Http::redirigir('login.php');
