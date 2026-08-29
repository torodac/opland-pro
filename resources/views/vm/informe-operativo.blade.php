<x-app-layout :project="$project">

<div style="max-width:900px;margin:0 auto;padding:1.5rem 1rem;">

    <h1 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 3px;">Informe operativo</h1>
    <p style="font-size:12.5px;color:#9ca3af;margin:0 0 20px;">Datos operativos de las propiedades de Opland.</p>

    <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;flex-wrap:wrap;">
        <select onchange="window.location.href=this.value"
                style="font-size:12.5px;font-weight:500;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;cursor:pointer;">
            @foreach($anios as $a)
            <option value="{{ request()->fullUrlWithQuery(['anio' => $a]) }}" {{ $a == $anioActual ? 'selected' : '' }}>Año {{ $a }}</option>
            @endforeach
        </select>
        @if(!empty($series))
        <button type="button" id="btn-modo-pct" onclick="toggleModoOperativo()"
                style="font-size:12.5px;font-weight:500;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;cursor:pointer;">
            Ver en %
        </button>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13.5px;font-weight:700;color:#111827;">Propiedades por mes y tipo de renta — {{ $anioActual }} vs {{ $anioAnterior }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:1px;">vm_propiedades.tipo_renta · columnas apiladas, propiedades activas cada mes según fecha_inicio / fecha_fin</div>
        </div>
        <div style="padding:14px 18px;">
            @if(empty($series))
            <p style="font-size:12.5px;color:#9ca3af;margin:0;">Sin propiedades activas en {{ $anioActual }} ni en {{ $anioAnterior }}.</p>
            @else
            <div style="display:flex;gap:16px;font-size:11.5px;color:#6b7280;margin-bottom:10px;flex-wrap:wrap;">
                @foreach($series as $s)
                <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:{{ $s['color'] }};opacity:{{ $s['esActual'] ? 1 : 0.35 }};"></span>{{ $s['tipoRenta'] }} {{ $s['anio'] }}</span>
                @endforeach
            </div>
            <div style="height:280px;">
                <canvas id="chart-operativo-clusters"></canvas>
            </div>

            {{-- Pie de la gráfica: los mismos datos numéricos que las barras, en tabla.
                 Cada celda muestra "{{ $anioActual }} ({{ $anioAnterior }})"; el botón "Ver en %"
                 alterna entre unidades y % sobre el total del mes, usando los data-* precargados. --}}
            <div style="overflow-x:auto;margin-top:16px;">
                <table id="tabla-operativo" style="width:100%;border-collapse:collapse;font-size:11.5px;white-space:nowrap;">
                    <thead>
                        <tr style="border-bottom:1px solid #e5e7eb;">
                            <th style="text-align:left;padding:5px 10px 5px 0;color:#6b7280;font-weight:600;">Tipo de renta</th>
                            @foreach($categorias as $mes)
                            <th style="text-align:right;padding:5px 8px;color:#6b7280;font-weight:600;">{{ $mes }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filas as $fila)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 10px 5px 0;font-weight:600;color:#111827;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:{{ $fila['color'] }};margin-right:6px;"></span>{{ $fila['tipo'] }}
                            </td>
                            @foreach($categorias as $i => $mes)
                            <td data-actual="{{ $fila['actual'][$i] }}" data-anterior="{{ $fila['anterior'][$i] }}"
                                data-actual-pct="{{ $fila['actualPct'][$i] }}" data-anterior-pct="{{ $fila['anteriorPct'][$i] }}"
                                style="text-align:right;padding:5px 8px;color:#374151;font-variant-numeric:tabular-nums;">{{ $fila['actual'][$i] }} ({{ $fila['anterior'][$i] }})</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>

@if(!empty($series))
<script>
    var categorias    = @json($categorias);
    var series        = @json($series);
    var seriesPercent = @json($seriesPercent);
    var modoPctOperativo = false;

    function pintarGraficoOperativo() {
        var datos = modoPctOperativo ? seriesPercent : series;
        window.renderInformeOperativoClusters('chart-operativo-clusters', categorias, datos, {
            sufijo: modoPctOperativo ? '%' : '',
            maxY: modoPctOperativo ? 100 : undefined,
        });
    }

    function toggleModoOperativo() {
        modoPctOperativo = !modoPctOperativo;
        document.getElementById('btn-modo-pct').textContent = modoPctOperativo ? 'Ver en unidades' : 'Ver en %';
        pintarGraficoOperativo();
        document.querySelectorAll('#tabla-operativo td[data-actual]').forEach(function (td) {
            var actual   = modoPctOperativo ? td.dataset.actualPct : td.dataset.actual;
            var anterior = modoPctOperativo ? td.dataset.anteriorPct : td.dataset.anterior;
            var sufijo   = modoPctOperativo ? '%' : '';
            td.textContent = actual + sufijo + ' (' + anterior + sufijo + ')';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pintarGraficoOperativo);
    } else {
        pintarGraficoOperativo();
    }
</script>
@endif

</x-app-layout>
