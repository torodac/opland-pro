{{--
    Nodo recursivo del árbol de jerarquías. Recibe $nodo con:
      id, titulo, subtitulo, personas[], vacio_texto, hijos[], ciclo
    Se autoincluye para los hijos; la recursión ya viene cortada desde el controlador
    (JerarquiasController::construir), aquí solo se pinta.
--}}
@php $tieneHijos = !empty($nodo['hijos']); @endphp
<li>
    @if($tieneHijos)
        <details open>
            <summary>
                <span class="jer-chevron" aria-hidden="true">▸</span>
                @include('vm.partials.jerarquia-tarjeta', ['nodo' => $nodo, 'hijos' => count($nodo['hijos'])])
            </summary>
            <ul>
                @foreach($nodo['hijos'] as $hijo)
                    @include('vm.partials.jerarquia-nodo', ['nodo' => $hijo])
                @endforeach
            </ul>
        </details>
    @else
        <div class="jer-hoja">
            @include('vm.partials.jerarquia-tarjeta', ['nodo' => $nodo, 'hijos' => 0])
        </div>
    @endif
</li>
