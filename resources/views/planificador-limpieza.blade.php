<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions">
    <a href="{{ route('listado', [$project->slug, 'tareas_limpieza']) }}"
       class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
        </svg>
        Listado
    </a>
</x-slot>

@php
    $diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $fechaTexto = $diasSemana[$fechaCarbon->dayOfWeek] . ', ' . $fechaCarbon->day . ' de ' . $meses[$fechaCarbon->month - 1];

    $fechaAnterior  = $fechaCarbon->copy()->subDay()->toDateString();
    $fechaSiguiente = $fechaCarbon->copy()->addDay()->toDateString();
    $urlAnterior    = route('planificador-limpieza', [$project->slug, 'fecha' => $fechaAnterior]);
    $urlSiguiente   = route('planificador-limpieza', [$project->slug, 'fecha' => $fechaSiguiente]);
    $urlHoy         = route('planificador-limpieza', $project->slug);

    $pxPerHora = 28;
    $COLORS    = ['#ea580c','#2563eb','#7c3aed','#0891b2','#16a34a','#dc2626','#d97706','#be185d','#0d9488','#6d28d9','#b45309','#4f46e5'];

    $usuariosConConfig = $usuarios->values()->map(function ($u, $idx) use ($COLORS) {
        $parts    = explode(' ', trim($u->nombre));
        $initials = strtoupper(substr($parts[0] ?? 'X', 0, 1) . substr($parts[1] ?? $parts[0] ?? 'X', 0, 1));
        return (object)[
            'id'       => $u->id,
            'nombre'   => $u->nombre,
            'initials' => $initials,
            'color'    => ($u->id_rol == 6) ? '#92400e' : $COLORS[$idx % count($COLORS)],
            'ext'      => ($u->id_rol == 6),
        ];
    });

    $TYPE_COLOR = ['Checkout' => '#ea580c', 'Cliente' => '#2563eb', 'Mantenimiento' => '#7c3aed'];

    // Distribuir tareas en 5 lanes (round-robin)
    $lanes = [[], [], [], [], []];
    foreach ($tareas as $i => $t) {
        $lanes[$i % 5][] = $t;
    }
@endphp

{{-- Navegador de fechas --}}
<div class="flex items-center gap-3 mb-5">
    <a href="{{ $urlAnterior }}"
       class="flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold text-gray-700">{{ $fechaTexto }}</h2>
        @if(!$esHoy)
        <a href="{{ $urlHoy }}"
           class="text-xs text-orange-500 hover:text-orange-600 border border-orange-200 hover:border-orange-300 rounded-md px-2 py-0.5 transition-colors">
            Hoy
        </a>
        @endif
        <span class="text-xs text-gray-400">· {{ $tareas->count() }} {{ $tareas->count() === 1 ? 'tarea' : 'tareas' }}</span>
    </div>
    <a href="{{ $urlSiguiente }}"
       class="flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<div style="display:flex;gap:10px;align-items:flex-start;">

    {{-- COLUMNA EQUIPO --}}
    <div style="width:148px;flex-shrink:0;">
        <p class="text-xs font-medium text-gray-400 mb-2 uppercase tracking-wide">Equipo</p>
        <div style="display:flex;flex-direction:column;gap:6px;">
            @foreach($usuariosConConfig as $u)
            <div class="cleaner-card"
                 draggable="true"
                 data-cleaner-id="{{ $u->id }}"
                 data-cleaner-name="{{ $u->nombre }}"
                 data-cleaner-initials="{{ $u->initials }}"
                 data-cleaner-color="{{ $u->color }}"
                 data-cleaner-ext="{{ $u->ext ? '1' : '0' }}"
                 style="display:flex;align-items:center;gap:7px;background:{{ $u->ext ? '#fffbeb' : 'white' }};border:0.5px solid {{ $u->ext ? '#fbbf24' : '#e5e7eb' }};border-radius:8px;padding:6px 8px;cursor:grab;user-select:none;">
                <div style="width:24px;height:24px;border-radius:50%;background:{{ $u->color }};display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:500;color:white;flex-shrink:0;">{{ $u->initials }}</div>
                <span style="font-size:11px;font-weight:500;color:#374151;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $u->nombre }}</span>
                <span data-cleaner-count="{{ $u->id }}"
                      style="min-width:17px;height:17px;padding:0 3px;border-radius:9px;background:#f3f4f6;color:#6b7280;font-size:10px;font-weight:500;display:flex;align-items:center;justify-content:center;flex-shrink:0;">0</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 5 LANES KANBAN --}}
    <div style="flex:1;min-width:0;display:flex;gap:10px;align-items:flex-start;">
        @foreach($lanes as $laneIdx => $laneTareas)
        <div class="task-lane"
             style="flex:1;min-width:0;display:flex;flex-direction:column;gap:10px;min-height:80px;border-radius:8px;padding:2px;">
            @foreach($laneTareas as $tarea)
            @php
                $horas    = max(1, (int)($tarea->tiempo_limpieza ?? 5));
                $h        = $horas * $pxPerHora;
                $barColor = $TYPE_COLOR[$tarea->tipo ?? ''] ?? '#6b7280';
                $assigned = [];
                if ($tarea->control_user) {
                    $decoded = json_decode($tarea->control_user, true);
                    if (is_array($decoded)) $assigned = $decoded;
                }
                $assignedStr = json_encode($assigned);
            @endphp
            <div class="task-card"
                 data-task-id="{{ $tarea->id }}"
                 data-horas="{{ $horas }}"
                 data-assigned='{{ $assignedStr }}'
                 data-assign-url="{{ route('planificador-limpieza.asignar', [$project->slug, $tarea->id]) }}"
                 style="position:relative;height:{{ $h }}px;background:white;border:0.5px solid #e5e7eb;border-radius:8px;overflow:hidden;box-sizing:border-box;display:flex;flex-direction:column;">

                {{-- Barra lateral tipo --}}
                <div style="position:absolute;left:0;top:0;bottom:0;width:3px;background:{{ $barColor }};border-radius:3px 0 0 3px;"></div>

                {{-- Contenido --}}
                <div style="flex:1;display:flex;flex-direction:column;padding:9px 8px 8px 11px;gap:4px;min-height:0;">

                    {{-- Fila 1: nombre + duración --}}
                    <div style="display:flex;align-items:baseline;gap:4px;">
                        <span style="flex:1;font-size:11px;font-weight:600;color:#111827;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"
                              title="{{ $tarea->propiedad }}">{{ $tarea->propiedad }}</span>
                        <span style="font-size:10px;color:#9ca3af;white-space:nowrap;flex-shrink:0;padding-right:2px;">{{ $horas }} h</span>
                    </div>

                    {{-- Filas 2+: tipo+estado (izq) y chips (der) a la misma altura --}}
                    <div style="display:flex;flex:1;gap:4px;min-height:0;padding-top:1px;">
                        <div style="width:50%;flex-shrink:0;display:flex;flex-direction:column;gap:3px;">
                            <span style="font-size:10px;border-radius:20px;padding:1px 7px;background:{{ $barColor }}18;color:{{ $barColor }};align-self:flex-start;">{{ $tarea->tipo ?? '—' }}</span>
                            @if($tarea->estado)
                            <span style="font-size:10px;color:#9ca3af;font-style:italic;">{{ $tarea->estado }}</span>
                            @endif
                        </div>
                        <div class="task-drop-zone"
                             style="flex:1;display:flex;flex-direction:column;gap:3px;justify-content:flex-start;">
                            <span class="drop-hint"
                                  style="font-size:9px;color:#d1d5db;border:0.5px dashed #e5e7eb;border-radius:4px;padding:2px 5px;align-self:flex-start;{{ count($assigned) > 0 ? 'display:none;' : '' }}">···</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>

