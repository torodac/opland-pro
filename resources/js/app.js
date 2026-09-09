import Alpine from 'alpinejs';
import {
    Chart,
    BarController,
    LineController,
    PieController,
    DoughnutController,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, LineController, PieController, DoughnutController, BarElement, LineElement, PointElement, ArcElement, CategoryScale, LinearScale, Tooltip);

window.Alpine = Alpine;
Alpine.start();

// ───────────────────────── Aviso previo de aprobación de informe mensual (vm) ─────────────────────────
// Envoltorio de fetch para los guardados que pueden alterar un informe mensual en proceso de
// aprobación (App\Services\InformeAprobacionGuard). El backend responde 409 con
// {requiere_confirmacion, mensaje} si el usuario/mes está en_aprobacion y la petición no llevaba
// confirmar_reset=1 -- aquí se pregunta con confirm() y, si acepta, se reintenta la misma petición
// añadiendo ese flag. Si responde 423, el informe ya está aprobado y bloqueado: no hay reintento.
// OJO: el 409 también lo usan otros endpoints para conflictos de negocio ajenos a esto (p.ej.
// "La pausa ya está registrada"), así que solo se intercepta si trae requiere_confirmacion=true;
// en cualquier otro caso se devuelve la respuesta tal cual para que el llamante la trate igual
// que antes (se usa res.clone() para poder inspeccionar el JSON sin consumir el body original).
window.fetchConAprobacion = async function (url, options = {}) {
    options.headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
    let res = await fetch(url, options);

    if (res.status === 409) {
        let data = {};
        try { data = await res.clone().json(); } catch (e) {}
        if (data.requiere_confirmacion) {
            if (!confirm(data.mensaje)) return null;

            if (options.body instanceof FormData) {
                options.body.set('confirmar_reset', '1');
                res = await fetch(url, options);
            } else {
                const retryUrl = url.includes('?') ? url + '&confirmar_reset=1' : url + '?confirmar_reset=1';
                res = await fetch(retryUrl, options);
            }
        }
    }

    if (res.status === 423) {
        let data = {};
        try { data = await res.clone().json(); } catch (e) {}
        alert(data.error || data.mensaje || 'Este informe ya está aprobado y bloqueado. No se puede modificar.');
        return null;
    }

    return res;
};

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

// Extrae {color, nombre, valor} de un dataPoint del tooltip de Chart.js.
function filaDe(tooltip, dp, i) {
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
    return { color, nombre, valor };
}

function filaHtml({ color, nombre, valor }) {
    return `<div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">
        <span style="width:8px;height:8px;border-radius:2px;flex-shrink:0;background:${color};"></span>
        <span style="color:#6b7280;">${nombre}:</span>
        <span style="font-weight:600;margin-left:auto;padding-left:10px;">${valor}</span>
    </div>`;
}

// Fila sin swatch de color (no es una serie del gráfico) para el nº de propiedades activas ese mes.
function filaPropiedadesHtml(n) {
    if (n === null || n === undefined) return '';
    return `<div style="display:flex;align-items:center;gap:7px;margin-top:6px;padding-top:5px;border-top:1px solid #f3f4f6;color:#9ca3af;">
        <span>Propiedades activas:</span>
        <span style="font-weight:600;margin-left:auto;padding-left:10px;color:#374151;">${n}</span>
    </div>`;
}

function tooltipHtml(tooltip, chart) {
    let html = '';
    (tooltip.title || []).forEach((t) => {
        html += `<div style="font-weight:700;color:#111827;margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e5e7eb;">${t}</div>`;
    });

    const dataIndex = tooltip.dataPoints[0] ? tooltip.dataPoints[0].dataIndex : null;
    const propiedades = chart && chart.opPropiedades;

    // Gráfico "por ejercicio": dos columnas lado a lado, una por año (dataset.columna: 0=anterior,
    // 1=actual, asignado en renderInformeFinancieroChart) — más fácil de comparar que una lista
    // vertical mezclando ambos años. El resto de gráficos (interanual, waterfall, operativo) siguen
    // con la lista de siempre.
    const esPorEjercicio = chart && chart.canvas && chart.canvas.id === 'chart-ejercicio'
        && tooltip.dataPoints.every((dp) => dp.dataset.columna !== undefined);

    if (esPorEjercicio) {
        const columnas = [[], []];
        tooltip.dataPoints.forEach((dp, i) => columnas[dp.dataset.columna].push(filaDe(tooltip, dp, i)));

        const anioDe = (filas) => {
            const m = (filas[0] && filas[0].nombre || '').match(/(\d{4})/);
            return m ? m[1] : '';
        };
        const columnaHtml = (filas, numPropiedades) => {
            if (!filas.length) return '';
            const anio = anioDe(filas);
            const sinAnio = (nombre) => nombre.replace(/\s*\d{4}\s*→?\s*$/, '');
            return `<div style="flex:1;min-width:110px;">
                <div style="font-weight:600;color:#374151;margin-bottom:5px;">${anio}</div>
                ${filas.map((f) => filaHtml({ ...f, nombre: sinAnio(f.nombre) })).join('')}
                ${filaPropiedadesHtml(numPropiedades)}
            </div>`;
        };

        const propAnterior = propiedades && dataIndex !== null ? propiedades.anterior[dataIndex] : null;
        const propActual   = propiedades && dataIndex !== null ? propiedades.actual[dataIndex]   : null;
        html += `<div style="display:flex;gap:16px;">${columnaHtml(columnas[0], propAnterior)}${columnaHtml(columnas[1], propActual)}</div>`;
        return html;
    }

    tooltip.dataPoints.forEach((dp, i) => {
        html += filaHtml(filaDe(tooltip, dp, i));
    });
    if (propiedades && dataIndex !== null) {
        html += filaPropiedadesHtml(propiedades.actual[dataIndex]);
    }
    return html;
}

