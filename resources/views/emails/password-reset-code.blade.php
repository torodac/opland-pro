<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 40px 0; color: #374151; }
        .card { background: #fff; max-width: 440px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb; padding: 40px; }
        .btn { display: inline-block; margin: 28px 0; padding: 14px 28px; background: #f97316; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .url { font-size: 12px; color: #9ca3af; word-break: break-all; margin-top: 16px; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <p>Hola,</p>
        <p>Has solicitado restablecer tu contraseña en <strong>{{ $appName }}</strong>. Pulsa el botón para continuar:</p>

        <div style="text-align:center">
            <a href="{{ $link }}" class="btn">Restablecer contraseña</a>
        </div>

        <p>El enlace es válido durante <strong>30 minutos</strong>. Si no lo solicitaste, ignora este mensaje — tu contraseña no cambiará.</p>

        <p class="url">Si el botón no funciona, copia y pega este enlace en tu navegador:<br>{{ $link }}</p>

        <p class="footer">{{ $appName }}</p>
    </div>
</body>
</html>
