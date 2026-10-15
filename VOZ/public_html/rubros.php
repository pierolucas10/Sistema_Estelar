<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

Auth::requerirAdmin();

if (Http::esPost()) {
    Csrf::verificarFormulario();
    $accion = (string) ($_POST['accion'] ?? '');

    try {
        switch ($accion) {
            case 'crear':
                $r = RubroRepository::obtenerOCrear((string) ($_POST['nombre'] ?? ''));
                Vista::flash($r['nuevo'] ? 'exito' : 'info', $r['nuevo']
                    ? "Rubro «{$r['nombre']}» creado ✨"
                    : "Ya existía el rubro «{$r['nombre']}».");
                break;

            case 'renombrar':
                RubroRepository::renombrar((int) ($_POST['id'] ?? 0), (string) ($_POST['nombre'] ?? ''));
                Vista::flash('exito', 'Rubro actualizado.');
                break;

            case 'fusionar':
                $origen = RubroRepository::porId((int) ($_POST['origen_id'] ?? 0));
                $destino = RubroRepository::porId((int) ($_POST['destino_id'] ?? 0));
                if (!$origen || !$destino) {
                    throw new InvalidArgumentException('Elige los dos rubros a fusionar.');
                }
                $movidos = RubroRepository::fusionar((int) $origen['id'], (int) $destino['id']);
                Vista::flash('exito', "«{$origen['nombre']}» se fusionó en «{$destino['nombre']}» ({$movidos} contacto(s) movidos).");
                break;

            case 'eliminar':
                RubroRepository::eliminar((int) ($_POST['id'] ?? 0));
                Vista::flash('exito', 'Rubro eliminado.');
                break;

            default:
                Vista::flash('error', 'Acción no reconocida.');
        }
    } catch (InvalidArgumentException $e) {
        Vista::flash('error', $e->getMessage());
    }

    Http::redirigir('rubros.php');
}

$rubros = RubroRepository::conConteo();
$duplicados = RubroRepository::posiblesDuplicados($rubros);

Vista::cabecera('Rubros', 'rubros');
?>
<section class="saludo">
  <div>
    <h1>Rubros 🏷️</h1>
    <p>Crea, corrige y fusiona rubros repetidos para que los reportes queden limpios.</p>
  </div>
</section>

<?php if ($duplicados): ?>
  <section class="tarjeta tarjeta--alerta">
    <h2>Posibles duplicados</h2>
    <p class="texto-suave">Parecen el mismo rubro escrito distinto. Al fusionar, los contactos pasan al rubro que se conserva.</p>
    <ul class="lista-duplicados">
      <?php foreach ($duplicados as $par): ?>
        <li>
          <span><strong><?= e($par['absorber']['nombre']) ?></strong> (<?= (int) $par['absorber']['contactos'] ?>)
            → <strong><?= e($par['conservar']['nombre']) ?></strong> (<?= (int) $par['conservar']['contactos'] ?>)</span>
          <form method="post" action="rubros.php" data-confirmar="¿Fusionar «<?= e($par['absorber']['nombre']) ?>» en «<?= e($par['conservar']['nombre']) ?>»?">
            <?= Csrf::campo() ?>
            <input type="hidden" name="accion" value="fusionar">
            <input type="hidden" name="origen_id" value="<?= (int) $par['absorber']['id'] ?>">
            <input type="hidden" name="destino_id" value="<?= (int) $par['conservar']['id'] ?>">
            <button type="submit" class="btn btn--primario btn--chico">Fusionar</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<div class="grid-2">
  <section class="tarjeta">
    <h2>Nuevo rubro</h2>
    <form method="post" action="rubros.php" class="formulario formulario--linea">
      <?= Csrf::campo() ?>
      <input type="hidden" name="accion" value="crear">
      <label class="campo">
        <span>Nombre</span>
        <input type="text" name="nombre" maxlength="100" required placeholder="Ej. Veterinaria">
      </label>
      <button type="submit" class="btn btn--primario">Crear</button>
    </form>
  </section>

  <section class="tarjeta">
    <h2>Fusionar rubros</h2>
    <form method="post" action="rubros.php" class="formulario formulario--linea" data-confirmar="Los contactos del primer rubro pasarán al segundo y el primero se eliminará. ¿Continuar?">
      <?= Csrf::campo() ?>
      <input type="hidden" name="accion" value="fusionar">
      <label class="campo">
        <span>Mover de</span>
        <select name="origen_id" required>
          <option value="">Elegir…</option>
          <?php foreach ($rubros as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?> (<?= (int) $r['contactos'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="campo">
        <span>Hacia</span>
        <select name="destino_id" required>
          <option value="">Elegir…</option>
          <?php foreach ($rubros as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= e($r['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="btn btn--secundario">Fusionar</button>
    </form>
  </section>
</div>

<section class="tarjeta">
  <header class="tarjeta__cabecera">
    <h2>Todos los rubros</h2>
    <span class="pildora"><?= count($rubros) ?></span>
  </header>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead><tr><th>Nombre</th><th class="num">Contactos</th><th>Creado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($rubros as $r): ?>
          <tr>
            <td>
              <form method="post" action="rubros.php" class="editar-en-linea">
                <?= Csrf::campo() ?>
                <input type="hidden" name="accion" value="renombrar">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <label class="sr-only" for="rubro-<?= (int) $r['id'] ?>">Nombre</label>
                <input type="text" id="rubro-<?= (int) $r['id'] ?>" name="nombre" value="<?= e($r['nombre']) ?>" maxlength="100" required>
                <button type="submit" class="btn btn--fantasma btn--chico">Guardar</button>
              </form>
            </td>
            <td class="num"><?= (int) $r['contactos'] ?></td>
            <td><?= e(fecha_corta($r['creado_en'], false)) ?></td>
            <td>
              <?php if ((int) $r['contactos'] === 0): ?>
                <form method="post" action="rubros.php" data-confirmar="¿Eliminar el rubro «<?= e($r['nombre']) ?>»?">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn--peligro btn--chico">Eliminar</button>
                </form>
              <?php else: ?>
                <span class="texto-suave">En uso</span>
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
