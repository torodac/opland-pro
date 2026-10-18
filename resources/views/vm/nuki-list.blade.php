@php
    $hoyUrl = fn(array $extra = []) => route('vm.nuki_list', array_filter(array_merge(
        ['project' => $project->slug, 'q' => $q, 'filtro' => $filtro], $extra
    ), fn($v) => $v !== '' && $v !== null));
@endphp

<x-app-layout
    :breadcrumb="[
        ['label' => 'Gestión Nuki', 'url' => ''],
    ]"
    :project="$project">

<div style="padding:0 0 3rem;">

    <style>
        .nk-kpi{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:12px 14px;text-decoration:none;color:inherit;display:block}
        .nk-kpi:hover{background:rgba(0,0,0,.02)}
        .nk-kpi.on{outline:2px solid #f97316;outline-offset:-2px}
        .nk-kpi .n{font-size:24px;font-weight:600;line-height:1.1;font-variant-numeric:tabular-nums}
        .nk-kpi .l{font-size:12px;color:#777;margin-top:2px}
        .nk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
        .nk-card{border-radius:14px;border:0.5px solid rgba(0,0,0,.08);background:#fff;padding:14px 16px;text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:10px;transition:transform .12s,box-shadow .12s}
        .nk-card:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.08)}
        .nk-card--baja{background:#fdecec;border-color:#f3b4b4}
        .nk-card--sin_propiedad{background:#fff8d6;border-color:#eed77a}
        .nk-card--sin_dato{background:#f4f4f5}
        .nk-card .prop{font-size:15px;font-weight:600;line-height:1.25}
        .nk-card .cer{font-size:11px;color:#888}
        .nk-pct{font-size:30px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums}
        .nk-bar{height:6px;border-radius:99px;background:rgba(0,0,0,.08);overflow:hidden}
        .nk-bar>i{display:block;height:100%;border-radius:99px}
        .nk-badge{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px}
        .nk-badge--baja{background:#dc2626;color:#fff}
        .nk-badge--sin{background:#eab308;color:#422006}
        .nk-badge--ok{background:#e7f6ec;color:#166534}
        .nk-meta{font-size:11px;color:#666;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}
        .nk-chip{font-size:12px;padding:5px 10px;border-radius:99px;border:0.5px solid rgba(0,0,0,.12);text-decoration:none;color:#555;background:#fff}
        .nk-chip.on{background:#f97316;border-color:#f97316;color:#fff}
        .dark .nk-kpi,.dark .nk-card,.dark .nk-chip{background:#1f1f22;border-color:rgba(255,255,255,.08);color:#eee}
        .dark .nk-card--baja{background:#3a1717;border-color:#7f2a2a}
        .dark .nk-card--sin_propiedad{background:#3a3210;border-color:#7a6a1c}
        .dark .nk-card--sin_dato{background:#2a2a2e}
        .dark .nk-card .cer,.dark .nk-kpi .l,.dark .nk-meta{color:#aaa}
        @media (max-width:480px){ .nk-kpis{grid-template-columns:1fr 1fr 1fr!important} .nk-kpi .n{font-size:20px} }
    </style>

    {{-- Resumen: pulsar un contador filtra la lista. --}}
    <div class="nk-kpis" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
        <a href="{{ $hoyUrl(['filtro' => '']) }}" class="nk-kpi {{ $filtro === '' ? 'on' : '' }}">
            <div class="n">{{ $resumen['total'] }}</div>
            <div class="l">Cerraduras</div>
        </a>
        <a href="{{ $hoyUrl(['filtro' => 'baja']) }}" class="nk-kpi {{ $filtro === 'baja' ? 'on' : '' }}">
            <div class="n" style="color:#dc2626">{{ $resumen['bateria_baja'] }}</div>
            <div class="l">Batería ≤ {{ $umbral }} %</div>
        </a>
        <a href="{{ $hoyUrl(['filtro' => 'sin_propiedad']) }}" class="nk-kpi {{ $filtro === 'sin_propiedad' ? 'on' : '' }}">
            <div class="n" style="color:#a16207">{{ $resumen['sin_propiedad'] }}</div>
            <div class="l">Sin propiedad activa</div>
        </a>
    </div>

    {{-- Buscador: mismo sitio y forma que el resto de listados. Conserva el filtro activo. --}}
    <form method="GET" class="flex gap-2 mb-4 flex-wrap" style="align-items:center">
        @if($filtro !== '') <input type="hidden" name="filtro" value="{{ $filtro }}"> @endif
        <input type="text" name="q" value="{{ $q }}"
               placeholder="Buscar propiedad o cerradura..."
               class="flex-1 max-w-xs text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
        <button type="submit"
                class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">
            Buscar
        </button>
        @if($q !== '' || $filtro !== '')
            <a href="{{ route('vm.nuki_list', $project->slug) }}"
               class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 rounded-lg transition-colors">Quitar filtros</a>
        @endif
    </form>

    @if($tarjetas->isEmpty())
        <div style="background:#fff;border:0.5px dashed rgba(0,0,0,.15);border-radius:12px;padding:32px;text-align:center;color:#777;font-size:14px">
            No hay cerraduras que coincidan con el filtro.
        </div>
    @else
        <div class="nk-grid">
            @foreach($tarjetas as $t)
                @php
                    $colorBat = $t->bateria === null ? '#a1a1aa'
                        : ($t->bateria_baja ? '#dc2626' : ($t->bateria <= 50 ? '#eab308' : '#16a34a'));
                @endphp
                <a href="{{ route('vm.nuki_form', [$project->slug, $t->id]) }}"
                   class="nk-card nk-card--{{ $t->estado }}">
                    <div>
                        <div class="prop">{{ $t->titulo ?: 'Sin nombre' }}</div>
                        @if($t->cerradura && $t->cerradura !== $t->titulo)
                            <div class="cer">Cerradura: {{ $t->cerradura }}</div>
                        @endif
                    </div>

                    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px">
                        <div class="nk-pct" style="color:{{ $colorBat }}">
                            {{ $t->bateria !== null ? $t->bateria . ' %' : '—' }}
                        </div>
                        <div style="font-size:11px;color:#777;text-align:right">
                            Batería
                            @if($t->bateria === null) · sin dato @endif
                        </div>
                    </div>
                    @if($t->bateria !== null)
                        <div class="nk-bar"><i style="width:{{ max(0, min(100, (int) $t->bateria)) }}%;background:{{ $colorBat }}"></i></div>
                    @endif

                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @if($t->bateria_baja)
                            <span class="nk-badge nk-badge--baja">Batería baja</span>
                        @endif
                        @if($t->sin_propiedad)
                            <span class="nk-badge nk-badge--sin">Sin propiedad activa</span>
                        @endif
                        @if(!$t->bateria_baja && !$t->sin_propiedad && $t->bateria !== null)
                            <span class="nk-badge nk-badge--ok">OK</span>
                        @endif
                    </div>

                    <div class="nk-meta">
                        <span>{{ $t->accesos }} {{ $t->accesos === 1 ? 'acceso' : 'accesos' }}
                            @if($t->sin_caducidad > 0) · {{ $t->sin_caducidad }} sin caducidad @endif
                        </span>
                        <span>{{ $t->leido_at ? 'Leída ' . $t->leido_at->format('d/m H:i') : 'Sin lectura' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>

</x-app-layout>
