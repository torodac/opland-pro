<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
    $hoy        = now()->toDateString();
    $desdeC     = \Carbon\Carbon::parse($desde);
    $mesPrev    = $desdeC->copy()->subMonth()->startOfMonth()->toDateString();
    $mesSig     = $desdeC->copy()->addMonth()->startOfMonth()->toDateString();
    $mesLabel   = $desdeC->isoFormat('MMMM YYYY');
    $mesValue   = $desdeC->format('Y-m');
    $doW   = ['D','L','M','X','J','V','S'];
    $colW  = 22;
    $propW = 160;

    // Color del círculo según tipo de limpieza; mantenimiento usa llave inglesa sin círculo
    $tareaConfig = [
        'limpieza' => [
            'Checkout'      => ['circle' => '#ea580c', 'title' => 'Limpieza checkout'],
            'Cliente'       => ['circle' => '#2563eb', 'title' => 'Limpieza cliente'],
            'Mantenimiento' => ['circle' => '#7c3aed', 'title' => 'Mantenimiento (limpieza)'],
            '_default'      => ['circle' => '#6b7280', 'title' => 'Limpieza'],
        ],
        'mantenimiento' => [
            '_default'      => ['title' => 'Mantenimiento'],
        ],
    ];
@endphp

@php
    $urlBase          = request()->fullUrlWithQuery(['salidas' => null, 'page' => null]);
    $urlSalidasHoy    = request()->fullUrlWithQuery(['salidas' => 'hoy',    'page' => null]);
    $urlSalidasManana = request()->fullUrlWithQuery(['salidas' => 'manana', 'page' => null]);
@endphp

{{-- Fila 1: Días + Stats + conteo --}}
<div class="mb-2 flex items-center gap-3">
    <form method="GET" class="flex items-center gap-2 shrink-0">
        <input type="hidden" name="desde" value="{{ $desde }}">
        <label class="text-sm text-gray-500">Días:</label>
        <select name="dias" onchange="this.form.submit()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            @foreach([14, 21, 30, 45, 60] as $d)
                <option value="{{ $d }}" {{ $dias == $d ? 'selected' : '' }}>{{ $d }} días</option>
            @endforeach
        </select>
    </form>

    {{-- Navegador de meses --}}
    <div class="flex items-center gap-1 shrink-0">
        <a href="{{ request()->fullUrlWithQuery(['desde' => $mesPrev]) }}"
           class="flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-colors text-gray-500"
           title="Mes anterior" style="text-decoration:none;">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <form method="GET" class="flex items-center">
            <input type="hidden" name="dias" value="{{ $dias }}">
            <input type="hidden" name="desde" id="cal-desde-hidden">
            <input type="month" name="desde_mes" value="{{ $mesValue }}"
                   onchange="document.getElementById('cal-desde-hidden').value=this.value+'-01';this.form.submit();"
                   style="display:none;" id="cal-month-picker">
            <button type="button" onclick="var p=document.getElementById('cal-month-picker');if(p.showPicker){p.showPicker();}else{p.click();}"
                    class="px-3 py-1 text-sm font-semibold text-gray-700 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition-colors capitalize"
                    style="min-width:140px;">
                {{ $mesLabel }}
            </button>
        </form>
        <a href="{{ request()->fullUrlWithQuery(['desde' => $mesSig]) }}"
           class="flex items-center justify-center w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-colors text-gray-500"
           title="Mes siguiente" style="text-decoration:none;">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        @if($desde !== now()->toDateString())
        <a href="{{ request()->fullUrlWithQuery(['desde' => now()->toDateString()]) }}"
           class="px-2 py-1 text-xs text-orange-600 border border-orange-200 rounded-lg bg-orange-50 hover:bg-orange-100 transition-colors"
           style="text-decoration:none;">Hoy</a>
        @endif
    </div>

    <a href="{{ $salidasFiltro === 'hoy' ? $urlBase : $urlSalidasHoy }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-1.5 transition-all cursor-pointer select-none {{ $salidasFiltro === 'hoy' ? 'border-orange-400 bg-orange-50 ring-1 ring-orange-300' : 'border-gray-200 bg-white hover:border-orange-300 hover:bg-orange-50' }}"
       style="text-decoration:none;">
        <div class="flex items-center justify-center w-6 h-6 rounded-md {{ $salidasFiltro === 'hoy' ? 'bg-orange-500' : 'bg-orange-100' }}">
            <svg class="w-3.5 h-3.5 {{ $salidasFiltro === 'hoy' ? 'text-white' : 'text-orange-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H2.25"/></svg>
        </div>
        <span class="text-sm font-bold {{ $salidasFiltro === 'hoy' ? 'text-orange-600' : 'text-gray-800' }}">{{ $salidasHoy }}</span>
        <span class="text-xs {{ $salidasFiltro === 'hoy' ? 'text-orange-500 font-medium' : 'text-gray-400' }}">Salidas hoy</span>
    </a>

    <a href="{{ $salidasFiltro === 'manana' ? $urlBase : $urlSalidasManana }}"
       class="flex items-center gap-2 rounded-lg border px-3 py-1.5 transition-all cursor-pointer select-none {{ $salidasFiltro === 'manana' ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-300' : 'border-gray-200 bg-white hover:border-blue-300 hover:bg-blue-50' }}"
       style="text-decoration:none;">
        <div class="flex items-center justify-center w-6 h-6 rounded-md {{ $salidasFiltro === 'manana' ? 'bg-blue-500' : 'bg-blue-100' }}">
            <svg class="w-3.5 h-3.5 {{ $salidasFiltro === 'manana' ? 'text-white' : 'text-blue-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H2.25"/></svg>
        </div>
        <span class="text-sm font-bold {{ $salidasFiltro === 'manana' ? 'text-blue-600' : 'text-gray-800' }}">{{ $salidasManana }}</span>
        <span class="text-xs {{ $salidasFiltro === 'manana' ? 'text-blue-500 font-medium' : 'text-gray-400' }}">Salidas mañana</span>
    </a>

    <span class="ml-auto text-xs text-gray-400 shrink-0">{{ $propiedades->count() }} propiedades · {{ $reservasPorPropiedad->flatten()->count() }} reservas</span>
