# Guía del administrador

**Cómo gestionar la tienda de entradas desde el panel**

El panel de administración permite publicar obras, configurar las salas,
programar funciones, revisar las ventas, hacer reembolsos y configurar los
cobros y el correo.

---

## 1. Entrar al panel

1. Abra `https://SU-DOMINIO/admin`.
2. Escriba su correo y contraseña y pulse **Entrar**.

La **primera vez** el panel le pedirá cambiar la contraseña. La nueva debe
tener al menos 12 caracteres, con mayúsculas, minúsculas, números y algún
símbolo, y no puede ser una contraseña filtrada en Internet.

> Después de **5 intentos fallidos**, la cuenta se bloquea durante 15 minutos.
> Para salir, pulse **Salir** (arriba a la derecha), sobre todo en
> computadores compartidos.

El menú superior tiene: **Inicio, Obras, Funciones, Salas, Ventas, Usuarios,
Auditoría y Configuración**. Con **Ver sitio** abre la tienda en otra pestaña.

---

## 2. Inicio (resumen)

Muestra las ventas e ingresos de hoy, los ingresos totales, las entradas
vendidas, las próximas funciones con su ocupación y las últimas ventas.

---

## 3. Configuración

### 3.1 Pasarela de pago (PayPal)

En la parte superior se ve el modo actual:

| Estado | Significado |
|---|---|
| **Demostración · pagos simulados** | Las compras funcionan, pero no se cobra. Úselo para probar. |
| **PayPal Sandbox · pruebas** | Se paga en PayPal con cuentas de prueba (dinero ficticio). |
| **PayPal Live · cobros reales** | La tienda cobra de verdad. |

**Para activar PayPal:**

1. Consiga el **Client ID** y el **Secret** en developer.paypal.com → *Apps &
   Credentials* (la *Guía de instalación* lo explica paso a paso).
2. Elija el **Entorno** (Sandbox o Live) que corresponda a esas credenciales.
3. Pegue el Client ID y el Secret.
4. En **Modo de cobro** elija **PayPal**.
5. Pulse **Guardar pasarela de pago**.

El sistema verifica las credenciales con PayPal antes de activarlas. Si están
mal, muestra el error y no cambia nada.

- El **Secret** se guarda cifrado y no vuelve a mostrarse. Para conservarlo
  al editar otros datos, deje el campo vacío.
- **Volver a demostración:** elija *Demostración* y guarde. Las credenciales
  quedan guardadas para reactivar PayPal cuando quiera.
- **Probar conexión con PayPal** comprueba que las credenciales guardadas
  siguen funcionando.
- **Borrar credenciales** elimina el Client ID y el Secret, y deja la tienda
  en demostración.

### 3.2 Correo (SMTP)

Con el correo activado, los compradores reciben sus entradas y pueden
recuperarlas desde la tienda.

1. En **Envío** elija **SMTP**.
2. Complete el servidor, puerto, cifrado, usuario y contraseña de su proveedor
   de correo. La *Guía de instalación* tiene los datos de Gmail, Outlook, Zoho
   y otros.
3. Escriba el **correo y el nombre del remitente** (lo que verán los compradores).
4. Pulse **Guardar correo**.
5. En **Enviar un correo de prueba a**, escriba su correo y pulse **Enviar
   prueba**. Si algo falla, el mensaje indica la causa.

---

## 4. Salas

Cada sala tiene un plano de filas (A, B, C…) y asientos por fila.

**Crear una sala:** **Salas → Nueva sala**. Escriba el nombre, el número de
filas (hasta 26) y de asientos por fila (hasta 40), y pulse **Guardar**.

**Ajustar el plano:** al guardar, se abre el plano completo. Cada cuadrito
verde es una butaca. **Desmarque** los lugares donde no hay butaca (pasillos,
columnas, espacios para sillas de ruedas) y pulse **Guardar**. Los lugares
desmarcados no se venden y se ven como huecos en el plano de compra.

> Si cambia el número de filas o de asientos por fila, el plano se regenera:
> revíselo de nuevo. Una sala con funciones no se puede eliminar.

---

## 5. Obras

**Crear una obra:** **Obras → Nueva obra**.

