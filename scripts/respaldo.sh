#!/usr/bin/env bash
# Respaldo de la base de datos y de las imágenes subidas.
#   ./scripts/respaldo.sh            Crea backups/teatro-AAAAMMDD-HHMMSS.{sql.gz,imagenes.tar.gz}
#
# Para respaldar todos los días a las 03:00, agregar con "crontab -e":
#   0 3 * * * /ruta/a/teatro/scripts/respaldo.sh >/dev/null 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."

DESTINO=backups
CONSERVAR_DIAS=14
FECHA="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --no-tablespaces teatro' \
    | gzip -9 > "$DESTINO/teatro-$FECHA.sql.gz"

docker compose exec -T app tar -C /var/www/html/storage/app/public -czf - obras \
    > "$DESTINO/teatro-$FECHA.imagenes.tar.gz"

chmod 600 "$DESTINO"/teatro-"$FECHA".*
find "$DESTINO" -name 'teatro-*' -mtime +"$CONSERVAR_DIAS" -delete

echo "Respaldo creado: $DESTINO/teatro-$FECHA.sql.gz"
