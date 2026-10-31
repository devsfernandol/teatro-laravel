<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Prueba de correo</title></head>
<body style="font-family: Arial, sans-serif; color: #2b2024; background: #f7f3ee; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; border-top: 4px solid #c9a227;">
        <h1 style="font-family: Georgia, serif; color: #8c1c2e; margin-top: 0;">El correo funciona</h1>
        <p>Este mensaje confirma que {{ config('app.name') }} puede enviar correos con la configuración SMTP guardada en el panel.</p>
        <p style="color: #6b5c62; font-size: 13px;">Enviado el {{ fecha_larga(now()) }}.</p>
    </div>
</body>
</html>
