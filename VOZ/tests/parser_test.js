/*
 * Pruebas del parser del navegador con los mismos casos que tests/parser_test.php.
 *   node tests/parser_test.js
 */
'use strict';

const path = require('path');
const fs = require('fs');
const Parser = require(path.join(__dirname, '..', 'public_html', 'assets', 'parser.js'));

const datos = JSON.parse(fs.readFileSync(path.join(__dirname, 'casos_parser.json'), 'utf8'));
let ok = 0;
let fallidos = 0;

console.log(`\n⭐ Estelar · parser JS (${datos.casos.length} frases)\n`);

datos.casos.forEach((caso, n) => {
  const r = Parser.analizar(caso.frase, datos.rubros, null, caso.usar_estados ? datos.estados : []);
  const fallas = [];

  for (const [campo, valor] of Object.entries(caso.esperado)) {
    if (campo === 'error_contiene') {
      const todos = r.errores.join(' | ');
      if (!todos.includes(valor)) fallas.push(`errores debía contener "${valor}", obtuvo "${todos}"`);
    } else if (campo === 'sugerencia_pais') {
      const obtenido = r.telefono.sugerencia ? r.telefono.sugerencia.pais : null;
      if (obtenido !== valor) fallas.push(`sugerencia_pais: esperado ${valor}, obtuvo ${obtenido}`);
    } else {
      const obtenido = r[campo] === undefined ? null : r[campo];
      if (obtenido !== valor) fallas.push(`${campo}: esperado ${JSON.stringify(valor)}, obtuvo ${JSON.stringify(obtenido)}`);
    }
  }

  const num = String(n + 1).padStart(2, '0');
  if (fallas.length === 0) {
    ok++;
    console.log(`  ✔ ${num}. ${caso.descripcion}`);
  } else {
    fallidos++;
    console.log(`  ✘ ${num}. ${caso.descripcion}\n       frase: "${caso.frase}"`);
    fallas.forEach((f) => console.log(`       - ${f}`));
  }
});

console.log(fallidos === 0 ? `\nTodo en orden: ${ok} pruebas pasaron.\n` : `\n${fallidos} fallaron, ${ok} pasaron.\n`);
process.exit(fallidos === 0 ? 0 : 1);
