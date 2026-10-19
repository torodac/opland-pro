<p>Hola,</p>

<p>Hay que devolver la fianza de la reserva <strong>{{ $booking_id }}</strong>
({{ $propiedad }}, del {{ $entrada }} al {{ $salida }}) por transferencia.</p>

<p>Necesitamos el número de cuenta del huésped. Entre en este enlace, compruebe los datos y escriba el IBAN:</p>

<p><a href="{{ $enlace }}">{{ $enlace }}</a></p>

<p>El enlace caduca el {{ $caduca }}. Si ya no funciona, avísenos y le enviamos uno nuevo.</p>

<p>Gracias.</p>
