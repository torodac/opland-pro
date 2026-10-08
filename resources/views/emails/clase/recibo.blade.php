<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;font-size:14px;color:#1a1a1a;background:#f9fafb;margin:0;padding:0}
  .wrap{max-width:520px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  .top{background:#6366f1;color:#fff;padding:24px 28px;font-size:18px;font-weight:bold}
  .body{padding:28px}
  .footer{padding:16px 28px;background:#f3f4f6;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
  <div class="wrap">
    <div class="top">Academia Clase</div>
    <div class="body">
      <p>Estimado/a <strong>{{ $recibo->pagador_nombre }}</strong>,</p>
      <br>
      <p>Adjunto encontrará la factura n° <strong>{{ $recibo->numero_factura }}</strong>
         correspondiente al período de
         <strong>{{ \Carbon\Carbon::parse($recibo->mes.'-01')->translatedFormat('F Y') }}</strong>
         por un importe de <strong>{{ number_format((float)$recibo->importe_total,2,',','.') }} €</strong>.</p>
      <br>
      <p>Si tiene alguna consulta, no dude en contactarnos.</p>
      <br>
      <p>Atentamente,<br><strong>Academia Clase</strong></p>
    </div>
    <div class="footer">Este mensaje ha sido generado automáticamente. Por favor no responda a este correo.</div>
  </div>
</body>
</html>
