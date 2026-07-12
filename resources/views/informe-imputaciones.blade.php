@php use App\Http\Controllers\Vm\InformeImputacionesController as IC; @endphp

<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
$tipo_color = [
    'Asuntos propios' => '#34c163',
    'Baja'            => '#7b3f8c',
    'Compensación'    => '#e83e8c',
    'Revisar'         => '#fd7e14',
    'Vacaciones'      => '#e8b800',
    'Absentismo'      => '#dc3545',
];
$color_trabajo = '#74aaf8';

function tipoColor($nombre, $map) {
    if (isset($map[$nombre])) return $map[$nombre];
    $n = mb_strtolower($nombre);
    if (str_starts_with($n, 'comp')) return '#e83e8c';
    if (str_contains($n, 'vacac'))  return '#e8b800';
    if (str_contains($n, 'baja'))   return '#7b3f8c';
    if (str_contains($n, 'asunto')) return '#34c163';
    return '#888';
}

$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$year_min = now()->year - 3;
$year_max = now()->year + 1;

$sum_ep = array_sum(array_column($year_stats, 'ep'));
$sum_en = array_sum(array_column($year_stats, 'en'));
$sum_et = array_sum(array_column($year_stats, 'total'));
@endphp

<style>
.informe-wrap  { display:flex; gap:16px; align-items:flex-start; }
.informe-left  { flex:0 0 210px; min-width:0; }
.informe-right { flex:1; min-width:0; }

