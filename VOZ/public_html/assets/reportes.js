/*!
 * Estelar · reportes mensuales (api/reporte_mensual.php + api/listar.php + Chart.js).
 */
(function () {
  'use strict';

  var E = window.Estelar;
  var el = E.el;

  var form = document.getElementById('form-filtros');
  var contenedor = document.getElementById('reporte');
  var csvDetalle = document.getElementById('btn-csv-detalle');
  var csvResumen = document.getElementById('btn-csv-resumen');
  var buscador = document.getElementById('buscar-telefono');

  // Los 4 estados básicos siempre se muestran; el resto (creados en el sistema) solo si tienen datos.
  var BASICOS = ['interesado', 'reunion', 'medio_interesado', 'no_interesado'];
  var ETIQUETAS = { interesado: 'Interesados', reunion: 'Reuniones', medio_interesado: 'Medio interesados', no_interesado: 'No interesados' };
  var COLORES = {
    interesado: '#16a34a', reunion: '#0ea5e9', medio_interesado: '#f59e0b', no_interesado: '#e11d48',
    PE: '#dc2626', CO: '#e0a800', total: '#1f63d6'
  };
  var infoEstados = {};

  function registrarEstados(lista) {
    infoEstados = {};
    (lista || []).forEach(function (e) { infoEstados[e.clave] = e; });
  }
  function etiqueta(clave, porEstado) {
    if (ETIQUETAS[clave]) return ETIQUETAS[clave];
    if (porEstado && porEstado[clave]) return porEstado[clave].label;
    return infoEstados[clave] ? infoEstados[clave].nombre : clave;
  }
  function color(clave, porEstado) {
    if (BASICOS.indexOf(clave) !== -1) return COLORES[clave];
    if (porEstado && porEstado[clave]) return porEstado[clave].color;
    return infoEstados[clave] ? infoEstados[clave].color : '#64748b';
  }
  /** Claves a mostrar: los básicos + cualquier otro estado con contactos. */
  function clavesVisibles(porEstado) {
    var claves = BASICOS.slice();
    Object.keys(porEstado).forEach(function (k) {
      if (claves.indexOf(k) === -1 && porEstado[k].n > 0) claves.push(k);
    });
    return claves;
  }
  function textoConversion() {
    var nombres = Object.keys(infoEstados).filter(function (k) { return infoEstados[k].conversion; })
      .map(function (k) { return infoEstados[k].nombre.toLowerCase(); });
    return '(' + (nombres.length ? nombres.join(' + ') : 'interesados + reuniones') + ') / total';
  }

  var ultimo = null;
  var grafico = null;
  var paginaDetalle = 1;
  var timerBusqueda = null;

  function num(n) { return Number(n || 0).toLocaleString('es-PE'); }
  function pct(n) { return Number(n || 0).toLocaleString('es-PE', { maximumFractionDigits: 1 }) + '%'; }

  function parametros() {
    var qs = new URLSearchParams();
    new FormData(form).forEach(function (v, k) { if (v !== '' && v !== 'todos') qs.append(k, v); });
    return qs;
  }

  function actualizarEnlaces(qs) {
    csvDetalle.href = 'api/exportar.php?tipo=detalle&' + qs.toString();
    csvResumen.href = 'api/exportar.php?tipo=resumen&' + qs.toString();
  }

  // ---------------- 1. Resumen ----------------

  function variacion(valor, sufijo, etiquetaAnterior, esPuntos) {
    if (valor === null || valor === undefined) {
      return el('span', { class: 'kpi__sub', text: 'Sin datos en ' + etiquetaAnterior });
    }
    var clase = valor > 0 ? 'sube' : (valor < 0 ? 'baja' : 'igual');
    var flecha = valor > 0 ? '▲' : (valor < 0 ? '▼' : '＝');
    var texto = flecha + ' ' + (valor > 0 ? '+' : '') + Number(valor).toLocaleString('es-PE', { maximumFractionDigits: 1 }) +
      (esPuntos ? ' pts' : sufijo) + ' vs ' + etiquetaAnterior;
    return el('span', { class: 'kpi__sub kpi__sub--' + clase, text: texto });
  }

  function barra(valor, color) {
    var relleno = el('span', { class: 'barra-progreso__relleno' });
    relleno.style.width = Math.max(0, Math.min(100, valor)) + '%';
    relleno.style.background = color;
    return el('span', { class: 'barra-progreso' }, relleno);
  }

  function renderResumen(data) {
    var r = data.resumen;
    var ant = r.anterior;
    var kpis = document.getElementById('kpis');
    E.vaciar(kpis);
    document.querySelectorAll('[data-campo="etiqueta"]').forEach(function (n) { n.textContent = data.etiqueta; });

    var subTotal = r.variacion.total_pct === null
      ? (ant.total === 0 ? el('span', { class: 'kpi__sub', text: 'Sin registros en ' + ant.etiqueta }) : null)
      : variacion(r.variacion.total_pct, '%', ant.etiqueta, false);

    kpis.appendChild(el('article', { class: 'kpi kpi--destacado' }, [
      el('span', { class: 'kpi__etiqueta', text: 'Total de contactos' }),
      el('strong', { class: 'kpi__valor', text: num(r.total) }),
      el('span', { class: 'kpi__sub', text: (r.variacion.total >= 0 ? '+' : '') + num(r.variacion.total) + ' respecto a ' + ant.etiqueta + ' (' + num(ant.total) + ')' }),
      subTotal
    ]));

    kpis.appendChild(el('article', { class: 'kpi kpi--conversion' }, [
      el('span', { class: 'kpi__etiqueta', text: 'Tasa de conversión' }),
      el('strong', { class: 'kpi__valor', text: pct(r.conversion) }),
      el('span', { class: 'kpi__sub', text: textoConversion() }),
      ant.total > 0 ? variacion(r.variacion.conversion, '', ant.etiqueta, true) : null
    ]));

    clavesVisibles(r.por_estado).forEach(function (e) {
      var d = r.por_estado[e] || { n: 0, pct: 0 };
      var tarjeta = el('article', { class: 'kpi kpi--' + e }, [
        el('span', { class: 'kpi__etiqueta', text: etiqueta(e, r.por_estado) }),
        el('strong', { class: 'kpi__valor', text: num(d.n) }),
        el('span', { class: 'kpi__sub', text: pct(d.pct) + ' del total' }),
        barra(d.pct, color(e, r.por_estado))
      ]);
      if (BASICOS.indexOf(e) === -1) tarjeta.style.borderTopColor = color(e, r.por_estado);
      kpis.appendChild(tarjeta);
    });
  }

  // ---------------- 2. Perú vs Colombia ----------------

  function renderPaises(paises) {
    var cont = document.getElementById('paises');
    E.vaciar(cont);
    ['PE', 'CO'].forEach(function (iso) {
      var p = paises[iso];
      var apilada = el('div', { class: 'apilada', role: 'img', 'aria-label': 'Distribución por estado en ' + p.nombre });
      var visibles = clavesVisibles(p.por_estado);
      visibles.forEach(function (e) {
        var d = p.por_estado[e];
        if (!d || !d.pct) return;
        var seg = el('span', { class: 'apilada__seg', title: etiqueta(e, p.por_estado) + ': ' + pct(d.pct) });
        seg.style.width = d.pct + '%';
        seg.style.background = color(e, p.por_estado);
        apilada.appendChild(seg);
      });
      if (!p.total) apilada.appendChild(el('span', { class: 'apilada__vacia', text: 'Sin registros' }));

      var leyenda = el('ul', { class: 'leyenda' }, visibles.map(function (e) {
        var d = p.por_estado[e] || { n: 0, pct: 0 };
        var punto = el('span', { class: 'leyenda__punto' });
        punto.style.background = color(e, p.por_estado);
        return el('li', {}, [punto, etiqueta(e, p.por_estado) + ': ', el('strong', { text: num(d.n) }), ' (' + pct(d.pct) + ')']);
      }));

      var top = p.top_rubros.length
        ? el('ol', { class: 'top-rubros' }, p.top_rubros.map(function (t) {
          return el('li', {}, [el('span', { text: t.rubro }), el('span', { class: 'texto-suave', text: num(t.total) + ' · ' + pct(t.conversion) + ' conv.' })]);
        }))
        : el('p', { class: 'texto-suave', text: 'Sin rubros este mes.' });

      cont.appendChild(el('article', { class: 'tarjeta pais pais--' + iso }, [
        el('header', { class: 'pais__cabecera' }, [
          E.bandera(iso, p.nombre, 'bandera--grande'),
          el('h3', { text: p.nombre })
        ]),
        el('div', { class: 'pais__numeros' }, [
          el('div', {}, [el('strong', { text: num(p.total) }), el('span', { text: 'contactos' })]),
          el('div', {}, [el('strong', { text: pct(p.conversion) }), el('span', { text: 'conversión' })])
        ]),
        apilada,
        leyenda,
        el('h4', { text: 'Top 5 rubros' }),
        top
      ]));
    });
  }

  // ---------------- 3. Por rubro ----------------

  function filaVacia(tbody, columnas, texto) {
    tbody.appendChild(el('tr', {}, el('td', { colspan: columnas, class: 'vacio', text: texto })));
  }

  function renderRubros(filas) {
    var tbody = document.querySelector('#tabla-rubros tbody');
    E.vaciar(tbody);
    if (!filas.length) { filaVacia(tbody, 9, 'No hay contactos con estos filtros.'); return; }
    filas.forEach(function (f) {
      tbody.appendChild(el('tr', {}, [
        el('td', { text: f.rubro }),
        el('td', { class: 'nowrap' }, [E.bandera(f.pais), ' ' + f.pais]),
        el('td', { class: 'num', text: num(f.total) }),
        el('td', { class: 'num', text: num(f.interesado) }),
        el('td', { class: 'num', text: num(f.medio_interesado) }),
        el('td', { class: 'num', text: num(f.reunion) }),
        el('td', { class: 'num', text: num(f.no_interesado) }),
        el('td', { class: 'num', text: num(f.otros) }),
        el('td', { class: 'num' }, [el('span', { class: 'conversion-celda', text: pct(f.conversion) }), barra(f.conversion, COLORES.interesado)])
      ]));
    });
  }

  // ---------------- 4. Evolución ----------------

  function vistaGrafico() {
    var r = document.querySelector('input[name="vista-grafico"]:checked');
    return r ? r.value : 'pais';
  }

  function serie(etiqueta, datos, color, punteada) {
    return {
      label: etiqueta, data: datos, borderColor: color, backgroundColor: color,
      tension: 0.35, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6, fill: false,
      borderDash: punteada ? [6, 4] : []
    };
  }

  function renderGrafico() {
    if (!ultimo) return;
    var canvas = document.getElementById('grafico-evolucion');
    if (typeof window.Chart === 'undefined') {
      canvas.replaceWith(el('p', { class: 'vacio', text: 'No se pudo cargar la librería de gráficos. Revisa tu conexión.' }));
      return;
    }
    var filas = ultimo.evolucion.filas;
    var etiquetas = filas.map(function (f) { return f.etiqueta; });
    var vista = vistaGrafico();
    var series;

    if (vista === 'estado') {
      var claves = BASICOS.concat((ultimo.evolucion.estados_con_datos || []).filter(function (k) { return BASICOS.indexOf(k) === -1; }));
      series = claves.map(function (e) {
        return serie(etiqueta(e), filas.map(function (f) { return (f.estados && f.estados[e]) || 0; }), color(e));
      });
    } else if (vista === 'conversion') {
      series = [
        serie('Perú', filas.map(function (f) { return f.conv_PE; }), COLORES.PE),
        serie('Colombia', filas.map(function (f) { return f.conv_CO; }), COLORES.CO),
        serie('Total', filas.map(function (f) { return f.conversion; }), COLORES.total, true)
      ];
    } else {
      series = [
        serie('Perú', filas.map(function (f) { return f.PE; }), COLORES.PE),
        serie('Colombia', filas.map(function (f) { return f.CO; }), COLORES.CO)
      ];
    }

    var esPct = vista === 'conversion';
    window.Chart.defaults.font.family = "'Nunito', system-ui, sans-serif";
    window.Chart.defaults.color = '#5f5d7d';

    if (grafico) grafico.destroy();
    grafico = new window.Chart(canvas, {
      type: 'line',
      data: { labels: etiquetas, datasets: series },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + (esPct ? pct(ctx.parsed.y) : num(ctx.parsed.y)); }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            suggestedMax: esPct ? 100 : undefined,
            ticks: { precision: 0, callback: function (v) { return esPct ? v + '%' : num(v); } },
            grid: { color: 'rgba(80, 70, 160, .08)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  function renderEvolucion(evolucion) {
    var tbody = document.querySelector('#tabla-evolucion tbody');
    E.vaciar(tbody);
    evolucion.filas.slice().reverse().forEach(function (f) {
      tbody.appendChild(el('tr', { class: f.periodo === ultimo.periodo ? 'fila--actual' : '' }, [
        el('td', { text: f.etiqueta }),
        el('td', { class: 'num', text: num(f.total) }),
        el('td', { class: 'num', text: num(f.PE) }),
        el('td', { class: 'num', text: num(f.CO) }),
        el('td', { class: 'num', text: num(f.interesado) }),
        el('td', { class: 'num', text: num(f.medio_interesado) }),
        el('td', { class: 'num', text: num(f.reunion) }),
        el('td', { class: 'num', text: num(f.no_interesado) }),
        el('td', { class: 'num', text: num(f.otros) }),
        el('td', { class: 'num', text: pct(f.conversion) })
      ]));
    });
    renderGrafico();
  }

  // ---------------- 5. Detalle ----------------

  function renderPaginacion(datos) {
    var nav = document.getElementById('paginacion');
    E.vaciar(nav);
    if (datos.paginas <= 1) return;
    nav.appendChild(el('button', {
      type: 'button', class: 'btn btn--secundario btn--chico', disabled: datos.pagina <= 1, text: '← Anterior',
      onclick: function () { cargarDetalle(datos.pagina - 1); }
    }));
    nav.appendChild(el('span', { class: 'paginacion__info', text: 'Página ' + datos.pagina + ' de ' + datos.paginas }));
    nav.appendChild(el('button', {
      type: 'button', class: 'btn btn--secundario btn--chico', disabled: datos.pagina >= datos.paginas, text: 'Siguiente →',
      onclick: function () { cargarDetalle(datos.pagina + 1); }
    }));
  }

  function cargarDetalle(pagina) {
    paginaDetalle = pagina || 1;
    var qs = parametros();
    qs.set('pagina', paginaDetalle);
    qs.set('por_pagina', 20);
    if (buscador.value.trim()) qs.set('q', buscador.value.trim());

    var tbody = document.querySelector('#tabla-detalle tbody');
    E.api('api/listar.php?' + qs.toString()).then(function (r) {
      E.vaciar(tbody);
      if (!r.data || !r.data.ok) { filaVacia(tbody, 8, (r.data && r.data.error) || 'No se pudo cargar el detalle.'); return; }
      document.getElementById('detalle-total').textContent = num(r.data.total) + ' contacto(s)';
      if (!r.data.items.length) filaVacia(tbody, 8, 'No hay contactos con estos filtros.');
      r.data.items.forEach(function (c) {
        tbody.appendChild(el('tr', {}, [
          el('td', { class: 'nowrap' }, [E.bandera(c.pais, c.pais_nombre), ' ' + c.pais]),
          el('td', { class: 'nowrap', text: c.telefono_fmt }),
          el('td', {}, E.insigniaEstado(c.estado, c.estado_label, c.estado_color)),
          el('td', { text: c.rubro || '—' }),
          el('td', { class: 'celda-nota', text: c.nota || '' }),
          el('td', { text: c.asesor }),
          el('td', { class: 'nowrap', text: c.fecha }),
          el('td', { class: 'nowrap' }, [
            el('a', { class: 'btn-icono btn-icono--tel', href: c.tel, title: 'Llamar', 'aria-label': 'Llamar a ' + c.telefono_fmt, text: '📞' }),
            el('a', { class: 'btn-icono btn-icono--wa', href: c.whatsapp, target: '_blank', rel: 'noopener noreferrer', title: 'WhatsApp', 'aria-label': 'WhatsApp a ' + c.telefono_fmt, text: '💬' })
          ])
        ]));
      });
      renderPaginacion(r.data);
    }).catch(function () {
      E.vaciar(tbody);
      filaVacia(tbody, 8, 'Sin conexión.');
    });
  }

  // ---------------- Carga general ----------------

  function cargar() {
    var qs = parametros();
    actualizarEnlaces(qs);
    window.history.replaceState(null, '', 'reportes.php?' + qs.toString());
    contenedor.setAttribute('aria-busy', 'true');
    contenedor.classList.add('is-cargando');

    E.api('api/reporte_mensual.php?' + qs.toString()).then(function (r) {
      if (!r.data || !r.data.ok) { E.toast((r.data && r.data.error) || 'No se pudo cargar el reporte.', 'error'); return; }
      ultimo = r.data;
      registrarEstados(r.data.estados);
      renderResumen(r.data);
      renderPaises(r.data.paises);
      renderRubros(r.data.rubros);
      renderEvolucion(r.data.evolucion);
    }).catch(function () {
      E.toast('Sin conexión. Intenta otra vez.', 'error');
    }).then(function () {
      contenedor.setAttribute('aria-busy', 'false');
      contenedor.classList.remove('is-cargando');
    });

    cargarDetalle(1);
  }

  form.addEventListener('submit', function (ev) { ev.preventDefault(); cargar(); });
  form.addEventListener('change', cargar);
  document.getElementById('selector-grafico').addEventListener('change', renderGrafico);
  buscador.addEventListener('input', function () {
    clearTimeout(timerBusqueda);
    timerBusqueda = setTimeout(function () { cargarDetalle(1); }, 300);
  });

  cargar();
})();
