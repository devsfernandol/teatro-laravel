<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Sus entradas</title></head>
<body style="font-family: Arial, sans-serif; color: #2b2024; background: #f7f3ee; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px;">
        <h1 style="font-family: Georgia, serif; color: #8c1c2e; margin-top: 0;">Enlaces a sus entradas</h1>
        <p>Recibimos una solicitud para recuperar sus entradas. Estas son sus compras:</p>
        <ul>
            @foreach ($ordenes as $orden)
                <li style="margin-bottom: 8px;">
                    <a href="{{ route('entradas.show', $orden->codigo) }}">{{ $orden->funcion->obra->titulo }}</a>
                    · {{ fecha_larga($orden->funcion->inicia_en) }}
                </li>
            @endforeach
        </ul>
        <p style="color: #6b5c62; font-size: 13px;">Si no pidió este correo, puede ignorarlo.</p>
    </div>
</body>
</html>
