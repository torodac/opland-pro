<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 40px 0; color: #374151; }
        .card { background: #fff; max-width: 600px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .subtitle { font-size: 13px; color: #9ca3af; margin: 0 0 24px; }
        .hallazgo { margin-bottom: 16px; border-left: 3px solid #f59e0b; padding: 4px 0 4px 16px; }
        .hallazgo h2 { font-size: 15px; margin: 0 0 6px; color: #92400e; }
        .hallazgo p { font-size: 14px; line-height: 1.5; margin: 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Esto es lo que hay que revisar de ayer ({{ $fecha }})</h1>
        <p class="subtitle">Resumen automático, en el mismo tono en que te lo explicaría yo.</p>

        @foreach ($hallazgos as $h)
            <div class="hallazgo">
                <h2>{{ $h['titulo'] }}</h2>
                <p>{{ $h['cuerpo'] }}</p>
            </div>
        @endforeach

        <p class="footer">Opland PRO — admin:informe-fallos-diario</p>
    </div>
</body>
</html>
