<p>Hola Borja,</p>

<p>Se ha aplicado una retención de fianza en una reserva de <strong>Airbnb</strong>, para que hagas la reclamación.</p>

<p>Reserva: <strong>{{ $booking_id }}</strong> · {{ $propiedad }}<br>
Estancia: {{ $entrada }} – {{ $salida }}<br>
Huésped: {{ $huesped ?? '—' }}</p>

<p>Retenido: <strong>{{ number_format($retencion, 2, ',', '.') }} €</strong></p>
<p>Motivo: {{ $motivo }}</p>

<p>Ficha de la devolución: <a href="{{ $enlace }}">{{ $enlace }}</a></p>

<p>Vacation Marbella</p>
