@php
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$year_min = now()->year - 3;
$year_max = now()->year + 1;

$paso_labels = ['rrhh' => 'RRHH', 'coordinador' => 'Coordinador', 'trabajador' => 'Trabajador', 'direccion' => 'Dirección', 'completado' => 'Completado'];

$firma_routes = [
    'rrhh'        => route('informe-imputaciones.validar', $project->slug),
    'coordinador' => route('informe-imputaciones.firmar-coordinador', $project->slug),
    'trabajador'  => route('informe-imputaciones.firmar-trabajador', $project->slug),
    'direccion'   => route('informe-imputaciones.firmar-direccion', $project->slug),
];

$puede_firmar_paso = [
    'rrhh'        => $puede_firmar_rrhh,
    'coordinador' => $puede_firmar_coordinador,
    'direccion'   => $puede_firmar_direccion,
];

$conteos = ['todos' => $filas->count()];
foreach (['rrhh','coordinador','trabajador','direccion','completado'] as $p) {
    $conteos[$p] = $filas->where('paso', $p)->count();
}

function sprintfHoras($decimal) {
    $signo = $decimal < 0 ? '-' : '+';
    $abs   = abs($decimal);
    $h     = (int) floor($abs);
    $m     = (int) round(($abs - $h) * 60);
    return sprintf('%s%d:%02d', $signo, $h, $m);
}

function sprintfDiasHorasMin($totalMin) {
    $d = intdiv($totalMin, 1440);
    $h = intdiv($totalMin % 1440, 60);
    $m = $totalMin % 60;
    $partes = [];
    if ($d > 0) $partes[] = "{$d}d";
    if ($h > 0 || $d > 0) $partes[] = sprintf('%02dh', $h);
    $partes[] = sprintf('%02dm', $m);
    return implode(' ', $partes);
}
@endphp

<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<style>
.ap-mono { font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; font-variant-numeric: tabular-nums; }

