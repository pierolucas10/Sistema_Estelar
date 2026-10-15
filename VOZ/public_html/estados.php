<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

Auth::requerirAdmin();

if (Http::esPost()) {
    Csrf::verificarFormulario();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        switch ($accion) {
            case 'crear':
                $e = EstadoRepository::crear(
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['palabras'] ?? ''),
                    !empty($_POST['conversion'])
                );
                Vista::flash($e['nuevo'] ? 'exito' : 'info', $e['nuevo']
                    ? "Estado «{$e['nombre']}» creado ✨"
                    : "Ya existía el estado «{$e['nombre']}».");
                break;

            case 'editar':
                EstadoRepository::actualizar(
                    $id,
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['palabras'] ?? ''),
                    (string) ($_POST['color'] ?? '#64748b'),
                    !empty($_POST['conversion'])
                );
                Vista::flash('exito', 'Estado actualizado.');
                break;

            case 'activo':
                EstadoRepository::cambiarActivo($id, ($_POST['activo'] ?? '0') === '1');
                Vista::flash('exito', ($_POST['activo'] ?? '0') === '1' ? 'Estado activado.' : 'Estado desactivado (sus contactos se conservan).');
                break;

            case 'eliminar':
                EstadoRepository::eliminar($id);
                Vista::flash('exito', 'Estado eliminado.');
                break;

            default:
                Vista::flash('error', 'Acción no reconocida.');
        }
    } catch (InvalidArgumentException $e) {
        Vista::flash('error', $e->getMessage());
    }

    Http::redirigir('estados.php');
}

$estados = EstadoRepository::todos();
$conteos = EstadoRepository::conteos();

Vista::cabecera('Estados', 'estados');
?>
<section class="saludo">
  <div>
    <h1>Estados de contacto 🏷️</h1>
    <p>Crea los estados que usa tu equipo. Al dictar, el sistema reconoce el <strong>nombre</strong> del estado y sus <strong>palabras clave</strong>.</p>
  </div>
</section>

<section class="tarjeta">
  <h2>Nuevo estado</h2>
  <form method="post" action="estados.php" class="formulario formulario--grid">
    <?= Csrf::campo() ?>
    <input type="hidden" name="accion" value="crear">
    <label class="campo"><span>Nombre</span>
      <input type="text" name="nombre" maxlength="60" required placeholder="Ej. No contesta"></label>
    <label class="campo"><span>Palabras clave <small>(opcional, separadas por coma)</small></span>
      <input type="text" name="palabras" maxlength="255" placeholder="Ej. no responde, no contestó"></label>
    <label class="check check--campo"><input type="checkbox" name="conversion" value="1"> Cuenta como conversión</label>
    <button type="submit" class="btn btn--primario">+ Crear estado</button>
  </form>
</section>

<section class="tarjeta">
  <header class="tarjeta__cabecera">
    <h2>Todos los estados</h2>
    <span class="pildora"><?= count($estados) ?></span>
  </header>
  <p class="texto-suave">Los 4 estados básicos no se pueden eliminar. Un estado con contactos se desactiva en lugar de eliminarse.</p>
  <div class="tabla-scroll">
    <table class="tabla tabla--estados">
      <thead><tr><th>Color</th><th>Nombre</th><th>Palabras clave para el dictado</th><th>Conversión</th><th></th><th class="num">Contactos</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($estados as $est):
            $fid = 'estado-' . $est['id'];
            $basico = in_array($est['clave'], EstadoRepository::BASICOS, true);
            $n = $conteos[$est['clave']] ?? 0; ?>
          <tr class="<?= $est['activo'] ? '' : 'fila--inactiva' ?>">
            <td><input type="color" name="color" value="<?= e($est['color']) ?>" form="<?= $fid ?>" aria-label="Color de <?= e($est['nombre']) ?>"></td>
            <td><input type="text" name="nombre" value="<?= e($est['nombre']) ?>" maxlength="60" required form="<?= $fid ?>" aria-label="Nombre"></td>
            <td><input type="text" name="palabras" value="<?= e($est['palabras'] ?? '') ?>" maxlength="255" form="<?= $fid ?>" aria-label="Palabras clave" placeholder="<?= $basico ? 'Ya reconoce sus frases habituales' : 'Opcional' ?>"></td>
            <td><label class="check"><input type="checkbox" name="conversion" value="1" form="<?= $fid ?>"<?= $est['cuenta_conversion'] ? ' checked' : '' ?>> Sí</label></td>
            <td>
              <form method="post" action="estados.php" id="<?= $fid ?>">
                <?= Csrf::campo() ?>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" value="<?= (int) $est['id'] ?>">
                <button type="submit" class="btn btn--secundario btn--chico">Guardar</button>
              </form>
            </td>
            <td class="num"><?= $n ?></td>
            <td>
              <?php if ($basico): ?>
                <span class="pildora pildora--chica">Básico</span>
              <?php else: ?>
                <form method="post" action="estados.php">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="accion" value="activo">
                  <input type="hidden" name="id" value="<?= (int) $est['id'] ?>">
                  <input type="hidden" name="activo" value="<?= $est['activo'] ? '0' : '1' ?>">
                  <button type="submit" class="btn btn--fantasma btn--chico"><?= $est['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!$basico && $n === 0): ?>
                <form method="post" action="estados.php" data-confirmar="¿Eliminar el estado «<?= e($est['nombre']) ?>»?">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $est['id'] ?>">
                  <button type="submit" class="btn btn--peligro btn--chico">Eliminar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
Vista::pie();
