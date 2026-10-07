@php
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$year_min = now()->year - 3;
$year_max = now()->year + 1;

// Mismo circuito y mismas etiquetas que el informe mensual; lo que cambia son las rutas de firma
// y las cifras de cada fila (kilómetros en lugar de horas).
$firma_routes = [
    'aprueba'    => route('km.informe.firmar-aprueba', $project->slug),
    'rrhh'       => route('km.informe.firmar-rrhh', $project->slug),
    'trabajador' => route('km.informe.firmar-trabajador', $project->slug),
    'direccion'  => route('km.informe.firmar-direccion', $project->slug),
];

// Solo los pasos cuyo permiso es de PÁGINA (depende del rol de quien mira). 'aprueba' y
// 'trabajador' no están aquí porque su permiso depende de la fila.
$puede_firmar_paso = [
    'rrhh'      => $puede_firmar_rrhh,
    'direccion' => $puede_firmar_direccion,
];

$conteos = ['todos' => $filas->count()];
foreach (['aprueba','rrhh','trabajador','direccion','completado'] as $p) {
    $conteos[$p] = $filas->where('paso', $p)->count();
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
.ap-rol-pill.active { background:#f97316; border-color:#f97316; color:#fff; }
.ap-btn-listado:hover { background:#e5e7eb; color:#374151; }

.ap-chevrons { display:flex; margin-bottom:16px; flex-wrap:wrap; }
.ap-chev { position:relative; border:none; cursor:pointer; font:inherit; background:#eef1f5; color:#6b7280; padding:10px 20px 10px 26px; font-size:12.5px; font-weight:700; display:flex; align-items:center; gap:7px; clip-path: polygon(0 0, calc(100% - 13px) 0, 100% 50%, calc(100% - 13px) 100%, 0 100%, 12px 50%); margin-left:-12px; }
.ap-chev:first-child { clip-path: polygon(0 0, calc(100% - 13px) 0, 100% 50%, calc(100% - 13px) 100%, 0 100%); padding-left:16px; margin-left:0; }
.ap-chev:last-child { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 12px 50%); }
.ap-chevrons .ap-chev:nth-child(1){z-index:1}.ap-chevrons .ap-chev:nth-child(2){z-index:2}.ap-chevrons .ap-chev:nth-child(3){z-index:3}.ap-chevrons .ap-chev:nth-child(4){z-index:4}.ap-chevrons .ap-chev:nth-child(5){z-index:5}.ap-chevrons .ap-chev:nth-child(6){z-index:6}
.ap-chev:hover { background:#e2e6ec; color:#374151; }
.ap-chev.active { background:#f97316; color:#fff; }
.ap-chev.active.done { background:#2e8f5d; }
.ap-chev .n { font-family:ui-monospace,monospace; font-size:11px; font-weight:700; opacity:.85; }

.ap-hint { font-size:12.5px; color:#9ca3af; margin-bottom:10px; }

.ap-list { display:flex; flex-direction:column; gap:8px; }
.ap-row { background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.06); padding:12px 14px; display:flex; flex-direction:column; gap:6px; transition:opacity .3s, transform .3s; }
.ap-row.leaving { opacity:0; transform:translateX(10px); }
.ap-row-top { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.ap-row-right { display:flex; align-items:center; gap:14px; flex-shrink:0; margin-left:auto; }
.ap-who { display:flex; align-items:center; gap:10px; min-width:0; }
.ap-avatar { width:32px; height:32px; border-radius:50%; background:#ffedd5; color:#9a3412; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }
.ap-who-name { font-weight:700; font-size:13.5px; color:#111827; display:flex; align-items:center; gap:8px; }
.ap-progress { display:inline-flex; align-items:center; gap:6px; }
.ap-progress-bar { width:56px; height:6px; border-radius:4px; background:#e5e7eb; overflow:hidden; flex-shrink:0; }
.ap-progress-fill { display:block; height:100%; background:#f97316; border-radius:4px; }
.ap-progress-fill.off-range { background:#c24236; }
.ap-progress-label { font-family:ui-monospace,monospace; font-size:10.5px; font-weight:700; color:#6b7280; }
.ap-pct-fuera { font-size:10.5px; font-weight:700; color:#c24236; background:#FBE7E4; padding:2px 7px; border-radius:20px; }
.ap-who-dept { font-size:11.5px; color:#6b7280; }

.ap-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.ap-pendientes-validacion { font-size:11px; font-weight:700; color:#ff0000; background:#FBE7E4; padding:2px 8px; border-radius:20px; margin-left:auto; }
.ap-btn { border:1px solid transparent; border-radius:8px; padding:7px 13px; font-size:12.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; text-decoration:none; }
.ap-btn-sign { background:#f97316; color:#fff; }
.ap-btn-sign:hover { background:#ea580c; }
.ap-btn-sign:disabled { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }
.ap-btn-ghost { background:transparent; color:#6b7280; border-color:#e5e7eb; padding:7px 9px; }
.ap-btn-white { background:#fff; color:#374151; border-color:#e5e7eb; font-weight:400; }
.ap-btn-white:hover { background:#f9fafb; border-color:#9ca3af; }
.ap-btn-ghost:hover { color:#111827; border-color:#9ca3af; }
.ap-done-pill { background:#e4f4ea; color:#2e8f5d; font-size:12px; font-weight:700; padding:6px 12px; border-radius:8px; }

.ap-paso-badge { display:none; align-items:center; gap:6px; padding:3px 9px 3px 7px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
.ap-list.showing-todos .ap-paso-badge { display:inline-flex; }
.ap-paso-badge .dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.ap-paso-badge.s-aprueba { background:#dbeafe; color:#1d4ed8; }
.ap-paso-badge.s-rrhh { background:#ffedd5; color:#9a3412; }
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

/* Fondo rojo y letra amarilla: es el aviso mas urgente de la fila (dias de turno sin horario
   asignado), y antes era solo texto rojo, indistinguible del resto. Mismo alto y radio que las
   pastillas de .ap-pill para que no rompa la linea. */
.ap-sin-asignar { font-size:11px; font-weight:700; background:#B91C1C; color:#FDE047; padding:2px 8px; border-radius:20px; white-space:nowrap; }

.ap-line3 { display:flex; flex-direction:column; gap:3px; padding-left:42px; }
.ap-flag { display:flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600; }
.ap-flag.bad { color:#c24236; }
.ap-flag.gray { color:#6b7280; }
.ap-flag.warn { color:#b8790f; }

.ap-empty { text-align:center; padding:40px 0; color:#9ca3af; font-size:13.5px; }

@media (max-width:640px) {
  .ap-chev { clip-path:none !important; margin-left:0 !important; border-radius:8px; padding:8px 14px; }
  .ap-line2, .ap-line3 { padding-left:0; }
}
</style>

{{-- Selector de mes --}}
<form method="GET" id="form-filtros" class="flex items-end gap-2 mb-4">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Mes</label>
        <select name="month" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $m === $month ? 'selected' : '' }}>{{ $meses_es[$m] }}</option>
            @endfor
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Año</label>
        <select name="year" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
            @for($y = $year_max; $y >= $year_min; $y--)
                <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
    <button type="submit" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">Ver</button>
</form>

<div class="ap-chevrons" id="ap-tabs" data-default="{{ $default_tab }}">
    <button type="button" class="ap-chev {{ $default_tab === 'todos' ? 'active' : '' }}" data-step="todos">Todos <span class="n">{{ $conteos['todos'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'aprueba' ? 'active' : '' }}" data-step="aprueba">Supervisor <span class="n">{{ $conteos['aprueba'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'rrhh' ? 'active' : '' }}" data-step="rrhh">RRHH <span class="n">{{ $conteos['rrhh'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'trabajador' ? 'active' : '' }}" data-step="trabajador">Trabajador <span class="n">{{ $conteos['trabajador'] }}</span></button>
    <button type="button" class="ap-chev {{ $default_tab === 'direccion' ? 'active' : '' }}" data-step="direccion">Dirección <span class="n">{{ $conteos['direccion'] }}</span></button>
    <button type="button" class="ap-chev done {{ $default_tab === 'completado' ? 'active' : '' }}" data-step="completado">Completados <span class="n">{{ $conteos['completado'] }}</span></button>
</div>

@if(!$viewer_tiene_firma && ($puede_firmar_rrhh || $puede_firmar_direccion || $filas->contains('puede_firmar_aprueba', true)))
<div class="ap-hint" style="background:#FBF0DD;color:#B8790F;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
    No tienes firma manuscrita registrada en tu perfil — no podrás firmar hasta añadirla.
</div>
@endif

<div class="ap-list" id="ap-list">
@forelse($filas as $fila)
    @php
        $puedeFirmarEste = match($fila->paso) {
            'rrhh', 'direccion' => ($puede_firmar_paso[$fila->paso] ?? false) && $viewer_tiene_firma,
            'aprueba'    => $fila->puede_firmar_aprueba && $viewer_tiene_firma,
            'trabajador' => $fila->es_mi_informe && $fila->tiene_firma,
            default => false,
        };
        $motivoNoPuede = match(true) {
            $fila->paso === 'completado' => null,
            in_array($fila->paso, ['rrhh','direccion']) && !($puede_firmar_paso[$fila->paso] ?? false) => 'No tienes permiso para firmar como ' . $paso_labels[$fila->paso] . '.',
            $fila->paso === 'aprueba' && !$fila->puede_firmar_aprueba => 'Solo el supervisor asignado en la ficha de ' . $fila->nombre . ' puede firmar este paso.',
            in_array($fila->paso, ['rrhh','direccion','aprueba']) && !$viewer_tiene_firma => 'Debes registrar tu firma en tu perfil.',
            $fila->paso === 'trabajador' && !$fila->es_mi_informe => 'Solo ' . $fila->nombre . ' puede firmar su propio informe.',
            $fila->paso === 'trabajador' && !$fila->tiene_firma => $fila->nombre . ' no tiene firma registrada en su perfil.',
            default => null,
        };
        $iniciales = collect(explode(' ', $fila->nombre))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $verUrl = route('km.informe', $project->slug) . "?year={$year}&month={$month}&user_id={$fila->id}";
    @endphp
    <div class="ap-row" data-step="{{ $fila->paso }}">
        <div class="ap-row-top">
            <div class="ap-who">
                <div class="ap-avatar">{{ $iniciales }}</div>
                <div>
                    <div class="ap-who-name">{{ $fila->nombre }}</div>
                    <div class="ap-who-dept">{{ $fila->departamento ?? '—' }}</div>
                </div>
                <span class="ap-paso-badge s-{{ $fila->paso }}"><span class="dot"></span>{{ $paso_labels[$fila->paso] ?? $fila->paso }}</span>
            </div>
            <div class="ap-row-right">
                <div class="ap-actions">
                    <a class="ap-btn ap-btn-white" href="{{ $verUrl }}" target="_blank" rel="noopener" title="Abrir informe de kilómetros">Ver informe</a>
                    @if($fila->paso === 'completado')
                        <span class="ap-done-pill">✓ Completado</span>
                        @if($puede_reabrir)
                            {{-- Un informe completado NO se reabre: la acción existe para los pasos
                                 intermedios y aquí se deja fuera a propósito, igual que en el mensual. --}}
                        @endif
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
            <span><span class="ap-stat">{{ number_format($fila->total_km, 0, ',', '.') }}</span> km</span>
            <span class="ap-sep">·</span>
            <span><span class="ap-stat">{{ $fila->dias_con_km }}</span> {{ $fila->dias_con_km === 1 ? 'día con kilómetros' : 'días con kilómetros' }}</span>
        </div>
    </div>
@empty
    <div style="padding:1.5rem;text-align:center;font-size:13px;color:#999">
        Nadie tiene kilómetros registrados en {{ mb_strtolower($meses_es[$month]) }} de {{ $year }}.
    </div>
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
