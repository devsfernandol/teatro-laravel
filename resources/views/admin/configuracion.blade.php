@extends('layouts.admin')

@section('titulo', 'Configuración')

@section('contenido')
<h1>Configuración</h1>

<section class="tarjeta formulario-admin" aria-labelledby="titulo-pagos">
    <div class="barra-titulo">
        <h2 id="titulo-pagos">Pasarela de pago</h2>
        @if ($modoPagos === 'paypal')
            <span @class(['estado', 'estado--pagada' => $paypal['mode'] === 'live', 'estado--pendiente' => $paypal['mode'] !== 'live'])>
                PayPal {{ $paypal['mode'] === 'live' ? 'Live · cobros reales' : 'Sandbox · pruebas' }}
            </span>
        @else
            <span class="estado estado--pendiente">Demostración · pagos simulados</span>
        @endif
    </div>

    <form method="post" action="{{ route('admin.configuracion.pagos') }}" novalidate>
        @csrf
        @method('put')

        <fieldset class="interruptor">
            <legend>Modo de cobro</legend>
            <label class="casilla">
                <input type="radio" name="modo" value="demo" @checked(old('modo', $modoPagos) === 'demo')>
                <span><strong>Demostración</strong>: los pagos se simulan y no se cobra nada.</span>
            </label>
            <label class="casilla">
                <input type="radio" name="modo" value="paypal" @checked(old('modo', $modoPagos) === 'paypal')>
                <span><strong>PayPal</strong>: los compradores pagan con PayPal o tarjeta. Requiere las credenciales de abajo.</span>
            </label>
            @include('partials.campo-error', ['campo' => 'modo'])
        </fieldset>

        <h3>Credenciales de PayPal</h3>
        <p class="campo__ayuda">Se obtienen en <strong>developer.paypal.com → Apps &amp; Credentials</strong>. Use la pestaña
            <em>Sandbox</em> para pruebas y <em>Live</em> para cobrar de verdad. Al activar PayPal se comprueban las credenciales.</p>

        <div class="campo">
            <label for="paypal_mode">Entorno</label>
            <select id="paypal_mode" name="paypal_mode">
                <option value="sandbox" @selected(old('paypal_mode', $paypal['mode']) === 'sandbox')>Sandbox (pruebas, dinero ficticio)</option>
                <option value="live" @selected(old('paypal_mode', $paypal['mode']) === 'live')>Live (producción, cobros reales)</option>
            </select>
        </div>
        <div class="campo">
            <label for="paypal_client_id">Client ID</label>
            <input type="text" id="paypal_client_id" name="paypal_client_id" value="{{ old('paypal_client_id', $paypal['client_id']) }}" maxlength="200" autocomplete="off" spellcheck="false">
            @include('partials.campo-error', ['campo' => 'paypal_client_id'])
        </div>
        <div class="campo">
            <label for="paypal_secret">Secret</label>
            <input type="password" id="paypal_secret" name="paypal_secret" maxlength="200" autocomplete="new-password"
                   placeholder="{{ $paypal['tiene_secret'] ? '•••••••• guardado (deje vacío para conservarlo)' : '' }}">
            <p class="campo__ayuda">Se guarda cifrado y nunca vuelve a mostrarse.</p>
            @include('partials.campo-error', ['campo' => 'paypal_secret'])
        </div>
        <div class="campo">
            <label for="paypal_webhook_id">ID del webhook (opcional)</label>
            <input type="text" id="paypal_webhook_id" name="paypal_webhook_id" value="{{ old('paypal_webhook_id', $paypal['webhook_id']) }}" maxlength="64" autocomplete="off" spellcheck="false">
            <p class="campo__ayuda">Registra en la tienda los reembolsos hechos desde PayPal. En PayPal cree un webhook con la URL
                <code>{{ $urlWebhook }}</code> y los eventos <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.REFUNDED</code> y <code>PAYMENT.CAPTURE.REVERSED</code>.</p>
            @include('partials.campo-error', ['campo' => 'paypal_webhook_id'])
        </div>

        <div class="formulario-admin__acciones acciones-linea">
            <button type="submit" class="boton">Guardar pasarela de pago</button>
        </div>
    </form>

    @if ($paypalConfigurado)
        <div class="acciones-linea">
            <form method="post" action="{{ route('admin.configuracion.pagos.probar') }}">
                @csrf
                <button type="submit" class="boton boton--secundario">Probar conexión con PayPal</button>
            </form>
            <form method="post" action="{{ route('admin.configuracion.pagos') }}">
                @csrf
                @method('put')
                <input type="hidden" name="modo" value="demo">
                <input type="hidden" name="paypal_mode" value="{{ $paypal['mode'] }}">
                <input type="hidden" name="borrar_credenciales" value="1">
                <button type="submit" class="boton boton--peligro">Borrar credenciales</button>
            </form>
        </div>
    @endif
</section>

