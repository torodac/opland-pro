<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$year_min = now()->year - 3;
$year_max = now()->year + 1;
@endphp

<style>
.km-inf-wrap  { display:flex; gap:16px; align-items:flex-start; }
.km-inf-left  { flex:0 0 170px; min-width:0; }
.km-inf-right { flex:1; min-width:0; }

.km-panel { background:#fff; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.07);
            padding:12px 10px; font-size:.78rem; margin-bottom:12px; }
.km-panel h6 { font-size:.78rem; font-weight:700; margin-bottom:8px; color:#444; }
.km-panel table { width:100%; border-collapse:collapse; font-size:.75rem; }
.km-panel table th { text-align:center; padding:2px 4px; font-weight:600; color:#555; border-bottom:1px solid #eee; }
.km-panel table td { text-align:center; padding:2px 4px; color:#333; border-bottom:1px solid #f5f5f5; }
.km-panel table td:first-child { text-align:left; font-weight:600; }
.km-panel table tr.total-row td { font-weight:700; border-top:1px solid #ddd; }
.km-total-box { background:#f0f4ff; border-radius:6px; padding:6px 8px; font-size:.75rem; color:#333; margin-top:6px; }

.tbl-km-inf { width:100%; border-collapse:collapse; font-size:.8rem; table-layout:fixed; }
.tbl-km-inf th { background:#f7f8fa; color:#555; font-weight:600; padding:5px 6px;
                 text-align:center; border:1px solid #e5e5e5; white-space:nowrap; }
.tbl-km-inf td { padding:3px 6px; text-align:center; border:1px solid #eeeff2; white-space:nowrap; }
.tbl-km-inf td:first-child { font-weight:700; }
.tbl-km-inf th.col-dia, .tbl-km-inf td.col-dia { width:52px; }
.tbl-km-inf th.col-km,  .tbl-km-inf td.col-km  { width:70px; }
.tbl-km-inf th.col-tray,.tbl-km-inf td.col-tray { width:auto; text-align:left; color:#555;
                                                   white-space:normal; font-size:.76rem; }
.tbl-km-inf td.km-val { font-weight:600; color:#185FA5; }
.tbl-km-inf tr.weekend td { background:#fafafa; color:#aaa; }
.tbl-km-inf tr.total-row td { font-weight:700; border-top:2px solid #ccc; background:#eef2ff; }

.firma-section { margin-top:24px; font-size:.85rem; color:#444; }
.firma-line    { border-bottom:1px solid #888; width:240px; height:40px; margin-bottom:4px; }
.nombre-nif    { font-size:.9rem; color:#333; }

.btn-pdf { background:#dc3545; color:#fff; border:none; border-radius:6px;
           padding:5px 14px; font-size:.83rem; cursor:pointer; text-decoration:none;
           display:inline-flex; align-items:center; gap:6px; }
.btn-pdf:hover { background:#b02a37; color:#fff; }
.btn-pdf-total { background:#7367f0; }
.btn-pdf-total:hover { background:#5a50d0; }

@media (max-width:768px) { .km-inf-wrap { flex-direction:column; } .km-inf-left { flex:none; width:100%; } }
</style>

{{-- Cabecera filtros --}}
<form method="GET" id="form-filtros" class="mb-4">
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.07);">

  <select name="year" onchange="document.getElementById('form-filtros').submit()"
          class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none">
    @for($y = $year_min; $y <= $year_max; $y++)
      <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
    @endfor
  </select>

  <select name="month" onchange="document.getElementById('form-filtros').submit()"
          class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none">
    @for($m = 1; $m <= 12; $m++)
      <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $meses_es[$m] }}</option>
    @endfor
  </select>

  @if($can_select)
  <select name="user_id" onchange="document.getElementById('form-filtros').submit()"
          class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none">
    @foreach($usuarios as $u)
      <option value="{{ $u->id }}" {{ $u->id == $user_id ? 'selected' : '' }}>{{ $u->nombre }}</option>
    @endforeach
  </select>
  @else
    <input type="hidden" name="user_id" value="{{ $user_id }}">
  @endif

  <a class="btn-pdf"
     href="{{ route('km.informe.pdf', $project->slug) }}?year={{ $year }}&month={{ $month }}&user_id={{ $user_id }}">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    Descargar PDF
  </a>

  @if($can_select)
  <a class="btn-pdf btn-pdf-total"
     href="{{ route('km.informe.pdf-todos', $project->slug) }}?year={{ $year }}&month={{ $month }}">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    Descargar todos
  </a>
  @endif

</div>
</form>

<div class="km-inf-wrap">

  {{-- Panel izquierdo: histórico km año --}}
  <div class="km-inf-left">
    <div class="km-panel">
      <h6>Km por mes {{ $year }}</h6>
      <table>
        <thead><tr><th>Mes</th><th>Km</th></tr></thead>
        <tbody>
          @php $totalAnyo = 0; @endphp
          @foreach($year_stats as $m => $s)
            @if($s['km'] > 0)
            @php $totalAnyo += $s['km']; @endphp
            <tr>
              <td>{{ $s['label'] }}</td>
              <td style="color:#185FA5;font-weight:600;">{{ number_format($s['km'], 2, ',', '') }}</td>
            </tr>
            @endif
          @endforeach
        </tbody>
        <tfoot>
          <tr class="total-row">
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

  {{-- Panel derecho: tabla días --}}
  <div class="km-inf-right">
    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.07);padding:14px;">
      <table class="tbl-km-inf">
        <colgroup>
          <col style="width:52px;">
          <col>
          <col style="width:80px;">
        </colgroup>
        <thead>
          <tr>
            <th class="col-dia">Día</th>
            <th class="col-tray">Trayecto</th>
            <th class="col-km">Km</th>
          </tr>
        </thead>
        <tbody>
          @foreach($dias as $dia)
          <tr class="{{ $dia['weekend'] ? 'weekend' : '' }}">
            <td class="col-dia">{{ $dia['dow'] }} {{ $dia['num'] }}</td>
            <td class="col-tray">{{ $dia['trayecto'] }}</td>
            <td class="col-km km-val">{{ $dia['km'] > 0 ? number_format($dia['km'], 2, ',', '') : '' }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td class="col-dia"></td>
            <td class="col-tray" style="text-align:right;font-weight:700;">Total</td>
            <td class="col-km km-val">{{ number_format($total_km, 2, ',', '') }} km</td>
          </tr>
        </tfoot>
      </table>

      <div class="firma-section">
        <strong>Firma:</strong>
        <div class="firma-line"></div>
        <div class="nombre-nif">
          {{ $usuario->nombre ?? '' }}
          @if(!empty($usuario->dni)) — NIF {{ $usuario->dni }} @endif
        </div>
      </div>
    </div>
  </div>

</div>

</x-app-layout>
