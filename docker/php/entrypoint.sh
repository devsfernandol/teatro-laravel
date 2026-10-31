#!/bin/sh
# Arranque de los contenedores de la aplicación.
#   app        PHP-FPM. Además prepara la base (migraciones, datos de demostración
#              y administrador inicial). Los demás roles esperan a que termine.
#   worker     Procesa la cola (envío de correos).
#   scheduler  Tareas programadas (liberar reservas vencidas, confirmar pagos).
set -eu

ROL="${1:-app}"
cd /var/www/html

log() { echo "[teatro:$ROL] $*"; }

# Clave de cifrado: si no viene en el entorno, se genera una sola vez y se
# guarda en el volumen persistente, compartido por todos los contenedores.
if [ -z "${APP_KEY:-}" ]; then
    ARCHIVO_CLAVE=storage/app/.app_key
    if [ ! -s "$ARCHIVO_CLAVE" ]; then
        if [ "$ROL" = "app" ]; then
            (umask 077 && php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$ARCHIVO_CLAVE")
            log "Clave de la aplicación generada."
        else
            log "Esperando la clave de la aplicación..."
            i=0
            while [ ! -s "$ARCHIVO_CLAVE" ] && [ $i -lt 120 ]; do sleep 1; i=$((i + 1)); done
        fi
    fi
    APP_KEY="$(cat "$ARCHIVO_CLAVE")"
    export APP_KEY
fi

mkdir -p storage/app/public/obras storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs

# Espera a que la base de datos acepte conexiones.
i=0
until php -r '
    try {
        new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE")),
            getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
    } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
    i=$((i + 1))
    if [ $i -ge 90 ]; then
        log "No se pudo conectar a la base de datos."
        exit 1
    fi
    sleep 2
done

php artisan config:cache --no-ansi -q
php artisan route:cache --no-ansi -q
php artisan view:cache --no-ansi -q
php artisan event:cache --no-ansi -q

case "$ROL" in
    app)
        rm -f /tmp/teatro-listo
        php artisan migrate --force --no-ansi
        if [ "${DEMO_DATA:-true}" = "true" ] && [ ! -f storage/app/.demo-cargada ]; then
            php artisan db:seed --force --no-ansi
            touch storage/app/.demo-cargada
            log "Datos de demostración cargados."
        fi
        php artisan teatro:crear-admin --si-no-existe --email="${ADMIN_EMAIL:-admin@teatro.local}" --password="${ADMIN_PASSWORD:-}" --no-ansi
        touch /tmp/teatro-listo
        log "Listo."
        exec php-fpm
        ;;
    worker)
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --no-ansi
        ;;
    scheduler)
        exec php artisan schedule:work --no-ansi
        ;;
    *)
        exec "$@"
        ;;
esac