<section class="tarjeta formulario-admin" aria-labelledby="titulo-correo">
    <div class="barra-titulo">
        <h2 id="titulo-correo">Correo (SMTP)</h2>
        <span @class(['estado', 'estado--pagada' => $correo['mailer'] === 'smtp', 'estado--pendiente' => $correo['mailer'] !== 'smtp'])>
            {{ $correo['mailer'] === 'smtp' ? 'Enviando por SMTP' : 'Desactivado · solo registro' }}
        </span>
    </div>
    <p class="campo__ayuda">Con el correo activado, los compradores reciben sus entradas y pueden recuperarlas desde "Mis entradas".
        Sirve cualquier proveedor SMTP: Gmail (con contraseña de aplicación), Outlook, Zoho, Brevo, Amazon SES, Mailgun, el de su hosting, etc.</p>

    <form method="post" action="{{ route('admin.configuracion.correo') }}" novalidate>
        @csrf
        @method('put')

        <fieldset class="interruptor">
            <legend>Envío</legend>
            <label class="casilla">
                <input type="radio" name="mailer" value="log" @checked(old('mailer', $correo['mailer']) === 'log')>
                <span><strong>Desactivado</strong>: los correos se escriben en el registro y no se envían.</span>
            </label>
            <label class="casilla">
                <input type="radio" name="mailer" value="smtp" @checked(old('mailer', $correo['mailer']) === 'smtp')>
                <span><strong>SMTP</strong>: los correos se envían con el servidor indicado abajo.</span>
            </label>
        </fieldset>

        <div class="campos-linea">
            <div class="campo">
                <label for="host">Servidor SMTP</label>
                <input type="text" id="host" name="host" value="{{ old('host', $correo['host'] === '127.0.0.1' ? '' : $correo['host']) }}" maxlength="190" placeholder="smtp.gmail.com" autocomplete="off" spellcheck="false">
                @include('partials.campo-error', ['campo' => 'host'])
            </div>
            <div class="campo">
                <label for="port">Puerto</label>
                <select id="port" name="port">
                    @foreach ([587 => '587 (recomendado)', 465 => '465', 2525 => '2525', 25 => '25'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected((int) old('port', $correo['port']) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @include('partials.campo-error', ['campo' => 'port'])
            </div>
            <div class="campo">
                <label for="cifrado">Cifrado</label>
                <select id="cifrado" name="cifrado">
                    <option value="tls" @selected(old('cifrado', $correo['cifrado']) === 'tls')>STARTTLS (puerto 587)</option>
                    <option value="ssl" @selected(old('cifrado', $correo['cifrado']) === 'ssl')>SSL/TLS (puerto 465)</option>
                </select>
            </div>
        </div>
        <div class="campos-linea">
            <div class="campo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" value="{{ old('usuario', $correo['usuario']) }}" maxlength="190" autocomplete="off" spellcheck="false">
                @include('partials.campo-error', ['campo' => 'usuario'])
            </div>
            <div class="campo">
                <label for="password_smtp">Contraseña</label>
                <input type="password" id="password_smtp" name="password" maxlength="200" autocomplete="new-password"
                       placeholder="{{ $correo['tiene_password'] ? '•••••••• guardada (deje vacío para conservarla)' : '' }}">
                @include('partials.campo-error', ['campo' => 'password'])
                @if ($correo['tiene_password'])
                    <label class="casilla"><input type="checkbox" name="borrar_password" value="1"> Borrar la contraseña guardada</label>
                @endif
            </div>
        </div>
        <div class="campos-linea">
            <div class="campo">
                <label for="remitente">Correo del remitente</label>
                <input type="email" id="remitente" name="remitente" value="{{ old('remitente', $correo['remitente']) }}" maxlength="190" required>
                <p class="campo__ayuda">Debe estar autorizado en su proveedor SMTP (normalmente, la misma cuenta del usuario).</p>
                @include('partials.campo-error', ['campo' => 'remitente'])
            </div>
            <div class="campo">
                <label for="remitente_nombre">Nombre del remitente</label>
                <input type="text" id="remitente_nombre" name="remitente_nombre" value="{{ old('remitente_nombre', $correo['remitente_nombre']) }}" maxlength="100">
                @include('partials.campo-error', ['campo' => 'remitente_nombre'])
            </div>
        </div>

        <div class="formulario-admin__acciones">
            <button type="submit" class="boton">Guardar correo</button>
        </div>
    </form>

    @if ($correo['mailer'] === 'smtp')
        <form method="post" action="{{ route('admin.configuracion.correo.probar') }}" class="prueba-correo">
            @csrf
            <div class="campo">
                <label for="destino">Enviar un correo de prueba a</label>
                <input type="email" id="destino" name="destino" value="{{ old('destino') }}" maxlength="190" required placeholder="usted@ejemplo.com">
                @include('partials.campo-error', ['campo' => 'destino'])
            </div>
            <button type="submit" class="boton boton--secundario">Enviar prueba</button>
        </form>
    @endif
</section>
@endsection
