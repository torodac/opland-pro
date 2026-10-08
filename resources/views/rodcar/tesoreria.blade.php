<x-app-layout :project="$project" :breadcrumb="[['label'=>'Informe tesorería','url'=>'']]">

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<style>
  .tes-year-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .tes-year-btn { padding:5px 16px; border-radius:20px; border:1.5px solid var(--border,#e5e7eb);
                  background:none; font-size:12px; cursor:pointer; color:#6b7280; transition:.15s; }
  .tes-year-btn.active { background:#3b82f6; color:#fff; border-color:#3b82f6; font-weight:700; }
  .tes-tit-btn { padding:5px 14px; border-radius:20px; border:1.5px solid var(--border,#e5e7eb);
                 background:none; font-size:12px; cursor:pointer; color:#6b7280; transition:.15s;
                 text-decoration:none; display:inline-block; }
  .tes-tit-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; font-weight:700; }
  .tes-tit-btn:hover:not(.active) { background:#f1f5f9; }

  .tes-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }
  .tes-kpi  { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
              border-radius:12px; padding:10px 14px; overflow:hidden; }
  .tes-kpi-label { font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;
                   letter-spacing:.05em; margin-bottom:6px; }
  .tes-kpi-value { font-size:17px; font-weight:800; line-height:1.1; }
  .tes-kpi.green { border-left:4px solid #10b981; }
  .tes-kpi.red   { border-left:4px solid #ef4444; }
  .tes-kpi.blue   { border-left:4px solid #3b82f6; }
  .tes-kpi.amber  { border-left:4px solid #f59e0b; }

  .tes-chart-card { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
                    border-radius:12px; padding:20px; margin-bottom:20px; }
  .tes-chart-card h3 { font-size:13px; font-weight:700; color:#374151; margin:0 0 14px; }
  .tes-chart-wrap { position:relative; height:240px; }

  .tes-tabla-wrap { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
                    border-radius:12px; overflow:auto; }
  .tes-tabla { width:100%; border-collapse:collapse; font-size:12px; }
  .tes-tabla th { background:#f8fafc; padding:7px 10px; text-align:right; font-size:11px;
                  font-weight:700; color:#64748b; border-bottom:2px solid var(--border,#e5e7eb);
                  white-space:nowrap; position:sticky; top:0; z-index:1; }
  .tes-tabla th:first-child { text-align:left; min-width:200px; left:0; z-index:2; }
  .tes-tabla td { padding:5px 10px; text-align:right; border-bottom:1px solid #f3f4f6;
                  white-space:nowrap; }
  .tes-tabla td:first-child { text-align:left; position:sticky; left:0;
                               background:var(--surface,#fff); z-index:1; }

  /* Nivel 1: Ingresos / Gastos */
  .tes-tr-ig td { font-weight:800; font-size:12px; text-transform:uppercase; letter-spacing:.06em;
                  padding:10px 10px; border-top:3px solid var(--border,#e5e7eb);
                  border-bottom:2px solid var(--border,#e5e7eb); }
  .tes-tr-ig.ing td { color:#059669; background:#f0fdf4; }
  .tes-tr-ig.ing td:first-child { background:#f0fdf4; }
  .tes-tr-ig.gas td { color:#dc2626; background:#fef2f2; }
  .tes-tr-ig.gas td:first-child { background:#fef2f2; }

  /* Nivel 2: categoría */
  .tes-tr-cat td { font-weight:700; font-size:11.5px; background:#f8fafc; color:#374151;
                   padding-left:14px; border-top:1px solid var(--border,#e5e7eb); }
  .tes-tr-cat td:first-child { background:#f8fafc; }

  /* Nivel 3: tipo (fila de detalle) */
  .tes-tr-tipo td { color:#6b7280; }
  .tes-tr-tipo td:first-child { padding-left:28px; }

  /* Subtotal categoría */
  .tes-tr-subcat td { font-weight:700; background:#f1f5f9; font-size:11.5px; }
  .tes-tr-subcat td:first-child { padding-left:14px; background:#f1f5f9; }

  /* Total ing/gas */
  .tes-tr-tot-ig td { font-weight:800; font-size:12.5px; border-top:2px solid var(--border,#e5e7eb); }
  .tes-tr-tot-ig.ing td { color:#059669; }
  .tes-tr-tot-ig.gas td { color:#dc2626; }

  /* Resultado neto */
  .tes-tr-resultado td { font-weight:800; font-size:13px;
                         border-top:3px double var(--border,#e5e7eb); }
  .tes-tr-resultado td:first-child { background:var(--surface,#fff); }
  .tes-tr-resultado.pos td { color:#3b82f6; }
  .tes-tr-resultado.neg td { color:#ef4444; }

  .tes-num-pos { color:#059669; }
  .tes-num-neg { color:#dc2626; }
  .tes-num-zer { color:#d1d5db; }
</style>

@php
$meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',
           7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

// Totales mensuales globales
$totMesIng = array_fill_keys(array_keys($meses), 0.0);
$totMesGas = array_fill_keys(array_keys($meses), 0.0);
foreach ($jerarquia['ingreso'] as $cat => $tipos) {
    foreach ($tipos as $tipo => $vals) {
        foreach ($vals as $m => $v) { $totMesIng[$m] += $v; }
    }
}
foreach ($jerarquia['gasto'] as $cat => $tipos) {
    foreach ($tipos as $tipo => $vals) {
        foreach ($vals as $m => $v) { $totMesGas[$m] += abs($v); }
    }
}
$totIng = array_sum($totMesIng);
$totGas = array_sum($totMesGas);
$resultado = $totIng - $totGas;

// JSON para gráfico
$chartLabels = array_values($meses);
$chartIng    = array_values($totMesIng);
$chartGas    = array_map(fn($v) => -abs($v), array_values($totMesGas));
$chartRes    = array_map(fn($i,$g) => $i + $g, $chartIng, $chartGas);

function fmtNum(float $v, bool $sign=false): string {
    $f = number_format(abs($v), 0, ',', '.');
    if ($sign) return ($v >= 0 ? '+' : '-') . $f . ' €';
    return $f . ' €';
}
function clasNum(float $v): string {
    if ($v == 0) return 'tes-num-zer';
    return $v > 0 ? 'tes-num-pos' : 'tes-num-neg';
}
@endphp

{{-- Selector de año --}}
<div class="tes-year-row">
  <span style="font-size:11px;color:#9ca3af;font-weight:700;letter-spacing:.05em">AÑO</span>
  @foreach($years as $y)
    <a href="{{ route('rodcar.tesoreria', array_filter(['project'=>$project->slug,'year'=>$y,'titular'=>$titular])) }}"
       class="tes-year-btn {{ $y==$year ? 'active' : '' }}">{{ $y }}</a>
  @endforeach
</div>

{{-- Filtro titular --}}
<div class="tes-year-row" style="margin-top:-10px">
  <span style="font-size:11px;color:#9ca3af;font-weight:700;letter-spacing:.05em">TITULAR</span>
  <a href="{{ route('rodcar.tesoreria', array_filter(['project'=>$project->slug,'year'=>$year])) }}"
     class="tes-tit-btn {{ !$titular ? 'active' : '' }}">Todos</a>
  @foreach($titulares as $t)
    <a href="{{ route('rodcar.tesoreria', array_filter(['project'=>$project->slug,'year'=>$year,'titular'=>$t])) }}"
       class="tes-tit-btn {{ $titular===$t ? 'active' : '' }}">{{ $t }}</a>
  @endforeach
</div>

{{-- Filtro tipo --}}
<div class="tes-year-row" style="margin-top:-10px">
  <span style="font-size:11px;color:#9ca3af;font-weight:700;letter-spacing:.05em">TIPO</span>
  <a href="{{ route('rodcar.tesoreria', array_filter(['project'=>$project->slug,'year'=>$year,'titular'=>$titular])) }}"
     style="padding:4px 13px;border-radius:20px;border:1.5px solid {{ !$tipo ? '#10b981' : '#e5e7eb' }};background:{{ !$tipo ? '#10b981' : 'none' }};color:{{ !$tipo ? '#fff' : '#6b7280' }};font-size:12px;font-weight:{{ !$tipo ? '700' : '400' }};text-decoration:none">Todos</a>
  @foreach($tiposFilter as $tp)
    <a href="{{ route('rodcar.tesoreria', array_filter(['project'=>$project->slug,'year'=>$year,'titular'=>$titular,'tipo'=>$tp])) }}"
       style="padding:4px 13px;border-radius:20px;border:1.5px solid {{ $tipo===$tp ? '#10b981' : '#e5e7eb' }};background:{{ $tipo===$tp ? '#10b981' : 'none' }};color:{{ $tipo===$tp ? '#fff' : '#6b7280' }};font-size:12px;font-weight:{{ $tipo===$tp ? '700' : '400' }};text-decoration:none">{{ $tp }}</a>
  @endforeach
</div>

{{-- KPIs --}}
<div class="tes-kpis">
  <div class="tes-kpi green">
    <div class="tes-kpi-label">Total ingresos</div>
    <div class="tes-kpi-value">{{ fmtNum($totIng) }}</div>
  </div>
  <div class="tes-kpi red">
    <div class="tes-kpi-label">Total gastos</div>
    <div class="tes-kpi-value">{{ fmtNum($totGas) }}</div>
  </div>
  <div class="tes-kpi {{ $resultado >= 0 ? 'blue' : 'red' }}">
    <div class="tes-kpi-label">Resultado neto</div>
    <div class="tes-kpi-value" style="color:{{ $resultado >= 0 ? '#3b82f6' : '#ef4444' }}">{{ fmtNum($resultado, true) }}</div>
  </div>
  @php $pn = (int)$pendientesStats->n; @endphp
  <div class="tes-kpi amber" title="Movimientos del año {{ $year }} sin tipo confirmado">
    <div class="tes-kpi-label">Pendientes validar</div>
    <div class="tes-kpi-value" style="color:#b45309">{{ $pn }}</div>
    <div style="margin-top:3px;font-size:10px;display:flex;gap:8px;flex-wrap:wrap">
      <span style="color:#10b981;font-weight:600" title="Suma importes positivos">▲ {{ fmtNum((float)$pendientesStats->pos) }}</span>
      <span style="color:#ef4444;font-weight:600" title="Suma importes negativos">▼ {{ fmtNum((float)$pendientesStats->neg) }}</span>
    </div>
  </div>
</div>

{{-- Gráfico mensual --}}
<div class="tes-chart-card">
  <h3>Evolución mensual {{ $year }}</h3>
  <div class="tes-chart-wrap"><canvas id="chart-tesoreria"></canvas></div>
</div>

{{-- Tabla jerárquica --}}
<div class="tes-tabla-wrap">
<table class="tes-tabla">
  <thead>
    <tr>
      <th>Concepto</th>
      @foreach($meses as $m => $ml)<th>{{ $ml }}</th>@endforeach
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
  @foreach(['ingreso','gasto'] as $ig)
    @php
      $igLabel  = $ig === 'ingreso' ? 'Ingresos' : 'Gastos';
      $igClass  = $ig === 'ingreso' ? 'ing' : 'gas';
      $totMesIG = array_fill_keys(array_keys($meses), 0.0);
      foreach ($jerarquia[$ig] as $cat => $tipos) {
          foreach ($tipos as $tipo => $vals) {
              foreach ($vals as $m => $v) { $totMesIG[$m] += abs($v); }
          }
      }
      $totIG = array_sum($totMesIG);
    @endphp
    @if(!empty($jerarquia[$ig]))
    {{-- Cabecera Ingresos / Gastos --}}
    <tr class="tes-tr-ig {{ $igClass }}">
      <td>{{ $igLabel }}</td>
      @foreach($meses as $m => $ml)
        <td>{{ $totMesIG[$m] > 0 ? fmtNum($totMesIG[$m]) : '' }}</td>
      @endforeach
      <td>{{ fmtNum($totIG) }}</td>
    </tr>
    @foreach($jerarquia[$ig] as $cat => $tipos)
      @php
        $totMesCat = array_fill_keys(array_keys($meses), 0.0);
        foreach ($tipos as $tipo => $vals) {
            foreach ($vals as $m => $v) { $totMesCat[$m] += abs($v); }
        }
        $totCat = array_sum($totMesCat);
      @endphp
      {{-- Categoría --}}
      <tr class="tes-tr-cat">
        <td>{{ $cat }}</td>
        @foreach($meses as $m => $ml)
          <td class="{{ $totMesCat[$m] > 0 ? ($ig==='ingreso'?'tes-num-pos':'tes-num-neg') : 'tes-num-zer' }}">
            {{ $totMesCat[$m] > 0 ? fmtNum($totMesCat[$m]) : '—' }}
          </td>
        @endforeach
        <td>{{ fmtNum($totCat) }}</td>
      </tr>
      {{-- Tipos --}}
      @foreach($tipos as $tipo => $vals)
        @php $totTipo = array_sum(array_map('abs', $vals)); @endphp
        <tr class="tes-tr-tipo">
          <td>{{ $tipo }}</td>
          @foreach($meses as $m => $ml)
            @php $v = abs($vals[$m] ?? 0); @endphp
            <td class="{{ $v > 0 ? ($ig==='ingreso'?'tes-num-pos':'tes-num-neg') : 'tes-num-zer' }}">
              {{ $v > 0 ? fmtNum($v) : '—' }}
            </td>
          @endforeach
          <td>{{ fmtNum($totTipo) }}</td>
        </tr>
      @endforeach
      {{-- Subtotal categoría (solo si hay >1 tipo) --}}
      @if(count($tipos) > 1)
      <tr class="tes-tr-subcat">
        <td style="color:{{ $ig==='ingreso'?'#059669':'#dc2626' }}">Subtotal {{ $cat }}</td>
        @foreach($meses as $m => $ml)
          <td style="color:{{ $ig==='ingreso'?'#059669':'#dc2626' }}">
            {{ $totMesCat[$m] > 0 ? fmtNum($totMesCat[$m]) : '' }}
          </td>
        @endforeach
        <td style="color:{{ $ig==='ingreso'?'#059669':'#dc2626' }}">{{ fmtNum($totCat) }}</td>
      </tr>
      @endif
    @endforeach
    {{-- Total Ingresos / Gastos --}}
    <tr class="tes-tr-tot-ig {{ $igClass }}">
      <td>Total {{ $igLabel }}</td>
      @foreach($meses as $m => $ml)
        <td>{{ $totMesIG[$m] > 0 ? fmtNum($totMesIG[$m]) : '' }}</td>
      @endforeach
      <td>{{ fmtNum($totIG) }}</td>
    </tr>
    @endif
  @endforeach

  {{-- Resultado neto --}}
  @php
    $totMesRes = [];
    foreach ($meses as $m => $ml) {
        $totMesRes[$m] = ($totMesIng[$m] ?? 0) - ($totMesGas[$m] ?? 0);
    }
    $resClass = $resultado >= 0 ? 'pos' : 'neg';
  @endphp
  <tr class="tes-tr-resultado {{ $resClass }}">
    <td>Resultado neto</td>
    @foreach($meses as $m => $ml)
      @php $rv = $totMesRes[$m]; @endphp
      <td>{{ $rv != 0 ? fmtNum($rv, true) : '' }}</td>
    @endforeach
    <td>{{ fmtNum($resultado, true) }}</td>
  </tr>
  </tbody>
</table>
</div>

<script>
(function() {
  const labels = @json($chartLabels);
  const ing    = @json($chartIng);
  const gas    = @json($chartGas);
  const res    = @json($chartRes);

  const fmt = v => new Intl.NumberFormat('es-ES',{maximumFractionDigits:0}).format(Math.abs(v)) + ' €';

  new Chart(document.getElementById('chart-tesoreria').getContext('2d'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label:'Ingresos', data:ing, backgroundColor:'#10b98166', borderColor:'#10b981',
          borderWidth:1.5, borderRadius:4, borderSkipped:false, stack:'main' },
        { label:'Gastos',   data:gas, backgroundColor:'#ef444466', borderColor:'#ef4444',
          borderWidth:1.5, borderRadius:4, borderSkipped:false, stack:'main' },
        { label:'Resultado', data:res, type:'line',
          borderColor:'#3b82f6', backgroundColor:'transparent',
          pointBackgroundColor: res.map(v=>v>=0?'#3b82f6':'#ef4444'),
          pointRadius:5, borderWidth:2.5, tension:.3 },
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins: {
        legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},
        tooltip:{callbacks:{label(c){return c.dataset.label+': '+fmt(c.parsed.y);}}}
      },
      scales: {
        x:{grid:{display:false}},
        y:{grid:{color:'#f1f5f9'},
           ticks:{callback(v){return fmt(Math.abs(v));},font:{size:10}}}
      }
    }
  });
})();
</script>

</x-app-layout>
