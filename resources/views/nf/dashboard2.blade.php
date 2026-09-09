<x-app-layout :breadcrumb="[['label'=>'Dashboard','url'=>route('nf.dashboard2',[$project->slug])]]" :project="$project">

<div id="nf-rentabilidad">

<style>
  #nf-rentabilidad .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.1rem;margin-bottom:14px; }
  #nf-rentabilidad .sec-title { font-weight:600;font-size:14.5px;margin:0 0 12px;display:flex;align-items:center; }
  #nf-rentabilidad .info-dot { display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:6px;font-style:normal; }
  #nf-rentabilidad .chart-wrap { height:320px;margin-bottom:20px; }
  #nf-rentabilidad .chart-wrap-sm { height:280px; }
  #nf-rentabilidad .chart-wrap-genero { height:224px; }
  #nf-rentabilidad .mkt-actual-grid { display:grid;grid-template-columns:1fr 1fr;gap:24px; }
  @media (max-width: 800px) { #nf-rentabilidad .mkt-actual-grid { grid-template-columns:1fr; } }
  #nf-rentabilidad .mkt-subtitulo { font-size:12.5px;font-weight:600;color:#6b7280;margin:0 0 8px;text-align:center; }

  #nf-rentabilidad .pills { display:flex;gap:6px;flex-wrap:wrap;margin:0 0 14px; }
  #nf-rentabilidad .pill { display:flex;align-items:center;gap:6px;border:1px solid var(--pill-color);border-radius:999px;padding:4px 12px 4px 8px;font-size:12px;font-weight:600;background:#fff;color:#1B1B18;cursor:pointer;transition:opacity .15s; }
  #nf-rentabilidad .pill .dot { width:8px;height:8px;border-radius:50%;background:var(--pill-color);flex-shrink:0; }
  #nf-rentabilidad .pill.inactivo { opacity:.35; }
  #nf-rentabilidad .pill.inactivo .dot { background:#ccc; }

  #nf-rentabilidad table.rent { width:100%;border-collapse:collapse;font-size:12.5px; }
  #nf-rentabilidad table.rent th { text-align:center;padding:6px 8px;font-weight:600;color:#555;border-bottom:.5px solid rgba(0,0,0,.08);white-space:nowrap; }
  #nf-rentabilidad table.rent th.curso-col { text-align:left; }
  #nf-rentabilidad table.rent td { text-align:center;padding:6px 8px;white-space:nowrap; }
  #nf-rentabilidad table.rent td.curso-col { text-align:left;font-weight:600; }
  #nf-rentabilidad table.rent tr.trow { border-top:.5px solid rgba(0,0,0,.06); }
  #nf-rentabilidad table.rent .gr { color:#d1d5db;font-weight:400;font-size:10px;margin-right:4px; }
  #nf-rentabilidad table.rent .euro { color:#1B1B18; }
  #nf-rentabilidad table.rent .euro.vacio { color:#ccc; }
  #nf-rentabilidad table.rent td.avg { background:#F1EFE8;font-weight:700; }
  #nf-rentabilidad .swatch { display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px;vertical-align:middle; }

  #nf-rentabilidad .nf-tabs { display:flex;gap:4px;border-bottom:1px solid rgba(0,0,0,.08);margin-bottom:16px; }
  #nf-rentabilidad .nf-tab { border:none;background:none;padding:9px 16px;font-size:13.5px;font-weight:600;color:#9ca3af;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px; }
  #nf-rentabilidad .nf-tab:hover { color:#1B1B18; }
  #nf-rentabilidad .nf-tab.active { color:#1B1B18;border-bottom-color:#1B1B18; }
  #nf-rentabilidad .nf-empty { color:#9ca3af;font-size:13px;text-align:center;padding:2.5rem 0; }

  #nf-rentabilidad .obj-cabecera { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px; }
  #nf-rentabilidad .obj-cabecera .sec-title { margin:0; }
  #nf-rentabilidad .obj-selectores { display:flex;gap:10px;align-items:center; }
  #nf-rentabilidad .obj-selectores label { font-size:11px;color:#6b7280;font-weight:600;display:flex;align-items:center;gap:6px; }
  #nf-rentabilidad .obj-selectores select { font-size:12.5px;padding:3px 6px;border:1px solid rgba(0,0,0,.12);border-radius:6px;background:#fff;color:#1B1B18;font-weight:600; }
  #nf-rentabilidad .obj-grid-grupos { display:grid;grid-template-columns:repeat(8, 1fr);row-gap:4px;column-gap:0;justify-items:center; }
  @media (max-width: 600px) { #nf-rentabilidad .obj-grid-grupos { grid-template-columns:repeat(4, 1fr); } }
  #nf-rentabilidad .obj-lineales { display:grid;grid-template-columns:repeat(2, 1fr);gap:8px 28px;margin-top:8px;padding-top:10px;border-top:.5px solid rgba(0,0,0,.06); }
  #nf-rentabilidad .gauge-label { font-size:12px;color:#6b7280;margin:0 0 4px;text-align:center; }
  #nf-rentabilidad .gauge-label b { color:#1B1B18; }
  #nf-rentabilidad .gauge-circ { position:relative;width:133px;height:133px; }
  #nf-rentabilidad .gauge-overlay { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;flex-direction:column;align-items:center;pointer-events:none; }
  #nf-rentabilidad .gauge-objetivo-label { position:absolute;font-size:10px;font-weight:700;color:#1f2937;white-space:nowrap; }
  #nf-rentabilidad .gauge-overlay-valor { font-size:18px;font-weight:700;color:#1B1B18;line-height:1.2; }
  #nf-rentabilidad .gauge-overlay-pct { font-size:14px;font-weight:700; }
  #nf-rentabilidad .gauge-progreso { transition: stroke-dashoffset .7s ease; }

  #nf-rentabilidad .gauge-valor { font-size:20px;font-weight:700;color:#f97316;margin:0 0 8px; }
  #nf-rentabilidad .gauge-valor span { font-size:13px;font-weight:600;color:#9ca3af;margin-left:6px; }
  #nf-rentabilidad .gauge-track-wrap { position:relative;margin-top:20px; }
  #nf-rentabilidad .gauge-objetivo-tag { position:absolute;bottom:100%;transform:translateX(-50%);margin-bottom:3px;font-size:10.5px;font-weight:700;color:#1f2937;white-space:nowrap; }
  #nf-rentabilidad .gauge-track { position:relative;height:10px;background:#F1EFE8;border-radius:5px;overflow:visible; }
  #nf-rentabilidad .gauge-fill { position:absolute;left:0;top:0;height:100%;background:#f97316;border-radius:5px 0 0 5px;transition:width .7s ease; }
  #nf-rentabilidad .gauge-target { position:absolute;top:-3px;bottom:-3px;width:2px;background:#1f2937; }
  #nf-rentabilidad .gauge-scale { display:flex;justify-content:space-between;font-size:10px;color:#c1c1c1;margin-top:4px; }
</style>

<div class="nf-tabs">
    <button type="button" class="nf-tab active" data-tab="objetivos" onclick="nfMostrarTab('objetivos')">Objetivos</button>
    <button type="button" class="nf-tab" data-tab="rentabilidad" onclick="nfMostrarTab('rentabilidad')">Rentabilidad</button>
    <button type="button" class="nf-tab" data-tab="marketing" onclick="nfMostrarTab('marketing')">Marketing</button>
</div>

<div id="nf-tab-rentabilidad" class="nf-tab-panel" hidden>

<div class="section-card">
    <p class="sec-title">Evolución del negocio <span class="app-tooltip"><span class="info-dot">i</span><span class="app-tooltip-box">Facturación (Pendiente + Pagada), tique medio y clientes activos, mes a mes. Al desactivar un curso, sus meses desaparecen también del eje.</span></span></p>
    <div class="pills">
        @foreach ($cursos as $curso)
        <button type="button" class="pill {{ $curso['activo'] ? '' : 'inactivo' }}" style="--pill-color: {{ $curso['color'] }};" data-curso="{{ $curso['label'] }}" onclick="nfToggleNegocio('{{ $curso['label'] }}', this)">
            <span class="dot"></span>{{ $curso['label'] }}
        </button>
        @endforeach
    </div>
    <div class="chart-wrap"><canvas id="chart-nf-evolucion"></canvas></div>
</div>

<div class="section-card">
    <p class="sec-title">Evolución de rentabilidad por hora <span class="app-tooltip"><span class="info-dot">i</span><span class="app-tooltip-box">€/h = facturación de pagos Fitness del mes (Pendiente + Pagada, sin Anulado) ÷ horas de clase estimadas ese mes (8h fijas por cada grupo con contrato activo). Página sin publicar en el menú todavía.</span></span></p>
    <div class="pills">
        @foreach ($cursos as $curso)
        <button type="button" class="pill {{ $curso['activo'] ? '' : 'inactivo' }}" style="--pill-color: {{ $curso['color'] }};" data-curso="{{ $curso['label'] }}" onclick="nfToggleRentabilidad('{{ $curso['label'] }}', this)">
            <span class="dot"></span>{{ $curso['label'] }}
        </button>
        @endforeach
    </div>
    <div class="chart-wrap"><canvas id="chart-nf-rentabilidad"></canvas></div>
</div>

<div class="section-card" style="overflow-x:auto;">
    <p class="sec-title">Detalle por curso y mes</p>
    <table class="rent">
        <thead>
            <tr>
                <th class="curso-col">Curso</th>
                @foreach ($meses as $mes)
                <th>{{ $mes }}</th>
                @endforeach
                <th>Promedio</th>
            </tr>
        </thead>
        <tbody>
            @foreach (array_reverse($cursos) as $curso)
            <tr class="trow" data-curso="{{ $curso['label'] }}" @if(!$curso['activo']) style="display:none;" @endif>
                <td class="curso-col"><span class="swatch" style="background:{{ $curso['color'] }};"></span>{{ $curso['label'] }}</td>
                @php $valores = []; @endphp
                @foreach ($meses as $mes)
                    @php $c = $tabla[$mes][$curso['label']] ?? null; @endphp
                    <td>
                        @if ($c && $c['euroHora'] !== null)
                            <span class="gr">{{ $c['gr'] }}</span><span class="euro">{{ number_format($c['euroHora'], 1, ',', '.') }} €</span>
                            @php $valores[] = $c['euroHora']; @endphp
                        @else
                            <span class="euro vacio">—</span>
                        @endif
                    </td>
                @endforeach
                <td class="avg">{{ count($valores) ? number_format(array_sum($valores) / count($valores), 1, ',', '.') . ' €' : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

</div>

<div id="nf-tab-objetivos" class="nf-tab-panel">

<div class="section-card">
    <div class="obj-cabecera">
        <p class="sec-title">Objetivos <span class="app-tooltip"><span class="info-dot">i</span><span class="app-tooltip-box">Objetivo de facturación por grupo (nf_objetivos) frente a lo realmente facturado (Pendiente + Pagada). El año afecta a todo; el mes afecta a todo salvo al total anual, que siempre suma el curso completo.</span></span></p>
        <div class="obj-selectores">
            <label>Año
                <select id="obj-anio" onchange="nfCargarObjetivos()">
                    @foreach (array_reverse($cursos) as $curso)
                    <option value="{{ $curso['anioInicio'] }}" @selected($curso['activo'] && $curso['anioInicio'] === $anioActualEjercicio)>{{ $curso['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Mes
                <select id="obj-mes" onchange="nfCargarObjetivos()">
                    @foreach ([9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre',1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto'] as $num => $nombreMes)
                    <option value="{{ $num }}" @selected($num === now()->month)>{{ $nombreMes }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div id="obj-grupos" class="obj-grid-grupos"></div>
    <div id="obj-lineales" class="obj-lineales"></div>
</div>

</div>

<div id="nf-tab-marketing" class="nf-tab-panel" hidden>

<div class="section-card">
    <p class="sec-title">Clientes Fitness activos hoy <span class="app-tooltip"><span class="info-dot">i</span><span class="app-tooltip-box">Clientes con un contrato Fitness en vigor hoy. Edad calculada a partir de la fecha de nacimiento; "(En blanco)" son clientes sin género o sin fecha de nacimiento registrada.</span></span></p>
    <div class="mkt-actual-grid">
        <div>
            <p class="mkt-subtitulo">Distribución por género</p>
            <div class="chart-wrap chart-wrap-genero"><canvas id="chart-nf-genero"></canvas></div>
        </div>
        <div>
            <p class="mkt-subtitulo">Distribución por edades</p>
            <div class="chart-wrap chart-wrap-sm"><canvas id="chart-nf-edades"></canvas></div>
        </div>
    </div>
</div>

<div class="section-card">
    <p class="sec-title">Evolución histórica <span class="app-tooltip"><span class="info-dot">i</span><span class="app-tooltip-box">% de hombres y edad media de los clientes Fitness activos, mes a mes, por curso.</span></span></p>
    <div class="pills" id="mkt-pills"></div>
    <p class="mkt-subtitulo">% de hombres</p>
    <div class="chart-wrap"><canvas id="chart-nf-pct-hombres"></canvas></div>
    <p class="mkt-subtitulo">Edad media</p>
    <div class="chart-wrap"><canvas id="chart-nf-edad-media"></canvas></div>
</div>

</div>

</div>

<script>
    var nfObjetivosCargados = false;

    function nfEsc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function nfMostrarTab(tab) {
        document.querySelectorAll('#nf-rentabilidad .nf-tab-panel').forEach(function (p) {
            p.hidden = p.id !== 'nf-tab-' + tab;
        });
        document.querySelectorAll('#nf-rentabilidad .nf-tab').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tab === tab);
        });
        if (tab === 'objetivos' && !nfObjetivosCargados) {
            nfObjetivosCargados = true;
            nfCargarObjetivos();
        }
        if (tab === 'rentabilidad') {
            nfCargarRentabilidad();
        }
        if (tab === 'marketing') {
            nfCargarMarketing();
        }
    }

    // Objetivos es la pestaña activa por defecto -- se carga en cuanto el DOM está listo (los
    // canvas de Rentabilidad, en cambio, esperan a que se entre en esa pestaña por primera vez).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { nfMostrarTab('objetivos'); });
    } else {
        nfMostrarTab('objetivos');
    }

    function nfFormatoEuro(v) {
        return v.toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' €';
    }

    var nfGaugeSeq = 0;
    var nfGaugeAnimarPendientes = []; // [{id, dashoffset}] -- arranca cada arco en 0% y lo anima hasta el valor real tras insertarlo en el DOM

    // Gauge circular tipo Animata (https://animata.design/docs/graphs/gauge-chart): un círculo
    // completo con un hueco abajo, pista gris + arco de progreso con extremos redondeados, mismo
    // SVG puro que el componente original (sin canvas ni Chart.js). Escala adaptativa:
    // - Si NO se ha llegado al objetivo: la escala del anillo ES el objetivo (fracción = actual/
    //   objetivo), así que la cuantía del objetivo cae siempre justo en el extremo del arco
    //   (fracción 1) -- no hace falta marca, sería redundante con el propio borde del anillo.
    // - Si se ha superado: la escala pasa a ser el propio actual (arco 100% coloreado) y el
    //   objetivo se sitúa, proporcionalmente, en objetivo/actual dentro del anillo -- ahí sí se
    //   dibuja la marca radial, en el mismo punto que la cuantía.
    // El arco arranca en 0 y se anima hasta su valor real vía nfAnimarGauges(). Las posiciones de
    // la marca y la cuantía se calculan por trigonometría (no con el truco de dasharray, que solo
    // da control fino sobre longitud de arco, no sobre un punto exacto).
    // El SVG solo dibuja el anillo (+ la marca, si toca) -- ya NO reserva lienzo extra para la
    // cuantía del objetivo, que se devuelve aparte como HTML flotante (ver nfGaugeInnerHtml):
    // antes el margen para ese texto estaba precalculado para el peor caso en TODOS los gauges
    // por igual, así que en la mayoría (donde el texto cae en un ángulo menos exigente) sobraba
    // muchísimo espacio en blanco alrededor del anillo. El texto flotante puede desbordar el
    // recuadro del gauge sin recortarse (el contenedor no aplica overflow:hidden), así que el
    // anillo puede ocupar casi todo el hueco disponible.
    function nfGaugeSvg(objetivo, actual, color, size, grosor, gap, radio) {
        var centro = size / 2;
        var circunferencia = Math.PI * radio * 2;

        var excedido = objetivo > 0 && actual > objetivo;
        var escalaMax = excedido ? actual : (objetivo > 0 ? objetivo : Math.max(actual, 1));
        var fraccionActual = escalaMax > 0 ? Math.max(0, Math.min(actual / escalaMax, 1)) : 0;
        var fraccionObjetivo = escalaMax > 0 ? Math.max(0, Math.min(objetivo / escalaMax, 1)) : 1;

        var gapDeg = (gap / circunferencia) * 360;
        var sweep = 360 - gapDeg;
        var anguloInicio = 90 + gapDeg / 2;

        function puntoEn(fraccion, r) {
            var angRad = (anguloInicio + fraccion * sweep) * Math.PI / 180;
            return { x: centro + r * Math.cos(angRad), y: centro + r * Math.sin(angRad) };
        }

        var rotacion = 90 + (gap / (2 * circunferencia)) * 360; // para los <circle> con dasharray (igual que Animata)
        var dashoffsetFinal = circunferencia - fraccionActual * (circunferencia - gap);
        var id = 'gauge-progreso-' + (nfGaugeSeq++);
        nfGaugeAnimarPendientes.push({ id: id, dashoffset: dashoffsetFinal });

        var svg = '' +
            '<svg width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '">' +
            '<circle r="' + radio + '" cx="' + centro + '" cy="' + centro + '" fill="transparent" stroke="#F1EFE8" stroke-width="' + grosor + '" stroke-dasharray="' + circunferencia + '" stroke-dashoffset="' + gap + '" stroke-linecap="round" transform="rotate(' + rotacion + ' ' + centro + ' ' + centro + ')" />' +
            '<circle id="' + id + '" class="gauge-progreso" r="' + radio + '" cx="' + centro + '" cy="' + centro + '" fill="transparent" stroke="' + color + '" stroke-width="' + grosor + '" stroke-dasharray="' + circunferencia + '" stroke-dashoffset="' + circunferencia + '" stroke-linecap="round" transform="rotate(' + rotacion + ' ' + centro + ' ' + centro + ')" />';

        // La marca es corta (apenas sobresale del grosor del anillo) así que cabe de sobra dentro
        // del propio lienzo compacto -- solo la cuantía (texto ancho) necesita salir del SVG.
        if (excedido) {
            var pIn  = puntoEn(fraccionObjetivo, radio - grosor / 2 - 3);
            var pOut = puntoEn(fraccionObjetivo, radio + grosor / 2 + 3);
            svg += '<line x1="' + pIn.x + '" y1="' + pIn.y + '" x2="' + pOut.x + '" y2="' + pOut.y + '" stroke="#1f2937" stroke-width="2.5" stroke-linecap="round" />';
        }

        svg += '</svg>';
        return svg;
    }

    // Cuantía del objetivo, posicionada por trigonometría sobre el mismo círculo pero como HTML
    // normal en vez de dentro del SVG -- así no necesita lienzo propio reservado y puede asomarse
    // fuera del recuadro del gauge (el contenedor no recorta overflow) sin que el anillo tenga que
    // encogerse para dejarle sitio "por si acaso" en los ángulos más exigentes.
    function nfGaugeEtiquetaHtml(objetivo, actual, size, grosor, gap, radio) {
        var centro = size / 2;
        var circunferencia = Math.PI * radio * 2;
        var excedido = objetivo > 0 && actual > objetivo;
        var escalaMax = excedido ? actual : (objetivo > 0 ? objetivo : Math.max(actual, 1));
        var fraccionObjetivo = escalaMax > 0 ? Math.max(0, Math.min(objetivo / escalaMax, 1)) : 1;

        var gapDeg = (gap / circunferencia) * 360;
        var sweep = 360 - gapDeg;
        var anguloInicio = 90 + gapDeg / 2;
        var angRad = (anguloInicio + fraccionObjetivo * sweep) * Math.PI / 180;
        var r = radio + grosor / 2 + 5;
        var x = centro + r * Math.cos(angRad);
        var y = centro + r * Math.sin(angRad);

        var anchorTransform = 'translate(-50%,-50%)';
        if (x > centro + 4) anchorTransform = 'translate(4px,-50%)';
        else if (x < centro - 4) anchorTransform = 'translate(calc(-100% - 4px),-50%)';

        return '<span class="gauge-objetivo-label" style="left:' + (x / size * 100) + '%;top:' + (y / size * 100) + '%;transform:' + anchorTransform + ';">' + nfFormatoEuro(objetivo) + '</span>';
    }

    function nfAnimarGauges() {
        var pendientes = nfGaugeAnimarPendientes;
        nfGaugeAnimarPendientes = [];
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                pendientes.forEach(function (g) {
                    var el = document.getElementById(g.id);
                    if (el) el.style.strokeDashoffset = g.dashoffset;
                });
                document.querySelectorAll('#nf-rentabilidad .gauge-fill[data-target-width]').forEach(function (el) {
                    el.style.width = el.dataset.targetWidth + '%';
                });
            });
        });
    }

    // Tamaño del anillo de grupo: el SVG en sí es compacto (solo el anillo + una marca corta),
    // sin margen reservado para la cuantía -- ver nfGaugeEtiquetaHtml.
    var NF_GAUGE_SIZE = 133, NF_GAUGE_GROSOR = 11, NF_GAUGE_GAP = 44, NF_GAUGE_RADIO = 56;

    // Tacómetro de grupo: circular, coloreado con el color propio del grupo. Sin título (ni
    // nombre ni cuantía) encima -- el nombre se identifica por el color.
    // Distribución al tresbolillo a propósito: el grid tiene 8 columnas y cada gauge ocupa 2 --
    // la fila 1 (i=0..3) empieza en las columnas impares (1,3,5,7), la fila 2 (i=4..6) arranca
    // media columna más a la derecha (2,4,6), dejando el característico hueco de medio gauge a
    // cada lado de la segunda fila.
    function nfGaugeGrupoHtml(d, i) {
        var inicioCol = i < 4 ? (i * 2 + 1) : ((i - 4) * 2 + 2);
        return '' +
            '<div style="grid-column:' + inicioCol + ' / span 2;">' +
            '<div class="gauge-circ">' +
                nfGaugeSvg(d.objetivo, d.actual, d.color, NF_GAUGE_SIZE, NF_GAUGE_GROSOR, NF_GAUGE_GAP, NF_GAUGE_RADIO) +
                nfGaugeEtiquetaHtml(d.objetivo, d.actual, NF_GAUGE_SIZE, NF_GAUGE_GROSOR, NF_GAUGE_GAP, NF_GAUGE_RADIO) +
                '<div class="gauge-overlay">' +
                    '<span class="gauge-overlay-valor">' + nfFormatoEuro(d.actual) + '</span>' +
                    '<span class="gauge-overlay-pct" style="color:' + d.color + ';">' + d.pct.toFixed(1) + '%</span>' +
                '</div>' +
            '</div>' +
            '</div>';
    }

    // Tacómetro de los totales: barra horizontal tipo "bullet chart" (como en la captura de
    // referencia original) -- track gris, relleno naranja hasta el valor real, marca vertical en
    // el punto del objetivo.
    function nfEscalaGauge(objetivo, actual) {
        var base = Math.max(objetivo, actual, 1) * 1.15;
        var paso = base < 2000 ? 100 : (base < 10000 ? 500 : (base < 50000 ? 1000 : 5000));
        return Math.ceil(base / paso) * paso;
    }

    function nfGaugeBarraHtml(nombre, d) {
        var escala = nfEscalaGauge(d.objetivo, d.actual);
        var pctFill = Math.min(d.actual / escala * 100, 100);
        var pctTarget = Math.min(d.objetivo / escala * 100, 100);
        return '' +
            '<div>' +
            '<p class="gauge-label">' + nfEsc(nombre) + '</p>' +
            '<p class="gauge-valor">' + nfFormatoEuro(d.actual) + '<span>' + d.pct.toFixed(1) + '%</span></p>' +
            '<div class="gauge-track-wrap">' +
            '<div class="gauge-objetivo-tag" style="left:' + pctTarget + '%;">' + nfFormatoEuro(d.objetivo) + '</div>' +
            '<div class="gauge-track">' +
            '<div class="gauge-fill" data-target-width="' + pctFill + '" style="width:0%;"></div>' +
            '<div class="gauge-target" style="left:' + pctTarget + '%;"></div>' +
            '</div>' +
            '</div>' +
            '<div class="gauge-scale"><span>0 €</span><span>' + nfFormatoEuro(escala) + '</span></div>' +
            '</div>';
    }

    function nfCargarObjetivos() {
        var anio = document.getElementById('obj-anio').value;
        var mes  = document.getElementById('obj-mes').value;
        var url  = '{{ route('nf.dashboard2.objetivos', [$project->slug]) }}?anio=' + anio + '&mes=' + mes;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.getElementById('obj-grupos').innerHTML = data.grupos.map(function (g, i) { return nfGaugeGrupoHtml(g, i); }).join('');

                document.getElementById('obj-lineales').innerHTML =
                    nfGaugeBarraHtml('Total mensual', data.totalMensual) +
                    nfGaugeBarraHtml('Total anual', data.totalAnual);

                nfAnimarGauges();
            });
    }
