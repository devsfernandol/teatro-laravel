<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Sus entradas</title></head>
<body style="font-family: Arial, sans-serif; color: #2b2024; background: #f7f3ee; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; border-top: 4px solid #c9a227;">
        <h1 style="font-family: Georgia, serif; color: #8c1c2e; margin-top: 0;">¡Gracias por su compra, {{ $orden->nombre }}!</h1>
        <p><strong>{{ $orden->funcion->obra->titulo }}</strong><br>
            {{ fecha_larga($orden->funcion->inicia_en) }}<br>
            {{ $orden->funcion->sala->nombre }}</p>
        <p>Entradas:</p>
        <ul>
            @foreach ($orden->entradas as $entrada)
                <li>Entrada n.º {{ $entrada->id }} · asiento {{ $entrada->asiento->etiqueta() }}</li>
            @endforeach
        </ul>
        <p>Total pagado: <strong>{{ dinero($orden->total, $orden->moneda) }}</strong></p>
        <p>Código de compra: <strong>{{ $orden->codigo }}</strong></p>
        <p><a href="{{ route('entradas.show', $orden->codigo) }}" style="display: inline-block; background: #8c1c2e; color: #fff; padding: 12px 20px; border-radius: 8px; text-decoration: none;">Ver mis entradas y el código QR</a></p>
        <p style="color: #6b5c62; font-size: 13px;">No comparta este enlace: quien lo tenga puede ver sus entradas.</p>
    </div>
</body>
</html>
