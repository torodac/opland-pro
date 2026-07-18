<x-app-layout :project="$project">

<div style="max-width:900px;margin:0 auto;padding:1.5rem 1rem;">

    <h1 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 3px;">Informe financiero</h1>
    <p style="font-size:12.5px;color:#9ca3af;margin:0 0 20px;">Generado a partir de la contabilidad importada de A3 (P&amp;G) y los datos de propiedades de Opland.</p>

    <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;flex-wrap:wrap;">
        <div id="selector-ejercicio" style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            @foreach($anios as $a)
            <a href="{{ request()->fullUrlWithQuery(['anio' => $a]) }}"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;{{ $a == $anioActual ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                Ejercicio {{ $a }}
            </a>
            @endforeach
        </div>
        <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-left:8px;">
            <button type="button" id="btn-anual" onclick="setModoGrafico('anual')"
                    style="font-size:12.5px;font-weight:500;padding:6px 14px;border:none;background:#f97316;color:#fff;cursor:pointer;font-family:inherit;">
                Anual
            </button>
            <button type="button" id="btn-interanual" onclick="setModoGrafico('interanual')"
                    style="font-size:12.5px;font-weight:500;padding:6px 14px;border:none;background:#fff;color:#6b7280;cursor:pointer;font-family:inherit;">
                Interanual
            </button>
        </div>

        <span style="font-size:11px;color:#9ca3af;margin:0 2px 0 14px;">Propiedades — aplica a los KPIs y al gráfico</span>
        <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            @foreach($grupos as $key => $grupo)
            <a href="{{ request()->fullUrlWithQuery(['filtro' => $key]) }}"
               class="filtro-btn" data-key="{{ $key }}"
               data-count-anual="{{ $grupo['count'] }}"
               data-count-interanual="{{ $gruposInteranual[$key]['count'] ?? 0 }}"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;white-space:nowrap;{{ $key === $filtro ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                {{ $grupo['label'] }}<span class="filtro-count">{{ $key !== 'todas' ? " ({$grupo['count']})" : '' }}</span>
            </a>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;">

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #ffedd5;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Ingresos</div>
            <div style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#ea580c;">{{ number_format($ingresos, 0, ',', '.') }} €</div>
            @if($delta)
            <div style="font-size:11.5px;font-weight:600;margin-top:3px;color:{{ $delta['ingresos'] >= 0 ? '#15803d' : '#b91c1c' }};">
                {{ $delta['ingresos'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta['ingresos']), 1, ',', '.') }} % vs {{ $anioActual - 1 }}
            </div>
            @endif
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Gastos</div>
            <div style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#1f2937;">{{ number_format(abs($gastos), 0, ',', '.') }} €</div>
            @if($delta)
            <div style="font-size:11.5px;font-weight:600;margin-top:3px;color:{{ $delta['gastos'] >= 0 ? '#15803d' : '#b91c1c' }};">
                {{ $delta['gastos'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta['gastos']), 1, ',', '.') }} % vs {{ $anioActual - 1 }}
            </div>
            @endif
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Beneficio</div>
            <div style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:{{ $beneficio >= 0 ? '#1f2937' : '#b91c1c' }};">{{ number_format($beneficio, 0, ',', '.') }} €</div>
            @if($delta)
            <div style="font-size:11.5px;font-weight:600;margin-top:3px;color:{{ $delta['beneficio'] >= 0 ? '#15803d' : '#b91c1c' }};">
                {{ $delta['beneficio'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta['beneficio']), 1, ',', '.') }} % vs {{ $anioActual - 1 }}
            </div>
            @endif
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Propiedades en cartera</div>
            <div style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#1f2937;">{{ $propiedadesEnCartera }}</div>
            <div style="font-size:11.5px;margin-top:3px;color:#9ca3af;">a {{ \Carbon\Carbon::now()->translatedFormat('d \d\e F') }}</div>
        </div>

    </div>

    @if(!$delta)
    <p style="font-size:11.5px;color:#9ca3af;margin:0 0 20px;">Sin comparativa disponible: no hay datos de {{ $anioActual - 1 }} para los mismos meses cargados en {{ $anioActual }}.</p>
    @endif

    @if($filtro !== 'todas')
    <p style="font-size:11.5px;color:#9ca3af;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin:0 0 20px;line-height:1.5;">
        Filtro <strong>{{ $grupos[$filtro]['label'] }}</strong> aplicado a los KPIs y al gráfico de abajo: suma solo <code>vm_pyg_valores.importe</code> de esas {{ $grupos[$filtro]['count'] }} propiedades — no incluye centros de coste, por eso no coincide con el total de "Todas" (que sí los incluye).
    </p>
    @endif

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
            <div id="chart-title" style="font-size:13.5px;font-weight:700;color:#111827;">Ingresos y gastos por mes — {{ $anioActual }} vs {{ $anioActual - 1 }}</div>
            <div id="chart-hint" style="font-size:11px;color:#9ca3af;margin-top:1px;">vm_pyg.importe_ingresos / importe_gastos, agrupado por mes natural · eje = mes, no fecha continua · líneas = beneficio acumulado por ejercicio</div>
        </div>

        <div id="view-ejercicio">
            @php $grafico = $graficoEjercicio; $modo = 'ejercicio'; $anioActualLabel = $anioActual; $anioAnteriorLabel = $anioActual - 1; @endphp
            @include('vm.partials.informe-financiero-chart')
        </div>

        <div id="view-interanual" style="display:none;">
            @php $grafico = $graficoInteranual; $modo = 'interanual'; @endphp
            @include('vm.partials.informe-financiero-chart')
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13.5px;font-weight:700;color:#111827;">Puente de rentabilidad — {{ $anioActual }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:1px;">De Ingresos a Resultado del ejercicio, según la jerarquía de epígrafes de vm_pyg_cuentas</div>
        </div>
        <div class="card__pad">
            @if(empty($waterfall))
            <p style="font-size:12.5px;color:#9ca3af;margin:0;">Sin datos todavía.</p>
            @else
            <div style="height:320px;">
                <canvas id="chart-waterfall"></canvas>
            </div>
            @endif
        </div>
    </div>

</div>

@if(!empty($waterfall))
<script>
    (function () {
        var waterfall = @json($waterfall);
        function pintar() { window.renderWaterfallPyg('chart-waterfall', waterfall); }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pintar);
        } else {
            pintar();
        }
    })();
