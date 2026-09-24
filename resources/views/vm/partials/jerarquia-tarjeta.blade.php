{{-- Tarjeta de un nodo. Sirve tanto dentro del árbol como en la columna de sueltos. --}}
<div class="jer-card">
    <div class="jer-card-h">
        <span class="jer-pill">{{ $nodo['id'] }}</span>
        @if(!empty($nodo['url']))
            {{-- Se abre en pestaña nueva para no perder el estado desplegado del árbol. --}}
            <a class="jer-titulo jer-link" href="{{ $nodo['url'] }}" target="_blank" rel="noopener">{{ $nodo['titulo'] }}</a>
        @else
            <span class="jer-titulo">{{ $nodo['titulo'] }}</span>
        @endif
        @if(($hijos ?? 0) > 0)
            <span class="jer-count">{{ $hijos }}</span>
        @endif
        @if(!empty($nodo['ciclo']))
            <span class="jer-ciclo" title="Este nodo ya aparece más arriba en la misma rama: hay un ciclo en la jerarquía.">ciclo</span>
        @endif
    </div>

    @if(!empty($nodo['subtitulo']))
        <div class="jer-sub">{{ $nodo['subtitulo'] }}</div>
    @endif

    @if(!empty($nodo['personas']))
        <ul class="jer-personas">
            @foreach($nodo['personas'] as $persona)
                <li>{{ $persona }}</li>
            @endforeach
        </ul>
    @elseif(!empty($nodo['vacio_texto']))
        <div class="jer-vacio">{{ $nodo['vacio_texto'] }}</div>
    @endif
</div>