</script>

<script>
    var nfRentabilidadChart = null;
    var nfEvolucionChart = null;
    var nfCursoIndex = {};
    var nfCursoColor = @json(collect($cursos)->pluck('color', 'label'));
    var nfCursosInfo = @json($cursos); // [{label, activo, ...}] -- estado inicial de los pills
    var nfRentabilidadOcultos = {};
    var nfNegocioOcultos = {};
    nfCursosInfo.forEach(function (c) {
        nfRentabilidadOcultos[c.label] = !c.activo;
        nfNegocioOcultos[c.label] = !c.activo;
    });
    var nfEvolucionData = @json($evolucion); // fuente completa, sin filtrar -- nunca se muta
    var nfRentabilidadCargada = false;

    // Los canvas de esta pestaña se inicializan la primera vez que se muestra (no al cargar la
    // página) -- si Chart.js midiera el canvas mientras el panel está oculto (hidden), el ancho
    // sería 0 y el gráfico saldría roto. Objetivos, en cambio, es la pestaña activa por defecto,
    // así que sí puede cargarse de inmediato (ver nfMostrarTab / carga inicial más abajo).
    function nfCargarRentabilidad() {
        if (nfRentabilidadCargada) return;
        nfRentabilidadCargada = true;

        var series     = @json($series);
        var categorias = @json($meses);
        series.forEach(function (s, i) { nfCursoIndex[s.label] = i; });

        nfRentabilidadChart = window.renderNfRentabilidadChart('chart-nf-rentabilidad', series, categorias);
        // Aplica el estado inicial de los pills (solo los 3 últimos cursos activos).
        Object.keys(nfRentabilidadOcultos).forEach(function (label) {
            if (nfRentabilidadOcultos[label] && nfCursoIndex[label] !== undefined) {
                nfRentabilidadChart.setDatasetVisibility(nfCursoIndex[label], false);
            }
        });
        nfRentabilidadChart.update();

        var filtradoInicial = nfEvolucionData.filter(function (e) { return !nfNegocioOcultos[e.curso]; });
        nfEvolucionChart = window.renderNfEvolucionChart('chart-nf-evolucion', filtradoInicial, nfCursoColor);
    }

    function nfToggleRentabilidad(label, btn) {
        var visible = btn.classList.toggle('inactivo') === false;
        nfRentabilidadOcultos[label] = !visible;

        if (nfRentabilidadChart && nfCursoIndex[label] !== undefined) {
            nfRentabilidadChart.setDatasetVisibility(nfCursoIndex[label], visible);
            nfRentabilidadChart.update();
        }
        document.querySelectorAll('#nf-rentabilidad table.rent tr.trow[data-curso="' + CSS.escape(label) + '"]').forEach(function (tr) {
            tr.style.display = visible ? '' : 'none';
        });
    }

    // "Evolución del negocio" es una línea de tiempo continua (no una serie por curso), así que
    // desactivar un curso no solo oculta sus valores -- se reconstruye el gráfico entero sin esos
    // meses, quitándolos también del eje X (a diferencia del de rentabilidad, que solo oculta la
    // serie manteniendo el eje intacto).
    function nfToggleNegocio(label, btn) {
        var visible = btn.classList.toggle('inactivo') === false;
        nfNegocioOcultos[label] = !visible;

        var filtrado = nfEvolucionData.filter(function (e) { return !nfNegocioOcultos[e.curso]; });
        nfEvolucionChart = window.renderNfEvolucionChart('chart-nf-evolucion', filtrado, nfCursoColor);
    }

    // Pestaña "Marketing" -- foto actual (género + edades) y evolución histórica (% hombres +
    // edad media) con pills de curso análogos a los de Rentabilidad (mismo color/estado inicial,
    // simplemente ocultan la serie del curso en los dos gráficos de línea, sin tocar el eje X).
    var nfMarketingCargado = false;
    var nfMarketingOcultos = {};
    var nfMktCursoIndex = {};
    var nfMktPctChart = null;
    var nfMktEdadChart = null;

    function nfCargarMarketing() {
        if (nfMarketingCargado) return;
        nfMarketingCargado = true;

        fetch('{{ route('nf.dashboard2.marketing', [$project->slug]) }}', { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                window.renderNfGeneroChart('chart-nf-genero', data.genero);
                window.renderNfEdadesChart('chart-nf-edades', data.edades);

                document.getElementById('mkt-pills').innerHTML = data.evolucion.map(function (c) {
                    return '<button type="button" class="pill' + (c.activo ? '' : ' inactivo') + '" style="--pill-color: ' + c.color + ';" data-curso="' + c.label + '" onclick="nfToggleMarketing(\'' + c.label + '\', this)"><span class="dot"></span>' + c.label + '</button>';
                }).join('');

                data.evolucion.forEach(function (c, i) {
                    nfMktCursoIndex[c.label] = i;
                    nfMarketingOcultos[c.label] = !c.activo;
                });

                var seriesPct  = data.evolucion.map(function (c) { return { label: c.label, color: c.color, data: c.pctHombres }; });
                var seriesEdad = data.evolucion.map(function (c) { return { label: c.label, color: c.color, data: c.edadMedia }; });

                nfMktPctChart  = window.renderNfLineaCursoChart('chart-nf-pct-hombres', seriesPct, data.meses, '%');
                nfMktEdadChart = window.renderNfLineaCursoChart('chart-nf-edad-media', seriesEdad, data.meses, ' años');

                Object.keys(nfMarketingOcultos).forEach(function (label) {
                    if (nfMarketingOcultos[label] && nfMktCursoIndex[label] !== undefined) {
                        nfMktPctChart.setDatasetVisibility(nfMktCursoIndex[label], false);
                        nfMktEdadChart.setDatasetVisibility(nfMktCursoIndex[label], false);
                    }
                });
                nfMktPctChart.update();
                nfMktEdadChart.update();
            });
    }

    function nfToggleMarketing(label, btn) {
        var visible = btn.classList.toggle('inactivo') === false;
        nfMarketingOcultos[label] = !visible;
        var idx = nfMktCursoIndex[label];
        if (idx === undefined) return;
        if (nfMktPctChart)  { nfMktPctChart.setDatasetVisibility(idx, visible);  nfMktPctChart.update(); }
        if (nfMktEdadChart) { nfMktEdadChart.setDatasetVisibility(idx, visible); nfMktEdadChart.update(); }
    }
</script>

</x-app-layout>
