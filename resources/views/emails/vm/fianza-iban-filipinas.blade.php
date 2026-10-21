<p>Hola,</p>

<p>Hay que devolver la fianza de la reserva <strong>{{ $booking_id }}</strong>
({{ $propiedad }}, del {{ $entrada }} al {{ $salida }}) por transferencia.</p>

<p>Necesitamos el número de cuenta del huésped. Entre en este enlace, compruebe los datos y escriba el IBAN:</p>

<p><a href="{{ $enlace }}">Abrir enlace</a></p>

<p>El enlace caduca el {{ $caduca }}. Si ya no funciona, avísenos y le enviamos uno nuevo.</p>

<p>Gracias.</p>

<hr style="border:0;border-top:1px solid #ddd;margin:24px 0">

<p>Hello,</p>

<p>The deposit for booking <strong>{{ $booking_id }}</strong>
({{ $propiedad }}, from {{ $entrada }} to {{ $salida }}) must be refunded by bank transfer.</p>

<p>We need the guest's bank account number. Please open this link, check the booking details and enter the IBAN:</p>

<p><a href="{{ $enlace }}">Open link</a></p>

<p>The link expires on {{ $caduca }}. If it no longer works, let us know and we will send you a new one.</p>

<p>Thank you.</p>
