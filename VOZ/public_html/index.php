<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$usuario = Auth::requerirLogin();
$esAdmin = $usuario['rol'] === 'admin';
$primerNombre = explode(' ', trim($usuario['nombre']))[0];

$datosApp = [
    'idioma'  => (string) config('app.idioma_voz'),
    'rubros'  => array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'nombre' => $r['nombre']], RubroRepository::todos()),
    'estados' => EstadoRepository::paraCliente(),
    'usuario' => ['id' => (int) $usuario['id'], 'nombre' => $usuario['nombre'], 'admin' => $esAdmin],
];

Vista::cabecera('Dictar', 'dictar');
?>
<section class="saludo">
  <div>
    <h1>Hola, <?= e($primerNombre) ?> 👋</h1>
    <p>Toca el micrófono y dicta: <strong>número con código de país</strong>, <strong>estado</strong> y <strong>rubro</strong>.</p>
  </div>
  <ul class="mini-kpis" id="kpis-hoy" aria-label="Resumen de hoy">
    <li><span class="mini-kpis__valor" data-kpi="total">0</span><span class="mini-kpis__etiqueta"><?= $esAdmin ? 'Hoy (equipo)' : 'Hoy' ?></span></li>
    <li class="t-interesado"><span class="mini-kpis__valor" data-kpi="interesado">0</span><span class="mini-kpis__etiqueta">Interesados</span></li>
    <li class="t-reunion"><span class="mini-kpis__valor" data-kpi="reunion">0</span><span class="mini-kpis__etiqueta">Reuniones</span></li>
    <li class="t-conversion"><span class="mini-kpis__valor" data-kpi="conversion">0%</span><span class="mini-kpis__etiqueta">Conversión</span></li>
  </ul>
</section>

