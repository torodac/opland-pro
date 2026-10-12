<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 40px 0; color: #374151; }
        .card { background: #fff; max-width: 640px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .subtitle { font-size: 13px; color: #6b7280; margin: 0 0 20px; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; color: #9ca3af; font-weight: 500; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; }
        .comollo { background: #FDF8F1; border-left: 3px solid #E3C9A3; padding: 12px 16px; margin: 20px 0 0; font-size: 13px; line-height: 1.6; border-radius: 0 6px 6px 0; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; line-height: 1.5; }
        a { color: #185FA5; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $tareas->count() }} limpieza{{ $tareas->count() === 1 ? '' : 's' }} de salida sin reserva asociada</h1>
        <p class="subtitle">
            Estas limpiezas no están enlazadas a ninguna reserva en Breezeway, y no se ha podido
            deducir a cuál pertenecen. Mientras siga así, esas reservas aparecen en Dev. fianzas
            sin comentarios ni fotos, y la fianza se decide sin ver en qué estado quedó la casa.
        </p>

        <table>
            <thead>
                <tr>
                    <th>Planificada</th>
                    <th>Propiedad</th>
                    <th>Tarea</th>
                    <th>Breezeway</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tareas as $t)
                    <tr>
                        <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($t->fecha_planificada)->format('d/m/Y') }}</td>
                        <td style="color:#6b7280">{{ $t->propiedad ?: '—' }}</td>
                        <td>{{ $t->nombre }}</td>
                        <td style="white-space:nowrap">
                            @if($t->breezeway_task_id)
                                <a href="https://app.breezeway.io/task/{{ $t->breezeway_task_id }}">#{{ $t->breezeway_task_id }}</a>
                            @else
                                <span style="color:#d1d5db">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="comollo">
            <strong>Cómo se arregla:</strong> abre la tarea en Breezeway y usa el botón
            «Enlace reserva» para asociarla. En la siguiente pasada de la sincronización, la
            reserva recupera sus comentarios y sus fotos sola.
        </div>

        @if($ambiguas)
            <p class="footer">
                Hay además <strong>{{ $ambiguas }}</strong> limpieza{{ $ambiguas === 1 ? '' : 's' }}
                con más de una reserva posible. No se eligen automáticamente a propósito: atar una
                limpieza a la estancia equivocada haría decidir una fianza con la evidencia de otra.
            </p>
        @endif

        <p class="footer">
            Este aviso solo se envía cuando hay algo que revisar. Lo que sí se puede deducir sin
            ambigüedad se enlaza solo, y no aparece aquí.
        </p>
    </div>
</body>
</html>
