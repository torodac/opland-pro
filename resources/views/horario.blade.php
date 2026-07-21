@php
$diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

$tipoLabels = [
    'turno'        => 'Turno',
    'descanso'     => 'Descanso',
    'vacaciones'   => 'Vacaciones',
    'baja'         => 'Baja',
    'comp_festivo' => 'Comp. festivo',
    'comp_horas'   => 'Comp. horas',
    'asuntos'      => 'Asuntos propios',
    'absentismo'   => 'Absentismo',
];

function horarioCellHtml($h): string {
    if (!$h) return '<div class="hce"></div>';
    if ($h->tipo === 'turno') {
        $de = $h->hora_inicio ? substr($h->hora_inicio, 0, 5) : '?';
        $a  = $h->hora_fin   ? substr($h->hora_fin,    0, 5) : '?';
        return "<span class=\"hc hc-turno\">{$de}–{$a}</span>";
    }
    $labels = [
        'descanso'     => 'Descanso',
        'vacaciones'   => 'Vacaciones',
        'baja'         => 'Baja',
        'comp_festivo' => 'Comp. festivo',
        'comp_horas'   => 'Comp. horas',
        'asuntos'      => 'Asuntos propios',
        'absentismo'   => 'Absentismo',
    ];
    $lbl = $labels[$h->tipo] ?? $h->tipo;
    return "<span class=\"hc hc-{$h->tipo}\">{$lbl}</span>";
}

function ausenciaCellHtml(string $tipo): string {
    $t = mb_strtolower($tipo);
    $cls = 'hc-aus';
    if (str_starts_with($t, 'comp')) $cls = 'hc-compensacion';
    elseif (str_contains($t, 'vacac'))  $cls = 'hc-vacaciones';
    elseif (str_contains($t, 'baja'))   $cls = 'hc-baja';
    elseif (str_contains($t, 'asunto')) $cls = 'hc-asuntos';
    elseif (str_contains($t, 'absent')) $cls = 'hc-absentismo';
    return "<span class=\"hc aus-readonly {$cls}\" title=\"Ausencia registrada\">{$tipo}</span>";
}
@endphp

<x-app-layout :breadcrumb="$breadcrumb" :project="$project">

<x-slot name="actions">
    <a href="{{ route('horario.listado', $project->slug) }}"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <line x1="8" y1="6" x2="21" y2="6" stroke-linecap="round"/><line x1="8" y1="12" x2="21" y2="12" stroke-linecap="round"/>
            <line x1="8" y1="18" x2="21" y2="18" stroke-linecap="round"/><line x1="3" y1="6" x2="3.01" y2="6" stroke-linecap="round"/>
            <line x1="3" y1="12" x2="3.01" y2="12" stroke-linecap="round"/><line x1="3" y1="18" x2="3.01" y2="18" stroke-linecap="round"/>
        </svg>
    </a>
</x-slot>

@include('partials.role-badge', ['project' => $project, 'texto' => 'Solo Dirección general y Director RRHH (o admin) pueden editar fechas pasadas de este horario.'])

