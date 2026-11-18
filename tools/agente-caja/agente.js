#!/usr/bin/env node
'use strict';

/**
 * Agente de caja de Mega Uni Store (RN-28).
 *
 * El servidor puede estar en la nube y no alcanza la impresora del mostrador.
 * Este proceso corre en el PC de caja, escucha SOLO en 127.0.0.1 y recibe del
 * navegador (el POS) dos órdenes: abrir el cajón e imprimir bytes ESC/POS.
 *
 *   GET  /estado?token=…           → { ok, driver, destino, version }
 *   POST /cajon     { token }      → { abierto: true }
 *   POST /imprimir  { token, datos: <base64> } → { impreso: true, bytes }
 *
 * Los POST llegan como text/plain con un JSON adentro: así el navegador los
 * trata como petición «simple» y no necesita preflight. Aun así se responde
 * el preflight (OPTIONS), porque Chrome lo exige cuando una página pública
 * le habla a la red local (Private/Local Network Access).
 *
 * Sin dependencias: solo módulos de Node (>= 18).
 */

const http = require('node:http');
const fs = require('node:fs');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFile } = require('node:child_process');

const VERSION = '2.0.0';
const LIMITE_CUERPO = 2 * 1024 * 1024; // un recibo pesa pocos KB; 2 MB es de sobra

/* ───────────────────────── Configuración ───────────────────────── */

/**
 * Lee y valida config.json. Falla con un mensaje claro: un agente mal
 * configurado que arranca «a medias» es peor que uno que no arranca.
 * @param {string} ruta
 * @returns {object}
 */
function cargarConfig(ruta) {
  let cfg;
  try {
    cfg = JSON.parse(fs.readFileSync(ruta, 'utf8'));
  } catch (e) {
    throw new Error(`No pude leer ${ruta}: ${e.message}. Copia config.example.json a config.json.`);
  }
  return validarConfig(cfg);
}

/** @param {object} cfg */
function validarConfig(cfg) {
  const puerto = Number(cfg.puerto ?? 9123);
  if (!Number.isInteger(puerto) || puerto < 1024 || puerto > 65535) {
    throw new Error('«puerto» debe estar entre 1024 y 65535.');
  }
  if (typeof cfg.token !== 'string' || cfg.token.length < 20 || cfg.token.startsWith('PEGA_AQUI')) {
    throw new Error('Falta «token»: cópialo de Locales › Editar › Token del agente (o php artisan cajon:agente).');
  }
  const driver = String(cfg.driver ?? 'archivo');
  if (!Object.prototype.hasOwnProperty.call(DRIVERS, driver)) {
    throw new Error(`Driver desconocido «${driver}». Usa: ${Object.keys(DRIVERS).join(', ')}.`);
  }
  const origenes = Array.isArray(cfg.origenes) ? cfg.origenes.map(String) : [];
  return { ...cfg, puerto, driver, origenes };
}

/* ───────────────────────── Drivers de salida ───────────────────────── */

/** Promesa de execFile sin shell: los argumentos nunca se interpretan. */
function ejecutar(programa, args, entrada) {
  return new Promise((resolve, reject) => {
    const hijo = execFile(programa, args, { timeout: 8000, windowsHide: true }, (error, _out, err) => {
      if (error) reject(new Error(`${programa}: ${String(err || error.message).trim()}`));
      else resolve();
    });
    if (entrada) hijo.stdin.end(entrada);
  });
}

