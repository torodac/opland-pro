<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

{{-- La vista incluida más abajo (vm.partials.informe-financiero-chart) reasigna $modo con otro
     significado ('ejercicio'/'interanual', no 'anual'/'interanual') -- se guarda aparte ANTES de
     que eso ocurra, porque el valor real (el del toggle Anual/Interanual de esta página) hace
     falta más abajo, después de ese include. --}}
@php $modoInicial = $modo; @endphp

<div style="max-width:900px;margin:0 auto;padding:1.5rem 1rem;">

    <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;flex-wrap:wrap;">
        <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <button type="button" id="btn-anual" onclick="setModoGrafico('anual')"
                    style="font-size:12.5px;font-weight:500;padding:6px 14px;border:none;background:#f97316;color:#fff;cursor:pointer;font-family:inherit;">
                Anual
            </button>
            <button type="button" id="btn-interanual" onclick="setModoGrafico('interanual')"
                    style="font-size:12.5px;font-weight:500;padding:6px 14px;border:none;background:#fff;color:#6b7280;cursor:pointer;font-family:inherit;">
                Interanual
            </button>
        </div>
        <div id="selector-ejercicio" style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-left:8px;">
            @foreach($anios as $a)
            <a href="{{ request()->fullUrlWithQuery(['anio' => $a, 'modo' => $modoInicial]) }}"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;{{ $a == $anioActual ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                Ejercicio {{ $a }}
            </a>
            @endforeach
        </div>

        <span style="font-size:11px;color:#9ca3af;margin:0 2px 0 14px;">Propiedades — aplica a los KPIs y al gráfico</span>
        <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            @foreach($grupos as $key => $grupo)
            <a href="{{ request()->fullUrlWithQuery(['filtro' => $key, 'modo' => $modoInicial]) }}"
               class="filtro-btn" data-key="{{ $key }}"
               data-count-anual="{{ $grupo['count'] }}"
               data-count-interanual="{{ $gruposInteranual[$key]['count'] ?? 0 }}"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;white-space:nowrap;{{ $key === $filtro ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                {{ $grupo['label'] }}<span class="filtro-count">{{ $key !== 'todas' ? " ({$grupo['count']})" : '' }}</span>
            </a>
            @endforeach
        </div>

        <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <a href="{{ request()->fullUrlWithQuery(['tipo_renta' => 'todas', 'modo' => $modoInicial]) }}" class="tipo-renta-btn"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;white-space:nowrap;{{ $tipoRenta === 'todas' ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                Todos los tipos de renta
            </a>
            @foreach($tiposRenta as $tr)
            <a href="{{ request()->fullUrlWithQuery(['tipo_renta' => $tr, 'modo' => $modoInicial]) }}" class="tipo-renta-btn"
               style="font-size:12.5px;font-weight:500;padding:6px 14px;text-decoration:none;white-space:nowrap;{{ $tipoRenta === $tr ? 'background:#f97316;color:#fff;' : 'background:#fff;color:#6b7280;' }}">
                {{ $tr }}
            </a>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px;">

        @php
            // Textos/colores precalculados para los dos modos -- el JS solo hace el swap, sin
            // reimplementar el formato de número ni la lógica de signo.
            $fmtEuro = fn($v) => number_format($v, 0, ',', '.') . ' €';
            $fmtDelta = fn($d, $etiqueta) => $d ? (($d >= 0 ? '▲' : '▼') . ' ' . number_format(abs($d), 1, ',', '.') . ' % vs ' . $etiqueta) : '';
            $colorDelta = fn($d) => $d !== null && $d >= 0 ? '#15803d' : '#b91c1c';
            $colorValor = fn($v) => $v >= 0 ? '#1f2937' : '#b91c1c';
        @endphp

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #ffedd5;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Ingresos</div>
            <div id="kpi-ingresos-value" style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#ea580c;"
                 data-value-anual="{{ $fmtEuro($ingresos) }}" data-value-interanual="{{ $fmtEuro($ingresosInteranual) }}">{{ $fmtEuro($ingresos) }}</div>
            <div id="kpi-ingresos-delta" style="font-size:11.5px;font-weight:600;margin-top:3px;{{ $delta ? '' : 'display:none;' }}"
                 data-delta-anual="{{ $fmtDelta($delta['ingresos'] ?? null, $anioActual - 1) }}" data-color-anual="{{ $colorDelta($delta['ingresos'] ?? null) }}"
                 data-delta-interanual="{{ $fmtDelta($deltaInteranual['ingresos'] ?? null, '12 meses anteriores') }}" data-color-interanual="{{ $colorDelta($deltaInteranual['ingresos'] ?? null) }}">
                {{ $fmtDelta($delta['ingresos'] ?? null, $anioActual - 1) }}
            </div>
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Gastos</div>
            <div id="kpi-gastos-value" style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#1f2937;"
                 data-value-anual="{{ $fmtEuro(abs($gastos)) }}" data-value-interanual="{{ $fmtEuro(abs($gastosInteranual)) }}">{{ $fmtEuro(abs($gastos)) }}</div>
            <div id="kpi-gastos-delta" style="font-size:11.5px;font-weight:600;margin-top:3px;{{ $delta ? '' : 'display:none;' }}"
                 data-delta-anual="{{ $fmtDelta($delta['gastos'] ?? null, $anioActual - 1) }}" data-color-anual="{{ $colorDelta($delta['gastos'] ?? null) }}"
                 data-delta-interanual="{{ $fmtDelta($deltaInteranual['gastos'] ?? null, '12 meses anteriores') }}" data-color-interanual="{{ $colorDelta($deltaInteranual['gastos'] ?? null) }}">
                {{ $fmtDelta($delta['gastos'] ?? null, $anioActual - 1) }}
            </div>
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Beneficio</div>
            <div id="kpi-beneficio-value" style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:{{ $colorValor($beneficio) }};"
                 data-value-anual="{{ $fmtEuro($beneficio) }}" data-color-anual="{{ $colorValor($beneficio) }}"
                 data-value-interanual="{{ $fmtEuro($beneficioInteranual) }}" data-color-interanual="{{ $colorValor($beneficioInteranual) }}">{{ $fmtEuro($beneficio) }}</div>
            <div id="kpi-beneficio-delta" style="font-size:11.5px;font-weight:600;margin-top:3px;{{ $delta ? '' : 'display:none;' }}"
                 data-delta-anual="{{ $fmtDelta($delta['beneficio'] ?? null, $anioActual - 1) }}" data-color-anual="{{ $colorDelta($delta['beneficio'] ?? null) }}"
                 data-delta-interanual="{{ $fmtDelta($deltaInteranual['beneficio'] ?? null, '12 meses anteriores') }}" data-color-interanual="{{ $colorDelta($deltaInteranual['beneficio'] ?? null) }}">
                {{ $fmtDelta($delta['beneficio'] ?? null, $anioActual - 1) }}
            </div>
        </div>

        <div style="border-radius:12px;padding:14px 16px;border:1px solid #e5e7eb;background:#fff;">
            <div style="font-size:11.5px;font-weight:500;color:#6b7280;margin-bottom:4px;">Propiedades en cartera</div>
            <div style="font-size:1.4rem;font-weight:700;font-variant-numeric:tabular-nums;color:#1f2937;">{{ $propiedadesEnCartera }}</div>
            <div style="font-size:11.5px;margin-top:3px;color:#9ca3af;">con datos en {{ $anioActual }}, según filtros</div>
        </div>

    </div>

    <p id="kpi-sin-comparativa" style="font-size:11.5px;color:#9ca3af;margin:0 0 20px;{{ $delta ? 'display:none;' : '' }}"
       data-texto-anual="Sin comparativa disponible: no hay datos de {{ $anioActual - 1 }} para los mismos meses cargados en {{ $anioActual }}."
       data-mostrar-anual="{{ $delta ? '0' : '1' }}"
       data-texto-interanual="Sin comparativa disponible: no hay datos de los 12 meses anteriores a la ventana actual."
       data-mostrar-interanual="{{ $deltaInteranual ? '0' : '1' }}">{{ $delta ? '' : "Sin comparativa disponible: no hay datos de " . ($anioActual - 1) . " para los mismos meses cargados en {$anioActual}." }}</p>

    @if($filtro !== 'todas')
    <p style="font-size:11.5px;color:#9ca3af;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin:0 0 20px;line-height:1.5;">
        Filtro <strong>{{ $grupos[$filtro]['label'] }}</strong> aplicado a los KPIs y al gráfico de abajo: suma solo <code>vm_pyg_valores.importe</code> de esas {{ $grupos[$filtro]['count'] }} propiedades — no incluye centros de coste, por eso no coincide con el total de "Todas" (que sí los incluye).
    </p>
    @endif

    <div style="display:inline-flex;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px;">
        <button type="button" id="btn-tab-pyg" onclick="setTab('pyg')"
                style="font-size:12.5px;font-weight:500;padding:6px 16px;border:none;background:#f97316;color:#fff;cursor:pointer;font-family:inherit;">
            PyG
        </button>
        <button type="button" id="btn-tab-rentabilidad" onclick="setTab('rentabilidad')"
                style="font-size:12.5px;font-weight:500;padding:6px 16px;border:none;background:#fff;color:#6b7280;cursor:pointer;font-family:inherit;">
            Rentabilidad
        </button>
    </div>

    <div id="tab-pyg">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
                <div id="chart-title" style="font-size:13.5px;font-weight:700;color:#111827;">Ingresos y gastos por mes — {{ $anioActual }} vs {{ $anioActual - 1 }}</div>
                <div id="chart-hint" style="font-size:11px;color:#9ca3af;margin-top:1px;">Ingresos / gastos por código contable (7xx/6xx), agrupado por mes natural · eje = mes, no fecha continua · líneas = beneficio acumulado por ejercicio</div>
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

    <div id="tab-rentabilidad" style="display:none;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div>
                    <div style="font-size:13.5px;font-weight:700;color:#111827;">Rentabilidad por propiedad — {{ $anioActual }}</div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:1px;">Margen = beneficio/ingresos del ejercicio · % ocupación = noches reservadas / noches disponibles · en todos los casos se excluyen las noches bloqueadas por "Propietario" · clic en un encabezado para ordenar</div>
                </div>
                @if(!empty($rentabilidad))
                <a href="{{ route('informe-financiero.rentabilidad.export', $project->slug) }}?{{ http_build_query(request()->only(['anio', 'filtro', 'tipo_renta'])) }}"
                   style="flex-shrink:0;font-size:12.5px;font-weight:500;padding:6px 12px;border-radius:8px;border:1px solid #e5e7eb;color:#374151;text-decoration:none;white-space:nowrap;">
                    <i class="fas fa-file-excel" style="color:#15803d;"></i> Exportar
                </a>
                @endif
            </div>
            <div style="overflow-x:auto;">
                @if(empty($rentabilidad))
                <p style="font-size:12.5px;color:#9ca3af;margin:0;padding:14px 18px;">Sin propiedades con datos en PyG este ejercicio.</p>
                @else
                <table id="tabla-rentabilidad" style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #e5e7eb;">
                            <th style="text-align:right;padding:8px 6px 8px 18px;color:#6b7280;font-weight:600;white-space:nowrap;">#</th>
                            <th data-sort="propiedad" data-tipo="texto" style="text-align:left;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">Propiedad ⇅</th>
                            <th data-sort="ingresos" data-tipo="num" style="text-align:right;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">Ingresos ⇅</th>
                            <th data-sort="beneficio" data-tipo="num" style="text-align:right;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">Beneficio ⇅</th>
                            <th data-sort="margen" data-tipo="num" style="text-align:right;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">Margen ⇅</th>
                            <th data-sort="dias_reservados" data-tipo="num" style="text-align:right;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">Días ocupados ⇅</th>
                            <th data-sort="ocupacion" data-tipo="num" style="text-align:right;padding:8px 18px;color:#6b7280;font-weight:600;cursor:pointer;white-space:nowrap;">% Ocupación ⇅</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rentabilidad as $f)
                        <tr style="border-bottom:1px solid #f3f4f6;"
                            data-propiedad="{{ $f['propiedad'] }}" data-ingresos="{{ $f['ingresos'] }}" data-beneficio="{{ $f['beneficio'] }}"
                            data-margen="{{ $f['margen'] ?? '' }}" data-dias_reservados="{{ $f['dias_reservados'] }}" data-ocupacion="{{ $f['ocupacion'] ?? '' }}">
                            <td class="fila-num" style="padding:8px 6px 8px 18px;text-align:right;color:#9ca3af;">{{ $loop->iteration }}</td>
                            <td style="padding:8px 18px;font-weight:600;color:#111827;white-space:nowrap;">{{ $f['propiedad'] }}</td>
                            <td style="padding:8px 18px;text-align:right;color:#374151;">{{ number_format($f['ingresos'], 0, ',', '.') }} €</td>
                            <td style="padding:8px 18px;text-align:right;color:{{ $f['beneficio'] >= 0 ? '#1f2937' : '#b91c1c' }};">{{ number_format($f['beneficio'], 0, ',', '.') }} €</td>
                            <td style="padding:8px 18px;text-align:right;color:{{ $f['margen'] === null ? '#9ca3af' : ($f['margen'] >= 0 ? '#15803d' : '#b91c1c') }};">{{ $f['margen'] !== null ? number_format($f['margen'], 0, ',', '.') . ' %' : '—' }}</td>
                            <td style="padding:8px 18px;text-align:right;color:#374151;">{{ number_format($f['dias_reservados'], 0, ',', '.') }}</td>
                            <td style="padding:8px 18px;text-align:right;color:#374151;">{{ $f['ocupacion'] !== null ? number_format($f['ocupacion'], 0, ',', '.') . ' %' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
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
        hint.textContent  = 'Ingresos / gastos por código contable (7xx/6xx) · ventana móvil: el último mes del eje es siempre el mes más reciente con datos · línea = beneficio acumulado de la ventana';
        if (window.resizeInformeFinancieroChart) window.resizeInformeFinancieroChart('chart-interanual');
    } else {
        title.textContent = 'Ingresos y gastos por mes — {{ $anioActual }} vs {{ $anioActual - 1 }}';
        hint.textContent  = 'Ingresos / gastos por código contable (7xx/6xx), agrupado por mes natural · eje = mes, no fecha continua · líneas = beneficio acumulado por ejercicio';
        if (window.resizeInformeFinancieroChart) window.resizeInformeFinancieroChart('chart-ejercicio');
    }

    // Los KPI (Ingresos/Gastos/Beneficio + su comparativa) también cambian con el modo -- antes
    // se quedaban siempre fijos en el ejercicio seleccionado, aunque el gráfico de abajo sí
    // pasara a interanual.
    ['kpi-ingresos', 'kpi-gastos', 'kpi-beneficio'].forEach(function(prefijo){
        var valor = document.getElementById(prefijo + '-value');
        var delta = document.getElementById(prefijo + '-delta');
        if (valor) {
            valor.textContent   = esInter ? valor.dataset.valueInteranual : valor.dataset.valueAnual;
            if (valor.dataset.colorInteranual) {
                valor.style.color = esInter ? valor.dataset.colorInteranual : valor.dataset.colorAnual;
            }
        }
        if (delta) {
            var texto = esInter ? delta.dataset.deltaInteranual : delta.dataset.deltaAnual;
            delta.textContent  = texto;
            delta.style.color  = esInter ? delta.dataset.colorInteranual : delta.dataset.colorAnual;
            delta.style.display = texto ? '' : 'none';
        }
    });
    var sinComparativa = document.getElementById('kpi-sin-comparativa');
    if (sinComparativa) {
        var mostrar = esInter ? sinComparativa.dataset.mostrarInteranual : sinComparativa.dataset.mostrarAnual;
        sinComparativa.textContent = esInter ? sinComparativa.dataset.textoInteranual : sinComparativa.dataset.textoAnual;
        sinComparativa.style.display = mostrar === '1' ? '' : 'none';
    }

    // Los contadores de Constantes/Altas/Bajas cambian: se recalculan sobre el Ejercicio
    // seleccionado en modo Anual, o sobre la ventana móvil de 12 meses en modo Interanual.
    document.querySelectorAll('.filtro-btn').forEach(function(btn){
        var key = btn.dataset.key;
        var n   = esInter ? btn.dataset.countInteranual : btn.dataset.countAnual;
        var span = btn.querySelector('.filtro-count');
        span.textContent = key !== 'todas' ? (' (' + n + ')') : '';
    });

    // Los enlaces de Ejercicio/filtro recargan la página -- si no se les propaga el modo actual,
    // esa recarga siempre volvía a "anual" (bug real: elegir un filtro deshacía el toggle a
    // Interanual). Se reescribe su href con el modo vigente cada vez que cambia.
    document.querySelectorAll('#selector-ejercicio a, .filtro-btn, .tipo-renta-btn').forEach(function(a){
        var url = new URL(a.href, window.location.origin);
        url.searchParams.set('modo', modo);
        a.href = url.toString();
    });
}

setModoGrafico('{{ $modoInicial }}');

// ── Pestañas PyG / Rentabilidad ──────────────────────────────────────────
function setTab(tab) {
    var pyg  = document.getElementById('tab-pyg');
    var rent = document.getElementById('tab-rentabilidad');
    var btnPyg  = document.getElementById('btn-tab-pyg');
    var btnRent = document.getElementById('btn-tab-rentabilidad');
    var esPyg = tab === 'pyg';

    pyg.style.display  = esPyg ? '' : 'none';
    rent.style.display = esPyg ? 'none' : '';
    btnPyg.style.background  = esPyg ? '#f97316' : '#fff';
    btnPyg.style.color       = esPyg ? '#fff' : '#6b7280';
    btnRent.style.background = esPyg ? '#fff' : '#f97316';
    btnRent.style.color      = esPyg ? '#6b7280' : '#fff';

    // Los canvas de Chart.js quedan con tamaño 0 mientras su contenedor está oculto
    // (display:none) -- al volver a mostrarlos hay que forzar un resize.
    if (esPyg && window.resizeInformeFinancieroChart) {
        var activo = document.getElementById('view-interanual').style.display === 'none' ? 'chart-ejercicio' : 'chart-interanual';
        window.resizeInformeFinancieroChart(activo);
    }
}

// ── Orden de la tabla de Rentabilidad (clic en encabezado) ──────────────
(function () {
    var tabla = document.getElementById('tabla-rentabilidad');
    if (!tabla) return;
    var tbody = tabla.querySelector('tbody');
    var sortActual = { campo: null, asc: true };

    tabla.querySelectorAll('th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var campo = th.dataset.sort;
            var tipo  = th.dataset.tipo;
            var asc   = sortActual.campo === campo ? !sortActual.asc : true;
            sortActual = { campo: campo, asc: asc };

            var filas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            filas.sort(function (a, b) {
                var va = a.dataset[campo], vb = b.dataset[campo];
                if (tipo === 'num') {
                    // Vacío ("—" en pantalla, sin dato) siempre al final, sea cual sea el sentido.
                    var na = va === '' ? null : parseFloat(va);
                    var nb = vb === '' ? null : parseFloat(vb);
                    if (na === null && nb === null) return 0;
                    if (na === null) return 1;
                    if (nb === null) return -1;
                    return asc ? na - nb : nb - na;
                }
                return asc ? va.localeCompare(vb) : vb.localeCompare(va);
            });
            filas.forEach(function (tr, i) {
                tbody.appendChild(tr);
                var num = tr.querySelector('.fila-num');
                if (num) num.textContent = i + 1;
            });
        });
    });
})();
</script>

</x-app-layout>