<style>
.hor-nav { display:flex; align-items:center; gap:8px; margin-bottom:1.25rem; }
.hor-nav h2 { font-size:1rem; font-weight:600; flex:1; }
.hor-btn { display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border:1px solid #d1d5db; border-radius:6px; background:#fff; font-size:13px; cursor:pointer; color:#374151; text-decoration:none; }
.hor-btn:hover { background:#f9fafb; }
.week-label { font-size:13px; font-weight:500; color:#6b7280; min-width:160px; text-align:center; }

.dept-block { margin-bottom:1.75rem; }
.dept-title { font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; display:flex; align-items:center; gap:6px; }

.hor-table { width:100%; border-collapse:collapse; font-size:12px; table-layout:fixed; }
.hor-table th { padding:5px 4px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb; }
.hor-table th.col-user { text-align:left; padding-left:8px; width:140px; }
.hor-table th.th-fest { background:#ffe0e0; color:#cc0000; }
.hor-table th.th-wk { color:#d1d5db; }
.hor-table th .fest-badge { font-size:9px; font-weight:400; display:block; }
.hor-table td { border:1px solid #e5e7eb; padding:3px 4px; text-align:center; height:34px; vertical-align:middle; }
.hor-table td.col-user { text-align:left; padding-left:8px; font-weight:500; background:#f9fafb; border-right:1px solid #d1d5db; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hor-table td.cell-wk { background:#fafafa; }
.hor-table td.cell-fest { background:#fff5f5; }
.hor-table td.cell-edit { cursor:pointer; }
.hor-table td.cell-edit:hover { background:#EFF6FF; }

.hce { width:100%; height:22px; border-radius:4px; border:1px dashed #d1d5db; }
td.cell-edit:hover .hce { border-color:#93C5FD; background:#EFF6FF; }

.hc { display:inline-block; padding:2px 6px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
.hc-turno       { background:#DBEAFE; color:#1E40AF; }
.hc-descanso    { background:#F3F4F6; color:#6B7280; }
.hc-vacaciones  { background:#FEF3C7; color:#92400E; }
.hc-baja        { background:#EDE9FE; color:#5B21B6; }
.hc-compensacion, .hc-comp_festivo, .hc-comp_horas { background:#FCE7F3; color:#9D174D; }
.hc-asuntos     { background:#D1FAE5; color:#065F46; }
.hc-absentismo  { background:#FEE2E2; color:#991B1B; }
.aus-readonly   { opacity:0.75; cursor:default; }
.cell-readonly .hc { opacity:0.7; }
td.cell-readonly { background:#fafafa; cursor:default; }

/* Popover */
#hor-pop { position:fixed; z-index:200; width:260px; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:10px; display:none; box-shadow:0 4px 16px rgba(0,0,0,0.12); }
#hor-pop.show { display:block; }
.pop-hdr { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.pop-hdr-name { font-size:12px; font-weight:600; color:#111827; }
.pop-hdr-date { font-size:11px; color:#6B7280; }
.pop-close { cursor:pointer; color:#9CA3AF; font-size:18px; line-height:1; }
.pop-section { font-size:10px; font-weight:600; color:#9CA3AF; padding:3px 0 2px; text-transform:uppercase; letter-spacing:.03em; }
.pop-time { display:flex; gap:6px; align-items:center; padding:2px 0 4px; }
.pop-time input { width:88px; padding:4px 6px; font-size:13px; border:1px solid #d1d5db; border-radius:5px; }
.pop-time span { font-size:11px; color:#9CA3AF; }
.pop-opt { display:flex; align-items:center; gap:6px; padding:4px 5px; cursor:pointer; border-radius:6px; font-size:12px; color:#374151; }
.pop-opt:hover, .pop-opt.sel { background:#F3F4F6; }
.pop-opt.sel { outline:1.5px solid #93C5FD; }
.pop-sep { height:1px; background:#F3F4F6; margin:4px 0; }
.aus-grid { display:grid; grid-template-columns:1fr 1fr; gap:2px; }

.days-row { padding:4px 0; }
.days-row-label { font-size:10px; font-weight:600; color:#9CA3AF; text-transform:uppercase; margin-bottom:4px; }
.day-boxes { display:flex; gap:3px; }
.day-box { width:28px; height:26px; border:1px solid #d1d5db; border-radius:5px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:600; color:#6B7280; cursor:pointer; user-select:none; }
.day-box.checked { background:#1D4ED8; color:#fff; border-color:#1D4ED8; }
.day-box.origin { outline:2px solid #60A5FA; outline-offset:1px; }
.days-quick { display:flex; gap:4px; margin-top:4px; }
.days-quick button { font-size:10px; padding:2px 8px; background:none; border:1px solid #e5e7eb; border-radius:4px; cursor:pointer; color:#6B7280; }
.days-quick button:hover { background:#F3F4F6; }

.pop-foot { display:flex; gap:6px; margin-top:8px; padding-top:8px; border-top:1px solid #F3F4F6; }
.pop-save { flex:1; padding:6px; font-size:12px; font-weight:600; background:#1D4ED8; color:#fff; border:none; border-radius:6px; cursor:pointer; }
.pop-save:hover { background:#1E40AF; }
.pop-del { padding:6px 8px; font-size:12px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; cursor:pointer; color:#9CA3AF; }
.pop-del:hover { background:#FEE2E2; color:#991B1B; border-color:#FCA5A5; }
</style>

@php
$isPastWeek  = $weekEnd->lt(\Carbon\Carbon::today());
$pastBlocked = $isPastWeek && !($canEditPast ?? false);
@endphp
<div class="hor-nav">
    <h2>Horarios semanales</h2>

    <a href="?semana={{ $prevWeek }}" class="hor-btn">&#8592;</a>
    <span class="week-label">
        {{ $weekStart->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM YYYY') }}
    </span>
    <a href="?semana={{ $nextWeek }}" class="hor-btn">&#8594;</a>
    <a href="?" class="hor-btn">Esta semana</a>
</div>

@foreach($departamentos as $dept)
@php
$usuarios = $usuariosByDept->get($dept->id, collect());
if ($usuarios->isEmpty()) continue;
$deptLabel = $dept->nombre ?: 'Sin departamento';
@endphp

<div class="dept-block">
    <div class="dept-title">{{ $deptLabel }}</div>
    <table class="hor-table">
        <thead>
            <tr>
                <th class="col-user">Usuario</th>
                @foreach($dates as $di => $d)
                @php
                    $ds = $d->toDateString();
                    $isFest = isset($festivosMap[$ds]);
                    $isWk   = $d->isWeekend();
                    $thCls  = $isFest ? 'th-fest' : ($isWk ? 'th-wk' : '');
                @endphp
                <th class="{{ $thCls }}">
                    {{ $diasNombres[$di] }} {{ $d->day }}
                    @if($isFest)
                        <span class="fest-badge">{{ $festivosMap[$ds] }}</span>
                    @endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($usuarios as $u)
            <tr>
                <td class="col-user"><span class="app-tooltip">{{ $u->nombre }}<span class="app-tooltip-box">{{ $u->nombre }}</span></span></td>
                @foreach($dates as $di => $d)
                @php
                    $ds     = $d->toDateString();
                    $isWk   = $d->isWeekend();
                    $isFest = isset($festivosMap[$ds]);
                    $ausencia = $ausenciasMap[$u->id][$ds] ?? null;
                    $horario  = $horariosMap[$u->id . '_' . $ds] ?? null;
                    $editable = !$ausencia && !$pastBlocked;
                    $tdCls  = 'cell-' . ($editable ? 'edit' : 'readonly');
                    $tdCls .= $isWk   ? ' cell-wk'   : '';
                    $tdCls .= $isFest ? ' cell-fest'  : '';
                @endphp
                @if($ausencia)
                    <td class="{{ $tdCls }}">{!! ausenciaCellHtml($ausencia) !!}</td>
                @elseif($editable)
                    <td class="{{ $tdCls }}"
                        id="cell-{{ $u->id }}-{{ $ds }}"
                        data-uid="{{ $u->id }}"
                        data-fecha="{{ $ds }}"
                        data-nombre="{{ $u->nombre }}"
                        data-tipo="{{ $horario?->tipo ?? '' }}"
                        data-hi="{{ $horario?->hora_inicio ? substr($horario->hora_inicio,0,5) : '' }}"
                        data-hf="{{ $horario?->hora_fin   ? substr($horario->hora_fin,0,5)   : '' }}"
                        onclick="openPop(this)">
                        {!! horarioCellHtml($horario) !!}
                    </td>
                @else
                    <td class="{{ $tdCls }}">
                        @if($isPastWeek)
                            <span class="app-tooltip">{!! horarioCellHtml($horario) !!}<span class="app-tooltip-box">Semana pasada — solo lectura</span></span>
                        @else
                            {!! horarioCellHtml($horario) !!}
                        @endif
                    </td>
                @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

@if($departamentos->isEmpty())
    <p style="color:#9CA3AF;font-size:13px">No hay usuarios con departamento asignado. Edita los usuarios para asignarles departamento.</p>
@endif

<!-- Popover -->
<div id="hor-pop">
    <div class="pop-hdr">
        <div>
            <div class="pop-hdr-name" id="pop-name"></div>
            <div class="pop-hdr-date" id="pop-date"></div>
        </div>
        <span class="pop-close" onclick="closePop()">×</span>
    </div>

    <div class="pop-section">Turno</div>
    <div class="pop-time">
        <input type="time" id="pop-hi" value="11:00" oninput="setSel('turno')">
        <span>–</span>
        <input type="time" id="pop-hf" value="19:00" oninput="setSel('turno')">
    </div>
    <div class="pop-opt" id="opt-turno" onclick="setSel('turno')"><span class="hc hc-turno" style="font-size:10px">Turno</span></div>
    <div class="pop-sep"></div>
    <div class="pop-opt" id="opt-descanso" onclick="setSel('descanso')"><span class="hc hc-descanso" style="font-size:10px">Descanso</span></div>
    <div class="pop-sep"></div>
    <div class="pop-section">Ausencia</div>
    <div class="aus-grid">
        <div class="pop-opt" id="opt-vacaciones"   onclick="setSel('vacaciones')">  <span class="hc hc-vacaciones"  style="font-size:10px">Vacaciones</span></div>
        <div class="pop-opt" id="opt-baja"         onclick="setSel('baja')">        <span class="hc hc-baja"        style="font-size:10px">Baja</span></div>
        <div class="pop-opt" id="opt-asuntos"      onclick="setSel('asuntos')">     <span class="hc hc-asuntos"     style="font-size:10px">Asuntos propios</span></div>
        <div class="pop-opt" id="opt-comp_festivo" onclick="setSel('comp_festivo')"><span class="hc hc-comp_festivo" style="font-size:10px">Comp. festivo</span></div>
        <div class="pop-opt" id="opt-comp_horas"   onclick="setSel('comp_horas')">  <span class="hc hc-comp_horas"  style="font-size:10px">Comp. horas</span></div>
        <div class="pop-opt" id="opt-absentismo"   onclick="setSel('absentismo')">  <span class="hc hc-absentismo"  style="font-size:10px">Absentismo</span></div>
    </div>

    <div class="pop-sep"></div>
    <div class="days-row">
        <div class="days-row-label">Aplicar también en</div>
        <div class="day-boxes" id="pop-days"></div>
        <div class="days-quick">
            <button type="button" onclick="qLab()">L–V</button>
            <button type="button" onclick="qAll()">Todos</button>
            <button type="button" onclick="qNone()">Ninguno</button>
        </div>
    </div>

    <div class="pop-foot">
        <button class="pop-del" onclick="doDelete()" title="Borrar selección">🗑 Borrar</button>
        <button class="pop-save" onclick="doSave()">Guardar</button>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const BASE = '{{ route("horario.store", $project->slug) }}';
const DEL  = '{{ route("horario.destroy", $project->slug) }}';

const DIAS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
const LABELS = {turno:'Turno',descanso:'Descanso',vacaciones:'Vacaciones',baja:'Baja',comp_festivo:'Comp. festivo',comp_horas:'Comp. horas',asuntos:'Asuntos propios',absentismo:'Absentismo'};

// Week dates (Mon→Sun) from PHP
const WEEK_DATES = @json($dates->map(fn($d) => $d->toDateString())->values());

let activeCell = null, selTipo = null;

function openPop(td) {
    activeCell = td;
    selTipo = null;

    document.getElementById('pop-name').textContent = td.dataset.nombre;
    const d = new Date(td.dataset.fecha + 'T00:00:00');
    document.getElementById('pop-date').textContent =
        DIAS[d.getDay() === 0 ? 6 : d.getDay() - 1] + ' ' +
        d.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'numeric'});

    const tipo = td.dataset.tipo || '';
    document.getElementById('pop-hi').value = td.dataset.hi || '11:00';
    document.getElementById('pop-hf').value = td.dataset.hf || '19:00';

    document.querySelectorAll('.pop-opt').forEach(el => el.classList.remove('sel'));
    setSel(tipo || 'turno');

    buildDays(td.dataset.fecha);

    const pop = document.getElementById('hor-pop');
    pop.classList.add('show');

    // Posicionar dentro del viewport
    const rect = td.getBoundingClientRect();
    const pw = pop.offsetWidth, ph = pop.offsetHeight;
    const margin = 8;
    let px = rect.left;
    let py = rect.bottom + 4;
    if (px + pw > window.innerWidth - margin) px = window.innerWidth - pw - margin;
    if (px < margin) px = margin;
    if (py + ph > window.innerHeight - margin) py = rect.top - ph - 4;
    if (py < margin) py = margin;
    pop.style.left = px + 'px';
    pop.style.top  = py + 'px';
}

function closePop() {
    document.getElementById('hor-pop').classList.remove('show');
    activeCell = null;
}

function setSel(tipo) {
    selTipo = tipo;
    document.querySelectorAll('.pop-opt').forEach(el => el.classList.remove('sel'));
    const el = document.getElementById('opt-' + tipo);
    if (el) el.classList.add('sel');
}

function buildDays(originDate) {
    const box = document.getElementById('pop-days');
    box.innerHTML = '';
    WEEK_DATES.forEach((ds, i) => {
        const div = document.createElement('div');
        div.className = 'day-box' + (ds === originDate ? ' origin checked' : '');
        div.textContent = DIAS[i];
        div.dataset.date = ds;
        if (ds === originDate) {
            div.dataset.fixed = '1';
        } else {
            div.onclick = () => div.classList.toggle('checked');
        }
        box.appendChild(div);
    });
}

function checkedDates() {
    return [...document.querySelectorAll('#pop-days .day-box.checked')].map(d => d.dataset.date);
}

function qLab()  { document.querySelectorAll('#pop-days .day-box:not([data-fixed])').forEach(d => { d.classList.toggle('checked', WEEK_DATES.indexOf(d.dataset.date) < 5); }); }
function qAll()  { document.querySelectorAll('#pop-days .day-box:not([data-fixed])').forEach(d => d.classList.add('checked')); }
function qNone() { document.querySelectorAll('#pop-days .day-box:not([data-fixed])').forEach(d => d.classList.remove('checked')); }

function cellHtml(tipo, hi, hf) {
    if (!tipo) return '<div class="hce"></div>';
    if (tipo === 'turno') {
        const t = (hi||'?').slice(0,5) + '–' + (hf||'?').slice(0,5);
        return `<span class="hc hc-turno">${t}</span>`;
    }
    return `<span class="hc hc-${tipo}">${LABELS[tipo]||tipo}</span>`;
}

function doSave() {
    if (!activeCell || !selTipo) { closePop(); return; }
    const hi = selTipo === 'turno' ? document.getElementById('pop-hi').value : null;
    const hf = selTipo === 'turno' ? document.getElementById('pop-hf').value : null;
    const uid = activeCell.dataset.uid;
    const dates = checkedDates();

    const entries = dates.map(fecha => ({
        id_usuario: uid,
        fecha,
        tipo: selTipo,
        hora_inicio: hi,
        hora_fin: hf,
    }));

    fetch(BASE, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ entries }),
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            dates.forEach(fecha => {
                const td = document.getElementById(`cell-${uid}-${fecha}`);
                if (td) {
                    td.dataset.tipo = selTipo;
                    td.dataset.hi   = hi || '';
                    td.dataset.hf   = hf || '';
                    td.innerHTML = cellHtml(selTipo, hi, hf);
                }
            });
        }
        closePop();
    }).catch(() => closePop());
}

function doDelete() {
    if (!activeCell) { closePop(); return; }
    const uid = activeCell.dataset.uid;
    const dates = checkedDates();

    const entries = dates.map(fecha => ({ id_usuario: uid, fecha }));

    fetch(DEL, {
        method: 'DELETE',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ entries }),
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            dates.forEach(fecha => {
                const td = document.getElementById(`cell-${uid}-${fecha}`);
                if (td) {
                    td.dataset.tipo = '';
                    td.dataset.hi   = '';
                    td.dataset.hf   = '';
                    td.innerHTML = '<div class="hce"></div>';
                }
            });
        }
        closePop();
    }).catch(() => closePop());
}

document.addEventListener('click', e => {
    if (!e.target.closest('#hor-pop') && !e.target.closest('.cell-edit')) closePop();
});
</script>

</x-app-layout>
