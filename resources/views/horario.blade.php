@php use App\Http\Controllers\Vm\InformeImputacionesController as IC; @endphp
@php
$diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

// La tabla de etiquetas vive en VmAusenciaTipos: la comparten esta pantalla, la PWA, el
// dashboard y la conciliación que crea la ausencia a partir del horario.
$tipoLabels = \App\Services\VmAusenciaTipos::LABEL_HORARIO;

// horarioCellHtml() y ausenciaCellHtml() viven en app/Support/vista-helpers.php
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

.dept-block { margin-bottom:1.75rem; overflow-x:auto; }
.dept-title { font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
/* El botón de publicar va un 25% por debajo del .hor-btn general (13px/5px-12px): es una acción
   de cada bloque, no de la cabecera, y a tamaño completo competía con el nombre del
   departamento. La pastilla de estado, que ocupa su sitio para quien no puede publicar, se
   iguala para que la fila no baile según quién mire. */
.btn-publicar-dept { font-size:9.75px; padding:3.75px 9px; border-radius:5px; }
.dept-estado { font-size:9.75px; font-weight:500; color:#9CA3AF; border:1px solid #E5E7EB; border-radius:5px; padding:3.75px 9px; }

/* table-layout:fixed reparte a partes iguales todo lo que sobra tras la columna de usuario, que
   es la unica con ancho propio. Con 'auto' cada dia se ensanchaba segun lo que le tocara dentro
   -- un festivo con nombre largo, un turno con mas texto -- y la semana salia descuadrada.
   min-width + el scroll del bloque evitan que, al estrecharse la ventana, los siete dias se
   aplasten hasta no poder leerse. */
.hor-table { width:100%; border-collapse:collapse; font-size:12px; table-layout:fixed; min-width:780px; }
.hor-table th { padding:5px 4px; text-align:center; font-size:11px; font-weight:600; color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb; }
.hor-table th.col-user { text-align:left; padding-left:8px; white-space:nowrap; width:{{ $colUserMaxLen }}ch; }
.hor-table th.th-fest { background:#ffe0e0; color:#cc0000; }
.hor-table th.th-wk { color:#d1d5db; }
.hor-table th .fest-badge { font-size:9px; font-weight:400; display:block; overflow-wrap:anywhere; }
.hor-table td { border:1px solid #e5e7eb; padding:3px 4px; text-align:center; height:34px; vertical-align:middle; }
.hor-table td.col-user { text-align:left; padding-left:8px; font-weight:500; background:#f9fafb; border-right:1px solid #d1d5db; white-space:nowrap; width:{{ $colUserMaxLen }}ch; }
.hor-table td.cell-wk { background:#fafafa; }
.hor-table td.cell-fest { background:#fff5f5; }
.hor-table td.cell-edit { cursor:pointer; }
.hor-table td.cell-edit:hover { background:#EFF6FF; }
/* Ausencia registrada ese día -- mismo amarillo de conflicto que usa el informe mensual. El
   horario (turno/descanso) sigue siendo el badge visible y editable; esto solo lo señala. */
.hor-table td.cell-ausencia { background:#ffff00; }
.hor-table td.cell-ausencia.cell-edit:hover { background:#fff44d; }

/* La columna Usuario es la primera de la tabla, justo al lado del sidebar -- el tooltip por
   defecto se centra sobre el elemento y su mitad izquierda queda tapada por el sidebar. Aquí se
   ancla por la izquierda (el borde izquierdo del tooltip coincide con el del texto) para que
   crezca siempre hacia la derecha, nunca hacia el sidebar. */
.app-tooltip-right .app-tooltip-box { left: 0; transform: none; }

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
.hc-festivo     { background:#ffe0e0; color:#cc0000; }
/* Marca de ausencia. Solo distingue visualmente la ausencia de un horario: la celda sigue
   siendo editable, asi que no lleva cursor:default, que daria a entender lo contrario. */
.aus-badge      { opacity:0.8; }
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

@if(!empty($deptsQuePublica))
<script>
// Un botón por departamento. El aviso al ocultar nombra el departamento, porque ocultar
// Limpieza y ocultar Mantenimiento ya no es lo mismo.
document.querySelectorAll('.btn-publicar-dept').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const publicar = btn.dataset.publicado !== '1';
        const dept     = btn.dataset.deptNombre;

        if (!publicar && !confirm('Al ocultar esta semana en ' + dept + ', su equipo dejará de ver '
            + 'en la app esta y todas las posteriores, aunque estén publicadas.\n\n¿Continuar?')) return;

        btn.disabled = true;
        try {
            const res = await fetch(@json(route('horario.publicar', $project->slug)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({
                    semana: btn.dataset.semana,
                    departamento: btn.dataset.dept,
                    publicado: publicar,
                }),
            });
            if (!res.ok) throw new Error('http ' + res.status);
            const data = await res.json();

            btn.dataset.publicado = data.publicado ? '1' : '0';
            btn.textContent = data.publicado ? 'Publicada' : '🚫 Oculta';
            btn.style.cssText = data.publicado
                ? 'background:#e8f2e2;border-color:#7BBF50;color:#27500A;'
                : 'background:#fdf3d3;border-color:#e8b800;color:#8a6300;';
            btn.title = data.publicado
                ? 'Visible en la app del trabajador. Pulsa para ocultarla.'
                : 'Oculta en la app del trabajador: no verá esta semana ni ninguna posterior. Pulsa para publicarla.';
        } catch (e) {
            alert('No se ha podido cambiar la publicación de ' + dept + '.');
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endif

@foreach($departamentos as $dept)
@php
$usuarios = $usuariosByDept->get($dept->id, collect());
if ($usuarios->isEmpty()) continue;
$deptLabel = $dept->nombre ?: 'Sin departamento';
@endphp

@php
    // La publicación es de cada departamento: el coordinador abre lo suyo sin esperar a los
    // demás. Quien no pueda publicar este departamento ve el estado, pero no el botón.
    $deptPublicada = $publicadaPorDept[$dept->id] ?? true;
    $deptPuede     = in_array((int) $dept->id, $deptsQuePublica, true);
@endphp

<div class="dept-block">
    <div class="dept-title">
        <span>{{ $deptLabel }}</span>
        @if($deptPuede)
        <button type="button" class="hor-btn btn-publicar-dept"
                data-dept="{{ $dept->id }}"
                data-dept-nombre="{{ $deptLabel }}"
                data-publicado="{{ $deptPublicada ? 1 : 0 }}"
                data-semana="{{ $weekStart->toDateString() }}"
                style="{{ $deptPublicada ? 'background:#e8f2e2;border-color:#7BBF50;color:#27500A;' : 'background:#fdf3d3;border-color:#e8b800;color:#8a6300;' }}"
                title="{{ $deptPublicada ? 'Visible en la app del trabajador. Pulsa para ocultarla.' : 'Oculta en la app del trabajador: no verá esta semana ni ninguna posterior. Pulsa para publicarla.' }}">
            {{ $deptPublicada ? 'Publicada' : '🚫 Oculta' }}
        </button>
        @else
        {{-- Sin permiso sobre este departamento se muestra el estado igualmente: saber si el
             equipo ya ve la semana es útil aunque no puedas cambiarlo. --}}
        <span class="dept-estado">{{ $deptPublicada ? 'Publicada' : 'Oculta' }}</span>
        @endif
    </div>
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
                @php
                    $saldo = $saldoMap[$u->id] ?? ['total' => 0, 'dias_fest' => 0, 'horas_resto' => 0];
                    $diasFestFmt = number_format($saldo['dias_fest'], 0, ',', '');
                    $horasRestoFmt = number_format($saldo['horas_resto'], 0, ',', '');
                    $desgloseTexto = "({$diasFestFmt} fest / {$horasRestoFmt} extr)";
                    $totalFmt = IC::fmtHoras($saldo['total'], true) ?: '0h 00m';
                    $tooltipTexto = "{$u->nombre}\nTotal: {$totalFmt}\n{$desgloseTexto}";
                @endphp
                <td class="col-user">
                    <span class="app-tooltip app-tooltip-right" style="flex-direction:column;align-items:flex-start;line-height:1.25;">
                        {{ $u->nombre }}
                        <span style="color:#9ca3af;font-size:10px;font-weight:400;">{{ $desgloseTexto }}</span>
                        <span class="app-tooltip-box">{{ $tooltipTexto }}</span>
                    </span>
                </td>
                @foreach($dates as $di => $d)
                @php
                    $ds     = $d->toDateString();
                    $isWk   = $d->isWeekend();
                    $isFest = isset($festivosMap[$ds]);
                    $ausencia = $ausenciasMap[$u->id][$ds] ?? null;
                    $horario  = $horariosMap[$u->id . '_' . $ds] ?? null;
                    // Si hay horario (turno/descanso/...), tiene prioridad de visualización sobre
                    // la ausencia -- la ausencia se señala con el mismo amarillo de conflicto del
                    // informe mensual, y solo si de verdad discrepan (horario="vacaciones" +
                    // ausencia="Vacaciones" no avisa de nada).
                    //
                    // Una celda con ausencia y SIN horario se muestra con la ausencia pintada,
                    // pero sigue siendo EDITABLE: dejarla en solo lectura bloqueaba la operativa
                    // de los coordinadores, que necesitan poder planificar encima. Esa NO va en
                    // amarillo: no hay conflicto, solo una ausencia que aún no se ha planificado,
                    // y es el caso normal -- pintarla llevaba el aviso de 6 celdas a 55, que es
                    // tanto como apagarlo.
                    $editable  = !$pastBlocked;
                    $mismoTipo = $ausencia && $horario && ($tipoLabels[$horario->tipo] ?? null) === $ausencia;
                    $tdCls  = 'cell-' . ($editable ? 'edit' : 'readonly');
                    $tdCls .= $isWk   ? ' cell-wk'   : '';
                    $tdCls .= $isFest ? ' cell-fest'  : '';
                    $tdCls .= ($horario && $ausencia && !$mismoTipo) ? ' cell-ausencia' : '';
                @endphp
                @if($editable)
                    <td class="{{ $tdCls }}"
                        id="cell-{{ $u->id }}-{{ $ds }}"
                        data-uid="{{ $u->id }}"
                        data-fecha="{{ $ds }}"
                        data-nombre="{{ $u->nombre }}"
                        data-ausencia="{{ $ausencia }}"
                        data-tipo="{{ $horario?->tipo ?? '' }}"
                        data-hi="{{ $horario?->hora_inicio ? substr($horario->hora_inicio,0,5) : '' }}"
                        data-hf="{{ $horario?->hora_fin   ? substr($horario->hora_fin,0,5)   : '' }}"
                        onclick="openPop(this)"
                        @if($ausencia) title="Ausencia registrada: {{ $ausencia }}" @endif>
                        {!! horarioCellHtml($horario, $isFest, $ausencia) !!}
                    </td>
                @else
                    <td class="{{ $tdCls }}"
                        data-ausencia="{{ $ausencia }}">
                        @if($isPastWeek)
                            <span class="app-tooltip">{!! horarioCellHtml($horario, $isFest, $ausencia) !!}<span class="app-tooltip-box">Semana pasada — solo lectura</span></span>
                        @else
                            {!! horarioCellHtml($horario, $isFest, $ausencia) !!}
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

// Espejo de horarioCellHtml() del servidor. Tiene que conocer el festivo y la ausencia: sin eso,
// al borrar el horario de un dia festivo la celda se reconstruia vacia y la marca "Dia festivo"
// desaparecia hasta recargar la pagina -- que es, literalmente, quitar la condicion de festivo.
function cellHtml(tipo, hi, hf, isFest, ausencia) {
    if (!tipo) {
        if (ausencia) return ausenciaBadgeHtml(ausencia);
        if (isFest)   return '<span class="hc hc-festivo">Día festivo</span>';
        return '<div class="hce"></div>';
    }
    if (tipo === 'turno') {
        const t = (hi||'?').slice(0,5) + '–' + (hf||'?').slice(0,5);
        return `<span class="hc hc-turno">${t}</span>`;
    }
    return `<span class="hc hc-${tipo}">${LABELS[tipo]||tipo}</span>`;
}

function ausenciaBadgeHtml(tipo) {
    const t = tipo.toLowerCase();
    let cls = 'hc-aus';
    if (t.startsWith('comp'))          cls = 'hc-compensacion';
    else if (t.includes('vacac'))      cls = 'hc-vacaciones';
    else if (t.includes('baja'))       cls = 'hc-baja';
    else if (t.includes('asunto'))     cls = 'hc-asuntos';
    else if (t.includes('absent'))     cls = 'hc-absentismo';
    return `<span class="hc aus-badge ${cls}" title="Ausencia registrada">${tipo}</span>`;
}

// Reconstruye una celda tras guardar/borrar su horario, con la misma prioridad que en el
// servidor (ver el bucle de celdas en PHP más arriba): con horario, el horario manda; sin él se
// pinta la ausencia o el festivo. La celda sigue SIEMPRE editable -- dejarla en solo lectura
// cuando había una ausencia bloqueaba la operativa de los coordinadores.
function renderCell(td, tipo, hi, hf) {
    const ausencia = td.dataset.ausencia || '';
    const isFest   = td.classList.contains('cell-fest');
    td.dataset.tipo = tipo || '';
    td.dataset.hi   = hi || '';
    td.dataset.hf   = hf || '';

    td.innerHTML = cellHtml(tipo, hi, hf, isFest, ausencia);
    td.classList.add('cell-edit');
    td.classList.remove('cell-readonly');
    td.onclick = () => openPop(td);

    // Amarillo solo cuando hay un horario que CONTRADICE la ausencia. Una ausencia sin horario
    // no es un conflicto, es lo normal, y pintarla vaciaba de significado el aviso.
    const mismo = ausencia && tipo && LABELS[tipo] === ausencia;
    td.classList.toggle('cell-ausencia', !!(ausencia && tipo && !mismo));
    if (ausencia) td.title = 'Ausencia registrada: ' + ausencia;
    else td.removeAttribute('title');
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

    window.fetchConAprobacion(BASE, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ entries }),
    }).then(r => r ? r.json() : null).then(data => {
        if (!data) { closePop(); return; }
        if (data.ok) {
            dates.forEach(fecha => {
                const td = document.getElementById(`cell-${uid}-${fecha}`);
                if (td) renderCell(td, selTipo, hi, hf);
            });
            if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
        }
        closePop();
    }).catch(() => closePop());
}

function doDelete() {
    if (!activeCell) { closePop(); return; }
    const uid = activeCell.dataset.uid;
    const dates = checkedDates();

    const entries = dates.map(fecha => ({ id_usuario: uid, fecha }));

    window.fetchConAprobacion(DEL, {
        method: 'DELETE',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ entries }),
    }).then(r => r ? r.json() : null).then(data => {
        if (!data) { closePop(); return; }
        if (data.ok) {
            dates.forEach(fecha => {
                const td = document.getElementById(`cell-${uid}-${fecha}`);
                if (td) renderCell(td, '', '', '');
            });
            if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
        }
        closePop();
    }).catch(() => closePop());
}

document.addEventListener('click', e => {
    if (!e.target.closest('#hor-pop') && !e.target.closest('.cell-edit')) closePop();
});
</script>

</x-app-layout>
