/*!
 * Estelar · pantalla de dictado.
 * Voz (Web Speech API) → parser (assets/parser.js) → vista previa editable → api/guardar.php
 */
(function () {
  'use strict';

  var P = window.EstelarParser;
  var E = window.Estelar;
  var el = E.el;

  var datos = JSON.parse(document.getElementById('datos-app').textContent);
  var rubros = datos.rubros || [];
  var estados = datos.estados || [];

  function $(id) { return document.getElementById(id); }

  var ui = {
    zona: $('zona-dictado'),
    mic: $('btn-mic'),
    estadoMic: $('estado-mic'),
    textoVivo: $('texto-vivo'),
    sinVoz: $('sin-voz'),
    formFrase: $('form-frase'),
    inputFrase: $('input-frase'),
    btnManual: $('btn-manual'),
    autoEscuchar: $('auto-escuchar'),
    panel: $('panel-previa'),
    previaDictado: $('previa-dictado'),
    previaTexto: $('previa-texto'),
    alternativas: $('previa-alternativas'),
    advertencias: $('previa-advertencias'),
    form: $('form-contacto'),
    pais: $('f-pais'),
    telefono: $('f-telefono'),
    msgTel: $('msg-telefono'),
    sugerencia: $('sugerencia-pais'),
    sugerenciaTexto: $('sugerencia-texto'),
    btnConfirmarPais: $('btn-confirmar-pais'),
    duplicado: $('duplicado'),
    rubro: $('f-rubro'),
    msgRubro: $('msg-rubro'),
    listaRubros: $('lista-rubros'),
    nota: $('f-nota'),
    error: $('previa-error'),
    guardar: $('btn-guardar'),
    redictar: $('btn-redictar'),
    cancelar: $('btn-cancelar'),
    lista: $('lista-hoy'),
    kpis: $('kpis-hoy'),
    estadoOtro: $('f-estado-otro'),
    btnNuevoEstado: $('btn-nuevo-estado'),
    formNuevoEstado: $('form-nuevo-estado'),
    neNombre: $('ne-nombre'),
    nePalabras: $('ne-palabras'),
    neConversion: $('ne-conversion'),
    neGuardar: $('ne-guardar'),
    neCancelar: $('ne-cancelar')
  };

  var estado = {
    texto: '',
    telefono: null,
    errorTelefono: null,
    errorRubro: null,
    rubroNuevo: false,
    confirmacionRubro: {},
    sugerencia: null,
    duplicado: null,
    ultimaVerificada: '',
    timerVerificar: null,
    abortVerificar: null,
    guardando: false
  };

  // ---------------- Preferencias locales ----------------

  function leerPref(clave, defecto) {
    try { var v = window.localStorage.getItem('estelar.' + clave); return v === null ? defecto : v; } catch (e) { return defecto; }
  }
  function guardarPref(clave, valor) {
    try { window.localStorage.setItem('estelar.' + clave, valor); } catch (e) { /* modo privado */ }
  }

  ui.autoEscuchar.checked = leerPref('autoEscuchar', '0') === '1';
  ui.autoEscuchar.addEventListener('change', function () { guardarPref('autoEscuchar', ui.autoEscuchar.checked ? '1' : '0'); });

  // ---------------- Rubros ----------------

  function llenarDatalist() {
    E.vaciar(ui.listaRubros);
    rubros.slice().sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); })
      .forEach(function (r) { ui.listaRubros.appendChild(el('option', { value: r.nombre })); });
  }
  llenarDatalist();

  // ---------------- Estados (los 4 básicos son botones; el resto, una lista) ----------------

  function esBasico(clave) { return E.BASICOS.indexOf(clave) !== -1; }

  function llenarEstadosExtra() {
    var actual = ui.estadoOtro.value;
    while (ui.estadoOtro.options.length > 1) ui.estadoOtro.remove(1);
    estados.filter(function (e) { return !esBasico(e.clave); })
      .sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); })
      .forEach(function (e) { ui.estadoOtro.appendChild(el('option', { value: e.clave, text: e.nombre })); });
    ui.estadoOtro.value = actual;
  }
  llenarEstadosExtra();

  function abrirNuevoEstado() {
    ui.formNuevoEstado.hidden = false;
    ui.neNombre.value = '';
    ui.nePalabras.value = '';
    ui.neConversion.checked = false;
    ui.neNombre.focus();
  }

  function cerrarNuevoEstado() {
    ui.formNuevoEstado.hidden = true;
  }

  function crearEstado() {
    var nombre = ui.neNombre.value.trim();
    if (nombre.length < 2) { E.toast('Escribe el nombre del estado.', 'error'); ui.neNombre.focus(); return; }
    ui.neGuardar.disabled = true;
    E.api('api/estado_crear.php', {
      method: 'POST',
      body: { nombre: nombre, palabras: ui.nePalabras.value.trim(), conversion: ui.neConversion.checked }
    }).then(function (r) {
      if (!r.data || !r.data.ok) { E.toast((r.data && r.data.error) || 'No se pudo crear el estado.', 'error'); return; }
      var nuevo = r.data.estado;
      var existe = estados.some(function (e) { return e.clave === nuevo.clave; });
      if (!existe) estados.push(nuevo);
      llenarEstadosExtra();
      marcarEstado(nuevo.clave);
      cerrarNuevoEstado();
      actualizarBoton();
      E.toast(r.data.nuevo ? 'Estado «' + nuevo.nombre + '» creado ✨' : 'Ese estado ya existía: lo seleccioné.', 'exito');
    }).catch(function () {
      E.toast('Sin conexión. Intenta otra vez.', 'error');
    }).then(function () { ui.neGuardar.disabled = false; });
  }

  ui.btnNuevoEstado.addEventListener('click', function () {
    if (ui.formNuevoEstado.hidden) abrirNuevoEstado(); else cerrarNuevoEstado();
  });
  ui.neGuardar.addEventListener('click', crearEstado);
  ui.neCancelar.addEventListener('click', cerrarNuevoEstado);
  [ui.neNombre, ui.nePalabras].forEach(function (input) {
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); ev.stopPropagation(); crearEstado(); }
      if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); cerrarNuevoEstado(); }
    });
  });
  ui.estadoOtro.addEventListener('change', function () {
    if (ui.estadoOtro.value) {
      ui.form.querySelectorAll('input[name="estado"]').forEach(function (r) { r.checked = false; });
    }
    ocultarError();
    actualizarBoton();
  });

  // ---------------- Reconocimiento de voz ----------------

  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var reconocedor = null;
  var escuchando = false;

  var ERRORES_VOZ = {
    'not-allowed': 'Permite el micrófono: toca el candado 🔒 junto a la dirección y activa "Micrófono".',
    'service-not-allowed': 'El navegador bloqueó el servicio de voz. Prueba en Google Chrome.',
    'no-speech': 'No te escuché 🙉 Toca el micrófono y habla cerca del micrófono.',
    'audio-capture': 'No se encontró un micrófono conectado.',
    'network': 'Sin conexión con el servicio de voz. Revisa tu internet.',
    'language-not-supported': 'El idioma de voz configurado no está disponible en este navegador.'
  };

  function setEscuchando(activo) {
    escuchando = activo;
    ui.mic.classList.toggle('is-escuchando', activo);
    ui.mic.setAttribute('aria-pressed', activo ? 'true' : 'false');
    ui.mic.setAttribute('aria-label', activo ? 'Detener dictado' : 'Empezar a dictar');
    ui.estadoMic.textContent = activo ? 'Te escucho… dicta número, estado y rubro' : 'Toca el micrófono para dictar';
  }

  if (!SR) {
    ui.sinVoz.hidden = false;
    ui.mic.disabled = true;
    ui.mic.classList.add('is-deshabilitado');
    ui.estadoMic.textContent = 'Dictado no disponible en este navegador';
  } else {
    reconocedor = new SR();
    reconocedor.lang = datos.idioma || 'es-CO';
    reconocedor.continuous = false;      // una frase por registro
    reconocedor.interimResults = true;   // texto en vivo mientras hablas
    reconocedor.maxAlternatives = 3;     // se elige la primera alternativa que sea válida

    reconocedor.onstart = function () {
      setEscuchando(true);
      ui.textoVivo.textContent = '';
    };

    reconocedor.onresult = function (ev) {
      var interino = '';
      var final = null;
      for (var i = ev.resultIndex; i < ev.results.length; i++) {
        if (ev.results[i].isFinal) final = ev.results[i];
        else interino += ev.results[i][0].transcript;
      }
      if (interino) ui.textoVivo.textContent = interino;
      if (final) {
        var alternativas = [];
        for (var j = 0; j < final.length; j++) {
          var t = (final[j].transcript || '').trim();
          if (t && alternativas.indexOf(t) === -1) alternativas.push(t);
        }
        ui.textoVivo.textContent = alternativas[0] || '';
        if (alternativas.length) mostrarPrevia(alternativas);
      }
    };

    reconocedor.onerror = function (ev) {
      if (ev.error === 'aborted') return;
      E.toast(ERRORES_VOZ[ev.error] || 'No se pudo usar el micrófono (' + ev.error + ').', 'error');
    };

    reconocedor.onend = function () { setEscuchando(false); };
  }

  function iniciarEscucha() {
    if (!reconocedor || escuchando) return;
    cerrarPanel();
    try { reconocedor.start(); } catch (e) { /* ya estaba iniciado */ }
  }

  function detenerEscucha() {
    if (reconocedor && escuchando) reconocedor.stop();
  }

  ui.mic.addEventListener('click', function () {
    if (escuchando) detenerEscucha(); else iniciarEscucha();
  });

  // ---------------- Entrada escrita y ejemplos ----------------

  ui.formFrase.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var texto = ui.inputFrase.value.trim();
    if (texto) mostrarPrevia([texto]);
  });

  document.querySelectorAll('[data-ejemplo]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      ui.inputFrase.value = chip.getAttribute('data-ejemplo');
      mostrarPrevia([ui.inputFrase.value]);
    });
  });

  ui.btnManual.addEventListener('click', abrirManual);

  // ---------------- Vista previa ----------------

  function mostrarPrevia(alternativas) {
    var analisis = alternativas.map(function (t) { return P.analizar(t, rubros, null, estados); });
    var elegido = 0;
    for (var i = 0; i < analisis.length; i++) { if (analisis[i].valido) { elegido = i; break; } }
    aplicarAnalisis(analisis[elegido]);

    E.vaciar(ui.alternativas);
    var otras = analisis.filter(function (_, i) { return i !== elegido; });
    if (otras.length) {
      ui.alternativas.appendChild(el('span', { class: 'alternativas__titulo', text: '¿Dijiste otra cosa?' }));
      otras.forEach(function (a) {
        ui.alternativas.appendChild(el('button', {
          type: 'button',
          class: 'chip chip--chico',
          text: a.texto,
          onclick: function () { aplicarAnalisis(a); }
        }));
      });
    }
    ui.alternativas.hidden = otras.length === 0;
  }

  function aplicarAnalisis(r) {
    reiniciarEstado();
    estado.texto = r.texto;
    ui.previaTexto.textContent = '“' + r.texto + '”';
    ui.previaDictado.hidden = false;

    ui.pais.value = r.pais || '';
    if (r.telefono.valido) ui.telefono.value = P.formatear(r.telefono_e164);
    else if (r.digitos) ui.telefono.value = (r.pais ? '+' : '') + r.digitos;
    else ui.telefono.value = '';

    marcarEstado(r.estado);
    ui.rubro.value = r.rubro || '';
    ui.nota.value = r.nota || '';

    ui.advertencias.textContent = r.advertencias.join(' ');
    ui.advertencias.hidden = r.advertencias.length === 0;

    abrirPanel();
    validarTelefono();
    validarRubro();
    actualizarBoton();

    if (!r.estado) {
      var primero = ui.form.querySelector('input[name="estado"]');
      if (primero) primero.focus();
    } else if (!r.telefono.valido) {
      ui.telefono.focus();
    } else {
      ui.guardar.focus();
    }
  }

  function abrirManual() {
    detenerEscucha();
    reiniciarEstado();
    ui.previaDictado.hidden = true;
    ui.alternativas.hidden = true;
    ui.advertencias.hidden = true;
    ui.pais.value = leerPref('pais', '');
    ui.telefono.value = '';
    marcarEstado(null);
    cerrarNuevoEstado();
    ui.rubro.value = '';
    ui.nota.value = '';
    abrirPanel();
    validarTelefono();
    validarRubro();
    actualizarBoton();
    (ui.pais.value ? ui.telefono : ui.pais).focus();
  }

  function abrirPanel() {
    ui.panel.hidden = false;
    ui.zona.classList.add('con-previa');
    ocultarError();
    if (window.matchMedia('(max-width: 900px)').matches) {
      ui.panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function cerrarPanel() {
    ui.panel.hidden = true;
    ui.zona.classList.remove('con-previa');
    reiniciarEstado();
  }

  function reiniciarEstado() {
    clearTimeout(estado.timerVerificar);
    if (estado.abortVerificar) estado.abortVerificar.abort();
    estado.texto = '';
    estado.telefono = null;
    estado.errorTelefono = null;
    estado.errorRubro = null;
    estado.rubroNuevo = false;
    estado.sugerencia = null;
    estado.ultimaVerificada = '';
    mostrarDuplicado(null, []);
    ocultarError();
  }

  function marcarEstado(valor) {
    ui.form.querySelectorAll('input[name="estado"]').forEach(function (r) { r.checked = r.value === valor; });
    ui.estadoOtro.value = valor && !esBasico(valor) ? valor : '';
  }

  function estadoSeleccionado() {
    var r = ui.form.querySelector('input[name="estado"]:checked');
    if (r) return r.value;
    return ui.estadoOtro.value || null;
  }

  function mensaje(nodo, tipo, texto) {
    nodo.className = 'mensaje-campo' + (tipo ? ' mensaje-campo--' + tipo : '');
    nodo.textContent = texto || '';
  }

  // ---------------- Validaciones ----------------

  function validarTelefono() {
    var valor = ui.telefono.value.trim();
    var tel = P.analizarTelefono(valor, ui.pais.value || null);
    ui.sugerencia.hidden = true;
    estado.sugerencia = null;

    if (!valor) {
      mensaje(ui.msgTel, '', ui.pais.value ? 'Escribe el número local, sin el código.' : 'Elige el país o dicta el número con su código (51 o 57).');
      estado.telefono = null;
      estado.errorTelefono = 'Falta el teléfono.';
    } else if (tel.valido) {
      if (!ui.pais.value) ui.pais.value = tel.pais;
      var p = P.PAISES[tel.pais];
      mensaje(ui.msgTel, 'ok', '✓ ' + (tel.tipo === 'fijo' ? 'Fijo' : 'Celular') + ' de ' + p.nombre + ' · ' + P.formatear(tel.e164));
      estado.telefono = tel;
      estado.errorTelefono = null;
    } else {
      mensaje(ui.msgTel, 'error', tel.error);
      estado.telefono = null;
      estado.errorTelefono = tel.error;
      if (tel.sugerencia && !ui.pais.value) {
        estado.sugerencia = tel.sugerencia;
        ui.sugerenciaTexto.textContent = tel.sugerencia.mensaje;
        ui.sugerencia.hidden = false;
      }
    }

    if (estado.telefono) programarVerificacion(estado.telefono);
    else { estado.ultimaVerificada = ''; mostrarDuplicado(null, []); }
  }

  function validarRubro() {
    var nombre = ui.rubro.value.trim();
    E.vaciar(ui.msgRubro);
    ui.msgRubro.className = 'mensaje-campo';
    estado.rubroNuevo = false;
    estado.errorRubro = null;
    if (!nombre) return;

    var b = P.buscarRubro(nombre, rubros);
    if (b.exacto) {
      if (P.normalizarTexto(b.exacto.nombre) !== P.normalizarTexto(nombre)) {
        mensaje(ui.msgRubro, 'ok', '✓ Se guardará como «' + b.exacto.nombre + '»');
      }
      return;
    }

    estado.rubroNuevo = true;
    var clave = P.claveRubro(nombre);
    if (!Object.prototype.hasOwnProperty.call(estado.confirmacionRubro, clave)) {
      estado.confirmacionRubro[clave] = !b.similar;
    }

    var chk = el('input', { type: 'checkbox', id: 'chk-crear-rubro' });
    chk.checked = estado.confirmacionRubro[clave];
    chk.addEventListener('change', function () {
      estado.confirmacionRubro[clave] = chk.checked;
      estado.errorRubro = chk.checked ? null : 'Confirma el rubro nuevo o elige uno de la lista.';
      actualizarBoton();
    });

    ui.msgRubro.className = 'mensaje-campo mensaje-campo--nuevo';
    ui.msgRubro.appendChild(el('label', { class: 'check', for: 'chk-crear-rubro' }, [
      chk, ' ✨ Crear el rubro nuevo «' + P.capitalizar(nombre) + '»'
    ]));
    if (b.similar) {
      ui.msgRubro.appendChild(el('button', {
        type: 'button',
        class: 'btn btn--enlace',
        text: '¿Quisiste decir «' + b.similar.nombre + '»?',
        onclick: function () { ui.rubro.value = b.similar.nombre; validarRubro(); actualizarBoton(); }
      }));
    }
    if (!chk.checked) estado.errorRubro = 'Confirma el rubro nuevo o elige uno de la lista.';
  }

  function errores() {
    var lista = [];
    if (estado.errorTelefono) lista.push(estado.errorTelefono);
    if (!estadoSeleccionado()) lista.push('Elige un estado.');
    if (estado.errorRubro) lista.push(estado.errorRubro);
    return lista;
  }

  function actualizarBoton() {
    ui.guardar.disabled = estado.guardando || errores().length > 0;
  }

  ui.telefono.addEventListener('input', function () { validarTelefono(); actualizarBoton(); });
  ui.telefono.addEventListener('blur', function () {
    if (estado.telefono) ui.telefono.value = P.formatear(estado.telefono.e164);
  });
  ui.pais.addEventListener('change', function () {
    guardarPref('pais', ui.pais.value);
    validarTelefono();
    actualizarBoton();
  });
  ui.rubro.addEventListener('input', function () { validarRubro(); actualizarBoton(); });
  ui.form.addEventListener('change', function (ev) {
    if (ev.target.name === 'estado') { ui.estadoOtro.value = ''; ocultarError(); actualizarBoton(); }
  });

  ui.btnConfirmarPais.addEventListener('click', function () {
    if (!estado.sugerencia) return;
    ui.pais.value = estado.sugerencia.pais;
    ui.telefono.value = P.formatear(estado.sugerencia.e164);
    validarTelefono();
    actualizarBoton();
    ui.guardar.focus();
  });

  // ---------------- Duplicados ----------------

  function programarVerificacion(tel) {
    if (tel.e164 === estado.ultimaVerificada) return;
    clearTimeout(estado.timerVerificar);
    estado.timerVerificar = setTimeout(function () {
      estado.ultimaVerificada = tel.e164;
      if (estado.abortVerificar) estado.abortVerificar.abort();
      estado.abortVerificar = window.AbortController ? new AbortController() : null;
      E.api('api/verificar.php?telefono=' + encodeURIComponent(tel.e164), {
        signal: estado.abortVerificar ? estado.abortVerificar.signal : undefined
      }).then(function (r) {
        if (r.data && r.data.ok && estado.telefono && estado.telefono.e164 === tel.e164) {
          mostrarDuplicado(r.data.duplicado, r.data.historial || []);
        }
      }).catch(function () { /* cancelada o sin red: se valida igual al guardar */ });
    }, 250);
  }

  function mostrarDuplicado(d, historial) {
    estado.duplicado = d || null;
    E.vaciar(ui.duplicado);
    if (!d) {
      ui.duplicado.hidden = true;
      ui.guardar.textContent = '⭐ Guardar';
      return;
    }

    var datosDup = el('dl', { class: 'duplicado__datos' }, [
      el('dt', { text: 'Registrado por' }), el('dd', { text: d.asesor + (d.usuario_id === datos.usuario.id ? ' (tú)' : '') }),
      el('dt', { text: 'Fecha' }), el('dd', { text: d.fecha }),
      el('dt', { text: 'Estado actual' }), el('dd', {}, E.insigniaEstado(d.estado, d.estado_label, d.estado_color)),
      el('dt', { text: 'Rubro actual' }), el('dd', { text: d.rubro || 'Sin rubro' })
    ]);
    if (d.nota) {
      datosDup.appendChild(el('dt', { text: 'Nota' }));
      datosDup.appendChild(el('dd', { text: d.nota }));
    }

    ui.duplicado.appendChild(el('p', { class: 'duplicado__titulo', text: '⚠️ Este número ya está registrado' }));
    ui.duplicado.appendChild(datosDup);

    if (historial.length > 1) {
      ui.duplicado.appendChild(el('details', { class: 'duplicado__historial' }, [
        el('summary', { text: 'Ver historial (' + historial.length + ')' }),
        el('ul', {}, historial.map(function (h) {
          return el('li', {
            text: h.fecha + ' · ' + (h.estado_anterior ? h.estado_anterior + ' → ' : 'Registro: ') + h.estado_nuevo +
              (h.rubro_nuevo ? ' · ' + h.rubro_nuevo : '') + ' · ' + h.usuario
          });
        }))
      ]));
    }

    ui.duplicado.appendChild(el('p', {
      class: 'duplicado__ayuda',
      text: 'No se duplicará: al guardar se actualiza su estado y rubro, y el cambio queda en el historial. Si dejas el rubro vacío se conserva el actual.'
    }));
    ui.duplicado.hidden = false;
    ui.guardar.textContent = '🔄 Actualizar contacto';
  }

  // ---------------- Guardar ----------------

  function mostrarError(texto) {
    ui.error.textContent = texto;
    ui.error.hidden = false;
  }

  function ocultarError() {
    ui.error.hidden = true;
    ui.error.textContent = '';
  }

  function guardar(ev) {
    if (ev) ev.preventDefault();
    if (estado.guardando || ui.panel.hidden) return;

    var lista = errores();
    if (lista.length) { mostrarError(lista[0]); return; }

    var nombreRubro = ui.rubro.value.trim();
    var cuerpo = {
      texto_dictado: estado.texto,
      telefono: estado.telefono.e164,
      pais: estado.telefono.pais,
      estado: estadoSeleccionado(),
      rubro: nombreRubro,
      crear_rubro: estado.rubroNuevo && estado.confirmacionRubro[P.claveRubro(nombreRubro)] === true,
      nota: ui.nota.value.trim(),
      modo: estado.duplicado ? 'actualizar' : 'crear'
    };

    estado.guardando = true;
    ui.guardar.classList.add('is-cargando');
    actualizarBoton();

    E.api('api/guardar.php', { method: 'POST', body: cuerpo }).then(function (r) {
      if (r.status === 200 && r.data.ok) {
        E.toast(r.data.accion === 'creado' ? '¡Contacto guardado! ⭐' : 'Contacto actualizado 🔄', 'exito');
        if (r.data.rubro && r.data.rubro.nuevo) {
          rubros.push({ id: r.data.rubro.id, nombre: r.data.rubro.nombre });
          llenarDatalist();
        }
        guardarPref('pais', cuerpo.pais);
        ui.inputFrase.value = '';
        ui.textoVivo.textContent = '';
        cerrarPanel();
        cargarHoy();
        if (ui.autoEscuchar.checked && reconocedor) setTimeout(iniciarEscucha, 600);
        else ui.mic.focus();
      } else if (r.status === 409 && r.data.duplicado) {
        estado.ultimaVerificada = r.data.duplicado.telefono_e164;
        mostrarDuplicado(r.data.duplicado, []);
        mostrarError('Este número ya existe. Revisa los datos y presiona «Actualizar contacto».');
      } else {
        mostrarError((r.data && r.data.error) || 'No se pudo guardar. Intenta otra vez.');
      }
    }).catch(function () {
      mostrarError('Sin conexión. Revisa tu internet e intenta otra vez; tus datos siguen aquí.');
    }).then(function () {
      estado.guardando = false;
      ui.guardar.classList.remove('is-cargando');
      actualizarBoton();
    });
  }

  ui.form.addEventListener('submit', guardar);
  ui.redictar.addEventListener('click', function () {
    if (reconocedor) iniciarEscucha();
    else { cerrarPanel(); ui.inputFrase.focus(); }
  });
  ui.cancelar.addEventListener('click', function () { cerrarPanel(); ui.mic.focus(); });

  // Atajos: Enter confirma, Esc vuelve a dictar.
  document.addEventListener('keydown', function (ev) {
    if (ui.panel.hidden) return;
    var tag = (ev.target.tagName || '').toLowerCase();
    if (ev.key === 'Escape') {
      ev.preventDefault();
      if (reconocedor) iniciarEscucha(); else cerrarPanel();
    } else if (ev.key === 'Enter' && !ev.shiftKey && ['input', 'select', 'textarea', 'button', 'a', 'summary'].indexOf(tag) === -1) {
      ev.preventDefault();
      guardar();
    }
  });

  // ---------------- Registros de hoy ----------------

  function renderKpis(items) {
    var c = { total: items.length, interesado: 0, reunion: 0 };
    var convertidos = 0;
    var claveConversion = {};
    estados.forEach(function (e) { if (e.conversion) claveConversion[e.clave] = true; });
    items.forEach(function (i) {
      if (c[i.estado] !== undefined) c[i.estado]++;
      if (claveConversion[i.estado]) convertidos++;
    });
    var conversion = c.total ? Math.round(convertidos * 1000 / c.total) / 10 : 0;
    ui.kpis.querySelector('[data-kpi="total"]').textContent = c.total;
    ui.kpis.querySelector('[data-kpi="interesado"]').textContent = c.interesado;
    ui.kpis.querySelector('[data-kpi="reunion"]').textContent = c.reunion;
    ui.kpis.querySelector('[data-kpi="conversion"]').textContent = conversion + '%';
  }

  function tarjetaContacto(c) {
    var tarjeta = el('article', { class: 'contacto contacto--' + c.estado }, [
      el('time', { class: 'contacto__hora', datetime: c.creado_en, text: c.hora }),
      el('div', { class: 'contacto__principal' }, [
        el('div', { class: 'contacto__tel' }, [
          E.bandera(c.pais, c.pais_nombre), ' ', c.telefono_fmt
        ]),
        el('div', { class: 'contacto__meta' }, [
          E.insigniaEstado(c.estado, c.estado_label, c.estado_color),
          c.rubro ? el('span', { class: 'etiqueta', text: c.rubro }) : null,
          datos.usuario.admin ? el('span', { class: 'texto-suave', text: c.asesor }) : null
        ]),
        c.nota ? el('p', { class: 'contacto__nota', text: c.nota }) : null
      ]),
      el('div', { class: 'contacto__acciones' }, [
        el('a', { class: 'btn-icono btn-icono--tel', href: c.tel, title: 'Llamar', 'aria-label': 'Llamar a ' + c.telefono_fmt, text: '📞' }),
        el('a', {
          class: 'btn-icono btn-icono--wa', href: c.whatsapp, target: '_blank', rel: 'noopener noreferrer',
          title: 'WhatsApp', 'aria-label': 'Escribir por WhatsApp a ' + c.telefono_fmt, text: '💬'
        })
      ])
    ]);
    if (!esBasico(c.estado) && c.estado_color) tarjeta.style.borderLeftColor = c.estado_color;
    return tarjeta;
  }

  function cargarHoy() {
    E.api('api/listar.php?hoy=1&por_pagina=100').then(function (r) {
      if (!r.data || !r.data.ok) return;
      var items = r.data.items;
      renderKpis(items);
      E.vaciar(ui.lista);
      if (!items.length) {
        ui.lista.appendChild(el('div', { class: 'vacio' }, [
          el('span', { class: 'vacio__icono', text: '🌟' }),
          el('p', { text: 'Aún no hay registros hoy. ¡Dicta el primero!' })
        ]));
        return;
      }
      items.forEach(function (c) { ui.lista.appendChild(tarjetaContacto(c)); });
      if (r.data.total > items.length) {
        ui.lista.appendChild(el('p', { class: 'texto-suave centro', text: 'Mostrando ' + items.length + ' de ' + r.data.total + '. Mira el resto en Reportes.' }));
      }
    }).catch(function () {
      E.vaciar(ui.lista).appendChild(el('p', { class: 'vacio', text: 'No se pudo cargar la lista. Revisa tu conexión.' }));
    });
  }

  cargarHoy();
})();