</div>

@if($tareas->isEmpty())
<div class="flex flex-col items-center justify-center py-16 text-gray-300">
    <svg class="w-10 h-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    <p class="text-sm">No hay tareas de limpieza para este día</p>
</div>
@endif

<meta name="csrf-token" content="{{ csrf_token() }}">

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // ── Mapa de limpiadores ─────────────────────────────────────
    const cleanerMap = {};
    document.querySelectorAll('.cleaner-card').forEach(el => {
        const id = el.dataset.cleanerId;
        cleanerMap[id] = {
            id:       id,
            name:     el.dataset.cleanerName,
            initials: el.dataset.cleanerInitials,
            color:    el.dataset.cleanerColor,
            ext:      el.dataset.cleanerExt === '1',
        };
    });

    // ── Estado inicial ──────────────────────────────────────────
    const state     = {};
    const taskHours = {};
    document.querySelectorAll('.task-card').forEach(card => {
        const tid      = card.dataset.taskId;
        const assigned = JSON.parse(card.dataset.assigned || '[]');
        state[tid]     = new Set(assigned.map(String));
        taskHours[tid] = parseInt(card.dataset.horas) || 0;
        if (state[tid].size > 0) renderCard(tid, card);
    });
    updateCounts();

    // ── Flag drag de limpiador ──────────────────────────────────
    let cleanerDragId = null;

    // ── SortableJS en cada lane ─────────────────────────────────
    const lanes = document.querySelectorAll('.task-lane');
    const sortables = [];
    lanes.forEach(lane => {
        const s = Sortable.create(lane, {
            group:      'tasks',
            animation:  160,
            ghostClass: 'task-ghost',
            dragClass:  'task-dragging',
            onStart() { document.body.classList.add('is-sorting'); },
            onEnd()   { document.body.classList.remove('is-sorting'); },
        });
        sortables.push(s);
    });

    // ── Drag limpiador: deshabilita Sortable ────────────────────
    document.querySelectorAll('.cleaner-card').forEach(el => {
        el.addEventListener('dragstart', e => {
            cleanerDragId = el.dataset.cleanerId;
            el.style.opacity = '0.45';
            e.dataTransfer.effectAllowed = 'copy';
            sortables.forEach(s => s.option('disabled', true));
        });
        el.addEventListener('dragend', () => {
            el.style.opacity = '';
            cleanerDragId = null;
            sortables.forEach(s => s.option('disabled', false));
            document.querySelectorAll('.task-card').forEach(c => c.style.outline = '');
        });
    });

    // ── Drop limpiador sobre tarjeta (delegado en body) ─────────
    document.addEventListener('dragover', e => {
        if (!cleanerDragId) return;
        const card = e.target.closest('.task-card');
        document.querySelectorAll('.task-card').forEach(c => c.style.outline = '');
        if (card) { e.preventDefault(); card.style.outline = '2px solid #ea580c'; }
    });

    document.addEventListener('drop', e => {
        if (!cleanerDragId) return;
        const card = e.target.closest('.task-card');
        if (!card) return;
        e.preventDefault();
        card.style.outline = '';
        const tid = card.dataset.taskId;
        if (!state[tid]) state[tid] = new Set();
        if (state[tid].has(cleanerDragId)) return;
        state[tid].add(cleanerDragId);
        saveAndRender(tid, card);
    });

    // ── Guardar ─────────────────────────────────────────────────
    function saveAndRender(tid, card) {
        fetch(card.dataset.assignUrl, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body:    JSON.stringify({ cleaners: [...state[tid]] }),
        }).catch(() => {});
        renderCard(tid, card);
        updateCounts();
    }

    // ── Renderizar chips en la mitad derecha ────────────────────
    function renderCard(tid, card) {
        const cleaners = [...state[tid]].map(id => cleanerMap[id]).filter(Boolean);
        const hasInt   = cleaners.some(c => !c.ext);

        card.style.background  = !cleaners.length ? 'white' : (hasInt ? '#f0fdf4' : '#fffbeb');
        card.style.borderColor = !cleaners.length ? '#e5e7eb' : (hasInt ? '#86efac' : '#fbbf24');

        const zone = card.querySelector('.task-drop-zone');
        zone.innerHTML = '';

        if (!cleaners.length) {
            const hint = document.createElement('span');
            hint.className = 'drop-hint';
            hint.style.cssText = 'font-size:9px;color:#d1d5db;border:0.5px dashed #e5e7eb;border-radius:4px;padding:2px 5px;align-self:flex-start;';
            hint.textContent = '···';
            zone.appendChild(hint);
            return;
        }

        cleaners.forEach(c => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;align-items:center;gap:4px;overflow:hidden;max-width:100%;';

            const avatar = document.createElement('div');
            avatar.style.cssText = `position:relative;width:18px;height:18px;border-radius:50%;background:${c.color};display:flex;align-items:center;justify-content:center;font-size:7px;color:white;font-weight:500;flex-shrink:0;`;
            avatar.textContent = c.initials;

            const xBtn = document.createElement('span');
            xBtn.dataset.rmTask    = tid;
            xBtn.dataset.rmCleaner = c.id;
            xBtn.style.cssText = 'position:absolute;top:-3px;right:-3px;width:11px;height:11px;border-radius:50%;background:#ef4444;color:white;font-size:8px;line-height:11px;text-align:center;cursor:pointer;display:none;';
            xBtn.textContent = '×';
            avatar.appendChild(xBtn);

            const name = document.createElement('span');
            name.style.cssText = 'font-size:10px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;';
            name.textContent = c.name.split(' ').slice(0, 2).join(' ');

            wrap.appendChild(avatar);
            wrap.appendChild(name);
            wrap.addEventListener('mouseenter', () => xBtn.style.display = 'block');
            wrap.addEventListener('mouseleave', () => xBtn.style.display = 'none');
            zone.appendChild(wrap);
        });
    }

    // ── Quitar limpiadora ───────────────────────────────────────
    document.addEventListener('click', e => {
        const tid = e.target.dataset.rmTask;
        const cid = e.target.dataset.rmCleaner;
        if (!tid || !cid) return;
        state[tid].delete(cid);
        const card = document.querySelector(`.task-card[data-task-id="${tid}"]`);
        if (card) saveAndRender(tid, card);
    });

    // ── Contadores (horas repartidas por nº de asignados) ───────
    function updateCounts() {
        const hours = {};
        Object.entries(state).forEach(([tid, set]) => {
            if (!set.size) return;
            const share = (taskHours[tid] || 0) / set.size;
            set.forEach(id => { hours[id] = (hours[id] || 0) + share; });
        });
        document.querySelectorAll('[data-cleaner-count]').forEach(badge => {
            const h = hours[badge.dataset.cleanerCount] || 0;
            const display = h > 0 ? (Number.isInteger(h) ? h : h.toFixed(1)) + 'h' : '0';
            badge.textContent      = display;
            badge.style.background = h > 0 ? '#ea580c' : '#f3f4f6';
            badge.style.color      = h > 0 ? 'white'   : '#6b7280';
        });
    }

})();
</script>

<style>
.task-ghost    { opacity:0.3; border:1.5px dashed #ea580c !important; background:#fff7ed !important; }
.task-dragging { box-shadow:0 6px 20px rgba(0,0,0,0.13); opacity:0.96; cursor:grabbing !important; }
.is-sorting .cleaner-card { pointer-events:none; }
</style>

</x-app-layout>
