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
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13.5px;font-weight:700;color:#111827;">Propiedades por mes y cluster — {{ $anioActual }} vs {{ $anioAnterior }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:1px;">vm_propiedades.cluster · columnas apiladas, propiedades activas cada mes según fecha_inicio / fecha_fin</div>
        </div>
        <div style="padding:14px 18px;">
            @if(empty($series))
            <p style="font-size:12.5px;color:#9ca3af;margin:0;">Sin propiedades activas en {{ $anioActual }} ni en {{ $anioAnterior }}.</p>
            @else
            <div style="display:flex;gap:16px;font-size:11.5px;color:#6b7280;margin-bottom:10px;flex-wrap:wrap;">
                @foreach($series as $s)
                <span style="display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:2px;flex-shrink:0;background:{{ $s['color'] }};opacity:{{ $s['esActual'] ? 1 : 0.35 }};"></span>{{ ucfirst($s['cluster']) }} {{ $s['anio'] }}</span>
                @endforeach
            </div>
            <div style="height:280px;">
                <canvas id="chart-operativo-clusters"></canvas>
            </div>
            @endif
        </div>
    </div>

</div>

@if(!empty($series))
<script>
    (function () {
        var categorias = @json($categorias);
        var series     = @json($series);
        function pintar() { window.renderInformeOperativoClusters('chart-operativo-clusters', categorias, series); }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pintar);
        } else {
            pintar();
        }
    })();
</script>
@endif

</x-app-layout>
