<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin:2.5cm 1cm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:DejaVu Sans,sans-serif; font-size:9pt; color:#222; padding:0.5cm 0.5cm 0; }

.pdf-header       { display:table; width:100%; margin-bottom:12pt; }
.pdf-header-left  { display:table-cell; vertical-align:middle; }
.pdf-title        { font-size:13pt; font-weight:bold; color:#333; }
.pdf-period       { font-size:9pt; color:#666; margin-top:2pt; }

.pdf-cols  { display:table; width:100%; }
.col-left  { display:table-cell; vertical-align:top; width:130pt; padding-right:10pt; }
.col-right { display:table-cell; vertical-align:top; }

.panel       { border:1pt solid #dde; border-radius:4pt; padding:6pt; margin-bottom:8pt; }
.panel-title { font-size:7.5pt; font-weight:bold; color:#444; margin-bottom:5pt;
               border-bottom:1pt solid #eee; padding-bottom:3pt; }

.tbl-small    { width:100%; border-collapse:collapse; font-size:7pt; }
.tbl-small th { text-align:center; padding:2pt 3pt; font-weight:bold;
                background:#f5f5f5; border-bottom:1pt solid #ddd; color:#555; }
.tbl-small td { text-align:center; padding:2pt 3pt; border-bottom:1pt solid #f0f0f0; }
.tbl-small td:first-child { text-align:left; font-weight:bold; }
.tbl-small tr.tot td { font-weight:bold; border-top:1pt solid #ccc; background:#f9f9f9; }

.km-total-box { background:#eef2ff; border-radius:3pt; padding:4pt 6pt; font-size:7pt;
                margin-top:4pt; border:1pt solid #ccd; }

.tbl-main    { width:100%; border-collapse:collapse; font-size:7.5pt; }
.tbl-main th { background:#f0f2f5; color:#444; font-weight:bold;
               padding:4pt 3pt; text-align:center; border:1pt solid #d5d8dc; }
.tbl-main td { padding:3pt 3pt; text-align:center; border:1pt solid #e8eaed; }
.tbl-main td.tray { text-align:left; color:#555; font-size:7pt; }
.tbl-main td.km-val { font-weight:bold; color:#185FA5; }
.tbl-main tr.wk td  { background:#fafafa; color:#bbb; }
.tbl-main tr.tot td { font-weight:bold; border-top:1pt solid #ccc; background:#eef2ff; }

.firma-sec  { margin-top:28pt; font-size:8pt; }
.firma-line { border-bottom:1pt solid #999; width:180pt; height:40pt; margin-top:10pt; margin-bottom:8pt; }
.nombre-nif { font-size:8.5pt; font-weight:bold; }
</style>
</head>
<body>
@php
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$totalAnyo = array_sum(array_column($year_stats, 'km'));
@endphp

<div class="pdf-header">
  <div class="pdf-header-left">
    <div class="pdf-title">Informe de Kilometraje</div>
    <div class="pdf-period">{{ $meses_es[$month] }} {{ $year }} &mdash; {{ $usuario->nombre ?? '' }}</div>
  </div>
</div>

<div class="pdf-cols">

  <div class="col-left">
    <div class="panel">
      <div class="panel-title">Km por mes {{ $year }}</div>
      <table class="tbl-small">
        <thead><tr><th>Mes</th><th>Km</th></tr></thead>
        <tbody>
          @foreach($year_stats as $m => $s)
            @if($s['km'] > 0)
            <tr>
              <td>{{ $s['label'] }}</td>
              <td style="color:#185FA5;">{{ number_format($s['km'], 2, ',', '') }}</td>
            </tr>
            @endif
          @endforeach
        </tbody>
        <tfoot>
          <tr class="tot">
            <td>Total</td>
            <td style="color:#185FA5;">{{ number_format($totalAnyo, 2, ',', '') }}</td>
          </tr>
        </tfoot>
      </table>
      <div class="km-total-box">
        Total {{ $year }}: <strong>{{ number_format($totalAnyo, 2, ',', '') }} km</strong>
      </div>
    </div>
  </div>

  <div class="col-right">
    <table class="tbl-main">
      <thead>
        <tr>
          <th width="12%">Día</th>
          <th width="74%">Trayecto</th>
          <th width="14%">Km</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dias as $dia)
        <tr class="{{ $dia['weekend'] ? 'wk' : '' }}">
          <td style="font-weight:bold;">{{ $dia['dow'] }} {{ $dia['num'] }}</td>
          <td class="tray">{{ $dia['trayecto'] }}</td>
          <td class="km-val">{{ $dia['km'] > 0 ? number_format($dia['km'], 2, ',', '') : '' }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr class="tot">
          <td></td>
          <td style="text-align:right;">Total</td>
          <td class="km-val">{{ number_format($total_km, 2, ',', '') }} km</td>
        </tr>
      </tfoot>
    </table>

    <div class="firma-sec">
      <strong>Firma:</strong>
      <div class="firma-line"></div>
      <div class="nombre-nif">
        {{ $usuario->nombre ?? '' }}
        @if(!empty($usuario->dni)) &nbsp; NIF {{ $usuario->dni }} @endif
      </div>
    </div>
  </div>

</div>
</body>
</html>
