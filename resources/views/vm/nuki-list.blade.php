@php
    $enlace = fn(array $extra = []) => route('vm.nuki_list', array_filter(array_merge(
        ['project' => $project->slug, 'q' => $q, 'filtro' => $filtro], $extra
    ), fn($v) => $v !== '' && $v !== null));
@endphp

{{-- Gestión Nuki: tarjetas de cerraduras ordenadas por nombre. Solo lectura. Pulsar una tarjeta
     abre su ficha con las personas que tienen acceso. --}}
<x-app-layout
    :breadcrumb="[
        ['label' => 'Gestión Nuki', 'url' => ''],
    ]"
    :project="$project">

<style>
.nk-chips{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:1.25rem}
.nk-chip{display:block;background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;
         padding:.7rem 1rem;min-width:140px;text-decoration:none;color:inherit;transition:border-color .12s,background .12s}
.nk-chip:hover{background:rgba(0,0,0,.025)}
.nk-chip.on{border-color:#F97316;background:#FFF7ED}
.nk-chip-n{font-size:20px;font-weight:600;line-height:1.1;margin:0;font-variant-numeric:tabular-nums}
.nk-chip-l{font-size:12px;color:#888;margin:3px 0 0}
.nk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.nk-card{position:relative;display:block;background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;
         padding:1rem 1.1rem;text-decoration:none;color:inherit;transition:background .12s}
/* El enlace de la tarjeta cubre toda la tarjeta; el de Breezeway va encima, sin anidar enlaces. */
.nk-stretch{position:absolute;inset:0;border-radius:12px}
.nk-bz{position:absolute;top:.6rem;right:.7rem;z-index:2;display:inline-flex;align-items:center;gap:6px;
       font-size:11px;font-weight:600;color:#1d4ed8;background:#EFF6FF;border:0.5px solid #BFDBFE;
       padding:3px 8px;border-radius:20px;text-decoration:none;white-space:nowrap}
.nk-bz:hover{background:#DBEAFE}
.nk-bz img{width:13px;height:13px;object-fit:contain}
.dark .nk-bz{background:rgba(59,130,246,.15);border-color:rgba(147,197,253,.3);color:#93C5FD}
.nk-card:hover{background:rgba(0,0,0,.025)}
.nk-card--baja{background:#FEF2F2;border-color:#FCA5A5}
.nk-card--baja:hover{background:#FDE8E8}
.nk-card--sin_propiedad{background:#FEFCE8;border-color:#FDE68A}
.nk-card--sin_propiedad:hover{background:#FEF9C3}
.nk-prop{font-size:14px;font-weight:600;margin:0}
.nk-cer{font-size:12px;color:#888;margin:3px 0 0}
.nk-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px}
.nk-bat{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;font-variant-numeric:tabular-nums}
.nk-meta{font-size:12px;color:#666;margin:10px 0 0;display:flex;flex-wrap:wrap;gap:4px 10px}
.nk-tag{font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:20px;background:rgba(0,0,0,.05);color:#555;white-space:nowrap}
.nk-tag--baja{background:#DC2626;color:#fff}
.nk-tag--sin{background:#FDE68A;color:#713F12}
.nk-btn-buscar{font-size:13px;padding:6px 14px;border-radius:8px;border:0.5px solid rgba(0,0,0,.15);
               background:#f3f4f6;color:#555;cursor:pointer}
.nk-btn-buscar:hover{background:#e9ebee}
.nk-empty{padding:2rem;text-align:center;color:#888;font-size:13px;
          background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px}
.dark .nk-chip,.dark .nk-card,.dark .nk-empty{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.dark .nk-chip:hover,.dark .nk-card:hover{background:rgba(255,255,255,.04)}
.dark .nk-chip.on{background:rgba(249,115,22,.12)}
.dark .nk-card--baja{background:rgba(220,38,38,.14);border-color:rgba(252,165,165,.35)}
.dark .nk-card--sin_propiedad{background:rgba(234,179,8,.12);border-color:rgba(253,230,138,.3)}
.dark .nk-cer,.dark .nk-chip-l,.dark .nk-meta{color:#aaa}
.dark .nk-tag{background:rgba(255,255,255,.08);color:#bbb}
</style>

<div style="padding:0 0 3rem;">

    @if(session('status'))
        <div style="margin-bottom:1rem;padding:.7rem 1rem;border-radius:8px;background:#F1FAF4;
                    border:0.5px solid #B7E0C4;color:#1B7F3B;font-size:13px">{{ session('status') }}</div>
    @endif

    {{-- Última lectura de Nuki y botón para volver a leer ahora, como el de Icnea en propiedades. --}}
    <div class="flex items-center gap-3 mb-3">
        <span class="text-xs text-gray-400">
            Última lectura de Nuki: {{ $ultimaLectura ? \Carbon\Carbon::parse($ultimaLectura)->format('d/m/Y H:i') : 'nunca' }}
        </span>
        <form method="POST" action="{{ route('vm.nuki_sync', $project->slug) }}">
            @csrf
            <button type="submit" class="text-xs text-orange-500 hover:text-orange-700 hover:underline transition-colors">
                Actualizar ahora
            </button>
        </form>
    </div>

    {{-- Resumen: pulsar un contador filtra la lista. --}}
    <div class="nk-chips">
        <a class="nk-chip {{ $filtro === '' ? 'on' : '' }}" href="{{ $enlace(['filtro' => '']) }}">
            <p class="nk-chip-n">{{ $resumen['total'] }}</p>
            <p class="nk-chip-l">Todas</p>
        </a>
        <a class="nk-chip {{ $filtro === 'baja' ? 'on' : '' }}" href="{{ $enlace(['filtro' => 'baja']) }}">
            <p class="nk-chip-n" style="color:#DC2626">{{ $resumen['bateria_baja'] }}</p>
            <p class="nk-chip-l">Batería ≤ {{ $umbral }} %</p>
        </a>
        <a class="nk-chip {{ $filtro === 'sin_propiedad' ? 'on' : '' }}" href="{{ $enlace(['filtro' => 'sin_propiedad']) }}">
            <p class="nk-chip-n" style="color:#A16207">{{ $resumen['sin_propiedad'] }}</p>
            <p class="nk-chip-l">Sin propiedad activa</p>
        </a>
    </div>

    {{-- Buscador: mismo aspecto que el de alarmas. Conserva el filtro activo. --}}
    <form method="GET" style="display:flex;gap:8px;margin:0 0 1rem;align-items:center">
        @if($filtro !== '')<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar propiedad o cerradura..."
               style="flex:1;max-width:20rem;font-size:13px;border:0.5px solid rgba(0,0,0,.18);
                      border-radius:8px;padding:6px 12px">
        <button type="submit" class="nk-btn-buscar">Buscar</button>
        @if($q !== '' || $filtro !== '')
            <a href="{{ route('vm.nuki_list', $project->slug) }}"
               style="font-size:13px;color:#888;text-decoration:none;padding:6px 10px">Quitar filtros</a>
        @endif
    </form>

    @if($tarjetas->isEmpty())
        <div class="nk-empty">No hay cerraduras que coincidan con el filtro.</div>
    @else
        <div class="nk-grid">
        @foreach($tarjetas as $t)
            @php
                // Icono de batería: el relleno es proporcional a la carga (18 px de 18 posibles).
                // Rojo bajo el umbral; en el resto, gris oscuro neutro. Sin dato, sin relleno.
                $anchoRelleno = $t->bateria === null ? 0 : max(0, min(18, (int) round(18 * $t->bateria / 100)));
                $colorBat = $t->bateria === null ? '#9CA3AF' : ($t->bateria_baja ? '#DC2626' : '#374151');
            @endphp
            <div class="nk-card nk-card--{{ $t->estado }}">
                <a class="nk-stretch" href="{{ route('vm.nuki_form', [$project->slug, $t->id]) }}" aria-label="Ver ficha"></a>
                @if($t->tarea)
                    <a class="nk-bz" href="{{ $t->tarea->url }}" target="_blank" rel="noopener"
                       title="Abrir la tarea en Breezeway">
                        <img src="{{ asset('img/breezeway.svg') }}" alt="">
                        {{ $t->tarea->fecha ? $t->tarea->fecha->format('d/m') : 'Breezeway' }}
                    </a>
                @endif
                <p class="nk-prop">{{ $t->titulo ?: 'Sin nombre' }}</p>
                @if($t->cerradura && $t->cerradura !== $t->titulo)
                    <p class="nk-cer">{{ $t->cerradura }}</p>
                @endif

                <div class="nk-row">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                        @if($t->bateria_baja)
                            <span class="nk-tag nk-tag--baja">Batería baja</span>
                        @endif
                        @if($t->sin_propiedad)
                            <span class="nk-tag nk-tag--sin">Sin propiedad activa</span>
                        @endif
                    </div>
                </div>

                {{-- Una sola línea: batería, último uso (calendario, quién al pasar el ratón) y tareas
                     de batería de los últimos 30 días (llave inglesa). --}}
                <div class="nk-meta" style="align-items:center;justify-content:space-between;margin-top:12px;color:#444">
                    <span title="Batería" style="display:inline-flex;align-items:center;gap:6px;font-weight:600;color:{{ $colorBat }};font-variant-numeric:tabular-nums">
                        <svg width="26" height="14" viewBox="0 0 26 14" aria-hidden="true">
                            <rect x="0.75" y="0.75" width="21.5" height="12.5" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            <rect x="23.25" y="4.5" width="2" height="5" rx="1" fill="currentColor"/>
                            @if($anchoRelleno > 0)
                                <rect x="3" y="3" width="{{ $anchoRelleno }}" height="8" rx="1.5" fill="currentColor"/>
                            @endif
                        </svg>
                        {{ $t->bateria !== null ? $t->bateria . ' %' : 'Sin dato' }}
                    </span>
                    <span title="Último uso{{ $t->ultimo_uso_quien ? ': ' . $t->ultimo_uso_quien : '' }}" style="display:inline-flex;align-items:center;gap:5px">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $t->ultimo_uso_at ? $t->ultimo_uso_at->format('d/m H:i') : '—' }}
                    </span>
                    <span title="Tareas de batería de los últimos 30 días" style="display:inline-flex;align-items:center;gap:5px">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z"/></svg>
                        {{ $t->tareas_mes }}
                    </span>
                </div>
            </div>
        @endforeach
        </div>
    @endif
</div>

</x-app-layout>
