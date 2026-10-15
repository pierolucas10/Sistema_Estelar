<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$usuario = Auth::requerirLogin();
$esAdmin = $usuario['rol'] === 'admin';
$filtros = ReporteRepository::filtrosDesdeRequest($_GET, $usuario);

$rubros = RubroRepository::todos();
$asesores = $esAdmin ? UsuarioRepository::asesores() : [];
$anioActual = (int) date('Y');

Vista::cabecera('Reportes', 'reportes');
?>
<section class="saludo saludo--reportes">
  <div>
    <h1>Reportes mensuales 📊</h1>
    <p><?= $esAdmin ? 'Todo el equipo, Perú y Colombia.' : 'Tus contactos registrados.' ?> Elige el mes y filtra lo que necesites.</p>
  </div>
</section>

<form class="tarjeta filtros" id="form-filtros" method="get" action="reportes.php">
  <label class="campo campo--compacto">
    <span>Mes</span>
    <select name="mes">
      <?php foreach (MESES_ES as $n => $nombre): ?>
        <option value="<?= $n ?>"<?= $n === $filtros['mes'] ? ' selected' : '' ?>><?= e($nombre) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="campo campo--compacto">
    <span>Año</span>
    <select name="anio">
      <?php for ($a = $anioActual + 1; $a >= 2024; $a--): ?>
        <option value="<?= $a ?>"<?= $a === $filtros['anio'] ? ' selected' : '' ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <fieldset class="campo campo--compacto">
    <legend>País</legend>
    <div class="segmentado">
      <?php foreach (['todos' => '🌎 Todos', 'PE' => '<span class="bandera bandera--PE" aria-hidden="true"></span> Perú', 'CO' => '<span class="bandera bandera--CO" aria-hidden="true"></span> Colombia'] as $valor => $texto): ?>
        <label><input type="radio" name="pais" value="<?= $valor ?>"<?= ($filtros['pais'] ?? 'todos') === $valor ? ' checked' : '' ?>><span><?= $texto ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <label class="campo campo--compacto">
    <span>Estado</span>
    <select name="estado">
      <option value="">Todos</option>
      <?php foreach (EstadoRepository::todos() as $est): ?>
        <option value="<?= e($est['clave']) ?>"<?= ($filtros['estado'] ?? '') === $est['clave'] ? ' selected' : '' ?>><?= e($est['nombre']) ?><?= $est['activo'] ? '' : ' (inactivo)' ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="campo campo--compacto">
    <span>Rubro</span>
    <select name="rubro_id">
      <option value="">Todos</option>
      <option value="-1"<?= ($filtros['rubro_id'] ?? 0) === -1 ? ' selected' : '' ?>>Sin rubro</option>
      <?php foreach ($rubros as $r): ?>
        <option value="<?= (int) $r['id'] ?>"<?= ($filtros['rubro_id'] ?? 0) === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($esAdmin): ?>
    <label class="campo campo--compacto">
      <span>Asesor</span>
      <select name="usuario_id">
        <option value="">Todos</option>
        <?php foreach ($asesores as $a): ?>
          <option value="<?= (int) $a['id'] ?>"<?= ($filtros['usuario_id'] ?? 0) === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['nombre']) ?><?= (int) $a['activo'] ? '' : ' (inactivo)' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <div class="filtros__acciones">
    <button type="submit" class="btn btn--primario">Aplicar</button>
    <a class="btn btn--secundario" id="btn-csv-detalle" href="#">⬇️ CSV del mes</a>
    <a class="btn btn--secundario" id="btn-csv-resumen" href="#">⬇️ CSV 12 meses</a>
  </div>
</form>

<div id="reporte" class="reporte" aria-busy="true">
  <!-- 1. Resumen -->
  <section class="bloque">
    <h2 class="bloque__titulo">Resumen de <span data-campo="etiqueta">…</span></h2>
    <div class="kpis" id="kpis"></div>
  </section>

  <!-- 2. Perú vs Colombia -->
  <section class="bloque">
    <h2 class="bloque__titulo">Perú vs Colombia</h2>
    <div class="paises" id="paises"></div>
  </section>

  <!-- 3. Por rubro -->
  <section class="bloque tarjeta">
    <h2 class="bloque__titulo">Por rubro <small>ordenado por mayor conversión</small></h2>
    <div class="tabla-scroll">
      <table class="tabla" id="tabla-rubros">
        <thead>
          <tr><th>Rubro</th><th>País</th><th class="num">Total</th><th class="num">Interesados</th><th class="num">Medio</th>
              <th class="num">Reuniones</th><th class="num">No interesados</th><th class="num">Otros estados</th><th class="num">% Conversión</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- 4. Evolución -->
  <section class="bloque tarjeta">
    <div class="bloque__cabecera">
      <h2 class="bloque__titulo">Evolución de los últimos 12 meses</h2>
      <div class="segmentado" id="selector-grafico" role="radiogroup" aria-label="Ver gráfico por">
        <label><input type="radio" name="vista-grafico" value="pais" checked><span>Por país</span></label>
        <label><input type="radio" name="vista-grafico" value="estado"><span>Por estado</span></label>
        <label><input type="radio" name="vista-grafico" value="conversion"><span>Conversión %</span></label>
      </div>
    </div>
    <div class="grafico"><canvas id="grafico-evolucion" aria-label="Gráfico de evolución mensual" role="img"></canvas></div>
    <div class="tabla-scroll">
      <table class="tabla" id="tabla-evolucion">
        <thead>
          <tr><th>Mes</th><th class="num">Total</th><th class="num"><span class="bandera bandera--PE" aria-hidden="true"></span> Perú</th><th class="num"><span class="bandera bandera--CO" aria-hidden="true"></span> Colombia</th><th class="num">Interesados</th>
              <th class="num">Medio</th><th class="num">Reuniones</th><th class="num">No interesados</th><th class="num">Otros</th><th class="num">% Conversión</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- 5. Detalle -->
  <section class="bloque tarjeta">
    <div class="bloque__cabecera">
      <h2 class="bloque__titulo">Detalle <small id="detalle-total"></small></h2>
      <label class="buscador">
        <span class="sr-only">Buscar teléfono</span>
        <input type="search" id="buscar-telefono" placeholder="🔎 Buscar teléfono" inputmode="tel" maxlength="20">
      </label>
    </div>
    <div class="tabla-scroll">
      <table class="tabla tabla--detalle" id="tabla-detalle">
        <thead>
          <tr><th>País</th><th>Teléfono</th><th>Estado</th><th>Rubro</th><th>Nota</th><th>Asesor</th><th>Fecha</th><th>Contactar</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="paginacion" id="paginacion" aria-label="Paginación"></nav>
  </section>
</div>

<?php
Vista::pie([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    'assets/reportes.js',
]);
