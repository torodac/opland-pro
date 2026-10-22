@php
    $es = $idioma === 'es';
    $etiquetas = $es
        ? ['propiedad' => 'Propiedad', 'reserva' => 'Reserva', 'estancia' => 'Estancia', 'devuelto' => 'Importe devuelto', 'retenido' => 'Importe retenido', 'motivo' => 'Motivo']
        : ['propiedad' => 'Property', 'reserva' => 'Booking', 'estancia' => 'Stay', 'devuelto' => 'Amount refunded', 'retenido' => 'Amount withheld', 'motivo' => 'Reason'];
    $filas = [
        [$etiquetas['propiedad'], $propiedad],
        [$etiquetas['reserva'], $booking_id],
        [$etiquetas['estancia'], $entrada . ' – ' . $salida],
        [$etiquetas['devuelto'], number_format($importe, 2, ',', '.') . ' €'],
    ];
    if ($retencion > 0) {
        $filas[] = [$etiquetas['retenido'], number_format($retencion, 2, ',', '.') . ' €'];
        $filas[] = [$etiquetas['motivo'], $motivo];
    }
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#16232b;">
  @foreach($filas as [$etiqueta, $valor])
    <tr>
      <td width="40%" style="padding:10px 12px;border-bottom:1px solid #e2e8ee;color:#5b6b76;background:#f8fafb;">{{ $etiqueta }}</td>
      <td style="padding:10px 12px;border-bottom:1px solid #e2e8ee;font-weight:bold;">{{ $valor }}</td>
    </tr>
  @endforeach
</table>
