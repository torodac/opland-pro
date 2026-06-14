{{--
    Renderiza el valor de una celda según el tipo de campo.
    Variables: $campo (TableField), $valor (mixed)
--}}
@switch($campo->type)

    @case('tinyint')
        <span class="{{ $valor ? 'text-green-600' : 'text-gray-300' }}">
            {{ $valor ? 'Sí' : 'No' }}
        </span>
        @break

    @case('smallint')
        <input type="checkbox" disabled {{ $valor ? 'checked' : '' }} class="accent-orange-500">
        @break

    @case('fecha')
        {{ $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '—' }}
        @break

    @case('email')
        @if($valor)
            <a href="mailto:{{ $valor }}" class="text-blue-500 hover:underline" onclick="event.stopPropagation()">{{ $valor }}</a>
        @endif
        @break

    @case('telefono')
        @if($valor)
            <a href="tel:{{ $valor }}" class="text-blue-500 hover:underline" onclick="event.stopPropagation()">{{ $valor }}</a>
        @endif
        @break

    @case('file')
        @if($valor)
            <a href="{{ asset($valor) }}" target="_blank" class="text-blue-500 hover:underline text-xs" onclick="event.stopPropagation()">
                Ver archivo
            </a>
        @endif
        @break

    @case('text')
        <span class="line-clamp-1 text-gray-500 text-xs">{{ $valor }}</span>
        @break

    @case('id')
    @case('desplegable')
        {{ ($fkOptions[$campo->name][$valor] ?? null) ?: '—' }}
        @break

    @case('multiusuario')
        @php
            $ids = json_decode($valor ?? '[]', true) ?? [];
            $uMap = $usuariosMap ?? [];
        @endphp
        @if(count($ids))
            <div class="flex flex-wrap gap-1">
                @foreach($ids as $uid)
                    @php
                        $nombre  = $uMap[(int) $uid] ?? $uMap[(string) $uid] ?? "#{$uid}";
                        $inicial = strtoupper(mb_substr($nombre, 0, 1));
                    @endphp
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold"
                          title="{{ $nombre }}">{{ $inicial }}</span>
                @endforeach
            </div>
        @else
            <span class="text-gray-300">—</span>
        @endif
        @break

    @default
        {{ $valor ?? '—' }}
@endswitch
