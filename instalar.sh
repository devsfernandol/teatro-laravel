#!/usr/bin/env bash
# Instalador de Teatro para servidores Linux con Docker.
#
#   sudo ./instalar.sh                               Asistente: pregunta el dominio y las opciones
#   sudo ./instalar.sh --dominio teatro.ejemplo.com  Producción con HTTPS automático
#   ./instalar.sh --local                            Prueba en este equipo: http://localhost:8080
#
# Opciones:
#   --dominio DOMINIO     Dominio de la tienda (su registro DNS A debe apuntar a este servidor).
#   --red-traefik RED     Usar el Traefik que ya existe en el servidor (por ejemplo, Easypanel).
#                         Si se omite, se detecta solo.
#   --sin-datos-demo      No cargar las obras y ventas de ejemplo.
#   --local               Instalar sin dominio, en http://localhost:PUERTO.
#   --puerto PUERTO       Puerto para --local (por defecto 8080).
#   --si                  No hacer preguntas (acepta las opciones por defecto).
#
# La primera vez crea el archivo .env con contraseñas aleatorias. Las ejecuciones
# siguientes lo conservan: sirven para actualizar la aplicación sin perder datos.
set -euo pipefail
cd "$(dirname "$0")"

DOMINIO=""
RED_TRAEFIK=""
PUERTO="8080"
LOCAL=0
SIN_DEMO=0
SIN_PREGUNTAS=0

while [ $# -gt 0 ]; do
    case "$1" in
        --dominio) DOMINIO="${2:-}"; shift 2 ;;
        --red-traefik) RED_TRAEFIK="${2:-}"; shift 2 ;;
        --puerto) PUERTO="${2:-}"; shift 2 ;;
        --local) LOCAL=1; shift ;;
        --sin-datos-demo) SIN_DEMO=1; shift ;;
        --si) SIN_PREGUNTAS=1; shift ;;
        -h|--help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opción desconocida: $1 (use --help)" >&2; exit 1 ;;
    esac
done

verde() { printf '\033[32m%s\033[0m\n' "$*"; }
aviso() { printf '\033[33m%s\033[0m\n' "$*"; }
error() { printf '\033[31m%s\033[0m\n' "$*" >&2; exit 1; }
interactivo() { [ "$SIN_PREGUNTAS" = "0" ] && [ -t 0 ]; }
preguntar() { # preguntar "Texto" "valor por defecto"
    local respuesta
    read -r -p "$1 " respuesta
    echo "${respuesta:-$2}"
}

# --- Docker --------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    aviso "Docker no está instalado."
    if [ "$(id -u)" = "0" ] && [ "$(uname -s)" = "Linux" ]; then
        RESP="s"
        if interactivo; then RESP="$(preguntar '¿Instalarlo ahora con el instalador oficial de Docker? [S/n]' s)"; fi
        case "$RESP" in
            [sSyY]*) curl -fsSL https://get.docker.com | sh || error "No se pudo instalar Docker." ;;
            *) error "Instale Docker y vuelva a ejecutar: https://docs.docker.com/engine/install/" ;;
        esac
    else
        error "Ejecute este script con sudo para instalar Docker, o instálelo: https://docs.docker.com/engine/install/"
    fi
fi
docker info >/dev/null 2>&1 || error "No se puede usar Docker. Ejecute el script con sudo (o agregue su usuario al grupo docker)."
docker compose version >/dev/null 2>&1 || error "Falta el complemento Docker Compose: https://docs.docker.com/compose/install/linux/"

# --- Archivo .env --------------------------------------------------------------
aleatorio() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1" || true; }
valor() { grep "^$1=" .env 2>/dev/null | head -n1 | cut -d= -f2- || true; }
poner() {
    local clave="$1" val="$2"
    if grep -q "^${clave}=" .env; then
        local tmp; tmp="$(mktemp)"
        awk -v c="$clave" -v v="$val" 'BEGIN{FS=OFS="="} $1==c {print c "=" v; next} {print}' .env > "$tmp"
        cat "$tmp" > .env && rm -f "$tmp"
    else
        printf '%s=%s\n' "$clave" "$val" >> .env
    fi
}

PRIMERA_VEZ=0
if [ ! -f .env ]; then
    cp .env.example .env
    PRIMERA_VEZ=1
fi
chmod 600 .env

