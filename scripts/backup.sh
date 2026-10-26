#!/bin/sh
# Copia de seguridad de la base de datos de la demo (R28, prueba T21).
# Uso:  ./scripts/backup.sh            → backups/sabitech-AAAAMMDD-HHMMSS.sql.gz
# La contraseña se lee dentro del contenedor db; nunca aparece en la línea de comandos del anfitrión.
set -eu

mkdir -p backups
file="backups/sabitech-$(date +%Y%m%d-%H%M%S).sql.gz"

docker compose -f compose.yaml exec -T db sh -c \
  'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases "$MYSQL_DATABASE"' \
  | gzip > "$file"

echo "Copia creada: $file ($(du -h "$file" | cut -f1))"