</div>

{{-- Fila 2: info + leyenda --}}
<div class="mb-4 flex items-center justify-between gap-4">
    <span class="flex items-center gap-1 text-xs text-gray-400">
        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01"/></svg>
        Tareas de checkout generadas automáticamente para los próximos 7 días
    </span>
    <div class="flex items-center gap-3 text-xs text-gray-500 shrink-0">
        @php
            $legendItems = $legend ?? [
                ['color' => '#86efac', 'label' => 'En curso'],
                ['color' => '#93c5fd', 'label' => 'Confirmada'],
                ['color' => '#fde68a', 'label' => 'Solicitada'],
            ];
        @endphp
        @foreach($legendItems as $item)
            <span class="flex items-center gap-1.5"><span style="width:14px;height:10px;border-radius:2px;background:{{ $item['color'] }};display:inline-block;"></span>{{ $item['label'] }}</span>
        @endforeach
    </div>
</div>

<div class="overflow-x-auto rounded-lg border border-gray-200">
<table style="border-collapse:collapse;table-layout:fixed;width:{{ $propW + $colW * $dias }}px;">

    {{-- Cabecera días --}}
    <thead>
    <tr>
        <th style="width:{{ $propW }}px;min-width:{{ $propW }}px;position:sticky;left:0;background:#f9fafb;z-index:3;border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:0 10px;height:40px;text-align:left;">
            <span class="text-xs font-medium text-gray-400">Propiedad</span>
        </th>
        @for($d = 0; $d < $dias; $d++)
            @php
                $fecha  = $desdeC->copy()->addDays($d);
                $isHoy  = $fecha->toDateString() === $hoy;
                $isWE   = in_array($fecha->dayOfWeek, [0, 6]);
            @endphp
            <th style="width:{{ $colW }}px;min-width:{{ $colW }}px;padding:0;border-right:0.5px solid #e5e7eb;border-bottom:1px solid #e5e7eb;text-align:center;height:40px;vertical-align:bottom;padding-bottom:4px;background:{{ $isHoy ? '#fff7ed' : ($isWE ? '#f9fafb' : 'white') }};">
                <span style="display:block;font-size:11px;font-weight:{{ $isHoy ? '700' : '500' }};color:{{ $isHoy ? '#ea580c' : '#6b7280' }};">{{ $fecha->format('d') }}</span>
                <span style="display:block;font-size:9px;color:{{ $isHoy ? '#ea580c' : '#9ca3af' }};">{{ $doW[$fecha->dayOfWeek] }}</span>
            </th>
        @endfor
    </tr>
    </thead>

    {{-- Filas por propiedad --}}
    <tbody>
    @foreach($propiedades as $propiedad)
        @php
            $resPropiedad = $reservasPorPropiedad[$propiedad] ?? collect();
            $tareasFecha  = $tareasPorPropiedad[$propiedad] ?? collect();
            $rowH         = 32;
        @endphp
        <tr>
            {{-- Nombre propiedad --}}
            <td style="position:sticky;left:0;background:white;z-index:2;border-right:1px solid #e5e7eb;border-bottom:0.5px solid #f3f4f6;padding:0;width:{{ $propW }}px;min-width:{{ $propW }}px;height:{{ $rowH }}px;overflow:hidden;">
                <div style="height:{{ $rowH }}px;display:flex;align-items:center;padding:0 10px;overflow:hidden;">
                    <span style="font-size:11px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $propiedad }}">{{ $propiedad }}</span>
                </div>
            </td>

            {{-- Celdas días --}}
            <td colspan="{{ $dias }}" style="padding:0;position:relative;height:{{ $rowH }}px;overflow:hidden;">
                {{-- Fondo de celdas (grid absoluto, sin inline-block) --}}
                <div style="position:absolute;inset:0;display:grid;grid-template-columns:repeat({{ $dias }},{{ $colW }}px);">
                @for($d = 0; $d < $dias; $d++)
                    @php
                        $fecha = $desdeC->copy()->addDays($d);
                        $isHoy = $fecha->toDateString() === $hoy;
                        $isWE  = in_array($fecha->dayOfWeek, [0, 6]);
                    @endphp
                    <div style="border-right:0.5px solid #f3f4f6;border-bottom:0.5px solid #f3f4f6;background:{{ $isHoy ? '#fff7ed' : ($isWE ? '#fafafa' : 'white') }};"></div>
                @endfor
                </div>

                {{-- Barras de reservas --}}
                @foreach($resPropiedad as $r)
                    @php
                        $checkin  = \Carbon\Carbon::parse($r->check_in_date);
                        $checkout = \Carbon\Carbon::parse($r->check_out_date);
                        $startDay = (int) $desdeC->copy()->startOfDay()->diffInDays($checkin->startOfDay(), false);
                        $endDay   = (int) $desdeC->copy()->startOfDay()->diffInDays($checkout->startOfDay(), false);
                        $s = max(0, $startDay);
                        $e = min($dias, $endDay);
                        if ($s >= $e) continue;
                        $left  = $s * $colW;
                        $width = ($e - $s) * $colW - 2;
                        $color = match($r->booking_status) {
                            'arrived'   => ['bg' => '#86efac', 'text' => '#14532d'],
                            'requested' => ['bg' => '#fde68a', 'text' => '#78350f'],
                            default     => ['bg' => '#93c5fd', 'text' => '#1e3a5f'],
                        };
                        $nombre = explode(' ', trim($r->guest_name))[0];
                    @endphp
                    <a href="{{ route('ficha', [$project->slug, 'reservas', $r->id]) }}"
                       title="{{ $r->guest_name }} · {{ $checkin->format('d/m') }} → {{ $checkout->format('d/m') }}"
                       style="position:absolute;top:3px;left:{{ $left }}px;width:{{ $width }}px;height:22px;border-radius:3px;background:{{ $color['bg'] }};display:flex;align-items:center;padding:0 5px;box-sizing:border-box;text-decoration:none;overflow:hidden;">
                        <span style="font-size:10px;font-weight:500;color:{{ $color['text'] }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $nombre }}</span>
                    </a>
                @endforeach

                {{-- Iconos de tareas (misma fila, dentro de las celdas) --}}
                @for($d = 0; $d < $dias; $d++)
                    @php
                        $fechaStr     = $desdeC->copy()->addDays($d)->toDateString();
                        $tareasDelDia = $tareasFecha[$fechaStr] ?? collect();
                    @endphp
                    @foreach($tareasDelDia as $tarea)
                        @php
                            $cat   = $tarea->categoria;
                            $tipo  = $tarea->tipo ?? '_default';
                            $cfg   = $tareaConfig[$cat][$tipo] ?? $tareaConfig[$cat]['_default'] ?? ['circle'=>'#9ca3af','title'=>$tipo];
                            $left  = $d * $colW + 2 + ($loop->index * 15);
                            $tabla = $cat === 'limpieza' ? 'tareas_limpieza' : 'tareas_mantenimiento';
                        @endphp
                        <a href="{{ route('ficha', [$project->slug, $tabla, $tarea->id]) }}"
                           data-tip-nombre="{{ $tarea->nombre }}"
                           data-tip-tipo="{{ $cat === 'limpieza' ? ($tarea->tipo ?? '') : '' }}"
                           data-tip-fecha="{{ \Carbon\Carbon::parse($tarea->fecha_planificada)->isoFormat('dddd D [de] MMMM') }}"
                           data-tip-propiedad="{{ $tarea->propiedad }}"
                           data-tip-cat="{{ $cat }}"
                           data-tip-color="{{ $cat === 'limpieza' ? ($cfg['circle'] ?? '#6b7280') : '#6b7c3a' }}"
                           class="cal-tip"
                           style="position:absolute;top:6px;left:{{ $left }}px;width:16px;height:16px;display:flex;align-items:center;justify-content:center;text-decoration:none;z-index:1;">
                            @if($cat === 'limpieza')
                                <span style="width:16px;height:16px;border-radius:50%;background:{{ $cfg['circle'] }};display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-broom" style="font-size:9px;color:white;"></i>
                                </span>
                            @else
                                <span style="width:16px;height:16px;border-radius:50%;background:#6b7c3a;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-wrench" style="font-size:9px;color:white;"></i>
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endfor
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

