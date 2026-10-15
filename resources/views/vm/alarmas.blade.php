{{-- Listado de alarmas: tarjetas de empresa que filtran y, debajo, una tarjeta por propiedad.
     Ninguna de las dos pinta la palabra clave ni la contraseña: aquí no existen, ni siquiera en
     el HTML. Se piden una a una desde la ficha, y cada consulta queda apuntada. --}}
<x-app-layout
    :breadcrumb="[
        ['label' => 'Alarmas', 'url' => ''],
    ]"
    :project="$project">

{{-- En la cabecera, como en el resto de las pantallas de Opland. --}}
<x-slot name="actions">
    @if($puedeEditar)
    <a href="{{ route('vm.alarma.nueva', $project->slug) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo
    </a>
    @endif
</x-slot>

<style>
.al-chips{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:1.25rem}
.al-chip{display:block;background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;
         padding:.7rem 1rem;min-width:140px;text-decoration:none;color:inherit;transition:border-color .12s,background .12s}
.al-chip:hover{background:rgba(0,0,0,.025)}
.al-chip.on{border-color:#F97316;background:#FFF7ED}
.dark .al-chip{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.dark .al-chip:hover{background:rgba(255,255,255,.04)}
.dark .al-chip.on{background:rgba(249,115,22,.12)}
.al-chip-n{font-size:20px;font-weight:600;line-height:1.1;margin:0}
.al-chip-l{font-size:12px;color:#888;margin:3px 0 0}
/* El logo manda en la tarjeta, asi que la cifra se queda a su lado y no encima. object-fit
   contain: los logos vienen con proporciones distintas y recortarlos los haria irreconocibles. */
.al-chip-top{display:flex;align-items:center;gap:10px}
.al-chip-logo{width:30px;height:30px;object-fit:contain;flex:0 0 auto}
.al-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.al-card{display:block;background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;
         padding:1rem 1.1rem;text-decoration:none;color:inherit;transition:background .12s}
.al-card:hover{background:rgba(0,0,0,.025)}
.dark .al-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.dark .al-card:hover{background:rgba(255,255,255,.04)}
.al-prop{font-size:14px;font-weight:600;margin:0}
.al-dir{font-size:12px;color:#888;margin:3px 0 0}
.al-meta{font-size:12px;color:#666;margin:10px 0 0;display:flex;flex-wrap:wrap;gap:4px 10px}
.al-btn-buscar{font-size:13px;padding:6px 14px;border-radius:8px;border:0.5px solid rgba(0,0,0,.15);
               background:#f3f4f6;color:#555;cursor:pointer}
.al-btn-buscar:hover{background:#e9ebee}
.al-tag{font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:20px;background:rgba(0,0,0,.05);color:#555;white-space:nowrap}
/* El logo de la compania en lugar de su nombre. Alto fijo y ancho libre: los logos vienen con
   proporciones muy distintas (AJAX es apaisado, Sector Alarm es un circulo) y forzar los dos
   lados los deformaria. */
.al-tag-logo{height:18px;width:auto;max-width:72px;object-fit:contain;display:block}
.dark .al-tag{background:rgba(255,255,255,.08);color:#bbb}
.al-empty{padding:2rem;text-align:center;color:#888;font-size:13px;
          background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px}
.dark .al-empty{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
</style>

<div style="padding:0 0 3rem;">

    @if(session('status'))
        <div style="margin-bottom:1rem;padding:.7rem 1rem;border-radius:8px;background:#F1FAF4;
                    border:0.5px solid #B7E0C4;color:#1B7F3B;font-size:13px">{{ session('status') }}</div>
    @endif

    {{-- Las tarjetas de empresa filtran; no son una pantalla intermedia. "Todas" primero, para
         que haya forma de volver sin usar el botón del navegador. --}}
    <div class="al-chips">
        <a class="al-chip {{ $empresa === '' ? 'on' : '' }}"
           href="{{ route('vm.alarmas', array_filter([$project->slug, 'q' => $q])) }}">
            <p class="al-chip-n">{{ $total }}</p>
            <p class="al-chip-l">Todas</p>
        </a>
        @foreach($empresas as $e)
            @php
                // Pulsar la empresa ya elegida la quita y devuelve a "Todas": si no, la unica
                // forma de deshacer el filtro era buscar la tarjeta de Todas. La busqueda de
                // texto se conserva al cambiar de empresa, que son dos filtros distintos.
                $seleccionada = $empresa === $e->empresa;
                $destino = array_filter([
                    $project->slug,
                    'empresa' => $seleccionada ? '' : $e->empresa,
                    'q'       => $q,
                ]);
            @endphp
            <a class="al-chip {{ $seleccionada ? 'on' : '' }}"
               href="{{ route('vm.alarmas', $destino) }}"
               title="{{ $seleccionada ? 'Quitar el filtro de ' . $e->empresa : 'Ver solo las de ' . $e->empresa }}">
                <div class="al-chip-top">
                    {{-- Mientras no haya logo de la empresa, el de Opland. --}}
                    <img class="al-chip-logo" alt="{{ $e->empresa }}"
                         src="{{ $logos[$e->empresa] ?? asset('projects/opland/logo.png') }}">
                    <p class="al-chip-n">{{ $e->propiedades }}</p>
                </div>
                <p class="al-chip-l">{{ $e->empresa }}</p>
            </a>
        @endforeach
    </div>

    {{-- Buscador por propiedad: es como se busca una alarma de verdad. Mismo aspecto que el del
         resto de listados de Opland. --}}
    <form method="GET" style="display:flex;gap:8px;margin:0 0 1rem;align-items:center">
        @if($empresa !== '')<input type="hidden" name="empresa" value="{{ $empresa }}">@endif
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar propiedad..."
               style="flex:1;max-width:20rem;font-size:13px;border:0.5px solid rgba(0,0,0,.18);
                      border-radius:8px;padding:6px 12px">
        <button type="submit" class="al-btn-buscar">Buscar</button>
        @if($q !== '')
            <a href="{{ route('vm.alarmas', array_filter([$project->slug, 'empresa' => $empresa])) }}"
               style="font-size:13px;color:#888;text-decoration:none;padding:6px 10px">Quitar filtro</a>
        @endif
    </form>


    @if($alarmas->isEmpty())
        <div class="al-empty">
            {{-- El mensaje nombra los filtros puestos: sin eso, buscar algo que no existe se
                 parece demasiado a que no haya nada cargado. --}}
            @if($q !== '' && $empresa !== '')
                Ninguna propiedad de {{ $empresa }} coincide con «{{ $q }}».
            @elseif($q !== '')
                Ninguna propiedad coincide con «{{ $q }}».
            @elseif($empresa !== '')
                No hay propiedades con alarma de {{ $empresa }}.
            @else
                Todavía no hay ninguna alarma registrada.
            @endif
        </div>
    @else
        <div class="al-grid">
        @foreach($alarmas as $a)
            <a class="al-card" href="{{ route('vm.alarma', [$project->slug, $a->id]) }}">
                <p class="al-prop">{{ $a->propiedad }}</p>
                <p class="al-dir">{{ $a->direccion ?: 'Sin dirección' }}</p>
                <div class="al-meta">
                    @if($a->empresa)
                        @if(isset($logos[$a->empresa]))
                            <img class="al-tag-logo" src="{{ $logos[$a->empresa] }}"
                                 alt="{{ $a->empresa }}" title="{{ $a->empresa }}">
                        @else
                            {{-- Sin fichero de logo se mantiene el nombre: mejor leerlo que ver
                                 un hueco o el logo de otra. --}}
                            <span class="al-tag">{{ $a->empresa }}</span>
                        @endif
                    @endif
                    @if($a->tipo_alarma)<span class="al-tag">{{ $a->tipo_alarma }}</span>@endif
                    @if($a->num_contrato)<span>Contrato {{ $a->num_contrato }}</span>@endif
                </div>
            </a>
        @endforeach
        </div>
    @endif

</div>
</x-app-layout>