.ap-filtros { display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:#fff; padding:10px 14px; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:14px; }
.ap-filtros select { font-size:13px; border:1px solid #e5e7eb; border-radius:8px; padding:7px 10px; }
.ap-btn-listado { margin-left:auto; display:inline-flex; align-items:center; gap:6px; padding:7px 12px; background:#f3f4f6; color:#4b5563; font-size:13px; font-weight:600; border-radius:8px; text-decoration:none; }

.ap-rol-pills { display:flex; gap:6px; }
.ap-rol-pill { border:1px solid #e5e7eb; background:#fff; color:#6b7280; font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:20px; cursor:pointer; }
.ap-rol-pill:hover { background:#f9fafb; color:#374151; }
.ap-rol-pill.active { background:#2c5c86; border-color:#2c5c86; color:#fff; }
.ap-btn-listado:hover { background:#e5e7eb; color:#374151; }

.ap-chevrons { display:flex; margin-bottom:16px; flex-wrap:wrap; }
.ap-chev { position:relative; border:none; cursor:pointer; font:inherit; background:#eef1f5; color:#6b7280; padding:10px 20px 10px 26px; font-size:12.5px; font-weight:700; display:flex; align-items:center; gap:7px; clip-path: polygon(0 0, calc(100% - 13px) 0, 100% 50%, calc(100% - 13px) 100%, 0 100%, 12px 50%); margin-left:-12px; }
.ap-chev:first-child { clip-path: polygon(0 0, calc(100% - 13px) 0, 100% 50%, calc(100% - 13px) 100%, 0 100%); padding-left:16px; margin-left:0; }
.ap-chev:last-child { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 12px 50%); }
.ap-chevrons .ap-chev:nth-child(1){z-index:1}.ap-chevrons .ap-chev:nth-child(2){z-index:2}.ap-chevrons .ap-chev:nth-child(3){z-index:3}.ap-chevrons .ap-chev:nth-child(4){z-index:4}.ap-chevrons .ap-chev:nth-child(5){z-index:5}.ap-chevrons .ap-chev:nth-child(6){z-index:6}
.ap-chev:hover { background:#e2e6ec; color:#374151; }
.ap-chev.active { background:#2c5c86; color:#fff; }
.ap-chev.active.done { background:#2e8f5d; }
.ap-chev .n { font-family:ui-monospace,monospace; font-size:11px; font-weight:700; opacity:.85; }

.ap-hint { font-size:12.5px; color:#9ca3af; margin-bottom:10px; }

.ap-list { display:flex; flex-direction:column; gap:8px; }
.ap-row { background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.06); padding:12px 14px; display:flex; flex-direction:column; gap:6px; transition:opacity .3s, transform .3s; }
.ap-row.leaving { opacity:0; transform:translateX(10px); }
.ap-row-top { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.ap-row-right { display:flex; align-items:center; gap:14px; flex-shrink:0; margin-left:auto; }
.ap-who { display:flex; align-items:center; gap:10px; min-width:0; }
.ap-avatar { width:32px; height:32px; border-radius:50%; background:#e4edf5; color:#1b3e5c; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }
.ap-who-name { font-weight:700; font-size:13.5px; color:#111827; display:flex; align-items:center; gap:8px; }
.ap-progress { display:inline-flex; align-items:center; gap:6px; }
.ap-progress-bar { width:56px; height:6px; border-radius:4px; background:#e5e7eb; overflow:hidden; flex-shrink:0; }
.ap-progress-fill { display:block; height:100%; background:#2c5c86; border-radius:4px; }
.ap-progress-fill.off-range { background:#f97316; }
.ap-progress-label { font-family:ui-monospace,monospace; font-size:10.5px; font-weight:700; color:#6b7280; }
.ap-pct-fuera { font-size:10.5px; font-weight:700; color:#c24236; background:#FBE7E4; padding:2px 7px; border-radius:20px; }
.ap-who-dept { font-size:11.5px; color:#6b7280; }

.ap-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.ap-btn { border:1px solid transparent; border-radius:8px; padding:7px 13px; font-size:12.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; text-decoration:none; }
.ap-btn-sign { background:#2c5c86; color:#fff; }
.ap-btn-sign:hover { background:#1b3e5c; }
.ap-btn-sign:disabled { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }
.ap-btn-ghost { background:transparent; color:#6b7280; border-color:#e5e7eb; padding:7px 9px; }
.ap-btn-white { background:#fff; color:#374151; border-color:#e5e7eb; font-weight:400; }
.ap-btn-white:hover { background:#f9fafb; border-color:#9ca3af; }
.ap-btn-ghost:hover { color:#111827; border-color:#9ca3af; }
.ap-done-pill { background:#e4f4ea; color:#2e8f5d; font-size:12px; font-weight:700; padding:6px 12px; border-radius:8px; }

.ap-paso-badge { display:none; align-items:center; gap:6px; padding:3px 9px 3px 7px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
.ap-list.showing-todos .ap-paso-badge { display:inline-flex; }
.ap-paso-badge .dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.ap-paso-badge.s-rrhh { background:#e4edf5; color:#1b3e5c; }
.ap-paso-badge.s-coordinador { background:#fbf0dd; color:#b8790f; }
.ap-paso-badge.s-trabajador { background:#ede7fb; color:#6b48c7; }
.ap-paso-badge.s-direccion { background:#fbe7ef; color:#b23368; }
.ap-paso-badge.s-completado { background:#e4f4ea; color:#2e8f5d; }

.ap-line2 { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12.5px; color:#6b7280; padding-left:42px; }
.ap-stat { font-family:ui-monospace,monospace; font-weight:700; color:#111827; }
.ap-stat.pos { color:#2e8f5d; }
.ap-stat.neg { color:#c24236; }
.ap-sep { color:#d1d5db; }

.ap-pill { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap; }
.ap-pill.Turno        { background:#DBEAFE; color:#1E40AF; }
.ap-pill.Descanso     { background:#F3F4F6; color:#6B7280; }
.ap-pill.Vacaciones   { background:#FEF3C7; color:#92400E; }
.ap-pill.Baja         { background:#EDE9FE; color:#5B21B6; }
.ap-pill.Compensacion { background:#FCE7F3; color:#9D174D; }
.ap-pill.Asuntos      { background:#D1FAE5; color:#065F46; }
.ap-pill.Absentismo   { background:#FEE2E2; color:#991B1B; }

.ap-sin-asignar { font-size:11.5px; font-weight:700; color:#991B1B; }

.ap-line3 { display:flex; flex-direction:column; gap:3px; padding-left:42px; }
.ap-flag { display:flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600; }
.ap-flag.bad { color:#c24236; }
.ap-flag.warn { color:#b8790f; }

.ap-empty { text-align:center; padding:40px 0; color:#9ca3af; font-size:13.5px; }

@media (max-width:640px) {
  .ap-chev { clip-path:none !important; margin-left:0 !important; border-radius:8px; padding:8px 14px; }
  .ap-line2, .ap-line3 { padding-left:0; }
}
</style>

<form method="GET" id="form-filtros" class="ap-filtros">
    <select name="year" onchange="document.getElementById('form-filtros').submit()">
        @for($y = $year_min; $y <= $year_max; $y++)
            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
    </select>
    <select name="month" onchange="document.getElementById('form-filtros').submit()">
        @for($m = 1; $m <= 12; $m++)
            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $meses_es[$m] }}</option>
        @endfor
    </select>

    <input type="hidden" name="rol" id="rol-filtro" value="{{ $rol_filtro }}">
    <div class="ap-rol-pills">
        <button type="button" class="ap-rol-pill {{ $rol_filtro === 'limpiadora' ? 'active' : '' }}" onclick="filtrarRol('limpiadora')">Limpiadora</button>
        <button type="button" class="ap-rol-pill {{ $rol_filtro === 'mantenimiento' ? 'active' : '' }}" onclick="filtrarRol('mantenimiento')">Mantenimiento</button>
        <button type="button" class="ap-rol-pill {{ $rol_filtro === 'sscc' ? 'active' : '' }}" onclick="filtrarRol('sscc')">SSCC</button>
    </div>

    <a class="ap-btn-listado" href="{{ route('informe-imputaciones', $project->slug) }}?year={{ $year }}&month={{ $month }}" title="Ver como ficha individual clásica">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
        Vista clásica
    </a>
</form>

<script>
function filtrarRol(rol) {
    const input = document.getElementById('rol-filtro');
    input.value = (input.value === rol) ? '' : rol;
    document.getElementById('form-filtros').submit();
}
</script>

<div class="ap-chevrons" id="ap-tabs" data-default="{{ $default_tab }}">
    <button type="button" class="ap-chev {{ $default_tab === 'todos' ? 'active' : '' }}" data-step="todos">Todos <span class="n">{{ $conteos['todos'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'rrhh' ? 'active' : '' }}" data-step="rrhh">RRHH <span class="n">{{ $conteos['rrhh'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'coordinador' ? 'active' : '' }}" data-step="coordinador">Coordinador <span class="n">{{ $conteos['coordinador'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'trabajador' ? 'active' : '' }}" data-step="trabajador">Trabajador <span class="n">{{ $conteos['trabajador'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'direccion' ? 'active' : '' }}" data-step="direccion">Dirección <span class="n">{{ $conteos['direccion'] }}</span></button>
    <button type="button" class="ap-chev done {{ $default_tab === 'completado' ? 'active' : '' }}" data-step="completado">Completados <span class="n">{{ $conteos['completado'] }}</span></button>
</div>

@if(!$viewer_tiene_firma && ($puede_firmar_rrhh || $puede_firmar_coordinador || $puede_firmar_direccion))
<div class="ap-hint" style="background:#FBF0DD;color:#B8790F;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
    No tienes firma manuscrita registrada en tu perfil — no podrás firmar hasta añadirla.
</div>
@endif

<div class="ap-list" id="ap-list">
@forelse($filas as $fila)
    @php
        $puedeFirmarEste = match($fila->paso) {
            'rrhh', 'coordinador', 'direccion' => ($puede_firmar_paso[$fila->paso] ?? false) && $viewer_tiene_firma,
            'trabajador' => $fila->es_mi_informe && $fila->tiene_firma,
            default => false,
        };
        $motivoNoPuede = match(true) {
            $fila->paso === 'completado' => null,
            in_array($fila->paso, ['rrhh','coordinador','direccion']) && !($puede_firmar_paso[$fila->paso] ?? false) => 'No tienes permiso para firmar como ' . $paso_labels[$fila->paso] . '.',
            in_array($fila->paso, ['rrhh','coordinador','direccion']) && !$viewer_tiene_firma => 'Debes registrar tu firma en tu perfil.',
            $fila->paso === 'trabajador' && !$fila->es_mi_informe => 'Solo ' . $fila->nombre . ' puede firmar su propio informe.',
            $fila->paso === 'trabajador' && !$fila->tiene_firma => $fila->nombre . ' no tiene firma registrada en su perfil.',
            default => null,
        };
        $iniciales = collect(explode(' ', $fila->nombre))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $verUrl = route('informe-imputaciones', $project->slug) . "?year={$year}&month={$month}&user_id={$fila->id}";
    @endphp
    <div class="ap-row" data-step="{{ $fila->paso }}">
        <div class="ap-row-top">
            <div class="ap-who">
                <div class="ap-avatar">{{ strtoupper($iniciales) }}</div>
                <div>
                    <div class="ap-who-name">
                        {{ $fila->nombre }}
                        @if($fila->pct_imputado === 'fuera_de_rango')
                            <span class="ap-pct-fuera" title="Horas de tareas imputadas muy alejadas de las horas fichadas">Fuera de rango</span>
                        @elseif($fila->pct_imputado !== null)
                            <span class="ap-progress" title="{{ $fila->pct_imputado }}% de horas de tareas imputadas sobre horas fichadas">
                                <span class="ap-progress-bar"><span class="ap-progress-fill {{ ($fila->pct_imputado < 95 || $fila->pct_imputado > 105) ? 'off-range' : '' }}" style="width:{{ min($fila->pct_imputado, 100) }}%"></span></span>
                                <span class="ap-progress-label">{{ $fila->pct_imputado }}%</span>
                            </span>
                        @endif
                    </div>
                    <div class="ap-who-dept">{{ $fila->departamento ?? '—' }}</div>
                </div>
                <span class="ap-paso-badge s-{{ $fila->paso }}"><span class="dot"></span>{{ $paso_labels[$fila->paso] ?? $fila->paso }}</span>
            </div>
            <div class="ap-row-right">
                @if($fila->desviacion)
                    <div class="ap-flag bad">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        Desviación fichaje/imputación en {{ $fila->desviacion['dias'] }} {{ $fila->desviacion['dias'] === 1 ? 'día' : 'días' }}, suma {{ sprintfDiasHorasMin($fila->desviacion['total_min']) }}
                    </div>
                @endif
                <div class="ap-actions">
                    <a class="ap-btn ap-btn-white" href="{{ $verUrl }}" target="_blank" rel="noopener" title="Abrir informe completo">Ver informe</a>
                    @if($fila->paso === 'completado')
                        <span class="ap-done-pill">✓ Completado</span>
                    @else
                        <button type="button" class="ap-btn ap-btn-sign"
                            data-url="{{ $firma_routes[$fila->paso] ?? '' }}?year={{ $year }}&month={{ $month }}&user_id={{ $fila->id }}"
                            data-nombre="{{ $fila->nombre }}"
                            data-paso="{{ $paso_labels[$fila->paso] ?? $fila->paso }}"
                            {{ $puedeFirmarEste ? '' : 'disabled' }}
                            title="{{ $motivoNoPuede ?? '' }}"
                            onclick="firmarFila(this)">Firmar</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="ap-line2">
            <span><span class="ap-stat">{{ $fila->dias_trabajados }}</span> fichajes</span>
            <span class="ap-sep">·</span>
            <span><span class="ap-stat {{ $fila->horas_extra > 0 ? 'pos' : ($fila->horas_extra < 0 ? 'neg' : '') }}">{{ sprintfHoras($fila->horas_extra) }}</span> extra</span>
            <span class="ap-sep">·</span>
            @if($fila->es_turno && $fila->dias_turno > 0)
                <span class="ap-pill Turno">{{ $fila->dias_turno }} Turno</span>
            @endif
            @if($fila->es_turno && $fila->dias_descanso > 0)
                <span class="ap-pill Descanso">{{ $fila->dias_descanso }} Descanso</span>
            @endif
            @forelse($fila->ausencias as $a)
                <span class="ap-pill {{ $a['clase'] }}">{{ $a['count'] }} {{ $a['nombre'] }}</span>
            @empty
                @if(!$fila->es_turno || ($fila->dias_turno === 0 && $fila->dias_descanso === 0))
                    <span style="color:#9ca3af">sin ausencias</span>
                @endif
            @endforelse
            @if($fila->es_turno && $fila->dias_sin_asignar > 0)
                <span class="ap-sin-asignar">{{ $fila->dias_sin_asignar }} sin horario</span>
            @endif
        </div>
        @if($fila->editado_tras_inicio)
        <div class="ap-line3">
            <div class="ap-flag warn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
                Editado después de iniciar la firma este mes
            </div>
        </div>
        @endif
    </div>
@empty
    <div class="ap-empty">No hay nadie visible para ti en este informe.</div>
@endforelse
</div>

<script>
function activarTab(step) {
    document.querySelectorAll('.ap-chev').forEach(t => t.classList.toggle('active', t.dataset.step === step));
    const list = document.getElementById('ap-list');
    list.classList.toggle('showing-todos', step === 'todos');
    document.querySelectorAll('#ap-list > .ap-row').forEach(row => {
        row.style.display = (step === 'todos' || row.dataset.step === step) ? '' : 'none';
    });
}

document.getElementById('ap-tabs').addEventListener('click', (e) => {
    const tab = e.target.closest('.ap-chev');
    if (!tab) return;
    activarTab(tab.dataset.step);
});

activarTab(document.getElementById('ap-tabs').dataset.default);

function firmarFila(btn) {
    if (btn.disabled) return;
    const nombre = btn.dataset.nombre;
    const paso   = btn.dataset.paso;
    if (!confirm(`Vas a firmar el informe de ${nombre} en el paso ${paso}.`)) return;

    btn.disabled = true;
    fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.error || data.message || 'No se pudo completar la acción.');
        const row = btn.closest('.ap-row');
        row.classList.add('leaving');
        setTimeout(() => location.reload(), 300);
    })
    .catch(e => {
        btn.disabled = false;
        alert(e.message || 'No se pudo completar la acción.');
    });
}
</script>

</x-app-layout>