| Campo | Indicaciones |
|---|---|
| Título | Nombre de la obra. |
| Sinopsis | Descripción que verán los compradores. Puede tener varios párrafos. |
| Duración | En minutos. Se usa para evitar choques de horario en la sala. |
| Clasificación | Opcional: ATP, +12, +16… |
| Imagen de portada | JPG, PNG o WebP, hasta 4 MB, mínimo 200×200 píxeles. Se recomienda horizontal (16:9), por ejemplo 1200×675. |
| Publicada | Si está marcada, la obra aparece en la cartelera (cuando tenga funciones). |

- **Ocultar una obra:** edítela y desmarque **Publicada**. Deja de verse en la
  tienda y conserva su historial.
- **Eliminar:** solo se pueden eliminar obras sin funciones.

> Una obra aparece en la cartelera solo si está publicada y tiene al menos una
> función futura con asientos libres.

---

## 6. Funciones

Una función es una presentación de una obra en una sala, en una fecha y hora,
con un precio.

**Programar:** **Funciones → Programar función**, o desde **Obras →
Programar función**.

1. Elija la **obra** y la **sala**.
2. Indique la **fecha y hora** de inicio.
3. Escriba el **precio por entrada**.
4. Pulse **Guardar**.

El sistema no permite dos funciones al mismo tiempo en la misma sala (tiene en
cuenta la duración de las obras).

- **Plano:** en la lista de funciones, **Plano** muestra cada asiento como
  libre (blanco), retenido mientras alguien paga (dorado) o vendido (granate).
  Pase el cursor sobre un asiento vendido para ver el comprador.
- **Editar:** puede cambiar la fecha y el precio. Si ya hay entradas vendidas,
  no se pueden cambiar la obra ni la sala.
- **Cancelar función:** detiene la venta inmediatamente. Las entradas ya
  pagadas deben reembolsarse (sección 7). Al cancelar, el panel le lleva a la
  lista de esas ventas.
- La venta en línea **cierra 15 minutos antes** del inicio de cada función.

---

## 7. Ventas

**Ventas** lista todas las órdenes, de la más reciente a la más antigua.

- **Buscar:** por número de orden, documento, correo o código de entradas.
- **Filtrar por estado:**

| Estado | Significado |
|---|---|
| Pendiente | Asientos reservados, esperando el pago (vence a los 10 minutos). |
| Procesando | PayPal está confirmando el pago. Se resuelve solo. |
| Pagada | Compra completa: las entradas son válidas. |
| Expirada / Cancelada | No se pagó. Los asientos se liberaron y no hubo cobro. |
| Reembolsada | Se devolvió el dinero y los asientos quedaron libres. |

- **Exportar CSV:** descarga la lista filtrada para abrirla en Excel.

### Reembolsar una venta

1. Abra la orden (pulse su número).
2. En **Pago**, marque **Confirmo el reembolso** y pulse **Reembolsar**.

Para ventas pagadas con PayPal, el dinero se devuelve automáticamente al
comprador. Los asientos quedan libres para volver a venderse. El reembolso no
se puede deshacer.

### Validar entradas en la boletería

El comprador presenta su **código QR** o su **número de entrada**. Al escanear
el QR se abre la página de sus entradas, donde se ve si son válidas o fueron
reembolsadas. También puede buscar el código o el documento en **Ventas**.

---

## 8. Usuarios

- **Nuevo administrador:** **Usuarios → Nuevo administrador**. Escriba el
  nombre, el correo y una contraseña inicial. Esa persona deberá cambiarla al
  entrar por primera vez.
- **Desactivar:** pulse **Desactivar** junto al usuario. Pierde el acceso al
  instante, incluso si tenía la sesión abierta. Puede reactivarlo cuando quiera.
- Nadie puede desactivar su propia cuenta.

Para cambiar su contraseña, pulse su nombre en la esquina superior derecha.

---

## 9. Auditoría

Registra quién hizo qué y cuándo: inicios de sesión (también los fallidos),
cambios en obras, salas y funciones, reembolsos, exportaciones y cambios de
configuración, con la fecha, el usuario y la dirección IP.

---

## Buenas prácticas

- Cree **un usuario por persona**; no comparta cuentas.
- Desactive de inmediato los usuarios de quienes dejen de trabajar en el teatro.
- Haga una compra de prueba en **Sandbox** antes de pasar a **Live**.
- Revise de vez en cuando la **Auditoría**, sobre todo los inicios de sesión fallidos.