{{-- Tooltip custom --}}
<div id="cal-tooltip" style="display:none;position:fixed;z-index:9999;pointer-events:none;min-width:200px;max-width:260px;background:white;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.14),0 1px 4px rgba(0,0,0,0.08);overflow:hidden;">
    <div id="cal-tip-header" style="padding:8px 12px 6px;display:flex;align-items:center;gap:8px;">
        <span id="cal-tip-icon" style="width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i id="cal-tip-icon-i" style="font-size:11px;color:white;"></i>
        </span>
        <span id="cal-tip-nombre" style="font-size:12px;font-weight:600;color:#111827;line-height:1.3;"></span>
    </div>
    <div style="height:1px;background:#f3f4f6;margin:0 12px;"></div>
    <div style="padding:8px 12px 10px;display:flex;flex-direction:column;gap:5px;">
        <div id="cal-tip-tipo-row" style="display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-tag" style="font-size:10px;color:#9ca3af;width:12px;text-align:center;"></i>
            <span id="cal-tip-tipo" style="font-size:11px;color:#6b7280;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-calendar" style="font-size:10px;color:#9ca3af;width:12px;text-align:center;"></i>
            <span id="cal-tip-fecha" style="font-size:11px;color:#6b7280;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-house" style="font-size:10px;color:#9ca3af;width:12px;text-align:center;"></i>
            <span id="cal-tip-propiedad" style="font-size:11px;color:#6b7280;"></span>
        </div>
    </div>
