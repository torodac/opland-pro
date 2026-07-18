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
    @php $canvasId = 'chart-' . $modo; @endphp
    <div style="height:280px;">
        <canvas id="{{ $canvasId }}"></canvas>
    </div>
</div>
<script>
    // app.js se carga como <script type="module">, que el navegador siempre difiere hasta
    // terminar de parsear el documento — por eso esta llamada no puede ejecutarse al vuelo,
    // hay que esperar a que el modulo ya haya definido window.renderInformeFinancieroChart.
    (function () {
        var canvasId = {{ Illuminate\Support\Js::from($canvasId) }};
        var data     = @json($g);
        var labels   = {
            ingresosAnterior: @json($modo === 'ejercicio' ? "Ingresos {$anioAnteriorLabel}" : 'Ingresos (año anterior)'),
            gastosAnterior:   @json($modo === 'ejercicio' ? "Gastos {$anioAnteriorLabel}"   : 'Gastos (año anterior)'),
            ingresosActual:   @json($modo === 'ejercicio' ? "Ingresos {$anioActualLabel}"   : 'Ingresos'),
            gastosActual:     @json($modo === 'ejercicio' ? "Gastos {$anioActualLabel}"     : 'Gastos'),
        };
        function pintar() { window.renderInformeFinancieroChart(canvasId, data, labels); }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pintar);
        } else {
            pintar();
        }
    })();
</script>
@endif
