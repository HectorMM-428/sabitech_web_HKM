#!/bin/sh
# Restaura una copia creada con backup.sh (R28, prueba T21).
# Uso:  ./scripts/restore.sh backups/sabitech-20270201-180000.sql.gz
# ATENCIÓN: sustituye los datos actuales de la base de datos de la demo.
set -eu

if [ $# -ne 1 ] || [ ! -f "$1" ]; then
  echo "Uso: $0 <fichero .sql.gz creado por backup.sh>" >&2
  exit 1
fi

printf "Se sustituirán los datos actuales por %s. ¿Continuar? (escribe SI): " "$1"
read -r answer
[ "$answer" = "SI" ] || { echo "Cancelado."; exit 1; }

gunzip -c "$1" | docker compose -f compose.yaml exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'

echo "Restauración completada. Comprueba: docker compose -f compose.yaml exec back php artisan migrate:status"
