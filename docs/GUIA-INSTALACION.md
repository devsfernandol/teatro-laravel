# Guía de instalación

**Teatro · Venta de entradas con selección de asientos**

Esta guía explica cómo poner la tienda en producción en un servidor Linux,
cómo actualizarla, respaldarla y resolver los problemas más comunes.

---

## 1. Qué necesita

| Requisito | Detalle |
|---|---|
| Servidor | Linux de 64 bits (Ubuntu 22.04 o superior, Debian 12, Rocky, etc.) con acceso `root` o `sudo`. Mínimo **1 GB de RAM** y 5 GB de disco libres. |
| Dominio | Un dominio o subdominio, por ejemplo `teatro.miempresa.com`. |
| DNS | Un registro **A** del dominio que apunte a la IP pública del servidor. |
| Puertos | 80 y 443 abiertos hacia Internet (los usa el certificado HTTPS). |
| Docker | No hace falta instalarlo antes: el instalador lo instala si falta. |

Para cobrar con tarjeta o PayPal necesitará además una **cuenta PayPal
Business** (se configura después de instalar, desde el panel).

---

## 2. Instalación en tres pasos

### Paso 1 · Apuntar el dominio al servidor

En el panel de su proveedor de dominios, cree un registro:

| Tipo | Nombre | Valor |
|---|---|---|
| A | `teatro` (o `@` para el dominio principal) | IP pública del servidor |

Puede comprobarlo desde el servidor con `getent hosts teatro.miempresa.com`:
debe mostrar la IP del servidor. El cambio de DNS puede tardar algunos minutos.

### Paso 2 · Descargar la aplicación

Conéctese al servidor por SSH y ejecute:

```bash
git clone https://github.com/devsfernandol/teatro-laravel.git teatro
cd teatro
```

> Si no tiene `git`: `sudo apt install -y git` (Ubuntu/Debian). También puede
> subir el archivo ZIP de la aplicación y descomprimirlo con `unzip`.

### Paso 3 · Ejecutar el instalador

```bash
sudo ./instalar.sh --dominio teatro.miempresa.com
```

La primera vez tarda entre 3 y 6 minutos. Al terminar muestra algo así:

```
✓ Teatro está funcionando

  Tienda:                  https://teatro.miempresa.com
  Panel de administración: https://teatro.miempresa.com/admin
  Usuario:                 admin@teatro.local
  Contraseña inicial:      Xq7Lm2...
```

**Anote la contraseña inicial.** El panel pedirá cambiarla en el primer
inicio de sesión. Si la pierde antes de cambiarla, está en el archivo `.env`
(línea `ADMIN_PASSWORD`).

> **Sin datos de ejemplo:** agregue `--sin-datos-demo` para empezar con la
> tienda vacía (sin las obras, salas y ventas de demostración).
>
> **Asistente:** si ejecuta `sudo ./instalar.sh` sin opciones, el instalador
> pregunta el dominio y si desea los datos de ejemplo.

---

## 3. Primeros pasos después de instalar

1. Entre a `https://SU-DOMINIO/admin`, inicie sesión y cambie la contraseña.
2. Vaya a **Configuración**:
   - **Correo (SMTP):** escriba los datos de su proveedor de correo, guarde y
     use *Enviar prueba*. Sin esto, los compradores no reciben las entradas
     por correo (pero las ven en pantalla al pagar).
   - **Pasarela de pago:** siga la sección 4 para activar PayPal.
3. Si instaló los datos de demostración y quiere empezar de cero, oculte o
   edite las obras de ejemplo desde **Obras**.
4. Cree sus salas, obras y funciones (vea la *Guía del administrador*).

---

## 4. Activar los cobros con PayPal

Mientras PayPal no esté activado, la tienda está en **modo demostración**: la
compra funciona completa pero el pago se simula y se ve un aviso amarillo.

1. Entre a <https://developer.paypal.com> con su cuenta PayPal Business.
2. Abra **Apps & Credentials**. Elija la pestaña **Sandbox** para hacer pruebas
   con dinero ficticio, o **Live** para cobrar de verdad.
3. Pulse **Create App**, póngale un nombre (por ejemplo, "Teatro") y créela.
4. Copie el **Client ID** y el **Secret**.
5. En el panel de Teatro, **Configuración → Pasarela de pago**:
   - Entorno: *Sandbox* o *Live* (el mismo de la pestaña del paso 2).
   - Pegue el Client ID y el Secret.
   - Modo de cobro: **PayPal**.
   - Pulse **Guardar pasarela de pago**. El sistema verifica las credenciales
     con PayPal antes de activarlas.