const DRIVERS = {
  /** Pruebas y diagnóstico: los bytes quedan en un archivo. */
  archivo: {
    destino: (c) => c.archivo?.ruta ?? path.join(os.tmpdir(), 'cajon.bin'),
    enviar: async (c, bytes) => fs.promises.appendFile(DRIVERS.archivo.destino(c), bytes),
  },

  /**
   * Linux: el aparato se presenta como archivo de dispositivo.
   *   /dev/usb/lp0   térmica USB (módulo usblp) — el usuario debe estar en el grupo «lp»
   *   /dev/ttyUSB0   adaptador serie/USB trigger — grupo «uucp» (Arch/CachyOS) o «dialout»
   * Con «baudios» > 0 se fija la velocidad con stty antes de escribir (equivale
   * al `mode COM3:` de Windows); una impresora USB no lo necesita.
   */
  dispositivo: {
    destino: (c) => c.dispositivo?.ruta ?? '/dev/usb/lp0',
    enviar: async (c, bytes) => {
      const ruta = DRIVERS.dispositivo.destino(c);
      const baudios = Number(c.dispositivo?.baudios ?? 0);
      if (baudios > 0) {
        await ejecutar('stty', ['-F', ruta, String(baudios), 'cs8', '-cstopb', '-parenb', 'raw', '-echo']);
      }
      const fd = await fs.promises.open(ruta, 'w');
      try { await fd.write(bytes); } finally { await fd.close(); }
    },
  },

  /** Linux/macOS: cola CUPS en modo crudo (lp -o raw). Los bytes viajan por stdin. */
  cups: {
    destino: (c) => `cups:${c.cups?.impresora ?? ''}`,
    enviar: async (c, bytes) => {
      const impresora = String(c.cups?.impresora ?? '');
      if (!/^[\w.-]+$/.test(impresora)) throw new Error('cups.impresora no es un nombre de cola válido');
      await ejecutar('lp', ['-d', impresora, '-o', 'raw'], bytes);
    },
  },

  /** Térmica con IP propia (puerto 9100, «raw / JetDirect»). */
  red: {
    destino: (c) => `${c.red?.host}:${c.red?.puerto ?? 9100}`,
    enviar: (c, bytes) => new Promise((resolve, reject) => {
      const s = net.createConnection({ host: c.red?.host, port: Number(c.red?.puerto ?? 9100) });
      s.setTimeout(Number(c.red?.espera_ms ?? 3000));
      s.once('timeout', () => { s.destroy(); reject(new Error('la impresora de red no contesta')); });
      s.once('error', reject);
      s.once('connect', () => s.end(bytes, resolve));
    }),
  },

  /** Windows: impresora compartida (\\PC\RECURSO). Se copia un temporal en binario. */
  windows: {
    destino: (c) => c.windows?.recurso ?? '',
    enviar: async (c, bytes) => {
      const tmp = path.join(os.tmpdir(), `mus-${crypto.randomUUID()}.bin`);
      await fs.promises.writeFile(tmp, bytes);
      try { await ejecutar('cmd', ['/c', 'copy', '/b', tmp, DRIVERS.windows.destino(c)]); }
      finally { fs.promises.unlink(tmp).catch(() => {}); }
    },
  },

  /** Windows: adaptador USB trigger que aparece como puerto COM. */
  serial: {
    destino: (c) => c.serial?.com ?? 'COM3',
    enviar: async (c, bytes) => {
      const com = String(c.serial?.com ?? 'COM3').toUpperCase();
      if (!/^COM\d{1,3}$/.test(com)) throw new Error('serial.com debe ser COMn');
      await ejecutar('mode', [`${com}:`, `BAUD=${Number(c.serial?.baudios ?? 9600)}`, 'PARITY=N', 'DATA=8', 'STOP=1']).catch(() => {});
      await fs.promises.writeFile(`\\\\.\\${com}`, bytes);
    },
  },
};

/** ESC p m t1 t2: pulso al pin del RJ11 (tiempos en unidades de 2 ms). */
function pulsoCajon(cfg) {
  const c = cfg.cajon ?? {};
  const acotar = (ms) => Math.max(1, Math.min(255, Math.round(Number(ms) / 2)));
  return Buffer.from([0x1b, 0x70, Number(c.pin) === 1 ? 1 : 0, acotar(c.on_ms ?? 120), acotar(c.off_ms ?? 240)]);
}

/* ───────────────────────── Seguridad ───────────────────────── */

