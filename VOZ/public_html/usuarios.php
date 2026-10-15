<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$admin = Auth::requerirAdmin();

if (Http::esPost()) {
    Csrf::verificarFormulario();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if (in_array($accion, ['rol', 'activo', 'password'], true)) {
            $objetivo = UsuarioRepository::porId($id);
            if (!$objetivo) {
                throw new InvalidArgumentException('Ese usuario ya no existe.');
            }
        }

        switch ($accion) {
            case 'crear':
                UsuarioRepository::crear(
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['rol'] ?? 'asesor')
                );
                Vista::flash('exito', 'Usuario creado. Ya puede iniciar sesión ⭐');
                break;

            case 'rol':
                $rol = (string) ($_POST['rol'] ?? '');
                if ($id === (int) $admin['id'] && $rol !== 'admin') {
                    throw new InvalidArgumentException('No puedes quitarte el rol de administrador a ti mismo.');
                }
                if ($objetivo['rol'] === 'admin' && $rol !== 'admin' && (int) $objetivo['activo'] === 1
                    && UsuarioRepository::contarAdminsActivos() <= 1) {
                    throw new InvalidArgumentException('Debe quedar al menos un administrador activo.');
                }
                UsuarioRepository::cambiarRol($id, $rol);
                Vista::flash('exito', "Rol de {$objetivo['nombre']} actualizado.");
                break;

            case 'activo':
                $activar = ($_POST['activo'] ?? '0') === '1';
                if (!$activar && $id === (int) $admin['id']) {
                    throw new InvalidArgumentException('No puedes desactivar tu propio usuario.');
                }
                if (!$activar && $objetivo['rol'] === 'admin' && UsuarioRepository::contarAdminsActivos() <= 1) {
                    throw new InvalidArgumentException('Debe quedar al menos un administrador activo.');
                }
                UsuarioRepository::cambiarActivo($id, $activar);
                Vista::flash('exito', $activar ? "{$objetivo['nombre']} fue reactivado." : "{$objetivo['nombre']} fue desactivado (sus contactos se conservan).");
                break;

            case 'password':
                $password = (string) ($_POST['password'] ?? '');
                UsuarioRepository::validarPassword($password);
                UsuarioRepository::cambiarPassword($id, $password);
                Vista::flash('exito', "Contraseña de {$objetivo['nombre']} actualizada.");
                break;

            default:
                Vista::flash('error', 'Acción no reconocida.');
        }
    } catch (InvalidArgumentException $e) {
        Vista::flash('error', $e->getMessage());
    }

    Http::redirigir('usuarios.php');
}

$usuarios = UsuarioRepository::listar();
$minimo = UsuarioRepository::LONGITUD_MINIMA_PASSWORD;

Vista::cabecera('Usuarios', 'usuarios');
?>
<section class="saludo">
  <div>
    <h1>Equipo 👥</h1>
    <p>Crea asesores, cambia roles y desactiva accesos. Los contactos de un usuario desactivado se conservan.</p>
  </div>
</section>

<section class="tarjeta">
  <h2>Nuevo usuario</h2>
  <form method="post" action="usuarios.php" class="formulario formulario--grid" autocomplete="off">
    <?= Csrf::campo() ?>
    <input type="hidden" name="accion" value="crear">
    <label class="campo"><span>Nombre</span><input type="text" name="nombre" maxlength="100" required></label>
    <label class="campo"><span>Correo</span><input type="email" name="email" maxlength="150" required></label>
    <label class="campo"><span>Contraseña <small>(mín. <?= $minimo ?>)</small></span>
      <input type="password" name="password" minlength="<?= $minimo ?>" maxlength="72" required autocomplete="new-password"></label>
    <label class="campo"><span>Rol</span>
      <select name="rol"><option value="asesor">Asesor</option><option value="admin">Administrador</option></select></label>
    <button type="submit" class="btn btn--primario">Crear usuario</button>
  </form>
</section>

<section class="tarjeta">
  <header class="tarjeta__cabecera">
    <h2>Usuarios</h2>
    <span class="pildora"><?= count($usuarios) ?></span>
  </header>
  <div class="tabla-scroll">
    <table class="tabla tabla--usuarios">
      <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th class="num">Contactos</th><th>Desde</th><th>Nueva contraseña</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): $soyYo = (int) $u['id'] === (int) $admin['id']; ?>
          <tr class="<?= (int) $u['activo'] ? '' : 'fila--inactiva' ?>">
            <td><strong><?= e($u['nombre']) ?></strong><?= $soyYo ? ' <span class="pildora pildora--chica">Tú</span>' : '' ?><br><small class="texto-suave"><?= e($u['email']) ?></small></td>
            <td>
              <form method="post" action="usuarios.php" class="editar-en-linea">
                <?= Csrf::campo() ?>
                <input type="hidden" name="accion" value="rol">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <label class="sr-only" for="rol-<?= (int) $u['id'] ?>">Rol</label>
                <select id="rol-<?= (int) $u['id'] ?>" name="rol" data-autoenviar<?= $soyYo ? ' disabled' : '' ?>>
                  <option value="asesor"<?= $u['rol'] === 'asesor' ? ' selected' : '' ?>>Asesor</option>
                  <option value="admin"<?= $u['rol'] === 'admin' ? ' selected' : '' ?>>Administrador</option>
                </select>
              </form>
            </td>
            <td>
              <?php if ($soyYo): ?>
                <span class="insignia insignia--interesado">Activo</span>
              <?php else: ?>
                <form method="post" action="usuarios.php"<?= (int) $u['activo'] ? ' data-confirmar="¿Desactivar a ' . e($u['nombre']) . '? Ya no podrá entrar."' : '' ?>>
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="accion" value="activo">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="activo" value="<?= (int) $u['activo'] ? '0' : '1' ?>">
                  <?php if ((int) $u['activo']): ?>
                    <button type="submit" class="btn btn--fantasma btn--chico">Desactivar</button>
                  <?php else: ?>
                    <button type="submit" class="btn btn--secundario btn--chico">Reactivar</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
            <td class="num"><?= (int) $u['contactos'] ?></td>
            <td><?= e(fecha_corta($u['creado_en'], false)) ?></td>
            <td>
              <form method="post" action="usuarios.php" class="editar-en-linea" autocomplete="off">
                <?= Csrf::campo() ?>
                <input type="hidden" name="accion" value="password">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <label class="sr-only" for="pw-<?= (int) $u['id'] ?>">Nueva contraseña</label>
                <input type="password" id="pw-<?= (int) $u['id'] ?>" name="password" minlength="<?= $minimo ?>" maxlength="72" required autocomplete="new-password" placeholder="Nueva">
                <button type="submit" class="btn btn--fantasma btn--chico">Cambiar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
Vista::pie();
