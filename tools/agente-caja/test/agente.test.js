'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { crearAgente, validarConfig, pulsoCajon } = require('../agente.js');

const TOKEN = 'x'.repeat(40);

/** Arrange común: un agente con driver «archivo» en un puerto libre. */
async function montar(extra = {}) {
  const ruta = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'agente-')), 'salida.bin');
  const servidor = crearAgente({ puerto: 9123, token: TOKEN, driver: 'archivo', archivo: { ruta }, origenes: ['https://mi-tienda.example'], ...extra }, { log: () => {} });
  await new Promise((r) => servidor.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${servidor.address().port}`;
  return { base, ruta, cerrar: () => new Promise((r) => servidor.close(r)) };
}

const post = (url, cuerpo, cabeceras = {}) =>
  fetch(url, { method: 'POST', headers: { 'Content-Type': 'text/plain', ...cabeceras }, body: JSON.stringify(cuerpo) });

test('feliz: /estado contesta con el driver y el destino', async () => {
  const a = await montar();
  const r = await fetch(`${a.base}/estado?token=${TOKEN}`);
  const d = await r.json();
  assert.equal(r.status, 200);
  assert.equal(d.ok, true);
  assert.equal(d.driver, 'archivo');
  assert.equal(d.destino, a.ruta);
  await a.cerrar();
});

test('feliz: /cajon escribe el pulso ESC p en el destino', async () => {
  const a = await montar();
  const r = await post(`${a.base}/cajon`, { token: TOKEN });
  assert.deepEqual(await r.json(), { abierto: true });
  assert.deepEqual([...fs.readFileSync(a.ruta)], [0x1b, 0x70, 0, 60, 120]);
  await a.cerrar();
});

test('feliz: /imprimir decodifica el base64 y entrega los bytes exactos', async () => {
  const a = await montar();
  const bytes = Buffer.from([0x1b, 0x40, 0x48, 0x6f, 0x6c, 0x61, 0x0a, 0x1d, 0x56, 0x00]);
  const r = await post(`${a.base}/imprimir`, { token: TOKEN, datos: bytes.toString('base64') });
  assert.deepEqual(await r.json(), { impreso: true, bytes: bytes.length });
  assert.deepEqual(fs.readFileSync(a.ruta), bytes);
  await a.cerrar();
});

test('error: sin token o con token distinto responde 401 y no escribe nada', async () => {
  const a = await montar();
  assert.equal((await post(`${a.base}/cajon`, {})).status, 401);
  assert.equal((await post(`${a.base}/imprimir`, { token: 'y'.repeat(40), datos: 'QQ==' })).status, 401);
  assert.equal((await fetch(`${a.base}/estado?token=corto`)).status, 401);
  assert.equal(fs.existsSync(a.ruta), false);
  await a.cerrar();
});

test('error: datos que no son base64 responden 422', async () => {
  const a = await montar();
  const r = await post(`${a.base}/imprimir`, { token: TOKEN, datos: '<script>' });
  assert.equal(r.status, 422);
  assert.equal((await r.json()).impreso, false);
  assert.equal((await post(`${a.base}/imprimir`, { token: TOKEN })).status, 422);
  await a.cerrar();
});

test('error: cuerpo que no es JSON responde 400; ruta desconocida, 404', async () => {
  const a = await montar();
  const r = await fetch(`${a.base}/cajon`, { method: 'POST', body: 'no-json' });
  assert.equal(r.status, 400);
  assert.equal((await fetch(`${a.base}/otra`)).status, 404);
  await a.cerrar();
});

test('error: si el aparato falla responde 502 con el detalle', async () => {
  const a = await montar({ archivo: { ruta: '/carpeta/que/no/existe/x.bin' } });
  const r = await post(`${a.base}/cajon`, { token: TOKEN });
  assert.equal(r.status, 502);
  assert.equal((await r.json()).abierto, false);
  await a.cerrar();
});

test('CORS: localhost y 127.0.0.1 en cualquier puerto, y el origen configurado, reciben cabeceras', async () => {
  const a = await montar();
  for (const origen of ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://localhost:5173', 'https://mi-tienda.example']) {
    const r = await post(`${a.base}/cajon`, { token: TOKEN }, { Origin: origen });
    assert.equal(r.status, 200, origen);
    assert.equal(r.headers.get('access-control-allow-origin'), origen);
  }
  await a.cerrar();
});

test('CORS: un origen ajeno recibe 403 sin cabeceras y no abre el cajón aunque traiga el token', async () => {
  const a = await montar();
  const r = await post(`${a.base}/cajon`, { token: TOKEN }, { Origin: 'https://sitio-malicioso.example' });
  assert.equal(r.status, 403);
  assert.equal(r.headers.get('access-control-allow-origin'), null);
  assert.equal(fs.existsSync(a.ruta), false);
  await a.cerrar();
});

test('CORS: el preflight de red local (Chrome) se responde 204 con Allow-Private-Network', async () => {
  const a = await montar();
  const r = await fetch(`${a.base}/imprimir`, {
    method: 'OPTIONS',
    headers: { Origin: 'https://mi-tienda.example', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Private-Network': 'true' },
  });
  assert.equal(r.status, 204);
  assert.equal(r.headers.get('access-control-allow-private-network'), 'true');
  assert.match(r.headers.get('access-control-allow-methods'), /POST/);
  await a.cerrar();
});

test('borde: dos recibos simultáneos salen completos y sin entremezclarse', async () => {
  const a = await montar();
  const uno = Buffer.alloc(40000, 0x41);
  const dos = Buffer.alloc(40000, 0x42);
  await Promise.all([
    post(`${a.base}/imprimir`, { token: TOKEN, datos: uno.toString('base64') }),
    post(`${a.base}/imprimir`, { token: TOKEN, datos: dos.toString('base64') }),
  ]);
  const salida = fs.readFileSync(a.ruta);
  assert.equal(salida.length, 80000);
  const mitad = salida.subarray(0, 40000);
  assert.ok(mitad.every((b) => b === mitad[0]), 'la primera mitad es de un solo recibo');
  await a.cerrar();
});

test('borde: un cuerpo de más de 2 MB se rechaza', async () => {
  const a = await montar();
  const r = await post(`${a.base}/imprimir`, { token: TOKEN, datos: 'A'.repeat(3 * 1024 * 1024) }).catch(() => ({ status: 413 }));
  assert.equal(r.status, 413);
  await a.cerrar();
});

test('driver red: los bytes llegan al puerto 9100 de la impresora', async () => {
  const recibido = [];
  const impresora = net.createServer((s) => s.on('data', (d) => recibido.push(d)));
  await new Promise((r) => impresora.listen(0, '127.0.0.1', r));
  const a = await montar({ driver: 'red', red: { host: '127.0.0.1', puerto: impresora.address().port } });
  const r = await post(`${a.base}/cajon`, { token: TOKEN });
  assert.equal(r.status, 200);
  await new Promise((r2) => setTimeout(r2, 50));
  assert.deepEqual([...Buffer.concat(recibido)], [...pulsoCajon({})]);
  await a.cerrar();
  await new Promise((r2) => impresora.close(r2));
});

test('driver dispositivo (Linux): escribe en el archivo de dispositivo indicado', async () => {
  const ruta = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'dev-')), 'lp0');
  fs.writeFileSync(ruta, '');
  const a = await montar({ driver: 'dispositivo', dispositivo: { ruta } });
  assert.equal((await post(`${a.base}/cajon`, { token: TOKEN })).status, 200);
  assert.equal(fs.readFileSync(ruta).length, 5);
  await a.cerrar();
});

test('configuración: token de relleno, puerto inválido y driver desconocido se rechazan', () => {
  assert.throws(() => validarConfig({ token: 'PEGA_AQUI_EL_TOKEN_DEL_LOCAL' }), /token/);
  assert.throws(() => validarConfig({ token: TOKEN, puerto: 80 }), /puerto/);
  assert.throws(() => validarConfig({ token: TOKEN, driver: 'magia' }), /Driver desconocido/);
  assert.equal(validarConfig({ token: TOKEN }).driver, 'archivo');
});

test('pulso: los tiempos se acotan a un byte y el pin solo puede ser 0 o 1', () => {
  assert.deepEqual([...pulsoCajon({ cajon: { pin: 1, on_ms: 9999, off_ms: 0 } })], [0x1b, 0x70, 1, 255, 1]);
  assert.deepEqual([...pulsoCajon({ cajon: { pin: 7 } })].slice(2, 3), [0]);
});
