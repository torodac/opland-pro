@php
    // Mismo lenguaje visual que el correo de la factura, con el color del estado como única
    // diferencia: el pagador debe distinguir de un vistazo un cobro confirmado de uno anulado.
    $color = match ($estado) {
        'Cobrado' => '#10b981',
        'Anulado' => '#ef4444',
        default   => '#f59e0b',
    };
    $titulo = match ($estado) {
        'Cobrado' => 'Cobro confirmado',
        'Anulado' => 'Cobro anulado',
        default   => 'Cobro pendiente',
    };
    $euros = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;font-size:14px;color:#1a1a1a;background:#f9fafb;margin:0;padding:0}
  .wrap{max-width:520px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  .top{background:#6366f1;color:#fff;padding:24px 28px;font-size:18px;font-weight:bold}
  .estado{padding:12px 28px;color:#fff;font-size:13px;font-weight:bold;letter-spacing:.04em}
  .body{padding:28px}
  table.det{width:100%;border-collapse:collapse;margin:8px 0 4px}
  table.det td{padding:7px 0;border-bottom:1px solid #eef1f4;font-size:13px}
  table.det td.imp{text-align:right;font-weight:bold;white-space:nowrap}
  table.det tr.total td{border-bottom:0;border-top:2px solid #e5e7eb;font-weight:bold;padding-top:10px}
  .footer{padding:16px 28px;background:#f3f4f6;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
  <div class="wrap">
    <div class="top">Academia Clase</div>
    <div class="estado" style="background:{{ $color }}">{{ $titulo }}</div>
    <div class="body">
      <p>Estimado/a <strong>{{ $pagador }}</strong>,</p>
      <br>

      @if($estado === 'Cobrado')
        <p>Le confirmamos que hemos registrado el cobro de los siguientes conceptos.</p>
      @elseif($estado === 'Anulado')
        <p>Le informamos de que los siguientes cobros han quedado anulados. No se le pasará
           ningún importe por ellos.</p>
      @else
        <p>Le informamos de que los siguientes cobros figuran de nuevo como pendientes de pago.</p>
      @endif

      <table class="det">
        @foreach($conceptos as $c)
          <tr>
            <td>
              {{ $c['alumno'] }}
              @if(!empty($c['mes']))
                <span style="color:#94a3b8">· {{ \Carbon\Carbon::parse($c['mes'] . '-01')->translatedFormat('F Y') }}</span>
              @endif
            </td>
            <td class="imp">{{ $euros($c['importe']) }}</td>
          </tr>
        @endforeach
        <tr class="total">
          <td>Total</td>
          <td class="imp">{{ $euros($total) }}</td>
        </tr>
      </table>

      @if($estado === 'Cobrado' && $numeroRecibo)
        <br>
        <p>Adjunto encontrará el recibo <strong>{{ $numeroRecibo }}</strong>.</p>
      @endif

      <br>
      <p>Si tiene alguna consulta, no dude en contactarnos.</p>
      <br>
      <p>Atentamente,<br><strong>Academia Clase</strong></p>
    </div>
    <div class="footer">Este mensaje ha sido generado automáticamente. Por favor no responda a este correo.</div>
  </div>
</body>
</html>
