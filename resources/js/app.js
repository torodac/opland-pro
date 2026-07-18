import Alpine from 'alpinejs';
import {
    Chart,
    BarController,
    LineController,
    BarElement,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, LineController, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip);

window.Alpine = Alpine;
Alpine.start();

// ───────────────────────── Informe financiero VM: gráfico Chart.js (barras + línea, doble eje) ─────────────────────────

function formatearEuros(v) {
    const abs = Math.abs(v);
    if (abs >= 1000000) return (v / 1000000).toLocaleString('es-ES', { maximumFractionDigits: 2 }) + 'M€';
    if (abs >= 1000) return (v / 1000).toLocaleString('es-ES', { maximumFractionDigits: 0 }) + 'k€';
    return v.toLocaleString('es-ES', { maximumFractionDigits: 0 }) + '€';
}

const opCharts = {};

// Tooltip HTML propio con el estilo de app-tooltip-box de Opland (fondo claro, borde verde,
// esquinas redondeadas) en vez del tooltip nativo de Chart.js dibujado en canvas. Ademas de
// visual, esto evita el bug de "tooltip pegado": al ser un <div> con pointer-events:none no
// intercepta el raton, asi que el mouseout del canvas siempre llega y Chart.js lo oculta bien.
function getOrCreateTooltip(chart) {
    const wrap = chart.canvas.parentNode;
    let el = wrap.querySelector('.op-chart-tooltip');
    if (!el) {
        el = document.createElement('div');
        el.className = 'op-chart-tooltip';
        el.style.cssText = [
            'position:absolute', 'pointer-events:none', 'opacity:0',
            'transition:opacity .1s ease, left .1s ease, top .1s ease',
            'background:#f9fafb', 'color:#1f2937', 'border:1px solid #166534',
            'border-radius:8px', 'font-size:11px', 'line-height:1.5',
            'padding:8px 10px', 'box-shadow:0 4px 12px rgba(0,0,0,.08)',
            'z-index:40', 'white-space:nowrap', 'transform:translate(-50%, calc(-100% - 10px))',
        ].join(';');
        wrap.style.position = 'relative';
        wrap.appendChild(el);
    }
    return el;
}

function tooltipHtml(tooltip) {
    let html = '';
    (tooltip.title || []).forEach((t) => {
        html += `<div style="font-weight:700;color:#111827;margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e5e7eb;">${t}</div>`;
    });
    tooltip.dataPoints.forEach((dp, i) => {
        // dp.element.options.backgroundColor es el color YA RESUELTO para esta barra en concreto
        // (necesario cuando el dataset define backgroundColor como array, un color por barra, como
        // en el waterfall) — dp.dataset.* solo vale cuando el color es un unico string para todo el dataset.
        const color = (dp.element && dp.element.options && dp.element.options.backgroundColor)
            || dp.dataset.borderColor
            || dp.dataset.backgroundColor;
        const linea = tooltip.body[i].lines[0];
        const sep = linea.indexOf(':');
        const nombre = sep === -1 ? linea : linea.slice(0, sep);
        const valor  = sep === -1 ? ''   : linea.slice(sep + 1).trim();
        html += `<div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">
            <span style="width:8px;height:8px;border-radius:2px;flex-shrink:0;background:${color};"></span>
            <span style="color:#6b7280;">${nombre}:</span>
            <span style="font-weight:600;margin-left:auto;padding-left:10px;">${valor}</span>
        </div>`;
    });
    return html;
}

function tooltipExterno(context) {
    const { chart, tooltip } = context;
    const el = getOrCreateTooltip(chart);

    if (tooltip.opacity === 0) {
        el.style.opacity = 0;
        return;
    }

    if (tooltip.body) el.innerHTML = tooltipHtml(tooltip);

    el.style.opacity = 1;
    el.style.left = tooltip.caretX + 'px';
    el.style.top = tooltip.caretY + 'px';
}

