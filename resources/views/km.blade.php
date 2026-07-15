<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@include('partials.role-badge', ['project' => $project, 'texto' => 'Solo los administradores pueden consultar el kilometraje de otro usuario; el resto del equipo solo ve el suyo.'])

@php
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dowLabel = fn($ds) => ['D','L','M','X','J','V','S'][(int)date('w', strtotime($ds))];
$isWk     = fn($ds) => in_array($dowLabel($ds), ['D','S']);
$fmtKm    = fn($v) => $v > 0 ? number_format($v, 2, ',', '') : '';
@endphp

<style>
.km-card { background:#fff; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.07); padding:14px 16px; margin-bottom:16px; }
.km-form { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.km-form label { font-size:.83rem; color:#555; }
.km-form input[type=date] { font-size:.83rem; border:1px solid #e2e8f0; border-radius:6px; padding:5px 10px; }
.km-form button { background:#185FA5; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:.83rem; cursor:pointer; }
.km-form button:hover { background:#154e8c; }

.tbl-km { width:100%; border-collapse:collapse; font-size:.78rem; }
.tbl-km th { background:#f0f2f5; color:#444; font-weight:600; padding:5px 6px; text-align:center;
             border:1px solid #d5d8dc; white-space:nowrap; position:sticky; top:0; z-index:1; }
.tbl-km th.col-user { text-align:left; min-width:130px; }
.tbl-km td { padding:4px 5px; text-align:center; border:1px solid #e8eaed; white-space:nowrap; }
.tbl-km td.col-user { text-align:left; font-weight:500; color:#333; }
.tbl-km tr.wk td { background:#fafafa; color:#aaa; }
.tbl-km th.wk-h { color:#bbb; }
.tbl-km tr.total-row td { font-weight:700; background:#f7f8fa; border-top:2px solid #ccc; }
.tbl-km td.has-km { color:#185FA5; font-weight:600; }
.tbl-km td.total-col { font-weight:700; background:#eef2ff; color:#333; }
.tbl-km th.total-col { background:#dde3f5; }
.tbl-scroll { overflow-x:auto; }
</style>

<div class="km-card">
  <form method="GET" class="km-form">
    <label>Desde</label>
    <input type="date" name="desde" value="{{ $desde }}" max="{{ date('Y-m-d') }}">
    <label>Hasta</label>
    <input type="date" name="hasta" value="{{ $hasta }}" max="{{ date('Y-m-d') }}">
    <button type="submit">Consultar</button>
  </form>
</div>

@if($usuarios->isEmpty())
  <div class="km-card" style="color:#888;">Sin registros de kilometraje en el período seleccionado.</div>
@else
<div class="km-card">
  <div style="font-size:.8rem; color:#666; margin-bottom:8px;">
    {{ $usuarios->count() }} empleado(s) con km &bull; {{ count($dias) }} días &bull; Total: <strong>{{ number_format($totalGeneral, 2, ',', '') }} km</strong>
  </div>
  <div class="tbl-scroll">
  <table class="tbl-km">
    <thead>
      <tr>
        <th class="col-user">Empleado</th>
        @foreach($dias as $d)
          @php $w = $isWk($d); $dow = $dowLabel($d); $num = (int)substr($d, 8, 2); @endphp
          <th class="{{ $w ? 'wk-h' : '' }}">{{ $dow }}<br>{{ $num }}</th>
        @endforeach
        <th class="total-col">Total</th>
      </tr>
    </thead>

    <tbody>
      @foreach($usuarios as $u)
      <tr>
        <td class="col-user">{{ $u->nombre }}</td>
        @foreach($dias as $d)
          @php $km = $kmMap[$u->id][$d]['km'] ?? 0; $w = $isWk($d); @endphp
          @php $trayecto = $km > 0 ? ($kmMap[$u->id][$d]['trayecto'] ?? '') : ''; @endphp
          <td class="{{ $w ? '' : '' }}{{ $km > 0 ? ' has-km' : '' }}">
            @if($trayecto)
                <span class="app-tooltip">{{ $fmtKm($km) }}<span class="app-tooltip-box">{{ $trayecto }}</span></span>
            @else
                {{ $fmtKm($km) }}
            @endif
          </td>
        @endforeach
        <td class="total-col">{{ number_format($totalUsuario[$u->id], 2, ',', '') }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td class="col-user">Total</td>
        @foreach($dias as $d)
          <td></td>
        @endforeach
        <td class="total-col">{{ number_format($totalGeneral, 2, ',', '') }} km</td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>
@endif

</x-app-layout>
