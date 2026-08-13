<x-app-layout :breadcrumb="[['label'=>'Dashboard','url'=>route('nf.dashboard',[$project->slug])]]" :project="$project">

<div id="nf-dashboard">

<style>
  #nf-dashboard .ejercicio-nav { display:flex;align-items:center;gap:14px;margin-bottom:18px; }
  #nf-dashboard .ejercicio-nav a { display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:.5px solid rgba(0,0,0,.12);color:#555;background:#fff;text-decoration:none;flex-shrink:0; }
  #nf-dashboard .ejercicio-nav a:hover { background:rgba(0,0,0,.045); }
  #nf-dashboard .ejercicio-label { font-weight:600;font-size:17px;min-width:140px;text-align:center; }

  #nf-dashboard .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.1rem;margin-bottom:14px; }
  #nf-dashboard .sec-title { font-weight:600;font-size:14.5px;margin:0 0 12px; }

  #nf-dashboard table.matriz { width:100%;border-collapse:collapse; }
  #nf-dashboard table.matriz th { text-align:left;padding:8px 10px;font-size:11px;color:#888;font-weight:500;border-bottom:.5px solid rgba(0,0,0,.08); }
  #nf-dashboard table.matriz th.num, #nf-dashboard table.matriz td.num { text-align:center; }
  #nf-dashboard table.matriz td { padding:10px;font-size:14px;vertical-align:middle; }
  #nf-dashboard table.matriz tr.trow { border-top:.5px solid rgba(0,0,0,.06); }
  #nf-dashboard .badge { font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap;display:inline-block; }
  #nf-dashboard .num-value { font-weight:700;font-size:16px; }
  #nf-dashboard .num-value.zero { color:#bbb;font-weight:500; }
  #nf-dashboard .stats-row { display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap; }
  #nf-dashboard .stat-pill { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:10px;padding:8px 14px;font-size:12.5px;color:#888;display:flex;align-items:center;gap:6px; }
  #nf-dashboard .stat-pill b { color:#1B1B18;font-size:14px; }
</style>

<div class="ejercicio-nav">
    <a href="{{ route('nf.dashboard', [$project->slug, $ejercicioAnterior]) }}" title="Ejercicio anterior">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="ejercicio-label">{{ $ejercicioLabel }}</span>
    <a href="{{ route('nf.dashboard', [$project->slug, $ejercicioSiguiente]) }}" title="Ejercicio siguiente">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

<div class="stats-row">
    <div class="stat-pill">Contratos Fitness activos hoy: <b>{{ $totalContratos }}</b></div>
</div>

<div class="section-card">
    <p class="sec-title">Contratos activos hoy por grupo y día</p>
    <table class="matriz">
        <thead><tr>
            <th>Grupo</th>
            <th class="num">Día 1</th>
            <th class="num">Día 2</th>
        </tr></thead>
        <tbody>
            @forelse($matriz as $fila)
            <tr class="trow">
                <td>
                    @if(isset($colorGrupo[$fila['nombre']]))
                        <span class="badge" style="background:{{ $colorGrupo[$fila['nombre']] }};color:#fff;">{{ $fila['nombre'] }}</span>
                    @else
                        <span class="badge" style="background:#F1EFE8;color:#5F5E5A;">{{ $fila['nombre'] }}</span>
                    @endif
                </td>
                <td class="num"><span class="num-value {{ $fila['dia1'] === 0 ? 'zero' : '' }}">{{ $fila['dia1'] }}</span></td>
                <td class="num"><span class="num-value {{ $fila['dia2'] === 0 ? 'zero' : '' }}">{{ $fila['dia2'] }}</span></td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center;color:#aaa;padding:1.5rem 0;">No hay grupos de Fitness configurados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</div>

</x-app-layout>
