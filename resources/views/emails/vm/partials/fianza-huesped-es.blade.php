{{-- Bloque en castellano del correo al huésped. Lo incluye la plantilla del correo y la previsualización
     de la modal del botón Hecho, así que lo que se ve ahí es lo que sale. --}}
        <tr>
          <td style="padding:20px 24px 6px;color:#16232b;font-size:15px;line-height:1.6;">
            <p style="margin:0 0 12px;">Hola{{ $nombre ? ' ' . $nombre : '' }},</p>
            @if($importe > 0 && $retencion > 0)
              <p style="margin:0 0 16px;">Le confirmamos la devolución parcial de la fianza de su estancia: se le devuelve el importe indicado y se retiene el resto por el motivo indicado a continuación.</p>
            @elseif($importe > 0)
              <p style="margin:0 0 16px;">Le confirmamos la devolución de la fianza de su estancia.</p>
            @else
              <p style="margin:0 0 16px;">Le informamos de que no se devuelve la fianza de su estancia: queda retenida en su totalidad por el motivo indicado a continuación.</p>
            @endif
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
