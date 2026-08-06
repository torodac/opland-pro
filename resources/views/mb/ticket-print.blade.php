<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket #{{ $ticket->id }}</title>
<style>
@page { size: 80mm auto; margin: 2mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Courier New', monospace; font-size: 12px; color: #000; width: 76mm; }
.centro { text-align: center; }
.logo { display: block; margin: 0 auto 6px; max-width: 55mm; max-height: 30mm; }
.titulo { font-size: 13px; font-weight: bold; margin: 0 0 2px; }
.small { font-size: 10.5px; margin: 0; }
.linea { border-top: 1px dashed #000; margin: 8px 0; }
table { width: 100%; border-collapse: collapse; font-size: 11px; }
td { padding: 2px 0; vertical-align: top; }
td.num { text-align: right; white-space: nowrap; }
.concepto { color: #333; font-size: 10px; }
.total-row td { font-weight: bold; font-size: 13px; padding-top: 6px; }
.pie { margin-top: 10px; font-size: 10px; text-align: center; color: #333; }
.no-imprimir { text-align: center; margin-top: 14px; }
.no-imprimir button { font-size: 13px; padding: 8px 16px; border-radius: 8px; border: 1px solid #999; background: #fff; cursor: pointer; }
@media print { .no-imprimir { display: none; } }
</style>
</head>
<body>

<div class="centro">
  @if($logoDataUri)
  <img class="logo" src="{{ $logoDataUri }}">
  @endif
  <p class="titulo">SOCIEDAD CIVIL MARENY BLAU</p>
  <p class="small">CIF: J-46717757</p>
</div>

<div class="linea"></div>

<p class="small">Ticket nº: <strong>{{ $ticket->id }}</strong></p>
<p class="small">Fecha: {{ \Illuminate\Support\Carbon::parse($ticket->fecha)->format('d/m/Y') }} {{ \Illuminate\Support\Carbon::parse($ticket->createdat)->format('H:i') }}</p>

<div class="linea"></div>

<table>
  @foreach($lineas as $l)
  <tr>
    <td>
      {{ $l->vivienda }}
      <div class="concepto">{{ $l->concepto }}</div>
    </td>
    <td class="num">{{ number_format($l->importe, 2, ',', '.') }} €</td>
  </tr>
  @endforeach
  <tr class="total-row">
    <td>TOTAL</td>
    <td class="num">{{ number_format($ticket->total, 2, ',', '.') }} €</td>
  </tr>
  @if($ticket->importe_efectivo > 0)
  <tr>
    <td>Efectivo</td>
    <td class="num">{{ number_format($ticket->importe_efectivo, 2, ',', '.') }} €</td>
  </tr>
  @endif
  @if($ticket->importe_tarjeta > 0)
  <tr>
    <td>Tarjeta</td>
    <td class="num">{{ number_format($ticket->importe_tarjeta, 2, ',', '.') }} €</td>
  </tr>
  @endif
</table>

<div class="linea"></div>

<p class="pie">Gracias.</p>

<div class="no-imprimir">
  <button type="button" onclick="window.print()">Imprimir</button>
</div>

<script>
window.addEventListener('load', function () {
  setTimeout(function () { window.print(); }, 200);
});
</script>
</body>
</html>
