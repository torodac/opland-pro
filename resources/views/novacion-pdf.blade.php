<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 2cm 1.5cm 2cm 1.5cm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1a1a1a; padding: 0.3cm 0.3cm 0; }

/* CABECERA */
.hdr-wrap  { display:table; width:100%; margin-bottom:12pt; }
.hdr-left  { display:table-cell; vertical-align:top; width:68%; }
.hdr-right { display:table-cell; vertical-align:top; text-align:right; }
.hdr-logo  { max-height:56pt; max-width:130pt; }

.doc-title { font-size:11pt; font-weight:bold; text-transform:uppercase;
             color:#111; letter-spacing:.03em; margin-bottom:8pt; white-space:nowrap; }

.prop-data         { font-size:8.5pt; color:#222; line-height:1.7; }
.prop-data strong  { font-weight:700; }

/* BLOQUE PROPIEDAD */
.propi-wrap   { display:table; width:100%; margin:20pt 0; }
.propi-block  { display:table-cell; padding:6pt 10pt;
                border-left:3pt solid #c8a96e; background:#fafaf7; vertical-align:middle; }
.propi-mes    { display:table-cell; vertical-align:middle; text-align:right;
                padding:6pt 0 6pt 10pt; background:#fafaf7; border-right:none;
                font-size:9pt; font-weight:bold; color:#9a7230;
                text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; width:1%; }
.propi-nombre { font-size:9.5pt; font-weight:bold; text-transform:uppercase; color:#111; }
.propi-dir    { font-size:8pt; color:#555; font-style:italic; margin-top:1pt; }

/* TÍTULOS DE SECCIÓN */
.sec-title  { display:table; width:100%; margin:24pt 0 16pt; }
.sec-arrow  { display:table-cell; width:12pt; vertical-align:middle; }
.sec-arrow-inner { width:0; height:0; border-top:5pt solid transparent;
                   border-bottom:5pt solid transparent; border-left:8pt solid #c8a96e; }
.sec-text   { display:table-cell; vertical-align:middle; font-size:9pt; font-weight:bold;
              text-transform:uppercase; letter-spacing:.04em; color:#111; }

/* TABLA (compartida) */
.tbl { width:100%; border-collapse:collapse; font-size:7.8pt; margin-bottom:12pt; }
.tbl thead tr { background:#1a1a2e; color:#fff; }
.tbl thead th { padding:5pt 4pt; text-align:center; font-weight:700; letter-spacing:.03em; font-size:7.5pt; white-space:nowrap; }
.tbl thead th.l { text-align:left;  padding-left:6pt; }
.tbl thead th.r { text-align:right; padding-right:6pt; }

.tbl tbody tr            { border-bottom:1pt solid #ebebeb; }
.tbl tbody tr.even       { background:#f9f9f7; }
.tbl tbody td            { padding:4pt 4pt; text-align:center; color:#222; }
.tbl tbody td.l          { text-align:left;  padding-left:6pt; }
.tbl tbody td.r          { text-align:right; padding-right:6pt; }
.tbl tbody td.num        { color:#185FA5; font-weight:700; }

.tbl tfoot tr  { background:#1a1a2e; color:#fff; }
.tbl tfoot td  { padding:5pt 4pt; text-align:center; font-weight:700; font-size:8pt; }
.tbl tfoot td.r   { text-align:right; padding-right:6pt; color:#c8a96e; }
.tbl tfoot td.lbl { text-align:right; padding-right:4pt; color:#9ca3af; font-weight:400; font-size:7.5pt; }

/* TOTALIZADOR */
.total-wrap  { display:table; width:100%; background:#1a1a2e; border-radius:4pt; margin-top:12pt; }
.total-space { display:table-cell; }
.total-lbl   { display:table-cell; padding:8pt 10pt 8pt 0; font-size:8.5pt; font-weight:bold;
               color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; vertical-align:middle;
               width:1%; white-space:nowrap; text-align:right; }
.total-val   { display:table-cell; padding:8pt 12pt; font-size:11pt; font-weight:bold;
               color:#c8a96e; text-align:right; vertical-align:middle; width:1%; white-space:nowrap; }
</style>
</head>
<body>
@php
use Carbon\Carbon;

$nf = fn($v) => number_format((float)$v, 2, ',', '.');
$fd = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

$mesNombre = strtoupper($meses_es[$month]);

$dirPropi = trim(implode(' ', array_filter([
    $propiedad->icnea_address ?? '',
    $propiedad->icnea_zip     ?? '',
    $propiedad->icnea_city    ?? '',
])));

$totalPropietario = $reservas->sum('base_propietario');
$totalCalculo     = $reservas->sum('base_calculo');

$SUMI_LABELS = ['electricidad'=>'Electricidad','agua'=>'Agua',
                'internet'=>'Internet','alarma'=>'Alarma','jardineria'=>'Jardinería'];
$totalSumi = 0;
foreach ($SUMI_LABELS as $k => $lbl) {
    $v = $gastos ? (float)($gastos->$k ?? 0) : 0;
    $totalSumi += $v;
}
$allTareas   = $tareas->concat($piscinas)->sortBy('fecha_finalizacion');
$totalMant   = $allTareas->sum('importe_novacion');
$totalGastos = $totalSumi + $totalMant;
$totalNeto   = $totalPropietario - $totalGastos;
@endphp

{{-- CABECERA --}}
<div class="hdr-wrap">
  <div class="hdr-left">
    <div class="doc-title">Documento informativo de ingresos en la propiedad</div>
    <div class="prop-data">
      <strong>{{ $propiedad->propietario_nombre ?? '—' }}</strong><br>
      {{ $propiedad->propietario_cif ?? '' }}<br>
      @if($propiedad->propietario_domicilio)Domicilio: {{ $propiedad->propietario_domicilio }}@endif
    </div>
  </div>
  <div class="hdr-right">
    @if($logoB64)<img src="{{ $logoB64 }}" class="hdr-logo" alt="Logo">@endif
  </div>
</div>

{{-- PROPIEDAD + MES --}}
<div class="propi-wrap">
  <div class="propi-block">
    <div class="propi-nombre">Propiedad: {{ $propiedad->nombre ?? '—' }}</div>
    @if($dirPropi)<div class="propi-dir">{{ $dirPropi }}</div>@endif
  </div>
  <div class="propi-mes">{{ $meses_es[$month] }} {{ $year }}</div>
</div>

{{-- TÍTULO INGRESOS --}}
<div class="sec-title">
  <div class="sec-arrow"><div class="sec-arrow-inner"></div></div>
  <div class="sec-text">Ingresos de estancias</div>
</div>

{{-- TABLA INGRESOS: Reserva | Check-in | Check-out | Client | Nights | Gross€/Night | Gross Amount | €/Nights | Amount --}}
<table class="tbl">
  <thead>
    <tr>
      <th class="l" style="width:50pt">Reserva</th>
      <th style="width:44pt">Check-in</th>
      <th style="width:44pt">Check-out</th>
      <th class="l">Client</th>
      <th style="width:32pt">Nights</th>
      <th class="r" style="width:52pt">Gross€/Night</th>
      <th class="r" style="width:60pt">Gross Amount</th>
      <th class="r" style="width:48pt">€/Nights</th>
      <th class="r" style="width:54pt">Amount</th>
    </tr>
  </thead>
  <tbody>
    @foreach($reservas as $i => $r)
    @php
      $nights = Carbon::parse($r->check_in_date)->diffInDays(Carbon::parse($r->check_out_date));
      $nights = max($nights, 1);
      $eNight = (float)$r->base_propietario / $nights;
      $gNight = (float)$r->base_calculo      / $nights;
      $rowClass = ($i % 2 === 1) ? ' class="even"' : '';
    @endphp
    <tr{{ $rowClass }}>
      <td class="l">{{ $r->booking_id }}</td>
      <td>{{ $fd($r->check_in_date) }}</td>
      <td>{{ $fd($r->check_out_date) }}</td>
      <td class="l">{{ $r->guest_name }}</td>
      <td>{{ $nights }}</td>
      <td class="r">{{ $nf($gNight) }} €</td>
      <td class="r">{{ $nf($r->base_calculo) }} €</td>
      <td class="r">{{ $nf($eNight) }} €</td>
      <td class="r num">{{ $nf($r->base_propietario) }} €</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="7"></td>
      <td class="lbl">Subtotal</td>
      <td class="r">{{ $nf($totalPropietario) }} €</td>
    </tr>
  </tfoot>
</table>

{{-- TÍTULO GASTOS --}}
<div class="sec-title">
  <div class="sec-arrow"><div class="sec-arrow-inner"></div></div>
  <div class="sec-text">Gastos de suministros y mantenimiento</div>
</div>

{{-- TABLA GASTOS: Date | Description | [phantom 48pt] | Amount --}}
<table class="tbl">
  <thead>
    <tr>
      <th class="l" style="width:66pt">Date</th>
      <th class="l">Description</th>
      <th style="width:48pt"></th>
      <th class="r" style="width:54pt">Amount</th>
    </tr>
  </thead>
  <tbody>
    @php $mantOffset = 0; @endphp
    @foreach($SUMI_LABELS as $k => $lbl)
      @if($gastos && $gastos->$k)
      @php $fk = 'fecha_' . $k; $rowClass = ($mantOffset % 2 === 1) ? ' class="even"' : ''; $mantOffset++; @endphp
      <tr{{ $rowClass }}>
        <td class="l">{{ ($gastos->$fk ?? null) ? $fd($gastos->$fk) : '' }}</td>
        <td class="l" colspan="2">{{ $lbl }}</td>
        <td class="r num">{{ $nf($gastos->$k) }} €</td>
      </tr>
      @endif
    @endforeach

    @foreach($allTareas as $i => $t)
    @php $rowClass = (($mantOffset + $i) % 2 === 1) ? ' class="even"' : ''; @endphp
    <tr{{ $rowClass }}>
      <td class="l">{{ $fd($t->fecha_finalizacion) }}</td>
      <td class="l" colspan="2">{{ $t->nombre_novacion ?? $t->nombre ?? '—' }}</td>
      <td class="r num">{{ $nf($t->importe_novacion) }} €</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2"></td>
      <td class="lbl">Subtotal</td>
      <td class="r">{{ $nf($totalGastos) }} €</td>
    </tr>
  </tfoot>
</table>

{{-- TOTALIZADOR --}}
<div class="total-wrap">
  <div class="total-space"></div>
  <div class="total-lbl">Total</div>
  <div class="total-val">{{ $nf($totalNeto) }} €</div>
</div>

</body>
</html>
