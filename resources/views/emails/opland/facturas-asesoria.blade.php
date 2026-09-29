<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 40px 0; color: #374151; }
        .card { background: #fff; max-width: 620px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; }
        h1 { font-size: 17px; margin: 0 0 4px; color: #111827; }
        .subtitle { font-size: 13px; color: #9ca3af; margin: 0 0 22px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; border-bottom: 1px solid #e5e7eb; padding: 0 0 6px; }
        td { padding: 7px 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        td.doc { font-family: monospace; font-size: 12px; color: #6b7280; }
        .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Facturas {{ $tipo === 'emitidas' ? 'emitidas' : 'recibidas' }} de Opland</h1>
        <p class="subtitle">
            Se adjuntan {{ count($documentos) }}
            {{ count($documentos) === 1 ? 'documento' : 'documentos' }}.
        </p>

        <table>
            <tr>
                <th>Factura</th>
                <th>Documento adjunto</th>
            </tr>
            @foreach($documentos as $d)
                <tr>
                    <td>{{ $d['detalle'] }}</td>
                    <td class="doc">{{ $d['nombre'] }}</td>
                </tr>
            @endforeach
        </table>

        <p class="footer">
            Mensaje automático de Opland PRO. No respondas a esta dirección.
        </p>
    </div>
</body>
</html>
