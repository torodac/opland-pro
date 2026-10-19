<p>Hola{{ $nombre ? ' ' . $nombre : '' }},</p>

<p>Le confirmamos la devolución de la fianza de su estancia en <strong>{{ $propiedad }}</strong>
(reserva {{ $booking_id }}).</p>

<p>Importe a devolver: <strong>{{ number_format($importe, 2, ',', '.') }} €</strong></p>

@if($retencion > 0)
<p>Se retienen <strong>{{ number_format($retencion, 2, ',', '.') }} €</strong> por:</p>
<p>{{ $motivo }}</p>
@endif

<p>Le avisaremos cuando el importe esté en camino.</p>

<p>Vacation Marbella</p>
