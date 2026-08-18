<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page{margin:0 0 1cm 0}
  body{font-family:DejaVu Sans, sans-serif; color:#16232b; font-size:12px; margin:0}
  .doc-header{width:100%; background-color:#bfdaf2; border-collapse:collapse}
  .doc-blob-cell{width:220px; padding:0; vertical-align:top}
  .doc-blob{background-color:#1b5d73; color:#fff; padding:18px 22px; border-bottom-right-radius:140px}
  .doc-logo-img{height:22px}
  .doc-company{font-size:9.5px; line-height:1.45; font-weight:bold; margin-top:8px}
  .doc-client{padding:18px 22px; vertical-align:top}
  .doc-client-lbl{font-size:9px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#3f5a6b; margin-bottom:6px}
  .doc-client-name{font-weight:bold; font-size:13px; color:#16232b; margin-bottom:6px}
  .doc-client-addr{color:#3f5a6b; font-size:10.5px; line-height:1.6}
  .doc-meta{padding:18px 22px 18px 0; text-align:right; width:150px; vertical-align:top}
  .doc-meta-lbl{font-size:9px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#3f5a6b}
  .doc-meta-val{font-weight:bold; font-size:14px; color:#16232b; margin:2px 0 12px}
  .doc-body{padding:22px 26px 210px}
  .doc-service-title{font-weight:bold; font-size:14px; color:#16232b; margin-bottom:16px; padding-bottom:10px; border-bottom:1.5px solid #16232b}
  .doc-table{width:100%; border-collapse:collapse; margin-bottom:22px; table-layout:fixed}
  .doc-table th{font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#7e93a1; text-align:left; padding:4px; line-height:1.2; vertical-align:middle; border-bottom:1.5px solid #16232b; white-space:nowrap}
  .doc-table td{padding:9px 4px; font-size:11px; border-bottom:1px solid #eaf1f6; word-wrap:break-word}
  .doc-table td.num{text-align:right; white-space:nowrap}
  .doc-table tr.doc-base-row td{border-bottom:none; border-top:1.5px solid #16232b; padding-top:11px; white-space:nowrap}
  .doc-col-concepto{width:50%}
  .doc-col-precio{width:14%}
  .doc-col-dtop{width:8%}
  .doc-col-dtoe{width:14%}
  .doc-col-total{width:14%}
  .doc-totals-grid{width:100%; border-collapse:separate; border-spacing:8px 0; margin-bottom:9px}
  .doc-total-box{border:1px solid #dce6ee; padding:12px 14px; width:33%}
  .doc-total-box .val{display:block; font-size:15px; font-weight:bold; color:#16232b}
  .doc-total-box .lbl{font-size:10px; color:#7e93a1; margin-top:2px}
  .doc-payment-note{text-align:center; font-weight:bold; font-size:11px; margin-bottom:18px; line-height:1.6}
  .doc-legal{padding-top:14px; border-top:1px solid #eaf1f6; font-size:8.5px; color:#8a9aa5; line-height:1.05; text-align:justify}
  .doc-footer-fixed{position:fixed; bottom:0; left:0; right:0; padding:0 26px}
</style>
</head>
<body>

@php
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.') . ' €';
  $hayDescuento = collect($lineas)->contains(fn($l) => ($l->descuentoe ?? 0) > 0);
  $totalDescuento = collect($lineas)->sum(fn($l) => $l->descuentoe ?: 0);
@endphp

<table class="doc-header" cellpadding="0" cellspacing="0">
  <tr>
    <td class="doc-blob-cell">
      <div class="doc-blob">
        @if($logoB64)
          <img src="{{ $logoB64 }}" class="doc-logo-img"><br>
        @endif
        <div class="doc-company">
          OPLAND ZERO PAPER S.L<br>
          B-10526911<br>
          Av. Aragón, 40<br>
          46021 Valencia
        </div>
      </div>
    </td>
    <td class="doc-client" valign="top">
      <div class="doc-client-lbl">Datos cliente</div>
      <div class="doc-client-name">{{ $cliente ? ($cliente->nombre_fiscal ?: $cliente->nombre) : '—' }}</div>
      <div class="doc-client-addr">
        @if($cliente)
          @if($cliente->nif) {{ $cliente->nif }}<br> @endif
          @if($cliente->direccion) {{ $cliente->direccion }}<br> @endif
          @if($cliente->cp || $cliente->poblacion) {{ trim(($cliente->cp ?? '').' '.($cliente->poblacion ?? '')) }} @endif
        @endif
      </div>
    </td>
    <td class="doc-meta" valign="top">
      <div class="doc-meta-lbl">Nº factura</div>
      <div class="doc-meta-val">{{ $numFact }}</div>
      <div class="doc-meta-lbl">Fecha</div>
      <div class="doc-meta-val">{{ $f->fecha_emision ? \Carbon\Carbon::parse($f->fecha_emision)->format('d/m/Y') : now()->format('d/m/Y') }}</div>
    </td>
  </tr>
</table>

<div class="doc-body">
  <div class="doc-service-title">{{ $f->descripcion ?: '—' }}</div>

  <table class="doc-table">
    <colgroup>
      <col style="{{ $hayDescuento ? '' : 'width:82%' }}" class="{{ $hayDescuento ? 'doc-col-concepto' : '' }}">
      @if($hayDescuento)
        <col class="doc-col-precio"><col class="doc-col-dtop"><col class="doc-col-dtoe">
      @endif
      <col class="{{ $hayDescuento ? 'doc-col-total' : '' }}" style="{{ $hayDescuento ? '' : 'width:18%' }}">
    </colgroup>
    <thead>
      <tr>
        <th class="doc-col-concepto">Concepto</th>
        @if($hayDescuento)
          <th class="doc-col-precio" style="text-align:right">Precio</th>
          <th class="doc-col-dtop" style="text-align:right">Dto. %</th>
          <th class="doc-col-dtoe" style="text-align:right">Dto. €</th>
        @endif
        <th class="doc-col-total" style="text-align:right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($lineas as $l)
        <tr>
          <td class="doc-col-concepto">{{ $l->nombre }}</td>
          @if($hayDescuento)
            <td class="num doc-col-precio">{{ $fmt($l->precio) }}</td>
            <td class="num doc-col-dtop">{{ $l->descuentoporc ?: 0 }}%</td>
            <td class="num doc-col-dtoe">{{ $fmt($l->descuentoe ?: 0) }}</td>
          @endif
          <td class="num doc-col-total"><strong>{{ $fmt($l->precio - ($l->descuentoe ?: 0)) }}</strong></td>
        </tr>
      @endforeach
      <tr class="doc-base-row">
        @if($hayDescuento)
          <td colspan="2"></td>
          <td class="num doc-col-dtop"></td>
          <td class="num doc-col-dtoe">{{ $fmt($totalDescuento) }}</td>
        @else
          <td></td>
        @endif
        <td class="num doc-col-total"><strong>{{ $fmt($base) }}</strong></td>
      </tr>
    </tbody>
  </table>

</div>

<div class="doc-footer-fixed">
  <table class="doc-totals-grid">
    <tr>
      <td class="doc-total-box"><span class="val">{{ $fmt($baseNeta) }}</span><span class="lbl">Base imponible</span></td>
      <td class="doc-total-box"><span class="val">{{ $fmt($iva) }}</span><span class="lbl">Total IVA</span></td>
      <td class="doc-total-box"><span class="val">{{ $fmt($total) }}</span><span class="lbl">Total factura</span></td>
    </tr>
  </table>

  <div class="doc-payment-note">
    <div style="white-space:nowrap">Rogamos hagan efectivo el pago de la presente factura mediante transferencia a la cuenta del Banco BBVA</div>
    <div>ES24 0182 7710 4502 0250 8013</div>
  </div>

  <div class="doc-legal">
    En cumplimiento de lo establecido en el Reglamento General de Protección de Datos (UE) 2016/679 del Parlamento Europeo y del Consejo, de 27 de abril de 2016,
    relativo a la protección de datos personales y la libre circulación de los mismos, le comunicamos que los datos que usted nos facilite quedarán incorporados y serán
    tratados en los ficheros titularidad de OPLAND ZERO PAPER S.L. con el fin de poderle prestar nuestros servicios, así como para mantenerle informado sobre cuestiones
    relativas a la actividad de la empresa. OPLAND ZERO PAPER S.L. se compromete a tratar de forma confidencial los datos de carácter personal facilitados y a no comunicar
    o ceder dicha información a terceros. De acuerdo con dicha Ley, tiene derecho a ejercer los derechos de acceso, rectificación, cancelación, limitación, oposición y
    portabilidad de manera gratuita mediante correo electrónico a opland@opland.es
  </div>
</div>

</body>
</html>
