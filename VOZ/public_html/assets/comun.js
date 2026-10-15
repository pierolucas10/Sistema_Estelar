/*!
 * Estelar · utilidades comunes (API, toasts, confirmaciones y creación segura de nodos).
 */
(function () {
  'use strict';

  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';

  /**
   * Crea un elemento sin usar innerHTML con datos del usuario.
   * el('a', {href: '#', class: 'x'}, ['texto', otroNodo])
   */
  function el(tag, attrs, hijos) {
    var nodo = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'class') nodo.className = v;
      else if (k === 'text') nodo.textContent = v;
      else if (k.indexOf('on') === 0 && typeof v === 'function') nodo.addEventListener(k.slice(2), v);
      else nodo.setAttribute(k, v === true ? '' : String(v));
    });
    (Array.isArray(hijos) ? hijos : (hijos === undefined ? [] : [hijos])).forEach(function (h) {
      if (h === null || h === undefined || h === false) return;
      nodo.appendChild(typeof h === 'string' || typeof h === 'number' ? document.createTextNode(String(h)) : h);
    });
    return nodo;
  }

  function vaciar(nodo) {
    while (nodo.firstChild) nodo.removeChild(nodo.firstChild);
    return nodo;
  }

  /**
   * fetch a la API con CSRF, JSON y manejo de sesión expirada.
   * Resuelve con {status, data}; nunca lanza por códigos 4xx.
   */
  function api(url, opciones) {
    opciones = opciones || {};
    var init = {
      method: opciones.method || 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
    };
    if (opciones.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opciones.body);
    }
    if (opciones.signal) init.signal = opciones.signal;

    return fetch(url, init).then(function (res) {
      if (res.status === 401) {
        toast('Tu sesión expiró. Te llevamos al inicio de sesión…', 'error');
        setTimeout(function () { window.location.href = 'login.php'; }, 1200);
      }
      return res.json().catch(function () { return { ok: false, error: 'Respuesta inesperada del servidor.' }; })
        .then(function (data) { return { status: res.status, data: data }; });
    });
  }

  var toastTimer = null;
  function toast(mensaje, tipo) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = mensaje;
    t.className = 'toast toast--' + (tipo || 'exito');
    t.hidden = false;
    // Reinicia la animación
    void t.offsetWidth;
    t.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      t.classList.remove('is-visible');
      setTimeout(function () { t.hidden = true; }, 300);
    }, 3200);
  }

  /** Bandera dibujada con CSS (Windows no muestra emojis de banderas). */
  function bandera(iso, titulo, extra) {
    return el('span', { class: 'bandera bandera--' + iso + (extra ? ' ' + extra : ''), title: titulo || null, role: titulo ? 'img' : null, 'aria-label': titulo || null, 'aria-hidden': titulo ? null : 'true' });
  }

  var BASICOS = ['interesado', 'reunion', 'medio_interesado', 'no_interesado'];

  /** Insignia de estado: los 4 básicos usan su clase CSS; los creados en el sistema, su color. */
  function insigniaEstado(estado, etiqueta, color) {
    var nodo = el('span', { class: 'insignia insignia--' + estado, text: etiqueta });
    if (BASICOS.indexOf(estado) === -1 && color) {
      nodo.style.backgroundColor = color;
      nodo.style.color = '#fff';
    }
    return nodo;
  }

  // Confirmación para formularios marcados con data-confirmar
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var msg = form.getAttribute && form.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  // Selects que se envían solos al cambiar
  document.addEventListener('change', function (ev) {
    if (ev.target.matches && ev.target.matches('select[data-autoenviar]')) ev.target.form.submit();
  });

  // Avisos flash que se ocultan solos
  document.querySelectorAll('[data-cerrable]').forEach(function (aviso) {
    setTimeout(function () { aviso.classList.add('is-oculto'); }, 6000);
    aviso.addEventListener('click', function () { aviso.classList.add('is-oculto'); });
  });

  window.Estelar = { api: api, el: el, vaciar: vaciar, toast: toast, insigniaEstado: insigniaEstado, bandera: bandera, BASICOS: BASICOS };
})();