window.renderInformeFinancieroChart = function (canvasId, g, labels) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !g || g.vacio) return null;

    if (opCharts[canvasId]) {
        opCharts[canvasId].destroy();
    }

    const datasets = [
        { type: 'bar', label: labels.ingresosAnterior, data: g.barras.anteriorIngresos, backgroundColor: 'rgba(249,115,22,0.35)', yAxisID: 'y', order: 2 },
        { type: 'bar', label: labels.gastosAnterior,   data: g.barras.anteriorGastos,   backgroundColor: 'rgba(55,65,81,0.35)',  yAxisID: 'y', order: 2 },
        { type: 'bar', label: labels.ingresosActual,   data: g.barras.actualIngresos,   backgroundColor: '#f97316', yAxisID: 'y', order: 2 },
        { type: 'bar', label: labels.gastosActual,     data: g.barras.actualGastos,     backgroundColor: '#374151', yAxisID: 'y', order: 2 },
    ];

    g.lineas.forEach((l) => {
        const nPuntos = l.valores.length;
        datasets.push({
            type: 'line',
            label: l.label,
            data: l.valores,
            borderColor: l.color,
            backgroundColor: l.color,
            borderDash: l.dashed ? [5, 4] : [],
            borderWidth: 2,
            pointRadius: (ctx) => (l.destacarUltimo && ctx.dataIndex === nPuntos - 1 ? 5 : 3),
            pointBackgroundColor: l.color,
            pointBorderColor: '#fff',
            pointBorderWidth: 1.2,
            tension: 0.15,
            yAxisID: 'y1',
            order: 1,
        });
    });

    const chart = new Chart(canvas, {
        data: { labels: g.categorias, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: false,
                    external: tooltipExterno,
                    // Sin esto, un mes sin dato en alguna serie (p.ej. "anterior" cuando ese año no
                    // existe todavia) sigue entrando en el tooltip con valor null, y formatearEuros()
                    // revienta al llamar toLocaleString() sobre null. Se descarta esa serie de la lista,
                    // igual que el grafico ya no dibuja barra/punto para ese mes.
                    filter: (ctx) => ctx.parsed.y !== null && ctx.parsed.y !== undefined,
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${formatearEuros(ctx.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: (ctx) => (g.mesActualIndex === ctx.index ? '#4b5563' : '#9ca3af'),
                        font: (ctx) => ({ weight: g.mesActualIndex === ctx.index ? '700' : '400', size: 10 }),
                    },
                },
                y: {
                    position: 'left',
                    min: g.escalaBarras.min,
                    max: g.escalaBarras.max,
                    grid: { color: '#f3f4f6' },
                    ticks: { stepSize: g.escalaBarras.paso, callback: (v) => formatearEuros(v) },
                },
                y1: {
                    position: 'right',
                    min: g.escalaLineas.min,
                    max: g.escalaLineas.max,
                    grid: { display: false },
                    ticks: { stepSize: g.escalaLineas.paso, callback: (v) => formatearEuros(v) },
                },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

window.resizeInformeFinancieroChart = function (canvasId) {
    const c = opCharts[canvasId];
    if (!c) return;
    // resize() ya dispara un update interno, pero si el canvas se creo oculto (display:none)
    // la geometria de interaccion (donde cae cada punto para el tooltip) puede quedar rezagada;
    // un update('none') explicito fuerza a recalcularla del todo, sin animacion.
    c.resize();
    c.update('none');
};

// ───────────────────────── Informe operativo VM: propiedades por mes, apiladas por cluster ─────────────────────────

function hexToRgba(hex, alpha) {
    const h = hex.replace('#', '');
    const r = parseInt(h.substring(0, 2), 16);
    const g = parseInt(h.substring(2, 4), 16);
    const b = parseInt(h.substring(4, 6), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

window.renderInformeOperativoClusters = function (canvasId, categorias, series) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !series || !series.length) return null;

    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    // Un stack por año (anterior/actual) para que se vean como dos columnas apiladas lado a
    // lado por mes; dentro de cada stack, un dataset por cluster con el mismo color en ambos
    // años (el año anterior a menor opacidad, igual que en el informe financiero).
    const datasets = series.map((s) => ({
        label: `${s.cluster} ${s.anio}`,
        data: s.valores,
        backgroundColor: s.esActual ? s.color : hexToRgba(s.color, 0.35),
        stack: s.esActual ? 'actual' : 'anterior',
        maxBarThickness: 30,
    }));

    const chart = new Chart(canvas, {
        type: 'bar',
        data: { labels: categorias, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: false,
                    external: tooltipExterno,
                    filter: (ctx) => ctx.parsed.y !== null && ctx.parsed.y !== undefined && ctx.parsed.y !== 0,
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}`,
                    },
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

// ───────────────────────── Puente de rentabilidad (waterfall): Ingresos → Resultado del ejercicio ─────────────────────────
// Barras "flotantes" (data = [inicio, fin] en vez de un valor unico): los pasos "total"/"subtotal"/
// "final" van de 0 al valor absoluto; los "delta" flotan entre el acumulado antes y despues de
// sumarlos. Conectores punteados y etiquetas de valor se dibujan a mano via plugins de Chart.js,
// porque no hay ningun tipo de grafico "waterfall" nativo.

window.renderWaterfallPyg = function (canvasId, steps) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !steps || !steps.length) return null;

    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    let running = 0;
    const floatData = [];
    const colors = [];

    steps.forEach((s) => {
        if (s.tipo === 'total' || s.tipo === 'subtotal' || s.tipo === 'final') {
            running = s.valor;
            floatData.push([0, running]);
            colors.push(s.tipo === 'final' ? '#0f766e' : (s.tipo === 'subtotal' ? '#94a3b8' : '#2563eb'));
        } else {
            const inicio = running;
            running += s.valor;
            floatData.push([inicio, running]);
            colors.push(s.valor >= 0 ? '#16a34a' : '#ef4444');
        }
    });

    const conectores = {
        id: 'waterfallConectores',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(0);
            const yScale = chart.scales.y;
            const ctx = chart.ctx;
            ctx.save();
            ctx.strokeStyle = '#d1d5db';
            ctx.setLineDash([3, 3]);
            ctx.lineWidth = 1;
            for (let i = 0; i < meta.data.length - 1; i++) {
                const y = yScale.getPixelForValue(floatData[i][1]);
                const barActual = meta.data[i];
                const barSiguiente = meta.data[i + 1];
                ctx.beginPath();
                ctx.moveTo(barActual.x + barActual.width / 2, y);
                ctx.lineTo(barSiguiente.x - barSiguiente.width / 2, y);
                ctx.stroke();
            }
            ctx.restore();
        },
    };

    const etiquetas = {
        id: 'waterfallEtiquetas',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(0);
            const yScale = chart.scales.y;
            const ctx = chart.ctx;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.font = '700 11px sans-serif';
            meta.data.forEach((bar, i) => {
                const [a, b] = floatData[i];
                const topPixel = yScale.getPixelForValue(Math.max(a, b));
                ctx.fillStyle = colors[i];
                ctx.fillText(formatearEuros(steps[i].valor), bar.x, topPixel - 8);
            });
            ctx.restore();
        },
    };

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: steps.map((s) => s.label),
            datasets: [{
                data: floatData,
                backgroundColor: colors,
                borderRadius: 3,
                maxBarThickness: 60,
            }],
        },
        plugins: [conectores, etiquetas],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 24 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: false,
                    external: tooltipExterno,
                    callbacks: {
                        label: (ctx) => `${steps[ctx.dataIndex].label}: ${formatearEuros(steps[ctx.dataIndex].valor)}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: { display: false, grid: { display: false } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};
