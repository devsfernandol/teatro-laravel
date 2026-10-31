# Teatro · Venta de entradas con selección de asientos

Tienda en línea de entradas de teatro. Los compradores eligen obra, función y
asientos en el plano de la sala, y pagan con PayPal. El personal del teatro
gestiona obras, salas, funciones, ventas, la pasarela de pago y el correo
desde un panel de administración.

Se instala con un solo comando en cualquier servidor Linux con Docker.

| Guía | Para quién | Formato |
|---|---|---|
| Guía de instalación | Quien instala y mantiene el servidor | [Markdown](docs/GUIA-INSTALACION.md) · [PDF](docs/GUIA-INSTALACION.pdf) |
| Guía del administrador | Personal del teatro que usa el panel | [Markdown](docs/GUIA-ADMINISTRADOR.md) · [PDF](docs/GUIA-ADMINISTRADOR.pdf) |
| Guía del comprador | Público que compra entradas | [Markdown](docs/GUIA-COMPRADOR.md) · [PDF](docs/GUIA-COMPRADOR.pdf) |

---

## Instalación en producción

Necesita un **servidor Linux** (Ubuntu, Debian u otro) con acceso `root` y un
**dominio** cuyo registro DNS `A` apunte a la IP del servidor.

```bash
git clone https://github.com/devsfernandol/teatro-laravel.git teatro
cd teatro
sudo ./instalar.sh --dominio teatro.miempresa.com
```

El instalador:

1. Instala Docker si no lo encuentra.
2. Genera contraseñas aleatorias para la base de datos, la clave de cifrado y
   el administrador.
3. Construye e inicia los contenedores.
4. Obtiene el certificado HTTPS de Let's Encrypt y lo renueva solo.
5. Muestra la dirección del panel y la contraseña inicial del administrador.

Si el servidor ya tiene **Easypanel o Traefik** en los puertos 80 y 443, el
instalador lo detecta y publica la tienda a través de él.

Después, todo se configura desde el panel, en **Administración →
Configuración**:

- **Pasarela de pago:** el interruptor *Demostración / PayPal*, las credenciales
  y el entorno *Sandbox* (pruebas) o *Live* (cobros reales).
- **Correo SMTP:** servidor, usuario, contraseña y un botón para enviar un
  correo de prueba.

Mientras PayPal no esté activado, la tienda funciona en **modo
demostración**: todo el flujo de compra es real, pero el pago se simula.

> Para probar en el propio equipo sin dominio: `./instalar.sh --local` →
> `http://localhost:8080`.

Los detalles, la actualización, los respaldos y la solución de problemas están
en la [guía de instalación](docs/GUIA-INSTALACION.md).

---

## Funciones

**Compradores**

- Cartelera con las obras que tienen funciones futuras y asientos libres.
- Plano de la sala para elegir hasta 8 asientos. Los asientos ocupados se ven
  al instante.
- Los asientos quedan retenidos 10 minutos mientras se paga.
- Pago con PayPal (cuenta o tarjeta).
- Entradas con número propio y código QR, enviadas por correo y recuperables
  desde "Mis entradas".

**Administración**

- Obras con portada, salas con editor del plano y funciones con control de
  choques de horario.
- Ventas: búsqueda, reembolsos a través de PayPal y exportación a CSV.
- Configuración de PayPal y del correo SMTP desde el panel.
- Varios administradores, con registro de auditoría de cada acción.

---

## Seguridad

| Riesgo | Medida |
|---|---|
| Vender un asiento dos veces | Índice único `(funcion_id, asiento_id)` en la base, y reserva dentro de una transacción con bloqueo. Probado con compradores simultáneos. |
| Reservas falsas que bloquean la sala | Las reservas vencen solas. Máximo 3 reservas sin pagar por IP o documento, y límites de peticiones por minuto. |
| Pagos manipulados | El importe se calcula en el servidor. La captura de PayPal se verifica (estado, importe, moneda y orden) antes de emitir entradas. Las llamadas a PayPal llevan una clave de idempotencia, así que un reintento nunca cobra dos veces. |
| Fallas de red con PayPal | La orden queda "procesando" con los asientos retenidos, y una tarea programada consulta a PayPal cada minuto hasta resolverla. |
| Credenciales de PayPal y SMTP | Se guardan cifradas (AES-256) con la clave de la aplicación y nunca se devuelven al navegador. Antes de activar PayPal, las credenciales se verifican con PayPal. |
| Webhooks falsos | Cada notificación se verifica con la API de PayPal. |
| Robo de cuenta del panel | Contraseñas Argon2id: mínimo 12 caracteres, mixtas y no filtradas. Bloqueo tras 5 intentos fallidos, tiempo de respuesta constante, cambio obligatorio de la contraseña inicial y cierre de las demás sesiones al cambiarla. |
| CSRF, XSS, clickjacking | Token CSRF y CSP estricta sin código en línea. Además: `X-Frame-Options: DENY`, HSTS y cookies `Secure`, `HttpOnly` y `SameSite`, con la sesión cifrada. |
| Inyección SQL | Solo consultas parametrizadas. |
| Archivos maliciosos | Las imágenes se validan y se recodifican a WebP con nombre aleatorio. |
| Acceso a datos ajenos | La página de pago solo la ve el navegador que reservó. Las entradas se abren con un código aleatorio de 80 bits. |
| Exposición del servidor | Nginx solo sirve recursos conocidos. Contenedores de solo lectura, sin privilegios. PHP sin funciones para ejecutar comandos. La base de datos está en una red interna. |

---

## Arquitectura

```
Navegador ──HTTPS──▶ Caddy o Traefik ──▶ web (Nginx) ──▶ app (PHP-FPM 8.5 · Laravel 13)
                                                               │
                         worker (cola de correos) ─────────────┤
                         scheduler (reservas vencidas, PayPal) ┤
                                                               ▼
                                              db (MySQL 8.4, red interna)
```

| Tabla | Contenido |
|---|---|
| `obras` | Título, sinopsis, duración, clasificación, portada, publicada. |
| `salas`, `asientos` | Plano de cada sala: fila (A–Z), número y si existe la butaca. |
| `funciones` | Obra + sala + fecha y hora + precio + estado. |
| `ordenes` | Una compra: comprador, total, estado, datos de PayPal y código de las entradas. |
| `entradas` | Una fila por asiento; su `id` es el número de entrada. |
| `ajustes` | Configuración editable desde el panel (los secretos, cifrados). |
| `auditoria`, `users` | Registro de acciones y administradores. |

### Pruebas automatizadas

Más de 60 pruebas cubren reservas, concurrencia y pagos simulados y con PayPal
(rechazos, montos alterados, caídas de red, webhooks). También cubren la
configuración desde el panel, los permisos y las cabeceras de seguridad.

```bash
docker build --target test -t teatro-test . && docker run --rm teatro-test
```

### Estructura

```
app/Services/                 Reservas, pagos y cliente de PayPal
app/Support/Ajustes.php       Configuración guardada desde el panel
app/Http/Controllers/         Sitio público y panel (Admin/)
database/migrations/          Esquema con restricciones
resources/views/              Plantillas
docker/                       PHP, Nginx y Caddy
docs/                         Guías (Markdown y PDF)
instalar.sh                   Instalador
scripts/respaldo.sh           Respaldos
tests/Feature/                Pruebas automatizadas
```
