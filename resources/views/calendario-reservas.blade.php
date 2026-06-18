<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
    $hoy   = now()->toDateString();
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

<div class="mb-4 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-500">Días:</label>
            <select name="dias" onchange="this.form.submit()"
                    class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                @foreach([14, 21, 30, 45, 60] as $d)
                    <option value="{{ $d }}" {{ $dias == $d ? 'selected' : '' }}>{{ $d }} días</option>
                @endforeach
            </select>
        </form>
        <div class="flex items-center gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span style="width:14px;height:10px;border-radius:2px;background:#86efac;display:inline-block;"></span>En curso</span>
            <span class="flex items-center gap-1.5"><span style="width:14px;height:10px;border-radius:2px;background:#93c5fd;display:inline-block;"></span>Confirmada</span>
            <span class="flex items-center gap-1.5"><span style="width:14px;height:10px;border-radius:2px;background:#fde68a;display:inline-block;"></span>Solicitada</span>
        </div>
    </div>
    <span class="text-xs text-gray-400">{{ $propiedades->count() }} propiedades · {{ $reservasPorPropiedad->flatten()->count() }} reservas</span>
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
                $fecha  = now()->addDays($d);
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
            <td colspan="{{ $dias }}" style="padding:0;position:relative;height:{{ $rowH }}px;max-height:{{ $rowH }}px;overflow:hidden;font-size:0;line-height:0;">
                {{-- Fondo de celdas --}}
                @for($d = 0; $d < $dias; $d++)
                    @php
                        $fecha = now()->addDays($d);
                        $isHoy = $fecha->toDateString() === $hoy;
                        $isWE  = in_array($fecha->dayOfWeek, [0, 6]);
                    @endphp
                    <span style="display:inline-block;width:{{ $colW }}px;height:{{ $rowH }}px;vertical-align:top;box-sizing:border-box;border-right:0.5px solid #f3f4f6;border-bottom:0.5px solid #f3f4f6;background:{{ $isHoy ? '#fff7ed' : ($isWE ? '#fafafa' : 'white') }};"></span>
                @endfor

                {{-- Barras de reservas --}}
                @foreach($resPropiedad as $r)
                    @php
                        $checkin  = \Carbon\Carbon::parse($r->check_in_date);
                        $checkout = \Carbon\Carbon::parse($r->check_out_date);
                        $startDay = (int) now()->startOfDay()->diffInDays($checkin->startOfDay(), false);
                        $endDay   = (int) now()->startOfDay()->diffInDays($checkout->startOfDay(), false);
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
                        $fechaStr     = now()->addDays($d)->toDateString();
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
                           title="{{ $cfg['title'] }}"
                           style="position:absolute;top:7px;left:{{ $left }}px;width:14px;height:14px;display:flex;align-items:center;justify-content:center;text-decoration:none;z-index:1;">
                            @if($cat === 'limpieza')
                                <span style="width:14px;height:14px;border-radius:50%;background:{{ $cfg['circle'] }};display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-spray-can-sparkles" style="font-size:8px;color:white;"></i>
                                </span>
                            @else
                                <i class="fa-solid fa-wrench" style="font-size:11px;color:#b45309;"></i>
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

</x-app-layout>
