#!/usr/bin/env bash
# Prueba de punta a punta del agente de caja en Linux (equivale a probar-agente.ps1).
#
#   tools/agente-caja/probar-agente.sh            # local principal, puerto 9123
#   tools/agente-caja/probar-agente.sh 3 9200     # local 3, puerto 9200
#   MANTENER=1 tools/agente-caja/probar-agente.sh # deja el agente corriendo al terminar
#
# Desde fish se llama igual (el script trae su propio intérprete). Para la
# variable:  env MANTENER=1 tools/agente-caja/probar-agente.sh
#
# Qué hace: 1) pone el local en modo agente y lee su token (php artisan cajon:agente)
#           2) si no hay config.json, crea uno con driver «archivo» → /tmp/cajon.bin
#              (si ya existe, solo le actualiza token y puerto: tu driver se respeta)
#           3) arranca el agente, prueba /estado, /cajon y un token malo
#           4) si hay ventas, manda el recibo ESC/POS de la última (cajon:probar-recibo)
set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"
TIENDA="${1:-}"
PUERTO="${2:-9123}"
CONFIG="$AQUI/config.json"
BITACORA="${TMPDIR:-/tmp}/agente-caja.log"

rojo()  { printf '\033[31m✘ %s\033[0m\n' "$*" >&2; }
verde() { printf '\033[32m✔ %s\033[0m\n' "$*"; }

for programa in php node curl; do
  command -v "$programa" >/dev/null || { rojo "Falta «$programa» en el PATH."; exit 1; }
done

MAYOR="$(node -p 'process.versions.node.split(".")[0]')"
[ "$MAYOR" -ge 18 ] || { rojo "Node $MAYOR es muy viejo: el agente necesita Node 18 o superior."; exit 1; }

# 1) Token del local. La última línea de cajon:agente es «TOKEN=…».
cd "$RAIZ"
if [ -n "$TIENDA" ]; then
  SALIDA="$(php artisan cajon:agente "$TIENDA" --puerto="$PUERTO")"
else
  SALIDA="$(php artisan cajon:agente --puerto="$PUERTO")"
fi
TOKEN="$(printf '%s\n' "$SALIDA" | sed -n 's/^TOKEN=//p' | tail -n 1)"
[ -n "$TOKEN" ] || { rojo "cajon:agente no devolvió el token:"; printf '%s\n' "$SALIDA" >&2; exit 1; }
verde "$(printf '%s\n' "$SALIDA" | head -n 1)"

# 2) config.json. El token viaja por variables de entorno, no por la línea de
#    órdenes, para que no quede a la vista en `ps`.
AGENTE_TOKEN="$TOKEN" AGENTE_PUERTO="$PUERTO" AGENTE_CONFIG="$CONFIG" node -e '
  const fs = require("fs");
  const ruta = process.env.AGENTE_CONFIG;
  const cfg = fs.existsSync(ruta)
    ? JSON.parse(fs.readFileSync(ruta, "utf8"))
    : { driver: "archivo", archivo: { ruta: "/tmp/cajon.bin" }, origenes: [] };
  cfg.token = process.env.AGENTE_TOKEN;
  cfg.puerto = Number(process.env.AGENTE_PUERTO);
  fs.writeFileSync(ruta, JSON.stringify(cfg, null, 2) + "\n", { mode: 0o600 });
  console.log("config.json listo · driver " + cfg.driver);
'
chmod 600 "$CONFIG"

# 3) Arranque. Si ya hay uno en ese puerto se reemplaza (tendría el token viejo).
if command -v fuser >/dev/null && fuser "$PUERTO/tcp" >/dev/null 2>&1; then
  fuser -k "$PUERTO/tcp" >/dev/null 2>&1 || true
  sleep 0.5
fi
nohup node "$AQUI/agente.js" "$CONFIG" >"$BITACORA" 2>&1 &
AGENTE_PID=$!
detener() { [ "${MANTENER:-0}" = "1" ] || kill "$AGENTE_PID" 2>/dev/null || true; }
trap detener EXIT

URL="http://127.0.0.1:$PUERTO"
for _ in 1 2 3 4 5 6 7 8 9 10; do
  curl -fsS -o /dev/null "$URL/estado?token=$TOKEN" 2>/dev/null && break
  sleep 0.3
done

ESTADO="$(curl -fsS "$URL/estado?token=$TOKEN")" || { rojo "El agente no arrancó. Mira $BITACORA"; cat "$BITACORA" >&2; exit 1; }
verde "Estado: $ESTADO"

CAJON="$(curl -sS -X POST -H 'Content-Type: text/plain' --data "{\"token\":\"$TOKEN\"}" "$URL/cajon")"
case "$CAJON" in
  *'"abierto":true'*) verde "Cajón: $CAJON" ;;
  *) rojo "El cajón no abrió: $CAJON"; exit 1 ;;
esac

MALO="$(curl -sS -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: text/plain' --data '{"token":"malo"}' "$URL/cajon")"
[ "$MALO" = "401" ] && verde "Un token malo se rechaza (401)" || { rojo "Un token malo devolvió $MALO, se esperaba 401"; exit 1; }

# El mismo camino que usa el navegador: con cabecera Origin debe volver CORS.
CORS="$(curl -sS -D - -o /dev/null -H 'Origin: http://localhost:8000' "$URL/estado?token=$TOKEN" | tr -d '\r' | grep -i '^access-control-allow-origin' || true)"
[ -n "$CORS" ] && verde "CORS para el navegador: $CORS" || { rojo "El agente no devolvió cabeceras CORS"; exit 1; }

# 4) Recibo de la última venta (no es un fallo si aún no hay ventas o el recibo es A4).
if php artisan cajon:probar-recibo; then
  verde "Recibo ESC/POS entregado al agente"
else
  printf '… sin recibo de prueba (lee el mensaje de arriba); cajón y agente sí quedaron verificados.\n'
fi

if [ "${MANTENER:-0}" = "1" ]; then
  verde "El agente sigue corriendo (PID $AGENTE_PID). Bitácora: $BITACORA · Para detenerlo: kill $AGENTE_PID"
else
  verde "Todo bien. El agente de prueba se detuvo; para dejarlo fijo: node $AQUI/agente.js"
fi