<div class="zona-dictado" id="zona-dictado">
  <section class="tarjeta dictado" aria-labelledby="titulo-dictado">
    <h2 id="titulo-dictado" class="sr-only">Dictado por voz</h2>

    <button type="button" class="mic" id="btn-mic" aria-pressed="false" aria-label="Empezar a dictar">
      <span class="mic__anillo" aria-hidden="true"></span>
      <span class="mic__anillo mic__anillo--2" aria-hidden="true"></span>
      <svg class="mic__icono" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 15a3.5 3.5 0 0 0 3.5-3.5v-6a3.5 3.5 0 1 0-7 0v6A3.5 3.5 0 0 0 12 15z"/>
        <path d="M18.5 11.5a6.5 6.5 0 0 1-13 0M12 18v3m-3.5 0h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>

    <p class="dictado__estado" id="estado-mic">Toca el micrófono para dictar</p>
    <p class="dictado__vivo" id="texto-vivo" aria-live="polite"></p>

    <div class="ejemplos">
      <span class="ejemplos__titulo">Ejemplos</span>
      <button type="button" class="chip" data-ejemplo="51 954 016 836, interesado, rubro educación"><span class="bandera bandera--PE" aria-hidden="true"></span> 51 954 016 836, interesado, rubro educación</button>
      <button type="button" class="chip" data-ejemplo="57 302 242 5340, reunión, rubro salud"><span class="bandera bandera--CO" aria-hidden="true"></span> 57 302 242 5340, reunión, rubro salud</button>
    </div>

    <div class="aviso aviso--info" id="sin-voz" hidden>
      Este navegador no permite dictado por voz. Usa <strong>Google Chrome</strong> o <strong>Edge</strong>,
      o escribe la frase / llena el formulario aquí abajo.
    </div>

    <form class="frase" id="form-frase" autocomplete="off">
      <label for="input-frase" class="sr-only">Escribir la frase</label>
      <input type="text" id="input-frase" maxlength="500" placeholder="…o escribe la frase y presiona Enter">
      <button type="submit" class="btn btn--secundario">Interpretar</button>
    </form>

    <div class="dictado__opciones">
      <button type="button" class="btn btn--enlace" id="btn-manual">✍️ Llenar formulario manual</button>
      <label class="interruptor">
        <input type="checkbox" id="auto-escuchar">
        <span class="interruptor__pista" aria-hidden="true"></span>
        Escuchar otra vez al guardar
      </label>
    </div>
  </section>

  <section class="tarjeta previa" id="panel-previa" aria-labelledby="titulo-previa" hidden>
    <header class="previa__cabecera">
      <h2 id="titulo-previa">Revisa antes de guardar</h2>
      <span class="atajos"><kbd>Enter</kbd> guardar · <kbd>Esc</kbd> volver a dictar</span>
    </header>

    <figure class="previa__dictado" id="previa-dictado">
      <figcaption>Lo que escuché</figcaption>
      <blockquote id="previa-texto"></blockquote>
    </figure>
    <div class="alternativas" id="previa-alternativas" hidden></div>
    <div class="aviso aviso--alerta" id="previa-advertencias" hidden></div>

    <form id="form-contacto" class="formulario" novalidate>
      <div class="fila-telefono">
        <label class="campo campo--pais">
          <span>País</span>
          <select id="f-pais">
            <option value="">Elegir…</option>
            <option value="PE">Perú (+51)</option>
            <option value="CO">Colombia (+57)</option>
          </select>
        </label>
        <label class="campo campo--telefono">
          <span>Teléfono</span>
          <input type="text" id="f-telefono" inputmode="tel" autocomplete="off" maxlength="24" placeholder="+51 954 016 836">
        </label>
      </div>
      <p class="mensaje-campo" id="msg-telefono" aria-live="polite"></p>

      <div class="aviso aviso--alerta sugerencia" id="sugerencia-pais" hidden>
        <span id="sugerencia-texto"></span>
        <button type="button" class="btn btn--primario btn--chico" id="btn-confirmar-pais">Sí, confirmar</button>
      </div>

      <div class="duplicado" id="duplicado" hidden></div>

      <fieldset class="campo">
        <legend>Estado</legend>
        <div class="estados" id="f-estados">
          <label class="estado-op estado-op--interesado"><input type="radio" name="estado" value="interesado"><span>😀 Interesado</span></label>
          <label class="estado-op estado-op--reunion"><input type="radio" name="estado" value="reunion"><span>📅 Reunión</span></label>
          <label class="estado-op estado-op--medio_interesado"><input type="radio" name="estado" value="medio_interesado"><span>🤔 Medio interesado</span></label>
          <label class="estado-op estado-op--no_interesado"><input type="radio" name="estado" value="no_interesado"><span>🙅 No interesado</span></label>
        </div>
        <div class="estados-extra">
          <label class="sr-only" for="f-estado-otro">Otro estado</label>
          <select id="f-estado-otro">
            <option value="">Otros estados…</option>
          </select>
          <button type="button" class="btn btn--secundario btn--chico" id="btn-nuevo-estado">+ Nuevo estado</button>
        </div>
        <div class="nuevo-estado" id="form-nuevo-estado" hidden>
          <label class="campo">
            <span>Nombre del estado</span>
            <input type="text" id="ne-nombre" maxlength="60" placeholder="Ej. No contesta">
          </label>
          <label class="campo">
            <span>Palabras clave para el dictado <small>(opcional, separadas por coma)</small></span>
            <input type="text" id="ne-palabras" maxlength="255" placeholder="Ej. no responde, no contestó">
          </label>
          <label class="check"><input type="checkbox" id="ne-conversion"> Cuenta como conversión</label>
          <div class="nuevo-estado__acciones">
            <button type="button" class="btn btn--primario btn--chico" id="ne-guardar">Crear estado</button>
            <button type="button" class="btn btn--fantasma btn--chico" id="ne-cancelar">Cancelar</button>
          </div>
        </div>
      </fieldset>

      <label class="campo">
        <span>Rubro <small>(opcional)</small></span>
        <input type="text" id="f-rubro" list="lista-rubros" maxlength="100" placeholder="Ej. Educación" autocomplete="off">
        <datalist id="lista-rubros"></datalist>
      </label>
      <div class="mensaje-campo" id="msg-rubro" aria-live="polite"></div>

      <label class="campo">
        <span>Nota <small>(opcional)</small></span>
        <input type="text" id="f-nota" maxlength="255" placeholder="Ej. Llamar el lunes por la tarde">
      </label>

      <div class="aviso aviso--error" id="previa-error" role="alert" hidden></div>

      <div class="acciones">
        <button type="submit" class="btn btn--primario" id="btn-guardar">⭐ Guardar</button>
        <button type="button" class="btn btn--secundario" id="btn-redictar">🎙️ Volver a dictar</button>
        <button type="button" class="btn btn--fantasma" id="btn-cancelar">Cancelar</button>
      </div>
    </form>
  </section>
</div>

<section class="tarjeta">
  <header class="tarjeta__cabecera">
    <h2><?= $esAdmin ? 'Registros de hoy (todo el equipo)' : 'Mis registros de hoy' ?></h2>
    <a href="reportes.php" class="btn btn--enlace">Ver reportes →</a>
  </header>
  <div id="lista-hoy" class="lista-contactos" aria-live="polite">
    <p class="vacio">Cargando…</p>
  </div>
</section>

<script type="application/json" id="datos-app"><?= json_html($datosApp) ?></script>
<?php
Vista::pie(['assets/parser.js', 'assets/app.js']);