</div>

<script>
(function() {
    const tip    = document.getElementById('cal-tooltip');
    const header = document.getElementById('cal-tip-header');
    const icon   = document.getElementById('cal-tip-icon');
    const iconI  = document.getElementById('cal-tip-icon-i');
    let hideTimer;

    document.querySelectorAll('.cal-tip').forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            clearTimeout(hideTimer);
            const cat      = this.dataset.tipCat;
            const color    = this.dataset.tipColor;
            const nombre   = this.dataset.tipNombre;
            const tipo     = this.dataset.tipTipo;
            const fecha    = this.dataset.tipFecha;
            const propiedad = this.dataset.tipPropiedad;

            icon.style.background = color;
            iconI.className = cat === 'limpieza'
                ? 'fa-solid fa-broom'
                : 'fa-solid fa-wrench';
            header.style.background = color + '18';

            document.getElementById('cal-tip-nombre').textContent    = nombre;
            document.getElementById('cal-tip-fecha').textContent      = fecha;
            document.getElementById('cal-tip-propiedad').textContent  = propiedad;

            const tipoRow = document.getElementById('cal-tip-tipo-row');
            if (tipo) {
                document.getElementById('cal-tip-tipo').textContent = tipo;
                tipoRow.style.display = 'flex';
            } else {
                tipoRow.style.display = 'none';
            }

            tip.style.display = 'block';
            positionTip(e);
        });

        el.addEventListener('mousemove', positionTip);

        el.addEventListener('mouseleave', function() {
            hideTimer = setTimeout(() => tip.style.display = 'none', 120);
        });
    });

    function positionTip(e) {
        const margin = 12;
        const tw = tip.offsetWidth;
        const th = tip.offsetHeight;
        let x = e.clientX + margin;
        let y = e.clientY + margin;
        if (x + tw > window.innerWidth  - margin) x = e.clientX - tw - margin;
        if (y + th > window.innerHeight - margin) y = e.clientY - th - margin;
        tip.style.left = x + 'px';
        tip.style.top  = y + 'px';
    }
})();
</script>

</x-app-layout>