6. *(Recomendado)* Para que los reembolsos hechos desde PayPal se registren en
   la tienda, en la misma app de PayPal abra **Webhooks → Add Webhook**:
   - URL: `https://SU-DOMINIO/webhooks/paypal` (el panel la muestra para copiarla).
   - Eventos: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`,
     `PAYMENT.CAPTURE.REVERSED`.
   - Copie el **Webhook ID** en el panel y guarde.

**Para pasar de pruebas a producción** repita los pasos con la pestaña
**Live** y elija el entorno *Live*. Desde ese momento los cobros son reales.

El interruptor **Demostración / PayPal** permite volver al modo de
demostración en cualquier momento sin borrar las credenciales. Los pagos que
ya estaban en curso en PayPal terminan con normalidad.

---

## 5. Configurar el correo

En **Configuración → Correo (SMTP)** elija *SMTP* y complete:

| Proveedor | Servidor | Puerto / cifrado | Usuario y contraseña |
|---|---|---|---|
| Gmail / Google Workspace | `smtp.gmail.com` | 587 · STARTTLS | Su correo y una **contraseña de aplicación** (myaccount.google.com → Seguridad → Contraseñas de aplicaciones). |
| Outlook / Microsoft 365 | `smtp.office365.com` | 587 · STARTTLS | Su correo y contraseña (SMTP autenticado debe estar habilitado). |
| Zoho Mail | `smtp.zoho.com` | 465 · SSL | Su correo y contraseña (o contraseña de aplicación). |
| Brevo, Mailgun, Amazon SES… | El que indique el proveedor | 587 · STARTTLS | Las credenciales SMTP del proveedor. |
| Hosting propio | `mail.su-dominio.com` | 587 o 465 | La cuenta de correo. |

El **correo del remitente** debe ser una dirección autorizada por el
proveedor (normalmente la misma del usuario). Guarde y pulse **Enviar
prueba**. Si falla, el mensaje de error indica la causa (credenciales,
puerto bloqueado, etc.).

> Muchos proveedores de VPS bloquean el puerto 25. Use 587 o 465.

---

## 6. Servidores con Easypanel o Traefik

Si el servidor ya tiene Easypanel (o Traefik) usando los puertos 80 y 443, el
instalador lo detecta solo y:

- Conecta la tienda a la red de Traefik sin publicar puertos propios.
- Crea la ruta del dominio en `/etc/easypanel/traefik/config/`, con
  certificado de Let's Encrypt.

Para indicar la red manualmente: `--red-traefik NOMBRE_DE_LA_RED`.

Si el servidor tiene otro programa en los puertos 80 y 443 (Apache, Nginx del
sistema), el instalador se detiene para no interferir. Libere esos puertos o
use el proxy existente.

---

## 7. Actualizar a una versión nueva

```bash
cd teatro
git pull
sudo ./instalar.sh
```

El instalador conserva el archivo `.env`, los datos y la configuración del
panel. Aplica las migraciones de la base de datos automáticamente. Haga un
respaldo antes de actualizar (sección 8).

---

## 8. Respaldos

```bash
sudo ./scripts/respaldo.sh
```

El script guarda la base de datos y las imágenes de las obras en
`backups/teatro-FECHA.sql.gz` y `backups/teatro-FECHA.imagenes.tar.gz`, y
borra los respaldos de más de 14 días.

**Respaldo diario automático** (todos los días a las 3:00). Ejecute
`sudo crontab -e` y agregue la línea:

```
0 3 * * * /ruta/a/teatro/scripts/respaldo.sh >/dev/null 2>&1
```

Copie también los respaldos **fuera del servidor** (otro equipo o un
almacenamiento externo) y guarde una copia del archivo `.env`. Sin su
`APP_KEY` no se pueden leer las credenciales guardadas en el panel.

**Restaurar un respaldo de la base de datos:**

```bash
gunzip -c backups/teatro-FECHA.sql.gz | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" teatro'
```

---

## 9. Comandos útiles

Ejecútelos dentro de la carpeta `teatro`:

| Acción | Comando |
|---|---|
| Ver el estado | `sudo docker compose ps` |
| Ver el registro de la aplicación | `sudo docker compose logs -f app` |
| Reiniciar | `sudo docker compose restart` |
| Detener (los datos se conservan) | `sudo docker compose down` |
| Volver a iniciar | `sudo ./instalar.sh` |
| Crear otro administrador | `sudo docker compose exec app php artisan teatro:crear-admin --email=persona@empresa.com` |

---

## 10. Solución de problemas

| Síntoma | Causa y solución |
|---|---|
| El navegador avisa que el sitio no es seguro (certificado) | El DNS todavía no apunta al servidor, o los puertos 80/443 están cerrados. Revise el registro A, el cortafuegos del proveedor del VPS y espere unos minutos. |
| "Los puertos 80 y 443 ya están en uso" | Hay otro servidor web instalado. Deténgalo (`sudo systemctl disable --now apache2` o `nginx`) o use `--red-traefik`. |
| "La aplicación no arrancó" | Ejecute `sudo docker compose logs app` y revise el último error. |
| PayPal: "PayPal rechazó las credenciales" | El Client ID o el Secret están mal copiados, o el entorno elegido (Sandbox/Live) no coincide con la pestaña de donde se copiaron. |
| El correo de prueba falla | Revise servidor, puerto y cifrado. En Gmail hace falta una contraseña de aplicación. Pruebe el puerto 587 si el 465 está bloqueado. |
| Olvidé la contraseña del administrador | Cree otro administrador con el comando de la sección 9 y desactive el anterior desde **Usuarios**. |
| La página dice "Servicio no disponible" | La base de datos no responde: `sudo docker compose ps` y `sudo docker compose restart db`. |

---

## 11. Seguridad del servidor (recomendado)

- Use acceso SSH **solo con llave**, sin contraseña (`PasswordAuthentication no`
  en `/etc/ssh/sshd_config`).
- Active el cortafuegos permitiendo solo SSH, 80 y 443:
  `sudo ufw allow OpenSSH && sudo ufw allow 80,443/tcp && sudo ufw enable`.
- Mantenga el sistema al día: `sudo apt update && sudo apt upgrade` o
  actualizaciones automáticas (`unattended-upgrades`).
- Guarde el archivo `.env` como un secreto: contiene las contraseñas de la base
  de datos y la clave de cifrado.
