/*!
 * Estelar · parser de dictado (navegador).
 * Misma lógica que src/Parser.php y src/Telefono.php. Si cambias uno, cambia el otro
 * y corre: php tests/parser_test.php && node tests/parser_test.js
 */
(function (global) {
  'use strict';

  var PAISES = {
    PE: { codigo: '51', nombre: 'Perú', bandera: '🇵🇪' },
    CO: { codigo: '57', nombre: 'Colombia', bandera: '🇨🇴' }
  };

  var ESTADOS = {
    interesado: 'Interesado',
    medio_interesado: 'Medio interesado',
    reunion: 'Reunión',
    no_interesado: 'No interesado'
  };

  // Patrones de los 4 estados básicos. Si varios coinciden gana la frase más larga;
  // los estados creados en el sistema se suman con su nombre y sus palabras clave.
  var PATRONES_ESTADO = [
    ['no_interesado', ['no\\s+(?:le\\s+)?interesad[oa]s?', 'no\\s+(?:le|les)\\s+interesa', 'no\\s+(?:esta|estan)\\s+interesad[oa]s?', 'sin\\s+interes']],
    ['medio_interesado', ['medio\\s+interesad[oa]s?', 'mas\\s+o\\s+menos', 'tal\\s+vez', 'lo\\s+pensara', 'lo\\s+va\\s+a\\s+pensar', 'quizas?', 'indecis[oa]']],
    ['reunion', ['reunion(?:es)?', 'agendar', 'agendad[oa]', 'citas?']],
    ['interesado', ['interesad[oa]s?', 'le\\s+interesa']]
  ];

  var PALABRAS_RUBRO = ['rubro', 'sector', 'giro'];
  var PALABRAS_NOTA = ['nota', 'observacion', 'observaciones', 'comentario'];

  var UNIDADES = { cero: 0, uno: 1, un: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6, siete: 7, ocho: 8, nueve: 9 };
  var ESPECIALES = {
    diez: 10, once: 11, doce: 12, trece: 13, catorce: 14, quince: 15, dieciseis: 16, diecisiete: 17,
    dieciocho: 18, diecinueve: 19, veinte: 20, veintiuno: 21, veintiun: 21, veintiuna: 21, veintidos: 22,
    veintitres: 23, veinticuatro: 24, veinticinco: 25, veintiseis: 26, veintisiete: 27, veintiocho: 28, veintinueve: 29
  };
  var DECENAS = { treinta: 30, cuarenta: 40, cincuenta: 50, sesenta: 60, setenta: 70, ochenta: 80, noventa: 90 };
  var CENTENAS = {
    cien: 100, ciento: 100, doscientos: 200, doscientas: 200, trescientos: 300, trescientas: 300,
    cuatrocientos: 400, cuatrocientas: 400, quinientos: 500, quinientas: 500, seiscientos: 600, seiscientas: 600,
    setecientos: 700, setecientas: 700, ochocientos: 800, ochocientas: 800, novecientos: 900, novecientas: 900
  };
  var RELLENO = ['el', 'la', 'es', 'numero', 'num', 'nro', 'celular', 'cel', 'codigo', 'telefono', 'movil',
    'fijo', 'whatsapp', 'de', 'del', 'pais', 'mas', 'y', 'con', 'al', 'su', 'mi'];

  var TILDES = {
    'á': 'a', 'à': 'a', 'ä': 'a', 'â': 'a', 'é': 'e', 'è': 'e', 'ë': 'e', 'ê': 'e',
    'í': 'i', 'ì': 'i', 'ï': 'i', 'î': 'i', 'ó': 'o', 'ò': 'o', 'ö': 'o', 'ô': 'o',
    'ú': 'u', 'ù': 'u', 'ü': 'u', 'û': 'u', 'ñ': 'n', 'ç': 'c'
  };

  function has(obj, k) { return Object.prototype.hasOwnProperty.call(obj, k); }

  function sinTildes(s) {
    return s.replace(/[áàäâéèëêíìïîóòöôúùüûñç]/g, function (c) { return TILDES[c]; });
  }

  function capitalizar(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  // ---------------- Teléfono ----------------

  function validarLocal(pais, local) {
    var n = local.length;
    if (pais === 'PE') {
      if (n === 0) return { valido: false, tipo: null, error: 'Después del 51 falta el número.' };
      if (local[0] === '9') {
        return n === 9 ? { valido: true, tipo: 'celular', error: null }
          : { valido: false, tipo: null, error: 'Para Perú (celular) se esperan 9 dígitos después del 51, se recibieron ' + n + '.' };
      }
      if (local[0] === '1') {
        return n === 8 ? { valido: true, tipo: 'fijo', error: null }
          : { valido: false, tipo: null, error: 'Para Perú (fijo de Lima) se esperan 8 dígitos después del 51 (1 + 7), se recibieron ' + n + '.' };
      }
      return { valido: false, tipo: null, error: 'Después del 51 el número debe empezar con 9 (celular) o 1 (fijo de Lima).' };
    }
    if (pais === 'CO') {
      if (n === 0) return { valido: false, tipo: null, error: 'Después del 57 falta el número.' };
      var tipo = local[0] === '3' ? 'celular' : (local.indexOf('60') === 0 ? 'fijo' : null);
      if (tipo === null) return { valido: false, tipo: null, error: 'Después del 57 el número debe empezar con 3 (celular) o 60 (fijo).' };
      return n === 10 ? { valido: true, tipo: tipo, error: null }
        : { valido: false, tipo: null, error: 'Para Colombia se esperan 10 dígitos después del 57, se recibieron ' + n + '.' };
    }
    return { valido: false, tipo: null, error: 'País no soportado.' };
  }

  function formatear(e164) {
    var d = String(e164 || '').replace(/\D+/g, '');
    var l;
    if (d.indexOf('51') === 0) {
      l = d.slice(2);
      if (l.length === 9) return '+51 ' + l.slice(0, 3) + ' ' + l.slice(3, 6) + ' ' + l.slice(6);
      if (l.length === 8) return '+51 1 ' + l.slice(1, 4) + ' ' + l.slice(4);
    }
    if (d.indexOf('57') === 0 && d.length === 12) {
      l = d.slice(2);
      return '+57 ' + l.slice(0, 3) + ' ' + l.slice(3, 6) + ' ' + l.slice(6);
    }
    return d === '' ? '' : '+' + d;
  }

  function sugerirPais(d) {
    var pais = null, detalle = '';
    if (d.length === 9 && d[0] === '9') { pais = 'PE'; detalle = 'celular de Perú (9 dígitos que empiezan en 9)'; }
    else if (d.length === 10 && d[0] === '3') { pais = 'CO'; detalle = 'celular de Colombia (10 dígitos que empiezan en 3)'; }
    else if (d.length === 10 && d.indexOf('60') === 0) { pais = 'CO'; detalle = 'fijo de Colombia (10 dígitos que empiezan en 60)'; }
    if (!pais) return null;
    var e164 = '+' + PAISES[pais].codigo + d;
    return { pais: pais, e164: e164, local: d, mensaje: 'Parece un ' + detalle + '. ¿Confirmas que es ' + formatear(e164) + '?' };
  }

  function analizarTelefono(entrada, paisConfirmado) {
    var digitos = String(entrada || '').replace(/\D+/g, '');
    if (digitos.indexOf('00') === 0) digitos = digitos.slice(2);
    var res = { valido: false, digitos: digitos, pais: null, e164: null, local: null, tipo: null, error: null, sugerencia: null };

    if (digitos === '') { res.error = 'No se detectó ningún número de teléfono.'; return res; }

    paisConfirmado = paisConfirmado ? String(paisConfirmado).toUpperCase().trim() : null;
    if (paisConfirmado && !has(PAISES, paisConfirmado)) paisConfirmado = null;

    if (paisConfirmado) {
      var codigo = PAISES[paisConfirmado].codigo;
      if (digitos.indexOf(codigo) !== 0) {
        var otro = paisConfirmado === 'PE' ? 'CO' : 'PE';
        var codigoOtro = PAISES[otro].codigo;
        if (digitos.indexOf(codigoOtro) === 0 && validarLocal(otro, digitos.slice(2)).valido) {
          res.pais = otro;
          res.error = 'El número tiene el código ' + codigoOtro + ' (' + PAISES[otro].nombre + ') pero se eligió ' + PAISES[paisConfirmado].nombre + '.';
          return res;
        }
        digitos = codigo + digitos;
        res.digitos = digitos;
      }
    }

    var isos = ['PE', 'CO'];
    for (var i = 0; i < isos.length; i++) {
      var iso = isos[i];
      if (digitos.indexOf(PAISES[iso].codigo) !== 0) continue;
      var local = digitos.slice(2);
      var v = validarLocal(iso, local);
      res.pais = iso;
      if (!v.valido) { res.error = v.error; return res; }
      res.valido = true; res.local = local; res.e164 = '+' + digitos; res.tipo = v.tipo;
      return res;
    }

    res.error = 'Falta el código de país (51 o 57) al inicio del número.';
    res.sugerencia = sugerirPais(digitos);
    return res;
  }

  // ---------------- Número hablado ----------------

  function unidad(t) {
    if (t === undefined || t === null) return null;
    if (has(UNIDADES, t)) return UNIDADES[t];
    return (t.length === 1 && /\d/.test(t)) ? parseInt(t, 10) : null;
  }

  function leerMenorCien(tokens, i) {
    var t = tokens[i], u;
    if (t === undefined) return null;
    if (has(ESPECIALES, t)) return [ESPECIALES[t], i + 1];
    if (t === 'veinti') {
      u = unidad(tokens[i + 1]);
      if (u !== null && u > 0) return [20 + u, i + 2];
      return [20, i + 1];
    }
    if (has(DECENAS, t)) {
      var decena = DECENAS[t];
      if (tokens[i + 1] === 'y') {
        u = unidad(tokens[i + 2]);
        if (u !== null && u > 0) return [decena + u, i + 3];
      }
      return [decena, i + 1];
    }
    if (has(UNIDADES, t)) return [UNIDADES[t], i + 1];
    return null;
  }

  function leerGrupo(tokens, i) {
    var t = tokens[i];
    if (t === undefined) return null;
    if (/^\d+$/.test(t)) return [t, i + 1];
    if (has(CENTENAS, t)) {
      var centena = CENTENAS[t];
      if (t !== 'cien') {
        var resto = leerMenorCien(tokens, i + 1);
        if (resto !== null && resto[0] > 0) return [String(centena + resto[0]), resto[1]];
      }
      return [String(centena), i + 1];
    }
    var menor = leerMenorCien(tokens, i);
    return menor !== null ? [String(menor[0]), menor[1]] : null;
  }

  function convertirNumero(texto) {
    texto = sinTildes(String(texto).toLowerCase())
      .replace(/(\d)([a-z])/g, '$1 $2')
      .replace(/([a-z])(\d)/g, '$1 $2');
    var tokens = texto.split(/[^a-z0-9]+/).filter(Boolean);
    var salida = '', desconocidas = [], i = 0, grupo;

    while (i < tokens.length) {
      var t = tokens[i];
      if (t === 'doble' || t === 'triple') {
        grupo = leerGrupo(tokens, i + 1);
        if (grupo !== null) {
          salida += t === 'doble' ? grupo[0] + grupo[0] : grupo[0] + grupo[0] + grupo[0];
          i = grupo[1];
        } else {
          desconocidas.push(t);
          i++;
        }
        continue;
      }
      grupo = leerGrupo(tokens, i);
      if (grupo !== null) {
        salida += grupo[0];
        i = grupo[1];
        continue;
      }
      if (RELLENO.indexOf(t) === -1) desconocidas.push(t);
      i++;
    }
    return { digitos: salida, desconocidas: desconocidas };
  }

  // ---------------- Estado, rubro y nota ----------------

  // Lista de patrones [clave, regex, orden]. Sin estados de la BD: solo los 4 básicos.
  function patronesEstado(estados) {
    var lista = [];
    var basicos = {};
    PATRONES_ESTADO.forEach(function (par) { basicos[par[0]] = par[1]; });

    if (!estados || !estados.length) {
      PATRONES_ESTADO.forEach(function (par, i) {
        par[1].forEach(function (p) { lista.push([par[0], p, i + 1]); });
      });
      return lista;
    }

    estados.forEach(function (e) {
      var clave = String(e.clave);
      var orden = e.orden === undefined || e.orden === null ? 100 : Number(e.orden);
      var patrones = has(basicos, clave) ? basicos[clave].slice() : [];
      var frases = has(basicos, clave) ? [] : [String(e.nombre)];
      String(e.palabras || '').split(',').forEach(function (f) { frases.push(f); });
      frases.forEach(function (f) {
        var n = normalizarTexto(f);
        if (n.length >= 3) patrones.push(n.replace(/ /g, '\\s+'));
      });
      patrones.forEach(function (p) { lista.push([clave, p, orden]); });
    });
    return lista;
  }

  // Si coinciden varios gana la frase más larga; a igual largo, el de menor orden.
  function detectarEstado(texto, patrones) {
    var mejor = null, inicio = null;
    patrones.forEach(function (par) {
      var m = new RegExp('\\b' + par[1] + '\\b').exec(texto);
      if (m) {
        var largo = m[0].length;
        inicio = inicio === null ? m.index : Math.min(inicio, m.index);
        if (mejor === null || largo > mejor[1] || (largo === mejor[1] && par[2] < mejor[2])) mejor = [par[0], largo, par[2]];
      }
    });
    return [mejor ? mejor[0] : null, inicio === null ? texto.length : inicio];
  }

  function limpiarRubro(texto) {
    texto = texto.toLowerCase()
      .replace(/[^\p{L}\p{N}&\s]/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/^(?:(?:de|del|el|la|los|las|es)\s+)+/, '')
      .slice(0, 100);
    return texto === '' ? null : capitalizar(texto);
  }

  function limpiarNota(texto) {
    texto = texto.replace(/\s+/g, ' ').replace(/^[\s,.;:\-]+|[\s,.;:\-]+$/g, '').slice(0, 255);
    return texto === '' ? null : capitalizar(texto);
  }

  function normalizarTexto(texto) {
    return sinTildes(String(texto).toLowerCase()).replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function claveRubro(texto) {
    return normalizarTexto(texto).split(' ').map(function (p) {
      if (p.length > 3 && p.slice(-1) === 's') p = p.slice(0, -1);
      if (p.length > 3 && 'aeo'.indexOf(p.slice(-1)) !== -1) p = p.slice(0, -1);
      return p;
    }).join(' ');
  }

  function levenshtein(a, b) {
    if (a === b) return 0;
    var prev = [], cur, i, j;
    for (j = 0; j <= b.length; j++) prev[j] = j;
    for (i = 1; i <= a.length; i++) {
      cur = [i];
      for (j = 1; j <= b.length; j++) {
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
      }
      prev = cur;
    }
    return prev[b.length];
  }

  function buscarRubro(nombre, rubros) {
    var clave = claveRubro(nombre);
    if (clave === '') return { exacto: null, similar: null };
    var similar = null, mejor = Infinity;
    for (var i = 0; i < rubros.length; i++) {
      var r = rubros[i];
      var otra = claveRubro(r.nombre);
      if (otra === clave) return { exacto: { id: Number(r.id), nombre: String(r.nombre) }, similar: null };
      var d = levenshtein(clave, otra);
      if (d < mejor) { mejor = d; similar = { id: Number(r.id), nombre: String(r.nombre) }; }
    }
    var tolerancia = clave.length >= 8 ? 2 : 1;
    return { exacto: null, similar: mejor <= tolerancia ? similar : null };
  }

  // ---------------- Análisis completo ----------------

  function analizar(texto, rubros, paisConfirmado, estados) {
    rubros = rubros || [];
    var patrones = patronesEstado(estados);
    var original = String(texto || '').normalize('NFC').replace(/\s+/g, ' ').trim();
    var bajo = original.toLowerCase().replace(/[,.;:!?¿¡"'()\[\]\/\\_+\-]/g, ' ');
    var norm = sinTildes(bajo).replace(/[^\x00-\x7F]/g, ' ');

    var marcas = [];
    [['rubro', PALABRAS_RUBRO], ['nota', PALABRAS_NOTA]].forEach(function (par) {
      var m = new RegExp('\\b(?:' + par[1].join('|') + ')\\b').exec(norm);
      if (m) marcas.push({ tipo: par[0], inicio: m.index, fin: m.index + m[0].length });
    });
    marcas.sort(function (a, b) { return a.inicio - b.inicio; });

    var nucleo = norm.slice(0, marcas.length ? marcas[0].inicio : norm.length);
    var segmentos = { rubro: null, nota: null };
    marcas.forEach(function (marca, i) {
      var hasta = marcas[i + 1] ? marcas[i + 1].inicio : norm.length;
      segmentos[marca.tipo] = { norm: norm.slice(marca.fin, hasta), orig: original.slice(marca.fin, hasta) };
    });

    nucleo = nucleo.replace(/^\s*mas\s+(?!o\s+menos\b)/, ' ');

    var est = detectarEstado(nucleo, patrones);
    var estado = est[0];
    var textoNumero = estado !== null ? nucleo.slice(0, est[1]) : nucleo;

    if (estado === null && segmentos.rubro !== null) {
      var er = detectarEstado(segmentos.rubro.norm, patrones);
      if (er[0] !== null) {
        estado = er[0];
        segmentos.rubro.orig = segmentos.rubro.orig.slice(0, er[1]);
      }
    }

    var conversion = convertirNumero(textoNumero);
    var telefono = analizarTelefono(conversion.digitos, paisConfirmado);

    var rubro = segmentos.rubro !== null ? limpiarRubro(segmentos.rubro.orig) : null;
    var nota = segmentos.nota !== null ? limpiarNota(segmentos.nota.orig) : null;

    var rubroId = null, rubroNuevo = false, rubroSimilar = null;
    if (rubro !== null && rubros.length) {
      var b = buscarRubro(rubro, rubros);
      if (b.exacto) { rubro = b.exacto.nombre; rubroId = b.exacto.id; }
      else { rubroNuevo = true; rubroSimilar = b.similar; }
    }

    var errores = [], advertencias = [];
    if (!telefono.valido) errores.push(telefono.error);
    if (estado === null) errores.push('No se reconoció el estado. Di por ejemplo: interesado, no interesado, reunión, no contesta…');
    if (conversion.desconocidas.length) advertencias.push('No entendí: "' + conversion.desconocidas.join('", "') + '" (se ignoró).');

    return {
      valido: telefono.valido && estado !== null,
      texto: original,
      numero_texto: textoNumero.replace(/\s+/g, ' ').trim(),
      digitos: conversion.digitos,
      telefono: telefono,
      pais: telefono.pais,
      telefono_e164: telefono.e164,
      telefono_local: telefono.local,
      estado: estado,
      rubro: rubro,
      rubro_id: rubroId,
      rubro_nuevo: rubroNuevo,
      rubro_similar: rubroSimilar,
      nota: nota,
      errores: errores,
      advertencias: advertencias
    };
  }

  var api = {
    PAISES: PAISES,
    ESTADOS: ESTADOS,
    analizar: analizar,
    analizarTelefono: analizarTelefono,
    convertirNumero: convertirNumero,
    formatear: formatear,
    buscarRubro: buscarRubro,
    claveRubro: claveRubro,
    normalizarTexto: normalizarTexto,
    capitalizar: capitalizar
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  } else {
    global.EstelarParser = api;
  }
})(typeof window !== 'undefined' ? window : this);