# --- Preguntas (solo la primera vez y si no se pasaron opciones) ----------------
if [ "$PRIMERA_VEZ" = "1" ] && [ -z "$DOMINIO" ] && [ "$LOCAL" = "0" ] && interactivo; then
    echo
    echo "Instalación de Teatro"
    echo "---------------------"
    DOMINIO="$(preguntar 'Dominio de la tienda (por ejemplo teatro.miempresa.com), o Enter para probar en este equipo:' '')"
    [ -n "$DOMINIO" ] || LOCAL=1
    RESP="$(preguntar '¿Cargar obras y ventas de demostración? [S/n]' s)"
    case "$RESP" in [nN]*) SIN_DEMO=1 ;; esac
fi

# En una reinstalación sin opciones se conserva el modo anterior.
if [ -z "$DOMINIO" ] && [ "$LOCAL" = "0" ]; then
    DOMINIO="$(valor DOMINIO)"
    [ -n "$DOMINIO" ] || LOCAL=1
fi
[ -n "$RED_TRAEFIK" ] || RED_TRAEFIK="$(valor RED_TRAEFIK)"

if [ -n "$DOMINIO" ] && ! [[ "$DOMINIO" =~ ^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$ ]]; then
    error "Dominio inválido: $DOMINIO"
fi
[[ "$PUERTO" =~ ^[0-9]{2,5}$ ]] || error "Puerto inválido: $PUERTO"

# --- Nombre del proyecto y protección contra colisiones --------------------------
PROYECTO="$(valor COMPOSE_PROJECT_NAME)"
if [ -z "$PROYECTO" ]; then
    PROYECTO="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9_-' '-' | sed 's/^-*//; s/-*$//')"
    PROYECTO="${PROYECTO:-teatro}"
    poner COMPOSE_PROJECT_NAME "$PROYECTO"
fi
OTRAS="$(docker ps -a --filter "label=com.docker.compose.project=${PROYECTO}" \
    --format '{{.Label "com.docker.compose.project.working_dir"}}' | sort -u | grep -vx "$PWD" || true)"
if [ -n "$OTRAS" ]; then
    error "Ya existe un proyecto de Docker llamado '${PROYECTO}' instalado en: ${OTRAS}
Para no tocarlo, ponga otro nombre en .env (COMPOSE_PROJECT_NAME=teatro2) y vuelva a ejecutar."
fi

# --- Secretos (se generan una sola vez) ------------------------------------------
[ -n "$(valor APP_KEY)" ] || poner APP_KEY "base64:$(head -c 32 /dev/urandom | base64)"
[ -n "$(valor DB_PASSWORD)" ] || poner DB_PASSWORD "$(aleatorio 32)"
[ -n "$(valor DB_ROOT_PASSWORD)" ] || poner DB_ROOT_PASSWORD "$(aleatorio 32)"
MOSTRAR_ADMIN=0
if [ -z "$(valor ADMIN_PASSWORD)" ]; then
    poner ADMIN_PASSWORD "$(aleatorio 20)"
    MOSTRAR_ADMIN=1
fi
if [ "$PRIMERA_VEZ" = "1" ] && [ "$SIN_DEMO" = "1" ]; then
    poner DEMO_DATA "false"
fi

puerto_ocupado() { ss -ltnH "( sport = :$1 )" 2>/dev/null | grep -q . ; }

# --- Publicación -------------------------------------------------------------------
if [ -n "$DOMINIO" ]; then
    poner DOMINIO "$DOMINIO"
    poner APP_URL "https://${DOMINIO}"
    poner SESSION_SECURE_COOKIE "true"
    poner TRUSTED_PROXIES "10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"

    # ¿Hay ya un proxy (Traefik/Easypanel) ocupando los puertos 80 y 443?
    if [ -z "$RED_TRAEFIK" ] && { puerto_ocupado 80 || puerto_ocupado 443; }; then
        if docker ps --format '{{.Image}}' | grep -qi traefik && docker network inspect easypanel >/dev/null 2>&1; then
            RED_TRAEFIK="easypanel"
            aviso "Se detectó Easypanel/Traefik en los puertos 80 y 443: Teatro se publicará a través de él."
        elif ! docker ps --filter "label=com.docker.compose.project=${PROYECTO}" --format '{{.Names}}' | grep -q caddy; then
            error "Los puertos 80 y 443 ya están en uso por otro programa. Libérelos, o si tiene Traefik
