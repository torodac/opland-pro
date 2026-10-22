<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f8;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">

        {{-- Cabecera con el logo --}}
        <tr>
          <td align="center" style="padding:20px 16px 14px;border-bottom:3px solid #c2410c;">
            <img src="{{ asset('projects/vm/logo.png') }}" alt="Vacation Marbella" width="126" style="display:block;width:126px;height:auto;border:0;">
          </td>
        </tr>

        {{-- Castellano --}}
        <tr>
          <td style="padding:20px 24px 6px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 12px;">Hola,</p>
            <p style="margin:0 0 12px;">Hay que devolver la fianza de la reserva <strong>{{ $booking_id }}</strong> ({{ $propiedad }}, del {{ $entrada }} al {{ $salida }}) por transferencia.</p>
            <p style="margin:0 0 16px;">Necesitamos el número de cuenta del huésped. Entre en este enlace, compruebe los datos y escriba el IBAN:</p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:4px 24px 14px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#c2410c" style="border-radius:6px;">
                  <a href="{{ $enlace }}" target="_blank" style="display:inline-block;padding:12px 26px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">Abrir enlace</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;color:#5b6b76;font-size:13px;">El enlace caduca el {{ $caduca }}. Si ya no funciona, avísenos y le enviamos uno nuevo.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;">Gracias.</p>
          </td>
        </tr>

        {{-- Separador --}}
        <tr>
          <td style="padding:0 32px;"><div style="border-top:1px solid #e2e8ee;height:0;line-height:0;">&nbsp;</div></td>
        </tr>

        {{-- Inglés --}}
        <tr>
          <td style="padding:16px 24px 6px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 12px;">Hello,</p>
            <p style="margin:0 0 12px;">The deposit for booking <strong>{{ $booking_id }}</strong> ({{ $propiedad }}, from {{ $entrada }} to {{ $salida }}) must be refunded by bank transfer.</p>
            <p style="margin:0 0 16px;">We need the guest's bank account number. Please open this link, check the booking details and enter the IBAN:</p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:4px 24px 14px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#c2410c" style="border-radius:6px;">
                  <a href="{{ $enlace }}" target="_blank" style="display:inline-block;padding:12px 26px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">Open link</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;color:#5b6b76;font-size:13px;">The link expires on {{ $caduca }}. If it no longer works, let us know and we will send you a new one.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;">Thank you.</p>
          </td>
        </tr>

        {{-- Pie --}}
        <tr>
          <td align="center" style="background:#16232b;padding:18px 24px;color:#c3ced6;font-size:12px;line-height:1.5;">
            <strong style="color:#ffffff;">Vacation Marbella</strong>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