</script>
@endif

<script>
function setModoGrafico(modo){
    var btnAnual = document.getElementById('btn-anual');
    var btnInter = document.getElementById('btn-interanual');
    var cal      = document.getElementById('view-ejercicio');
    var inter    = document.getElementById('view-interanual');
    var title    = document.getElementById('chart-title');
    var hint     = document.getElementById('chart-hint');
    var sel      = document.getElementById('selector-ejercicio');
    var esInter  = modo === 'interanual';

    inter.style.display = esInter ? '' : 'none';
    cal.style.display   = esInter ? 'none' : '';

    btnAnual.style.background = esInter ? '#fff' : '#f97316';
    btnAnual.style.color      = esInter ? '#6b7280' : '#fff';
    btnInter.style.background = esInter ? '#f97316' : '#fff';
    btnInter.style.color      = esInter ? '#fff' : '#6b7280';

    // El selector de Ejercicio solo tiene sentido en modo Anual — la ventana interanual
    // no depende de qué ejercicio esté marcado.
    sel.style.opacity       = esInter ? '0.4' : '1';
    sel.style.pointerEvents = esInter ? 'none' : '';

    if (esInter) {
        title.textContent = 'Ingresos y gastos — últimos 12 meses (interanual)';
        hint.textContent  = 'vm_pyg.importe_ingresos / importe_gastos · ventana móvil: el último mes del eje es siempre el mes más reciente con datos · línea = beneficio acumulado de la ventana';
        if (window.resizeInformeFinancieroChart) window.resizeInformeFinancieroChart('chart-interanual');
    } else {
        title.textContent = 'Ingresos y gastos por mes — {{ $anioActual }} vs {{ $anioActual - 1 }}';
        hint.textContent  = 'vm_pyg.importe_ingresos / importe_gastos, agrupado por mes natural · eje = mes, no fecha continua · líneas = beneficio acumulado por ejercicio';
        if (window.resizeInformeFinancieroChart) window.resizeInformeFinancieroChart('chart-ejercicio');
    }

    // Los contadores de Constantes/Altas/Bajas cambian: se recalculan sobre el Ejercicio
    // seleccionado en modo Anual, o sobre la ventana móvil de 12 meses en modo Interanual.
    document.querySelectorAll('.filtro-btn').forEach(function(btn){
        var key = btn.dataset.key;
        var n   = esInter ? btn.dataset.countInteranual : btn.dataset.countAnual;
        var span = btn.querySelector('.filtro-count');
        span.textContent = key !== 'todas' ? (' (' + n + ')') : '';
    });
}
</script>

</x-app-layout>
