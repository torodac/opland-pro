<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10pt;
    color: #2B3340;
    background: #FFFFFF;
    padding: 36px 40px 72px; /* espacio inferior para el footer fijo */
  }

  /* ── Footer fijo al pie ── */
  .footer {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 36px;
    background: #1B2A44;
    padding: 0 40px;
  }
  .footer table { width:100%; height:36px; border-collapse:collapse; }
  .footer td { vertical-align: middle; }
  .footer-tagline {
    font-size: 8pt;
    color: #B89C80;
    letter-spacing: .1em;
    font-style: italic;
  }
  .footer-addr {
    text-align: right;
    font-size: 7.5pt;
    color: #8C9A88;
    letter-spacing: .04em;
  }

  /* ── Cabecera ── */
  .hdr { margin-bottom: 24px; }
  .hdr table { width:100%; border-collapse:collapse; }
  .hdr td { vertical-align: middle; }
  .logo-cell { width: 56px; padding-right: 12px; }
  .name-cell { vertical-align: middle; }
  .academy-name {
    font-family: DejaVu Serif, serif;
    font-size: 15pt;
    font-weight: bold;
    color: #1B2A44;
    letter-spacing: .1em;
    line-height: 1.1;
  }
  .academy-sub {
    font-size: 7pt;
    color: #8C9A88;
    letter-spacing: .2em;
    margin-top: 3px;
  }
  .doc-cell { text-align:right; vertical-align: top; }
  .doc-tipo {
    font-size: 7.5pt;
    color: #8C9A88;
    letter-spacing: .16em;
    text-transform: uppercase;
    font-weight: bold;
    margin-bottom: 2px;
  }
  .doc-num {
    font-family: DejaVu Serif, serif;
    font-size: 18pt;
    font-weight: bold;
    color: #1B2A44;
    letter-spacing: .04em;
  }
  .doc-fecha {
    font-size: 8.5pt;
    color: #6E7B80;
    margin-top: 4px;
  }
  @if($recibo->numero_factura)
  .doc-ref {
    font-size: 7.5pt;
    color: #B89C80;
    margin-top: 2px;
  }
  @endif

  /* ── Separador ── */
  .divider {
    border: 0;
    border-top: 2px solid #B89C80;
    margin-bottom: 18px;
  }

  /* ── Bloque pagador ── */
  .pagador-row { margin-bottom: 20px; }
  .pagador-row table { width:100%; border-collapse:collapse; }
  .pagador-row td { vertical-align:top; }
  .field-label {
    font-size: 7pt;
    color: #8C9A88;
    text-transform: uppercase;
    letter-spacing: .14em;
    font-weight: bold;
    margin-bottom: 3px;
  }
  .field-val {
    font-size: 11pt;
    font-weight: bold;
    color: #1B2A44;
  }
  .field-sub {
    font-size: 8.5pt;
    color: #6E7B80;
    margin-top: 1px;
  }

  /* ── Sección título ── */
  .section-title {
    font-size: 7pt;
    color: #8C9A88;
    text-transform: uppercase;
    letter-spacing: .14em;
    font-weight: bold;
    padding-bottom: 5px;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 8px;
  }

  /* ── Alumno ── */
  .alumno-bloque { margin-bottom: 14px; }
  .alumno-nombre {
    font-size: 10pt;
    font-weight: bold;
    color: #1B2A44;
    padding: 5px 8px;
    border-left: 3px solid #B89C80;
    background: #FAF6EF;
    margin-bottom: 0;
  }
  table.lineas { width:100%; border-collapse:collapse; font-size: 9pt; }
  table.lineas td {
    padding: 5px 8px;
    border-bottom: .5px solid #F3F4F6;
    vertical-align: middle;
  }
  table.lineas .col-contrato { color: #2E3F5C; }
  table.lineas .col-mes { color: #9CA3AF; font-size: 8.5pt; width: 110px; }
  table.lineas .col-imp {
    text-align: right;
    font-weight: bold;
    color: #1B2A44;
    width: 90px;
    white-space: nowrap;
  }

  /* ── Total ── */
  .total-row { margin-top: 14px; }
  .total-row table { width:100%; border-collapse:collapse; }
  .total-row td { padding: 10px 8px; border-top: 2px solid #1B2A44; vertical-align:middle; }
  .total-label {
    font-size: 8.5pt;
    font-weight: bold;
    color: #6E7B80;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .total-val {
    text-align: right;
    font-family: DejaVu Serif, serif;
    font-size: 17pt;
    font-weight: bold;
    color: #1B2A44;
  }

  /* ── Formas de cobro ── */
  .formas-section { margin-top: 18px; }
  table.formas { width:100%; border-collapse:collapse; font-size: 9pt; }
  table.formas td {
    padding: 4px 8px;
    border-bottom: .5px solid #F3F4F6;
    color: #6E7B80;
  }
  table.formas .f-imp { text-align:right; color: #2B3340; font-weight:bold; width:90px; }
</style>
</head>
<body>

{{-- ── Footer fijo al pie ── --}}
<div class="footer">
  <table>
    <tr>
      <td class="footer-tagline">Aprende. Comprende. Avanza.</td>
      <td class="footer-addr">C/ Vicent Barrera Cambra, 3 &middot; 46020 València &middot; 634&nbsp;192&nbsp;017</td>
    </tr>
  </table>
</div>

{{-- ── Cabecera ── --}}
<div class="hdr">
  <table>
    <tr>
      <td class="logo-cell">
        <img src="{{ $logoB64 }}" style="height:50px;width:auto;display:block;" alt="Logo">
      </td>
      <td class="name-cell">
        <div class="academy-name">AL SALIR DE CLASE</div>
        <div class="academy-sub">CLUB DE REFUERZO EDUCATIVO</div>
      </td>
      <td class="doc-cell">
        <div class="doc-tipo">{{ $recibo->numero_factura ? 'Factura' : 'Recibo' }}</div>
        <div class="doc-num">{{ $recibo->numero_factura ?? $recibo->numero_recibo }}</div>
        @if($recibo->numero_factura)
        <div class="doc-ref">Recibo: {{ $recibo->numero_recibo }}</div>
        @endif
        <div class="doc-fecha">{{ \Carbon\Carbon::parse($recibo->numero_factura ? $recibo->fecha_factura : $recibo->fecha)->format('d/m/Y') }}</div>
      </td>
    </tr>
  </table>
</div>

<hr class="divider">

{{-- ── Pagador ── --}}
<div class="pagador-row">
  <table>
    <tr>
      <td>
        <div class="field-label">Facturado a</div>
        <div class="field-val">{{ $recibo->pagador_nombre }}</div>
        @if($recibo->pagador_email)
        <div class="field-sub">{{ $recibo->pagador_email }}</div>
        @endif
      </td>
      @if($recibo->numero_factura)
      <td style="width:150px; text-align:right;">
        <div class="field-label">Fecha factura</div>
        <div class="field-val" style="font-size:10pt;">{{ \Carbon\Carbon::parse($recibo->fecha_factura)->format('d/m/Y') }}</div>
      </td>
      @endif
    </tr>
  </table>
</div>

{{-- ── Detalle ── --}}
<div class="section-title">Detalle</div>

@foreach($porAlumno as $nombreAlumno => $cobros)
<div class="alumno-bloque">
  <div class="alumno-nombre">{{ $nombreAlumno }}</div>
  <table class="lineas">
    @foreach($cobros as $c)
    <tr>
      <td class="col-contrato">{{ $c->contrato_nombre ?? $c->grupo_nombre ?? 'Contrato' }}</td>
      <td class="col-mes">{{ \Carbon\Carbon::parse($c->fecha_cobro)->translatedFormat('F Y') }}</td>
      <td class="col-imp">{{ number_format((float)$c->cantidad_pagador,2,',','.') }}&nbsp;€</td>
    </tr>
    @endforeach
  </table>
</div>
@endforeach

<div class="total-row">
  <table>
    <tr>
      <td class="total-label">Total</td>
      <td class="total-val">{{ number_format((float)$recibo->importe_total,2,',','.') }}&nbsp;€</td>
    </tr>
  </table>
</div>

{{-- ── Formas de cobro ── --}}
@if(count($formas))
<div class="formas-section">
  <div class="section-title">Forma de cobro</div>
  <table class="formas">
    @foreach($formas as $f)
    <tr>
      <td>{{ $f['forma'] }}</td>
      <td class="f-imp">{{ number_format((float)$f['importe'],2,',','.') }}&nbsp;€</td>
    </tr>
    @endforeach
  </table>
</div>
@endif

</body>
</html>