/** Comparación en tiempo constante: el token no se adivina midiendo demoras. */
function tokenValido(cfg, recibido) {
  const a = Buffer.from(String(recibido ?? ''));
  const b = Buffer.from(cfg.token);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

/**
 * ¿Se le contesta con CORS a este origen?
 * Siempre a la propia máquina (localhost / 127.0.0.1 / [::1], cualquier puerto:
 * `php artisan serve` y Vite cambian de puerto) y a los orígenes de config.json
 * (la URL pública del sistema). «*» en la lista los admite todos: el token
 * sigue siendo obligatorio.
 */
function origenPermitido(cfg, origen) {
  if (!origen) return true; // curl, artisan: no son un navegador, no hay CORS que aplicar
  if (cfg.origenes.includes('*') || cfg.origenes.includes(origen)) return true;
  try {
    const host = new URL(origen).hostname;
    return host === 'localhost' || host === '127.0.0.1' || host === '[::1]' || host === '::1';
  } catch {
    return false;
  }
}

function cabecerasCors(cfg, origen) {
  const h = { 'Vary': 'Origin', 'Cache-Control': 'no-store' };
  if (origen && origenPermitido(cfg, origen)) {
    h['Access-Control-Allow-Origin'] = origen;
    h['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
    h['Access-Control-Allow-Headers'] = 'Content-Type';
    // Chrome (Private/Local Network Access): sin esto bloquea a una página
    // pública que le habla a 127.0.0.1.
    h['Access-Control-Allow-Private-Network'] = 'true';
    h['Access-Control-Max-Age'] = '600';
  }
  return h;
}

/* ───────────────────────── Servidor HTTP ───────────────────────── */

function leerCuerpo(req) {
  return new Promise((resolve, reject) => {
    let total = 0;
    const trozos = [];
    req.on('data', (t) => {
      total += t.length;
      if (total > LIMITE_CUERPO) { reject(Object.assign(new Error('cuerpo demasiado grande'), { estado: 413 })); req.destroy(); return; }
      trozos.push(t);
    });
    req.on('end', () => {
      try { resolve(JSON.parse(Buffer.concat(trozos).toString('utf8') || '{}')); }
      catch { reject(Object.assign(new Error('el cuerpo no es JSON'), { estado: 400 })); }
    });
    req.on('error', reject);
  });
}

/**
 * Crea el servidor (sin ponerlo a escuchar: así las pruebas eligen puerto).
 * @param {object} cfgCruda  contenido de config.json
 * @param {{log?: (linea: string) => void}} [opciones]
 */
function crearAgente(cfgCruda, opciones = {}) {
  const cfg = validarConfig(cfgCruda);
  const log = opciones.log ?? ((l) => console.log(`[${new Date().toISOString()}] ${l}`));
  const driver = DRIVERS[cfg.driver];

  // Una orden a la vez: dos recibos escritos a la vez salen entremezclados.
  let cola = Promise.resolve();
  const enSerie = (tarea) => { const p = cola.then(tarea, tarea); cola = p.catch(() => {}); return p; };

  return http.createServer(async (req, res) => {
    const origen = req.headers.origin;
    const url = new URL(req.url, 'http://127.0.0.1');
    const responder = (estado, cuerpo) => {
      res.writeHead(estado, { 'Content-Type': 'application/json; charset=utf-8', ...cabecerasCors(cfg, origen) });
      res.end(JSON.stringify(cuerpo));
    };

    try {
      if (!origenPermitido(cfg, origen)) {
        log(`403 origen no permitido: ${origen}`);
        return responder(403, { error: 'origen', detalle: `Agrega ${origen} a «origenes» en config.json` });
      }
      if (req.method === 'OPTIONS') { res.writeHead(204, cabecerasCors(cfg, origen)); return res.end(); }

      if (req.method === 'GET' && url.pathname === '/estado') {
        if (!tokenValido(cfg, url.searchParams.get('token'))) return responder(401, { error: 'token' });
        return responder(200, { ok: true, driver: cfg.driver, destino: driver.destino(cfg), version: VERSION });
      }

      if (req.method === 'POST' && (url.pathname === '/cajon' || url.pathname === '/imprimir')) {
        const cuerpo = await leerCuerpo(req);
        if (!tokenValido(cfg, cuerpo.token)) { log(`401 ${url.pathname}`); return responder(401, { error: 'token' }); }

        if (url.pathname === '/cajon') {
          await enSerie(() => driver.enviar(cfg, pulsoCajon(cfg)));
          log('cajón abierto');
          return responder(200, { abierto: true });
        }

        const datos = typeof cuerpo.datos === 'string' ? cuerpo.datos : '';
        if (datos === '' || !/^[A-Za-z0-9+/]+={0,2}$/.test(datos)) {
          return responder(422, { impreso: false, detalle: '«datos» debe ser base64' });
        }
        const bytes = Buffer.from(datos, 'base64');
        await enSerie(() => driver.enviar(cfg, bytes));
        log(`recibo impreso (${bytes.length} bytes)`);
        return responder(200, { impreso: true, bytes: bytes.length });
      }

      return responder(404, { error: 'ruta' });
    } catch (e) {
      log(`fallo ${url.pathname}: ${e.message}`);
      // El detalle va al POS para que el cajero sepa qué revisar; no lleva secretos.
      return responder(e.estado ?? 502, { abierto: false, impreso: false, detalle: e.message });
    }
  });
}

/* ───────────────────────── Arranque ───────────────────────── */

if (require.main === module) {
  const ruta = process.argv[2] || process.env.AGENTE_CONFIG || path.join(__dirname, 'config.json');
  let cfg;
  try { cfg = cargarConfig(ruta); } catch (e) { console.error(`✘ ${e.message}`); process.exit(1); }

  const servidor = crearAgente(cfg);
  servidor.on('error', (e) => {
    console.error(e.code === 'EADDRINUSE' ? `✘ El puerto ${cfg.puerto} ya está en uso: ¿hay otro agente corriendo?` : `✘ ${e.message}`);
    process.exit(1);
  });
  // 127.0.0.1 y nunca 0.0.0.0: nadie de la red debe poder abrir el cajón.
  servidor.listen(cfg.puerto, '127.0.0.1', () => {
    console.log(`Agente de caja ${VERSION} en http://127.0.0.1:${cfg.puerto} · driver ${cfg.driver} → ${DRIVERS[cfg.driver].destino(cfg)}`);
  });
  for (const s of ['SIGINT', 'SIGTERM']) process.on(s, () => servidor.close(() => process.exit(0)));
}

module.exports = { crearAgente, validarConfig, cargarConfig, pulsoCajon, origenPermitido, VERSION };
