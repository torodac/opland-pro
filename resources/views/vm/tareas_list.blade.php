@php
    function minToHmTl(int $min): string {
        if ($min <= 0) return '—';
        return intdiv($min, 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'm';
    }
    $today = date('Y-m-d');
    $hasFilters = request()->hasAny(['q','f_propiedad','f_fecha_desde','f_fecha_hasta','f_responsable']);
    $filterKeys = ['q','f_propiedad','f_fecha_desde','f_fecha_hasta','f_responsable','stat','borrados','ocultos'];
    $listUrl = fn($extra=[]) => route('vm.tarea.list', array_filter(array_merge(['project'=>$project->slug,'tipo'=>$tipo], request()->only($filterKeys), $extra), fn($v) => $v !== null));
@endphp

<x-app-layout :project="$project" :breadcrumb="[['label'=>$tipoLabel,'url'=>'']]">

<x-slot name="actions">
    @if($canEdit)
    {{-- Planificador (solo limpieza) --}}
    @if($tipo === 'limpieza')
    <a href="{{ route('planificador-limpieza', $project->slug) }}"
       title="Planificador del día"
       class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M12 12v4m0 0l-2-2m2 2l2-2"/>
        </svg>
    </a>
    @endif

    {{-- Nuevo --}}
    @if(auth()->user()?->isProjectAdmin($project))
    <button onclick="document.getElementById('modal-nueva-tarea').classList.add('open')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo
    </button>
    @endif
    @endif

    {{-- Exportar --}}
    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-file-excel text-green-600"></i>
            Exportar
            <i class="fas fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
        </button>
        <div x-show="open" x-cloak
             class="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
            @php $tableSuffix = $tipo === 'piscina' ? 'piscinas' : $tipo; $qs = http_build_query(request()->except('page')); @endphp
            <a href="{{ route('excel.export', [$project->slug, 'tareas_'.$tableSuffix]) }}?tipo=listado&{{ $qs }}"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-filter text-orange-400 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Listado</p>
                    <p class="text-xs text-gray-400">Con los filtros aplicados</p>
                </div>
            </a>
            <a href="{{ route('excel.export', [$project->slug, 'tareas_'.$tableSuffix]) }}?tipo=tabla"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-table text-blue-400 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Tabla completa</p>
                    <p class="text-xs text-gray-400">Todas las columnas y registros</p>
                </div>
            </a>
        </div>
    </div>

    {{-- Importar --}}
    @if(auth()->user()?->isProjectAdmin($project))
    <a href="{{ route('excel.import-form', [$project->slug, 'tareas_'.$tableSuffix ?? ($tipo === 'piscina' ? 'piscinas' : $tipo)]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        <i class="fas fa-file-upload text-blue-500"></i>
        Importar
    </a>
    @endif
</x-slot>

{{-- ── STATS ── --}}
<div class="flex gap-3 mb-4 flex-wrap">

    <a href="{{ $listUrl(['stat' => $stat === 'sin_imp' ? null : 'sin_imp', 'page' => null]) }}"
       class="tl-stat {{ $stat === 'sin_imp' ? 'active' : '' }}">
        <span class="tl-stat-num">{{ $sinImp }}</span>
        <span class="tl-stat-lbl">Sin imputar <span title="Tareas en trámite sin ninguna imputación de tiempo registrada." style="font-size:0.7rem;color:#6b7280;opacity:0.6;flex-shrink:0" onclick="event.preventDefault()">&#9432;</span></span>
    </a>

    <a href="{{ $listUrl(['stat' => $stat === 'pte_imp' ? null : 'pte_imp', 'page' => null]) }}"
       class="tl-stat {{ $stat === 'pte_imp' ? 'active' : '' }}">
        <span class="tl-stat-num">{{ $pteImp }}</span>
        <span class="tl-stat-lbl">Pendientes de completar <span title="Tareas con al menos un responsable que aún no ha imputado tiempo." style="font-size:0.7rem;color:#6b7280;opacity:0.6;flex-shrink:0" onclick="event.preventDefault()">&#9432;</span></span>
    </a>

    <div class="tl-stat" style="cursor:default">
        <span class="tl-stat-num">{{ $totalTramite }}</span>
        <span class="tl-stat-lbl">En trámite <span title="Total de tareas activas (no borradas, no ocultas)." style="font-size:0.7rem;color:#6b7280;opacity:0.6;flex-shrink:0" onclick="event.preventDefault()">&#9432;</span></span>
    </div>

</div>

{{-- ── FILTROS ── --}}
<form method="GET" id="form-listado" class="flex gap-2 mb-4" x-data="{ modalFiltros: false }">

    @if(request('stat'))
        <input type="hidden" name="stat" value="{{ request('stat') }}">
    @endif

    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Buscar..."
           class="flex-1 max-w-xs text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">

    <button type="submit"
            class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">
        Buscar
    </button>

    {{-- Filtros --}}
    <button type="button" @click="modalFiltros = true"
            title="Filtros"
            class="p-1.5 rounded-lg border transition-colors
                {{ request()->hasAny(['f_propiedad','f_fecha_desde','f_fecha_hasta','f_responsable']) ? 'border-orange-400 text-orange-500 bg-orange-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M11 12h2M11 16h2"/>
        </svg>
    </button>

    @if(request('q') || request()->hasAny(['f_propiedad','f_fecha_desde','f_fecha_hasta','f_responsable']) || request('stat'))
    <a href="{{ $listUrl() }}"
       title="Limpiar filtros"
       class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </a>
    @endif

    {{-- Ocultos --}}
    <a href="{{ $listUrl(['ocultos' => request('ocultos') ? null : 1, 'page' => null]) }}"
       title="Ocultos"
       class="ml-auto p-1.5 rounded-lg border transition-colors {{ request('ocultos') ? 'border-amber-400 text-amber-500 bg-amber-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
        <i class="fas fa-eye-slash text-base leading-none"></i>
    </a>

    {{-- Borrados --}}
    <a href="{{ $listUrl(['borrados' => request('borrados') ? null : 1, 'page' => null]) }}"
       title="Borrados"
       class="p-1.5 rounded-lg border transition-colors {{ request('borrados') ? 'border-red-400 text-red-500 bg-red-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
        <i class="fas fa-trash text-base leading-none"></i>
    </a>

    {{-- Campos ocultos para preservar filtros al buscar --}}
    @foreach(['f_propiedad','f_fecha_desde','f_fecha_hasta','f_responsable'] as $fp)
        @if(request($fp))
            <input type="hidden" name="{{ $fp }}" value="{{ request($fp) }}">
        @endif
    @endforeach

    {{-- Modal de filtros --}}
    <div x-show="modalFiltros"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center"
         @click.self="modalFiltros = false"
         style="display:none">
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Filtros</h3>
                <button type="button" @click="modalFiltros = false"
                        class="text-gray-300 hover:text-gray-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Propiedad</label>
                    <select name="f_propiedad" class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <option value="">Todas</option>
                        @foreach($propiedades as $prop)
                            <option value="{{ $prop->id }}" {{ request('f_propiedad') == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Responsable</label>
                    <select name="f_responsable" class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <option value="">Todos</option>
                        @foreach($allUsuarios as $u)
                            <option value="{{ $u->id }}" {{ request('f_responsable') == $u->id ? 'selected' : '' }}>{{ $u->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Fecha desde</label>
                    <input type="date" name="f_fecha_desde" value="{{ request('f_fecha_desde') }}"
                           class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Fecha hasta</label>
                    <input type="date" name="f_fecha_hasta" value="{{ request('f_fecha_hasta') }}"
                           class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                <button type="button" @click="modalFiltros = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">Cancelar</button>
                <button type="submit" @click="modalFiltros = false"
                        class="px-4 py-2 text-sm bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors">Aplicar</button>
            </div>
        </div>
    </div>
</form>

{{-- ── TABLA / LISTADO ── --}}

{{-- ── TABLA ── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
@if($tareas->isEmpty())
    <div class="text-center py-16 text-gray-400">
        <i class="ti {{ $tipoIcon }} text-5xl mb-3 block"></i>
        <p class="text-sm">No hay tareas de {{ strtolower($tipoLabel) }} con los filtros aplicados.</p>
    </div>
@else
<div class="overflow-x-auto">
<table class="w-full text-xs">
    <thead>
        <tr class="border-b border-gray-200 bg-gray-50">
            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-left text-gray-400">Fecha</th>
            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-left text-gray-400">Propiedad</th>
            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-left text-gray-400">Nombre</th>
            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-center text-gray-400">Resp.</th>
            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-right text-gray-400">Tiempo</th>
            <th class="w-8"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    @foreach($tareas as $tarea)
    @php
        $cuIds    = json_decode($tarea->control_user ?? '[]', true) ?? [];
        $impIds   = json_decode($tarea->imp_user_ids, true) ?? [];
        $cuCount  = count($cuIds);
        $impCount = count(array_filter($cuIds, fn($uid) => in_array($uid, $impIds)));
        $vencida  = $tarea->fecha_planificada && $tarea->fecha_planificada < $today && $tarea->deleted == 0;
        $estado   = 'pendiente';
        if ($cuCount > 0 && $impCount === $cuCount) $estado = 'finalizado';
        elseif ($impCount > 0) $estado = 'parcial';
        $fechaFmt = $tarea->fecha_planificada ? \Carbon\Carbon::parse($tarea->fecha_planificada)->locale('es')->isoFormat('D/MMM') : '—';
        $formUrl  = route('vm.tarea', ['project'=>$project->slug,'tipo'=>$tipo,'id'=>$tarea->id]);
        $rowBg    = $tarea->deleted ? 'opacity-50' : ($tarea->hidden ? 'bg-amber-50/40' : '');
    @endphp
    <tr class="hover:bg-gray-50 cursor-pointer {{ $rowBg }}" onclick="window.location='{{ $formUrl }}'">

        {{-- Fecha + Estado --}}
        <td class="px-4 py-2 whitespace-nowrap">
            <div class="text-xs font-semibold text-gray-700">{{ $fechaFmt }}</div>
            @if($estado === 'finalizado')
                <span class="tl-badge" style="background:#d1fae5;color:#065f46">Finalizado</span>
            @elseif($estado === 'parcial')
                <span class="tl-badge" style="background:#fef3c7;color:#92400e">Parcial</span>
            @else
                <span class="tl-badge {{ $vencida ? 'vencida' : '' }}">{{ $vencida ? 'Vencida' : 'Pendiente' }}</span>
            @endif
        </td>

        {{-- Propiedad --}}
        <td class="px-4 py-2 text-gray-500 max-w-[140px]">
            <div class="line-clamp-2">{{ $tarea->propiedad_nombre ?? '—' }}</div>
        </td>

        {{-- Nombre --}}
        <td class="px-4 py-2 font-medium text-gray-800 max-w-xs">
            <div class="line-clamp-3">{{ $tarea->nombre }}</div>
            @if($tarea->deleted)<span class="tl-tag-borrado">Borrado</span>@endif
            @if($tarea->hidden && !$tarea->deleted)<span class="tl-tag-oculto">Oculto</span>@endif
            @if($tarea->blocked)<span class="tl-tag-bloq">Bloq.</span>@endif
        </td>

        {{-- Chips responsables --}}
        <td class="px-4 py-2">
            <div class="flex flex-wrap gap-1 justify-center">
                @forelse($cuIds as $uid)
                    @php
                        $hasImp   = in_array($uid, $impIds);
                        $dotColor = $hasImp ? '#1D9E75' : ($vencida ? '#E24B4A' : '#EF9F27');
                        $uNombre  = isset($usuariosMap[$uid]) ? $usuariosMap[$uid]->nombre : 'Usuario '.$uid;
                        $initials = collect(explode(' ', $uNombre))->filter()->take(2)->map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)))->implode('');
                    @endphp
                    <span class="tl-chip" style="background:{{ $dotColor }}" title="{{ $uNombre }}">{{ $initials }}</span>
                @empty
                    <span class="text-gray-300">—</span>
                @endforelse
            </div>
        </td>

        {{-- Tiempo --}}
        <td class="px-4 py-2 text-right font-semibold text-gray-700 whitespace-nowrap">
            {{ minToHmTl((int)$tarea->total_min) }}
        </td>

        {{-- Foto --}}
        <td class="px-4 py-2 text-center text-gray-400">
            @if($tarea->foto_count > 0)
                <i class="ti ti-camera" title="{{ $tarea->foto_count }} foto(s)"></i>
            @endif
        </td>

    </tr>
    @endforeach
    </tbody>
</table>
</div>

{{-- Pie: recuento + paginación --}}
@if($tareas->hasPages() || $tareas->total() > 0)
<div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-xs text-gray-400">
    <span>{{ $tareas->total() }} registros</span>
    {{ $tareas->links('partials.pagination') }}
</div>
@endif

@endif {{-- fin @if($tareas->isEmpty()) --}}
</div>

{{-- ── MODAL NUEVA TAREA ── --}}
@if($canEdit)
<div class="modal-overlay" id="modal-nueva-tarea"
     onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal" style="width:420px;max-width:95vw">
        <div class="modal-title">Nueva tarea de {{ strtolower($tipoLabel) }}</div>
        <form id="form-nueva-tarea" onsubmit="guardarNuevaTarea(event)">
            @csrf
            <div class="modal-body">
                <div class="field-group">
                    <label class="field-label">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" id="nt-nombre" required maxlength="255"
                           class="field-input" placeholder="Nombre de la tarea">
                </div>
                <div class="field-group">
                    <label class="field-label">Propiedad</label>
                    <select name="id_propiedades" id="nt-propiedad" class="field-input">
                        <option value="">Sin propiedad</option>
                        @foreach($propiedades as $prop)
                            <option value="{{ $prop->id }}">{{ $prop->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Fecha planificada</label>
                    <input type="date" name="fecha_planificada" id="nt-fecha" class="field-input">
                </div>
                <div class="field-group">
                    <label class="field-label">Responsables</label>
                    <div class="nt-chips-wrap" id="nt-chips-list">
                        @foreach($allUsuarios as $u)
                        <label class="nt-chip-label">
                            <input type="checkbox" name="control_user[]" value="{{ $u->id }}" class="sr-only nt-chip-cb">
                            <span class="nt-chip-text">{{ $u->nombre }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn"
                        onclick="document.getElementById('modal-nueva-tarea').classList.remove('open')">Cancelar</button>
                <button type="submit" id="nt-submit" class="btn" style="background:{{ $c['bd'] }};color:#fff">
                    Guardar y abrir
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── ESTILOS ── --}}
<style>
/* Modal base */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:14px;padding:20px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:360px;max-width:95vw}
.modal-title{font-size:1rem;font-weight:700;color:#1f2937}
.modal-body{margin-top:12px}
.modal-footer{display:flex;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid #f3f4f6;justify-content:flex-end}
.btn{padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:600;border:1px solid #e5e7eb;cursor:pointer;background:#f9fafb;color:#374151}
.btn:hover{background:#f3f4f6}
.field-group{margin-bottom:12px}
.field-label{display:block;font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:4px}
.field-input{width:100%;font-size:.82rem;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;outline:none}
.field-input:focus{border-color:#9ca3af;box-shadow:0 0 0 2px rgba(156,163,175,.2)}

/* Stats */
.tl-stat {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    min-width:100px;padding:10px 14px;border-radius:10px;
    border:1.5px solid #e5e7eb;background:#fff;text-decoration:none;
    transition:border-color .15s,box-shadow .15s;cursor:pointer;
}
.tl-stat:hover { border-color:#d1d5db;box-shadow:0 1px 4px rgba(0,0,0,.07); }
.tl-stat.active { border-color:{{ $c['bd'] }};background:{{ $c['bg'] }}; }
.tl-stat-num { font-size:1.35rem;font-weight:700;color:#111;line-height:1; }
.tl-stat-lbl { font-size:.7rem;color:#6b7280;margin-top:2px;text-align:center;white-space:nowrap; }

/* Chips avatar */
.tl-chip { display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;flex-shrink:0;font-size:.62rem;font-weight:700;color:#fff;letter-spacing:0; }

/* Estado badge */
.tl-badge { display:inline-block;font-size:.65rem;font-weight:600;padding:1px 6px;border-radius:10px;background:#f3f4f6;color:#6b7280;width:fit-content; }
.tl-badge.vencida { background:#fee2e2;color:#991b1b; }

/* Tags inline (borrado/oculto/bloq) */
.tl-tag-borrado,.tl-tag-oculto,.tl-tag-bloq { display:inline-block;font-size:.6rem;font-weight:700;border-radius:4px;padding:0 4px;margin-left:4px;vertical-align:middle; }
.tl-tag-borrado { background:#fee2e2;color:#991b1b; }
.tl-tag-oculto  { background:#fef3c7;color:#92400e; }
.tl-tag-bloq    { background:#e0e7ff;color:#3730a3; }

/* Modal nueva tarea */
.modal-overlay.open { display:flex !important; }
.nt-chips-wrap { display:flex;flex-wrap:wrap;gap:6px;margin-top:4px; }
.nt-chip-label { cursor:pointer; }
.nt-chip-cb:checked ~ .nt-chip-text { background:{{ $c['bd'] }};color:#fff;border-color:{{ $c['bd'] }}; }
.nt-chip-text { display:inline-block;font-size:.75rem;padding:3px 10px;border-radius:20px;border:1px solid #d1d5db;background:#f9fafb;color:#374151;transition:background .12s,color .12s; }
.nt-chip-text:hover { border-color:#9ca3af; }
</style>

<script>
async function guardarNuevaTarea(e) {
    e.preventDefault();
    var btn = document.getElementById('nt-submit');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    var form  = document.getElementById('form-nueva-tarea');
    var fd    = new FormData(form);
    var body  = {
        _token:            '{{ csrf_token() }}',
        nombre:            fd.get('nombre'),
        id_propiedades:    fd.get('id_propiedades') || null,
        fecha_planificada: fd.get('fecha_planificada') || null,
        control_user:      fd.getAll('control_user[]').map(Number),
    };

    try {
        var r = await fetch('{{ route("vm.tarea.store", ["project"=>$project->slug,"tipo"=>$tipo]) }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
            body: JSON.stringify(body),
        });
        var d = await r.json();
        if (d.ok) {
            window.location = '{{ route("vm.tarea", ["project"=>$project->slug,"tipo"=>$tipo,"id"=>"__ID__"]) }}'.replace('__ID__', d.id);
        } else {
            alert(d.message || 'Error al guardar');
            btn.disabled = false;
            btn.textContent = 'Guardar y abrir';
        }
    } catch(err) {
        alert('Error de red');
        btn.disabled = false;
        btn.textContent = 'Guardar y abrir';
    }
}
</script>

</x-app-layout>
