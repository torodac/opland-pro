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

        @if(!empty($esquema['campos_rotos']))
            <div class="hallazgo" style="border-left-color:#dc2626">
                <h2 style="color:#991b1b">{{ count($esquema['campos_rotos']) }} campo(s) configurado(s) sin columna en su tabla</h2>
                <p>
                    Salen en la ficha pero no tienen dónde guardarse: al guardar uno de esos registros,
                    da error y no se guarda nada. Hay que quitar el campo o crear la columna.
                </p>
                <p style="font-family:monospace;font-size:12px;color:#991b1b;margin-top:8px">
                    @foreach($esquema['campos_rotos'] as $c){{ $c }}@if(!$loop->last)<br>@endif @endforeach
                </p>
            </div>
        @endif

        @if(($esquema['sin_declarar'] ?? 0) > 0)
            <div class="hallazgo" style="border-left-color:#9ca3af">
                <h2 style="color:#4b5563">{{ $esquema['sin_declarar'] }} columna(s) sin declarar en {{ $esquema['tablas_afectadas'] }} tabla(s)</h2>
                <p>
                    Informativo: existen en la base de datos y guardan datos, pero la configuración
                    todavía no las conoce. La pasada de las 05:30 las declara ocultas; si aquí sigue
                    saliendo un número alto, es que esa pasada no se está ejecutando.
                </p>
            </div>
        @endif

        <p class="footer">Opland PRO — admin:informe-fallos-diario</p>
    </div>
</body>
</html>
