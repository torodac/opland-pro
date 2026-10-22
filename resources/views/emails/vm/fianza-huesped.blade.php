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
            <p style="margin:0 0 12px;">Hola{{ $nombre ? ' ' . $nombre : '' }},</p>
            <p style="margin:0 0 16px;">Le confirmamos la devolución de la fianza de su estancia.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 6px;">
            @include('emails.vm.partials.fianza-huesped-datos', ['idioma' => 'es', 'propiedad' => $propiedad, 'booking_id' => $booking_id, 'entrada' => $entrada, 'salida' => $salida, 'importe' => $importe, 'retencion' => $retencion, 'motivo' => $motivo])
          </td>
        </tr>
        <tr>
          <td style="padding:10px 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;">Gracias por su estancia.</p>
          </td>
        </tr>

        {{-- Separador --}}
        <tr>
          <td style="padding:0 32px;"><div style="border-top:1px solid #e2e8ee;height:0;line-height:0;">&nbsp;</div></td>
        </tr>

        {{-- Inglés --}}
        <tr>
          <td style="padding:16px 24px 6px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 12px;">Hello{{ $nombre ? ' ' . $nombre : '' }},</p>
            <p style="margin:0 0 16px;">We confirm the refund of your deposit for your stay.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 24px 6px;">
            @include('emails.vm.partials.fianza-huesped-datos', ['idioma' => 'en', 'propiedad' => $propiedad, 'booking_id' => $booking_id, 'entrada' => $entrada, 'salida' => $salida, 'importe' => $importe, 'retencion' => $retencion, 'motivo' => $motivo])
          </td>
        </tr>
        <tr>
          <td style="padding:10px 24px 20px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0;">Thank you for your stay.</p>
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
