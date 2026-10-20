<p>Hola{{ $nombre ? ' ' . $nombre : '' }},</p>

<p>Le confirmamos la devolución de la fianza de su estancia en <strong>{{ $propiedad }}</strong>
(reserva {{ $booking_id }}, del {{ $entrada }} al {{ $salida }}).</p>

<p>Importe devuelto: <strong>{{ number_format($importe, 2, ',', '.') }} €</strong></p>

@if($retencion > 0)
<p>Se han retenido <strong>{{ number_format($retencion, 2, ',', '.') }} €</strong> por:<br>{{ $motivo }}</p>
@endif

<p>Gracias por su estancia.<br>Vacation Marbella</p>

<hr style="border:0;border-top:1px solid #ddd;margin:24px 0">

<p>Hello{{ $nombre ? ' ' . $nombre : '' }},</p>

<p>We confirm the refund of your deposit for your stay at <strong>{{ $propiedad }}</strong>
(booking {{ $booking_id }}, from {{ $entrada }} to {{ $salida }}).</p>

<p>Amount refunded: <strong>{{ number_format($importe, 2, ',', '.') }} €</strong></p>

@if($retencion > 0)
<p>We have withheld <strong>{{ number_format($retencion, 2, ',', '.') }} €</strong> for:<br>{{ $motivo }}</p>
@endif

<p>Thank you for your stay.<br>Vacation Marbella</p>
