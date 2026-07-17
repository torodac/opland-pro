@php $g = $grafico; @endphp
@if($g['vacio'] ?? true)
<div class="card__pad"><p style="font-size:12.5px;color:#9ca3af;margin:0;">Sin datos todavía.</p></div>
@else
<div class="card__pad">
    <div style="display:flex;gap:16px;font-size:11.5px;color:#6b7280;margin-bottom:10px;flex-wrap:wrap;">
        @if($modo === 'ejercicio')
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#f97316;opacity:.35;"></span>Ingresos {{ $anioAnteriorLabel }}</span>
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#374151;opacity:.35;"></span>Gastos {{ $anioAnteriorLabel }}</span>
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#f97316;"></span>Ingresos {{ $anioActualLabel }}</span>
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#374151;"></span>Gastos {{ $anioActualLabel }}</span>
        @else
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#f97316;"></span>Ingresos</span>
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#374151;"></span>Gastos</span>
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:#9ca3af;opacity:.6;"></span>Mismo mes, año anterior (si hay dato)</span>
        @endif
        @foreach($g['lineas'] as $l)
        <span style="display:flex;align-items:center;gap:6px;"><span style="width:16px;height:2px;flex-shrink:0;background:{{ $l['color'] }};{{ $l['dashed'] ? 'background-image:repeating-linear-gradient(90deg,'.$l['color'].' 0 3px,transparent 3px 5px);background-color:transparent;' : '' }}"></span>{{ $l['label'] }}</span>
        @endforeach
    </div>
    <svg viewBox="0 0 900 {{ $g['viewBoxAlto'] }}" width="100%" style="display:block;">
        <text x="{{ $g['plotLeft'] - 6 }}" y="{{ $g['yTop'] + 14 }}" text-anchor="end" font-size="9.5" fill="#9ca3af">{{ $g['etiquetaEjeIzq'] }}</text>
        <text x="{{ $g['plotLeft'] - 6 }}" y="{{ $g['yZero'] + 4 }}" text-anchor="end" font-size="9.5" fill="#9ca3af">0</text>
        @if($g['etiquetaEjeDer'])
        <text x="{{ $g['plotRight'] + 6 }}" y="{{ $g['yTop'] + 14 }}" text-anchor="start" font-size="9.5" fill="#1d4ed8">{{ $g['etiquetaEjeDer'] }}</text>
        <text x="{{ $g['plotRight'] + 6 }}" y="{{ $g['yZero'] + 4 }}" text-anchor="start" font-size="9.5" fill="#1d4ed8">0</text>
        @endif
        <line x1="{{ $g['plotLeft'] - 15 }}" y1="{{ $g['yZero'] }}" x2="{{ $g['plotRight'] + 5 }}" y2="{{ $g['yZero'] }}" stroke="#d1d5db" stroke-width="1" stroke-dasharray="3,3"/>

        @foreach($g['barras'] as $b)
        <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}" rx="1.5" fill="{{ $b['color'] }}" fill-opacity="{{ $b['opacity'] }}"/>
        @endforeach

        @foreach($g['lineas'] as $l)
        <polyline points="{{ $l['points'] }}" fill="none" stroke="{{ $l['color'] }}" stroke-width="2" @if($l['dashed']) stroke-dasharray="4,3" @endif/>
        @foreach($l['puntos'] as $i => $p)
            @php $esUltimo = $i === array_key_last($l['puntos']); @endphp
            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="{{ $esUltimo && $l['destacarUltimo'] ? 4 : 2.8 }}" fill="{{ $l['color'] }}" stroke="#fff" stroke-width="1.2"/>
            @if($esUltimo && $l['destacarUltimo'] && $l['etiquetaUltimo'])
            <text x="{{ $p['x'] + 9 }}" y="{{ $p['y'] + 3 }}" font-size="9.5" fill="{{ $l['color'] }}" font-weight="700">{{ $l['etiquetaUltimo'] }}</text>
            @endif
        @endforeach
        @endforeach

        <g font-size="10" fill="#9ca3af" text-anchor="middle">
            @foreach($g['categorias'] as $i => $cat)
            <text x="{{ $g['centrosX'][$i] }}" y="{{ $g['yEjes'] }}" @if(($g['mesActualIndex'] ?? null) === $i) font-weight="700" fill="#4b5563" @endif>{{ $cat }}</text>
            @endforeach
        </g>
    </svg>
</div>
@endif
