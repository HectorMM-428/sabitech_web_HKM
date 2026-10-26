#!/bin/sh
# Arranque del contenedor back en producción/demo.
# Las migraciones NO se ejecutan solas: se lanzan a mano (ver README) para que
# nadie borre ni altere datos de la demo por reiniciar un contenedor.
set -e

if [ -z "$APP_KEY" ]; then
  echo "ERROR: falta APP_KEY en .env. Genérala con: docker compose run --rm back php artisan key:generate --show" >&2
  exit 1
fi

if [ -z "$QR_SIGNING_KEY" ]; then
  echo "ERROR: falta QR_SIGNING_KEY en .env. Genérala con: openssl rand -base64 32" >&2
  exit 1
fi

# Cachear configuración y rutas con las variables de entorno reales del contenedor.
php artisan config:cache
php artisan route:cache
php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