.informe-panel {
    background:#fff; border-radius:8px;
    box-shadow:0 1px 6px rgba(0,0,0,.07);
    padding:12px 10px; font-size:.78rem; margin-bottom:12px;
}
.informe-panel h6 { font-size:.78rem; font-weight:700; margin-bottom:8px; color:#444; }
.leyenda-item  { display:flex; align-items:center; gap:6px; margin-bottom:5px; }
.leyenda-dot   { width:12px; height:12px; border-radius:3px; flex-shrink:0; }
.informe-panel table { width:100%; border-collapse:collapse; font-size:.75rem; }
.informe-panel table th { text-align:center; padding:2px 4px; font-weight:600; color:#555; border-bottom:1px solid #eee; }
.informe-panel table td { text-align:center; padding:2px 4px; color:#333; border-bottom:1px solid #f5f5f5; }
.informe-panel table td:first-child { text-align:left; font-weight:600; }
.informe-panel table tr.total-row td { font-weight:700; border-top:1px solid #ddd; }
.saldo-box { background:#f0f4ff; border-radius:6px; padding:6px 8px; font-size:.75rem; color:#333; margin-top:4px; }

.tbl-fichaje { width:100%; border-collapse:collapse; font-size:.8rem; }
.tbl-fichaje th {
    background:#f7f8fa; color:#555; font-weight:600;
    padding:5px 6px; text-align:center; border:1px solid #e5e5e5; white-space:nowrap;
}
.tbl-fichaje td { padding:3px 6px; text-align:center; border:1px solid #eeeff2; white-space:nowrap; }
.tbl-fichaje td:first-child { font-weight:700; }
.tbl-fichaje tr.weekend td { background:#fafafa; color:#aaa; }
.tbl-fichaje tr.weekend td:first-child { color:#bbb; }
.tbl-fichaje .tipo-badge {
    display:inline-block; padding:1px 6px; border-radius:10px;
    font-size:.7rem; font-weight:600; color:#fff;
}
.he-pos { color:#28a745; font-weight:600; }
.he-neg { color:#dc3545; font-weight:600; }

.firma-section { margin-top:24px; font-size:.85rem; color:#444; }
.firma-line    { border-bottom:1px solid #888; width:240px; height:40px; margin-bottom:4px; }
.nombre-nif    { text-align:center; margin-top:18px; font-size:.9rem; color:#333; }

.btn-pdf { background:#dc3545; color:#fff; border:none; border-radius:6px;
           padding:5px 14px; font-size:.83rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
.btn-pdf:hover { background:#b02a37; color:#fff; }
.btn-pdf-total { background:#7367f0; }
.btn-pdf-total:hover { background:#5a50d0; }

@media (max-width:768px) {
    .informe-wrap { flex-direction:column; }
    .informe-left { flex:none; width:100%; }
}
</style>

{{-- Cabecera con filtros --}}
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

    <button type="button" class="btn-pdf"
        onclick="descargarPdf('{{ route('informe-imputaciones.pdf', $project->slug) }}?year={{ $year }}&month={{ $month }}&user_id={{ $user_id }}', 'informe_{{ $year }}_{{ $month }}.pdf')">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Descargar PDF
    </button>

    @if($can_select)
    <button type="button" class="btn-pdf btn-pdf-total"
        onclick="descargarPdf('{{ route('informe-imputaciones.pdf-todos', $project->slug) }}?year={{ $year }}&month={{ $month }}', 'informes_{{ $year }}_{{ $month }}.pdf')">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Descargar todos
    </button>

    <a href="{{ route('vm.usuario_form', [$project->slug, $user_id]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Ficha usuario
    </a>
    @endif

</div>
</form>

@if(!empty($ajustes_anio) && $ajustes_anio->isNotEmpty())
<div style="margin-bottom:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;">
  <div style="font-size:12px;font-weight:600;color:#92400e;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
    Ajustes manuales {{ $year }}
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:6px;">
    @foreach($ajustes_anio as $aj)
    @php
      $absMin = abs($aj->ajuste_he);
      $ajD = intdiv($absMin, 1440); $ajH = intdiv($absMin % 1440, 60); $ajM = $absMin % 60;
      $ajParts = []; if ($ajD) $ajParts[] = $ajD.'d'; if ($ajH||$ajD) $ajParts[] = $ajH.'h'; $ajParts[] = str_pad($ajM,2,'0',STR_PAD_LEFT).'m';
      $ajHuman = ($aj->ajuste_he > 0 ? '+' : '-').implode(' ',$ajParts);
    @endphp
    <a href="{{ route('vm.fichaje_form', [$project->slug, $aj->id]) }}" target="_blank"
       style="display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #fcd34d;border-radius:6px;padding:3px 8px;font-size:11px;color:#78350f;text-decoration:none;white-space:nowrap;">
      <span style="color:#999;">{{ \Carbon\Carbon::parse($aj->fecha_fichaje)->locale('es')->isoFormat('D/MMM') }}</span>
      <span style="font-weight:600;color:{{ $aj->ajuste_he > 0 ? '#16a34a' : '#dc2626' }}">{{ $ajHuman }}</span>
      @if($aj->ajuste_he_motivo)
      <span>· {{ Str::limit($aj->ajuste_he_motivo, 30) }}</span>
      @endif
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
    </a>
    @endforeach
  </div>
</div>
@endif

<div class="informe-wrap">

    {{-- Panel izquierdo --}}
    <div class="informe-left">

        {{-- Leyenda --}}
        <div class="informe-panel">
            <h6>Leyenda</h6>
            @foreach($tipos as $t)
                @php $tn = mb_strtolower($t->nombre); if (str_starts_with($tn, 'comp. ')) continue; @endphp
                <div class="leyenda-item">
                    <span class="leyenda-dot" style="background:{{ tipoColor($t->nombre, $tipo_color) }}"></span>
                    <span>{{ $t->nombre }}</span>
                </div>
            @endforeach
            <div class="leyenda-item">
                <span class="leyenda-dot" style="background:{{ $color_trabajo }}"></span>
                <span>Trabajo</span>
            </div>
        </div>

        {{-- Sigma horas extra año --}}
        @if(!$is_liquidado)
        <div class="informe-panel">
            <h6>&#931; horas extra {{ $year }}</h6>
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th style="color:#28a745">&#9650;</th>
                        <th style="color:#dc3545">&#9660;</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <tr style="color:#aaa">
                    <td>{{ $year - 1 }}</td>
                    <td></td>
                    <td></td>
                    <td>{{ $saldo_prev_year != 0 ? number_format($saldo_prev_year, 1, ',', '') : '0,0' }}</td>
                </tr>
                @php $running = $saldo_prev_year; @endphp
                @foreach($year_stats as $m => $s)
                @php $running += $s['total']; @endphp
                <tr>
                    <td>{{ $s['label'] }}</td>
                    <td style="color:#28a745">{{ $s['ep'] != 0 ? number_format($s['ep'],1,',','') : '' }}</td>
                    <td style="color:#dc3545">{{ $s['en'] != 0 ? number_format($s['en'],1,',','') : '' }}</td>
                    <td style="{{ $running >= 0 ? 'color:#28a745' : 'color:#dc3545' }}"
                        title="{{ ($s['has_ajuste'] ?? false) ? 'Incluye ajuste manual' : '' }}">
                        {{ number_format($running,1,',','') }}{{ ($s['has_ajuste'] ?? false) ? '*' : '' }}
                    </td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>&#931;</td>
                        <td style="color:#28a745">{{ number_format($sum_ep,1,',','') }}</td>
                        <td style="color:#dc3545">{{ number_format($sum_en,1,',','') }}</td>
                        <td style="{{ $sum_et >= 0 ? 'color:#28a745' : 'color:#dc3545' }}">{{ number_format($sum_et,1,',','') }}</td>
                    </tr>
                </tfoot>
            </table>
            <div class="saldo-box mt-2">
                Saldo histórico: <strong>{{ IC::fmtHoras($hist_extras, true) ?: '0h 00m' }}</strong>
            </div>
            @if(!empty($sin_contrato) && $sin_contrato)
            <p style="font-size:11px;color:#374151;margin:6px 0 0;">Horas extras compensadas en la liquidación.</p>
            @endif
        </div>
        @else
        <div class="informe-panel">
            <h6>Saldo</h6>
            <div class="saldo-box">
                Saldo h. extra: <strong>0h 00m</strong><br>
                @if($liquidado_fecha)
                    <small style="color:#888">Liquidado a {{ \Carbon\Carbon::parse($liquidado_fecha)->format('d/m/Y') }}</small>
                @endif
            </div>
        </div>
        @endif

        {{-- Registro días año --}}
        @if(!$is_liquidado)
        <div class="informe-panel">
            <h6>Registro días {{ $year }}</h6>
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th title="Trabajado"       style="color:#4e8ef7">T</th>
                        <th title="Compensado"      style="color:#f0960a">C</th>
                        <th title="Vacaciones"      style="color:#e8b800">V</th>
                        <th title="Baja"            style="color:#7b3f8c">B</th>
                        <th title="Asuntos propios" style="color:#34c163">AP</th>
                        <th>&#931;</th>
                        <th title="Días laborables" style="color:#999">Lab.</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($year_stats as $m => $s)
                    @if($s['total_dias'] > 0)
                    <tr>
                        <td>{{ $s['label'] }}</td>
                        <td>{{ $s['dias_col']['T'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['C'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['V'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['B'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['AA'] ?: '' }}</td>
                        <td>{{ $s['total_dias'] }}</td>
                        <td style="color:#999">{{ $s['lab'] }}</td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>&#931;</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['T'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['C'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['V'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['B'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['AA'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['total_dias'], $year_stats)) }}</td>
                        <td style="color:#999">{{ array_sum(array_map(fn($s) => $s['lab'], $year_stats)) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if(!empty($sin_contrato) && $sin_contrato)
            <p style="font-size:11px;color:#374151;margin:6px 0 0;">Días de vacaciones saldados en la liquidación.</p>
            @endif
        </div>
        @endif

    </div>{{-- /informe-left --}}

    {{-- Panel derecho --}}
    <div class="informe-right">
        <div style="background:#fff;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.07);padding:14px;">
            <table class="tbl-fichaje">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Jornada</th>
                        <th>Pausa</th>
                        <th>H. Extras</th>
                        <th>H. reales</th>
                        <th>H. Tareas</th>
                        <th>Km</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($dias as $dia)
                @php
                    $badges = [];
                    if ($dia['is_rotatorio'])       $badges[] = ['Desc. Fest.','#6f42c1'];
                    elseif ($dia['is_fest_trab'])   $badges[] = ['Trab. fest.','#0d6efd'];
                    elseif ($dia['tipo'])            $badges[] = [$dia['tipo']->nombre, tipoColor($dia['tipo']->nombre, $tipo_color)];
                    elseif ($dia['entrada'])         $badges[] = ['Trabajo', $color_trabajo];
                    if ($dia['horario_tipo'] === 'descanso') $badges[] = ['Descanso', '#F3F4F6', '#6B7280'];
                    $conflicto = count($badges) > 1;
                @endphp
                <tr class="{{ $dia['weekend'] ? 'weekend' : '' }}" @if($conflicto) style="background:#ffff00;" @endif>
                    <td style="font-weight:bold{{ $dia['is_festivo'] ? ';background:#ffe0e0;color:#cc0000' : '' }}">
                        {{ $dia['dow'] }} {{ $dia['num'] }}
                    </td>
                    <td>{{ $dia['entrada'] ?? '' }}</td>
                    <td>{{ $dia['salida'] ?? '' }}</td>
                    <td>{{ $dia['tf_min'] !== null ? IC::fmtMin($dia['tf_min']) : '' }}</td>
                    <td @if($dia['pausa_resaltada']) style="font-weight:700;color:#4e8ef7" @endif>
                        {{ $dia['p_min'] !== null && $dia['p_min'] > 0 ? $dia['p_min']."'" : '' }}
                    </td>
                    <td class="{{ ($dia['he_min'] ?? 0) > 0 ? 'he-pos' : (($dia['he_min'] ?? 0) < 0 ? 'he-neg' : '') }}"
                        title="{{ ($dia['ajuste_he'] ?? 0) != 0 ? 'Incluye ajuste manual: '.($dia['ajuste_he'] > 0 ? '+' : '').$dia['ajuste_he'].' min' : '' }}">
                        {{ $dia['he_min'] !== null ? IC::fmtMin($dia['he_min'], true) : '' }}{{ ($dia['ajuste_he'] ?? 0) != 0 ? '*' : '' }}
                    </td>
                    @php $efMin = ($dia['tf_min'] !== null && $dia['p_min'] !== null) ? $dia['tf_min'] - $dia['p_min'] : $dia['tf_min']; @endphp
                    <td>{{ $efMin !== null ? IC::fmtMin($efMin) : '' }}</td>
                    <td>{{ $dia['ht_min'] > 0 ? IC::fmtMin($dia['ht_min']) : '' }}</td>
                    <td>{{ $dia['km'] !== null && $dia['km'] > 0 ? number_format($dia['km'], 1, ',', '') : ($dia['entrada'] ? '0,0' : '') }}</td>
                    <td>
                        <span style="display:flex;flex-direction:column;gap:2px;align-items:center;">
                        @foreach($badges as $badge)
                            <span class="tipo-badge" style="background:{{ $badge[1] }};color:{{ $badge[2] ?? '#fff' }}">{{ $badge[0] }}</span>
                        @endforeach
                        </span>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>

            <div class="firma-section">
                <strong>Firma:</strong>
                <div class="firma-line"></div>
            </div>
            <div class="nombre-nif">
                <strong>{{ $usuario->nombre ?? '' }}</strong>
                @if(!empty($usuario->dni))
                    con NIF {{ $usuario->dni }}
                @endif
            </div>
        </div>
    </div>

</div>{{-- /informe-wrap --}}



<div id="pdf-loading-overlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:36px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <svg style="width:48px;height:48px;margin-bottom:16px;animation:pdf-spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" stroke="#e5e7eb" stroke-width="3"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke="#7367f0" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <div style="font-size:16px;font-weight:600;color:#333;margin-bottom:6px;">Generando PDF</div>
        <div style="font-size:13px;color:#666;">Esto puede tardar unos segundos&hellip;</div>
    </div>
</div>
<style>@keyframes pdf-spin { to { transform: rotate(360deg); } }</style>
<script>
function descargarPdf(url, filename) {
    const overlay = document.getElementById('pdf-loading-overlay');
    overlay.style.display = 'flex';
    fetch(url)
        .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.blob(); })
        .then(blob => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        })
        .catch(e => alert('Error al generar el PDF: ' + e.message))
        .finally(() => { overlay.style.display = 'none'; });
}
</script>
</x-app-layout>