function tooltipExterno(context) {
    const { chart, tooltip } = context;
    const el = getOrCreateTooltip(chart);

    if (tooltip.opacity === 0) {
        el.style.opacity = 0;
        return;
    }

    if (tooltip.body) el.innerHTML = tooltipHtml(tooltip, chart);

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

    // columna: 0=año anterior, 1=año actual — solo la usa el tooltip de "chart-ejercicio" para
    // agrupar en dos columnas lado a lado (ver tooltipHtml en la sección de tooltip, arriba).
    const datasets = [
        { type: 'bar', label: labels.ingresosAnterior, data: g.barras.anteriorIngresos, backgroundColor: 'rgba(249,115,22,0.35)', yAxisID: 'y', order: 2, columna: 0 },
        { type: 'bar', label: labels.gastosAnterior,   data: g.barras.anteriorGastos,   backgroundColor: 'rgba(55,65,81,0.35)',  yAxisID: 'y', order: 2, columna: 0 },
        { type: 'bar', label: labels.ingresosActual,   data: g.barras.actualIngresos,   backgroundColor: '#f97316', yAxisID: 'y', order: 2, columna: 1 },
        { type: 'bar', label: labels.gastosActual,     data: g.barras.actualGastos,     backgroundColor: '#374151', yAxisID: 'y', order: 2, columna: 1 },
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
            columna: l.dashed ? 0 : 1,
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

    // No es una opción de Chart.js — se lee desde tooltipHtml() para mostrar "Propiedades activas"
    // sin tener que crear un dataset/eje falso solo para transportar este dato.
    chart.opPropiedades = g.propiedades || null;

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

window.renderInformeOperativoClusters = function (canvasId, categorias, series, opciones) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !series || !series.length) return null;

    opciones = opciones || {};
    const sufijo = opciones.sufijo || '';

    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    // Un stack por año (anterior/actual) para que se vean como dos columnas apiladas lado a
    // lado por mes; dentro de cada stack, un dataset por tipo_renta con el mismo color en ambos
    // años (el año anterior a menor opacidad, igual que en el informe financiero).
    const datasets = series.map((s) => ({
        label: `${s.tipoRenta} ${s.anio}`,
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
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}${sufijo}`,
                    },
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    max: opciones.maxY,
                    ticks: { precision: 0, callback: (v) => `${v}${sufijo}` },
                    grid: { color: '#f3f4f6' },
                },
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

// Nf: "Evolución de rentabilidad por hora" -- barras agrupadas, una serie por curso, con hueco
// (null) en los meses que aún no han llegado dentro del curso en curso. Tooltip nativo de
// Chart.js (sin el HTML a medida de arriba, no hace falta para un caso tan simple).
window.renderNfRentabilidadChart = function (canvasId, series, categorias) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    if (opCharts[canvasId]) {
        opCharts[canvasId].destroy();
    }

    const datasets = series.map((s) => ({
        label: s.label,
        data: s.data,
        backgroundColor: s.color,
    }));

    const chart = new Chart(canvas, {
        type: 'bar',
        data: { labels: categorias, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11.5 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y === null ? 'sin datos' : ctx.parsed.y.toFixed(1) + ' €/h'}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => v + ' €' },
                    grid: { color: 'rgba(0,0,0,.06)' },
                },
                x: { grid: { display: false } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

// Nf: "Evolución del negocio" -- Facturación (barras, coloreadas por curso igual que en el
// gráfico de rentabilidad) + Tique medio y Clientes activos (líneas). Los pills de este gráfico
// (independientes de los de "rentabilidad") no solo ocultan los valores de un curso: quitan
// también sus meses del eje X. Sin etiquetas fijas sobre el gráfico -- solo tooltip al pasar el
// ratón, igual que el resto de gráficos de la página.
window.renderNfEvolucionChart = function (canvasId, evolucion, cursoColorMap) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    if (opCharts[canvasId]) {
        opCharts[canvasId].destroy();
    }

    const colorTique    = '#3B6FA6'; // azul -- distinto de los colores de curso de las barras
    const colorClientes = '#7B4FA0'; // morado

    const labels          = evolucion.map((e) => e.ym);
    const facturacionData = evolucion.map((e) => e.facturacion);
    const tiqueData       = evolucion.map((e) => e.tiqueMedio);
    const clientesData    = evolucion.map((e) => e.clientesActivos);
    const coloresBarras   = evolucion.map((e) => cursoColorMap[e.curso] || '#E8987A');

    const chart = new Chart(canvas, {
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'Facturación', data: facturacionData, backgroundColor: coloresBarras, yAxisID: 'y', order: 2, borderRadius: 3 },
                { type: 'line', label: 'Tique medio', data: tiqueData, borderColor: colorTique, backgroundColor: colorTique, yAxisID: 'y1', order: 1, tension: 0.25, pointRadius: 3, spanGaps: false },
                { type: 'line', label: 'Clientes activos', data: clientesData, borderColor: colorClientes, backgroundColor: colorClientes, yAxisID: 'y1', order: 1, tension: 0.25, pointRadius: 3, spanGaps: false },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 20 } },
            // mode:'index' + intersect:false -- un único tooltip por mes con los 3 valores
            // juntos (Facturación, Tique medio, Clientes activos), no uno distinto por
            // barra/línea según a cuál se acerque el ratón.
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { size: 11.5 },
                        // Facturación no tiene un color único (varía por curso, ya explicado por
                        // las barras + los pills) -- solo tiene sentido un swatch de leyenda para
                        // las dos líneas.
                        filter: (item) => item.text !== 'Facturación',
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            if (ctx.parsed.y === null || ctx.parsed.y === undefined) return `${ctx.dataset.label}: sin datos`;
                            if (ctx.dataset.label === 'Facturación') return `Facturación: ${ctx.parsed.y.toLocaleString('es-ES')} €`;
                            if (ctx.dataset.label === 'Tique medio') return `Tique medio: ${ctx.parsed.y.toFixed(1)} €`;
                            return `Clientes activos: ${ctx.parsed.y}`;
                        },
                    },
                },
            },
            scales: {
                y:  { beginAtZero: true, position: 'left',  grid: { color: 'rgba(0,0,0,.06)' }, ticks: { callback: (v) => v + ' €' } },
                y1: { beginAtZero: true, position: 'right', grid: { display: false } },
                x:  { grid: { display: false } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

// Nf "Marketing" -- reparto de clientes activos por género, anillo (doughnut).
window.renderNfGeneroChart = function (canvasId, genero) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    const orden = [
        { key: 'M', label: 'M', color: '#B5439E' },
        { key: 'H', label: 'H', color: '#3B6FA6' },
        { key: 'B', label: '(En blanco)', color: '#D2A72C' },
    ].filter((s) => genero[s.key] > 0);

    const chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: orden.map((s) => s.label),
            datasets: [{ data: orden.map((s) => genero[s.key]), backgroundColor: orden.map((s) => s.color) }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11.5 } } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.parsed}` } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

// Nf "Marketing" -- histograma de edades (tramos de 5 años) de los clientes Fitness activos hoy,
// apilado por género -- mismos colores que el gráfico de tarta de al lado.
window.renderNfEdadesChart = function (canvasId, edades) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: edades.map((e) => e.label),
            datasets: [
                { label: 'M', data: edades.map((e) => e.M), backgroundColor: '#B5439E', stack: 'edad' },
                { label: 'H', data: edades.map((e) => e.H), backgroundColor: '#3B6FA6', stack: 'edad' },
                { label: '(En blanco)', data: edades.map((e) => e.B), backgroundColor: '#D2A72C', stack: 'edad' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11.5 } } },
                tooltip: { mode: 'index', intersect: false },
            },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,.06)' } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

// Nf "Marketing" -- línea genérica con una serie por curso (reutilizada para "% hombres" y "edad
// media"), mismo patrón de colores/pills que "Evolución de rentabilidad por hora".
window.renderNfLineaCursoChart = function (canvasId, series, categorias, sufijo) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    if (opCharts[canvasId]) opCharts[canvasId].destroy();

    const datasets = series.map((s) => ({
        label: s.label,
        data: s.data,
        borderColor: s.color,
        backgroundColor: s.color,
        tension: 0.25,
        pointRadius: 3,
        spanGaps: false,
    }));

    const chart = new Chart(canvas, {
        type: 'line',
        data: { labels: categorias, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11.5 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y === null ? 'sin datos' : ctx.parsed.y + sufijo}`,
                    },
                },
            },
            scales: {
                y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,.06)' }, ticks: { callback: (v) => v + sufijo } },
                x: { grid: { display: false } },
            },
        },
    });

    opCharts[canvasId] = chart;
    return chart;
};

