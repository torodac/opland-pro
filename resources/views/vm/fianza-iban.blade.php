<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Número de cuenta para la devolución</title>
  <style>
    :root { color-scheme: light dark; --fg:#1f2328; --bg:#fff; --mut:#656d76; --bd:#d0d7de; --ok:#1a7f37; --err:#cf222e; }
    @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --fg:#e6edf3; --bg:#0d1117; --mut:#8b949e; --bd:#30363d; } }
    body { margin:0; background:var(--bg); color:var(--fg); font:15px/1.5 system-ui, sans-serif; }
    main { max-width:520px; margin:0 auto; padding:24px 16px; }
    h1 { font-size:20px; margin:0 0 12px; }
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

  <dl>
    <dt>Reserva</dt><dd>{{ $reserva->booking_id }}</dd>
    <dt>Propiedad</dt><dd>{{ $reserva->propiedad ?? '—' }}</dd>
    <dt>Entrada</dt><dd>{{ \Illuminate\Support\Carbon::parse($reserva->check_in_date)->format('d/m/Y') }}</dd>
    <dt>Salida</dt><dd>{{ \Illuminate\Support\Carbon::parse($reserva->check_out_date)->format('d/m/Y') }}</dd>
  </dl>

  @if($yaGuardado)
    <p class="ok">Número de cuenta recibido. Gracias.</p>
    <p>No hace falta hacer nada más con este enlace.</p>
  @else
    <p class="aviso">Comprueba que los datos de la reserva son los correctos y escribe el número de cuenta (IBAN) del huésped.</p>

    @if($error)
      <p class="error">{{ $error }}</p>
    @endif

    <form method="POST" action="{{ request()->fullUrl() }}" autocomplete="off">
      @csrf
      <label for="iban">IBAN</label>
      <input id="iban" name="iban" type="text" inputmode="text" maxlength="40" required
             placeholder="ES00 0000 0000 0000 0000 0000" value="">
      <button type="submit">Guardar número de cuenta</button>
      <small>Una vez guardado no se puede volver a cambiar desde este enlace.</small>
    </form>
  @endif
</main>
</body>
</html>
