<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Número de cuenta para la devolución · Bank account for the refund</title>
  <link rel="icon" href="{{ asset('projects/vm/logo.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('projects/vm/logo.png') }}">
  <style>
    :root { color-scheme: light dark; --fg:#1f2328; --bg:#fff; --mut:#656d76; --bd:#d0d7de; --ok:#1a7f37; --err:#cf222e; }
    @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --fg:#e6edf3; --bg:#0d1117; --mut:#8b949e; --bd:#30363d; } }
    body { margin:0; background:var(--bg); color:var(--fg); font:15px/1.5 system-ui, sans-serif; }
    main { max-width:520px; margin:0 auto; padding:24px 16px; }
    h1 { font-size:20px; margin:0 0 12px; }
    .en { color:var(--mut); font-size:13px; margin:4px 0 0; font-style:italic; }
    dl { display:grid; grid-template-columns:auto 1fr; gap:4px 12px; margin:0 0 20px; }
    dt { color:var(--mut); }
    dd { margin:0; }
    label { display:block; font-weight:600; margin-bottom:6px; }
    input[type=text] { width:100%; box-sizing:border-box; padding:10px; font:inherit; border:1px solid var(--bd); border-radius:6px; background:var(--bg); color:var(--fg); letter-spacing:.04em; }
    button { margin-top:12px; padding:10px 16px; font:inherit; font-weight:600; border:0; border-radius:6px; background:#0969da; color:#fff; cursor:pointer; width:100%; }
    .aviso { padding:12px; border:1px solid var(--bd); border-radius:6px; margin-bottom:16px; }
    .error { color:var(--err); margin:0 0 12px; }
    .ok { color:var(--ok); font-weight:600; }
    small { color:var(--mut); display:block; margin-top:8px; }
  </style>
</head>
<body>
<main>
  <h1>Número de cuenta para la devolución</h1>
  <p class="en">Bank account for the deposit refund</p>

  <dl>
    <dt>Reserva</dt><dd>{{ $reserva->booking_id }}</dd>
    <dt>Propiedad</dt><dd>{{ $reserva->propiedad ?? '—' }}</dd>
    <dt>Entrada</dt><dd>{{ \Illuminate\Support\Carbon::parse($reserva->check_in_date)->format('d/m/Y') }}</dd>
    <dt>Salida</dt><dd>{{ \Illuminate\Support\Carbon::parse($reserva->check_out_date)->format('d/m/Y') }}</dd>
  </dl>
  <p class="en">Booking · Property · Check-in · Check-out</p>

  @if($yaGuardado)
    <p class="ok">Número de cuenta recibido. Gracias.</p>
    <p class="en">Bank account received. Thank you.</p>
    <p>No hace falta hacer nada más con este enlace.</p>
    <p class="en">No further action is needed with this link.</p>
  @else
    <div class="aviso">
      <p>Comprueba que los datos de la reserva son los correctos y escribe el número de cuenta (IBAN) del huésped.</p>
      <p class="en">Please check that the booking details are correct and enter the guest's bank account number (IBAN).</p>
    </div>

    @if($error)
      <p class="error">{{ $error }}</p>
      <p class="en">The account number is not valid. Please check it: it must be a complete IBAN.</p>
    @endif

    <form method="POST" action="{{ request()->fullUrl() }}" autocomplete="off" onsubmit="return validarIban(document.getElementById('iban'), true)">
      @csrf
      <label for="iban">IBAN</label>
      <input id="iban" name="iban" type="text" inputmode="text" maxlength="40" required
             placeholder="ES00 0000 0000 0000 0000 0000" value=""
             oninput="if (!ibanError(this.value)) validarIban(this)" onblur="validarIban(this)">
      <p id="iban-error" class="error" role="alert" style="display:none;margin:6px 0 0;font-size:14px"></p>
      <button type="submit">Guardar número de cuenta · Save bank account</button>
      <small>Una vez guardado no se puede volver a cambiar desde este enlace.</small>
      <small class="en">Once saved, it cannot be changed from this link.</small>
    </form>

    <script>
      // Mismos formatos y mismos motivos de error que FianzaIbanController::ibanError.
      const FORMATOS = @json(array_map(fn($f) => ['largo' => $f[0], 'estructura' => $f[1]], \App\Http\Controllers\Vm\FianzaIbanController::FORMATOS));

      function mod97(iban) {
        const reordenado = iban.slice(4) + iban.slice(0, 4);
        let resto = 0;
        for (const c of reordenado) {
          const digitos = /[A-Z]/.test(c) ? String(c.charCodeAt(0) - 55) : c;
          for (const d of digitos) resto = (resto * 10 + Number(d)) % 97;
        }
        return resto === 1;
      }

      function ibanError(raw) {
        const iban = raw.replace(/\s+/g, '').toUpperCase();
        if (iban === '') return 'Escribe el número de cuenta.';
        if (!/^[A-Z0-9]+$/.test(iban)) return 'El número solo puede contener letras y cifras.';
        if (!/^[A-Z]{2}[0-9]{2}/.test(iban)) return 'Debe empezar por el código de país (2 letras) y 2 cifras de control.';
        const pais = iban.slice(0, 2);
        const formato = FORMATOS[pais];
        if (!formato) return 'País no compatible con IBAN (' + pais + ').';
        if (iban.length !== formato.largo) return 'Longitud inválida: ' + pais + ' debe tener ' + formato.largo + ' caracteres (tiene ' + iban.length + ').';
        if (!new RegExp(formato.estructura).test(iban.slice(4))) return 'Estructura inválida: la cuenta no tiene el formato de ' + pais + '.';
        if (!mod97(iban)) return 'Dígitos de verificación inválidos: revisa el número.';
        return null;
      }

      // Muestra el motivo bajo el campo. Con «enviando» se impide enviar si no vale.
      function validarIban(input, enviando = false) {
        const error = ibanError(input.value);
        const aviso = document.getElementById('iban-error');
        const mostrar = error && (enviando || input.value.trim() !== '');
        aviso.textContent = mostrar ? error : '';
        aviso.style.display = mostrar ? '' : 'none';
        input.setAttribute('aria-invalid', error ? 'true' : 'false');
        return !error;
      }
    </script>
  @endif
</main>
</body>
</html>