indique su red con --red-traefik NOMBRE_DE_LA_RED."
        fi
    fi

    if [ -n "$RED_TRAEFIK" ]; then
        docker network inspect "$RED_TRAEFIK" >/dev/null 2>&1 || error "No existe la red de Docker '$RED_TRAEFIK'."
        poner RED_TRAEFIK "$RED_TRAEFIK"
        [ -n "$(valor ALIAS_WEB)" ] && [ "$(valor ALIAS_WEB)" != "teatro-web" ] || poner ALIAS_WEB "${PROYECTO}-web"
        poner COMPOSE_FILE "docker-compose.yml:docker-compose.traefik.yml"
    else
        poner COMPOSE_FILE "docker-compose.yml:docker-compose.caddy.yml"
    fi

    # El certificado HTTPS solo se emite si el dominio apunta a este servidor.
    IP_DOMINIO="$(getent ahostsv4 "$DOMINIO" 2>/dev/null | awk 'NR==1{print $1}' || true)"
    if [ -z "$IP_DOMINIO" ]; then
        aviso "Atención: $DOMINIO todavía no resuelve a ninguna IP. Cree el registro DNS A hacia este servidor;
el certificado HTTPS se obtendrá solo cuando el DNS esté activo."
    elif ! ip -4 addr 2>/dev/null | grep -q "inet ${IP_DOMINIO}/"; then
        IP_PUBLICA="$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
        if [ -n "$IP_PUBLICA" ] && [ "$IP_PUBLICA" != "$IP_DOMINIO" ]; then
            aviso "Atención: $DOMINIO apunta a $IP_DOMINIO, pero este servidor es $IP_PUBLICA.
Corrija el registro DNS A o el certificado HTTPS no podrá emitirse."
        fi
    fi

    # Cortafuegos: si ufw está activo, se abren los puertos web.
    if [ -z "$RED_TRAEFIK" ] && command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
        ufw allow 80/tcp >/dev/null && ufw allow 443/tcp >/dev/null && ufw allow 443/udp >/dev/null
    fi
else
    poner DOMINIO ""
    poner APP_URL "http://localhost:${PUERTO}"
    poner HTTP_PORT "$PUERTO"
    poner HTTP_BIND "127.0.0.1"
    poner SESSION_SECURE_COOKIE "false"
    poner TRUSTED_PROXIES ""
    poner COMPOSE_FILE "docker-compose.yml"
fi

# --- Arranque ------------------------------------------------------------------------
echo
echo "Construyendo e iniciando Teatro (la primera vez tarda unos minutos)..."
docker compose up -d --build --remove-orphans

printf "Esperando a que la aplicación esté lista"
for _ in $(seq 1 120); do
    [ "$(docker compose ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ] && break
    printf "."
    sleep 2
done
echo
[ "$(docker compose ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ] \
    || error "La aplicación no arrancó. Revise el detalle con: docker compose logs app"

# Ruta en Traefik de Easypanel: se crea sola si todavía no existe una para el dominio.
if [ -n "$RED_TRAEFIK" ] && [ -d /etc/easypanel/traefik/config ]; then
    if ! grep -rqs "Host(\`${DOMINIO}\`)" /etc/easypanel/traefik/config/; then
        cat > "/etc/easypanel/traefik/config/${PROYECTO}.yaml" <<EOF
http:
  routers:
    ${PROYECTO}-http:
      rule: "Host(\`${DOMINIO}\`)"
      service: ${PROYECTO}
      entryPoints: [http]
      middlewares: [redirect-to-https@file]
    ${PROYECTO}-https:
      rule: "Host(\`${DOMINIO}\`)"
      service: ${PROYECTO}
      entryPoints: [https]
      tls:
        certResolver: letsencrypt
        domains:
          - main: ${DOMINIO}
  services:
    ${PROYECTO}:
      loadBalancer:
        passHostHeader: true
        servers:
          - url: "http://$(valor ALIAS_WEB):8080/"
EOF
        verde "Ruta creada en Traefik: /etc/easypanel/traefik/config/${PROYECTO}.yaml"
    fi
elif [ -n "$RED_TRAEFIK" ]; then
    aviso "Configure en Traefik la ruta de ${DOMINIO} hacia http://$(valor ALIAS_WEB):8080"
fi

URL="$(valor APP_URL)"
echo
verde "✓ Teatro está funcionando"
echo
echo "  Tienda:                  ${URL}"
echo "  Panel de administración: ${URL}/admin"
if [ "$MOSTRAR_ADMIN" = "1" ]; then
    echo "  Usuario:                 $(valor ADMIN_EMAIL)"
    echo "  Contraseña inicial:      $(valor ADMIN_PASSWORD)"
    echo "  (el panel pedirá cambiarla en el primer inicio de sesión)"
fi
echo
echo "  Siguiente paso: en el panel, entre a Configuración para activar los cobros"
echo "  con PayPal y el envío de correos. Mientras tanto, los pagos se simulan."
if [ -n "$DOMINIO" ]; then
    echo
    echo "  El certificado HTTPS puede tardar un minuto en emitirse la primera vez."
fi
