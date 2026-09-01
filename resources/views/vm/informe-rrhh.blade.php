<x-app-layout :breadcrumb="$breadcrumb" :project="$project">
<style>
  .dash-root{
    color-scheme: light;
    position: relative;
    --bg-page:      #f9f9f7;
    --surface:      #fcfcfb;
    --surface-2:    #f3f2ee;
    --text-primary: #0b0b0b;
    --text-secondary:#52514e;
    --text-muted:   #898781;
    --text-deleted: #bcbab2;
    --grid:         #e1e0d9;
    --axis:         #c3c2b7;
    --border:       rgba(11,11,11,0.10);
    --good-text:    #006300;

    --s1: #2a78d6; /* blue   - Limpieza / Coste real / Altas */
    --s2: #eb6834; /* orange - Mantenimiento / Presupuesto / Bajas */
    --s3: #1baf7a; /* aqua   - SSCC */
    --s4: #9c7fd6; /* violeta - comparación año anterior */

    --st-good: #0ca30c;
    --st-warning: #fab219;
    --st-critical: #d03b3b;
  }
  @media (prefers-color-scheme: dark) {
    :root:where(:not([data-theme="light"])) .dash-root{
      color-scheme: dark;
      --bg-page:      #0d0d0d;
      --surface:      #1a1a19;
      --surface-2:    #232322;
      --text-primary: #ffffff;
      --text-secondary:#c3c2b7;
      --text-muted:   #898781;
      --text-deleted: #55554f;
      --grid:         #2c2c2a;
      --axis:         #383835;
      --border:       rgba(255,255,255,0.10);
      --good-text:    #0ca30c;

      --s1: #3987e5;
      --s2: #d95926;
      --s3: #199e70;
      --s4: #a98ce8;

      --st-good: #0ca30c;
      --st-warning: #fab219;
      --st-critical: #e66767;
    }
  }
  :root[data-theme="dark"] .dash-root{
    color-scheme: dark;
    --bg-page:      #0d0d0d;
    --surface:      #1a1a19;
    --surface-2:    #232322;
    --text-primary: #ffffff;
    --text-secondary:#c3c2b7;
    --text-muted:   #898781;
    --text-deleted: #55554f;
    --grid:         #2c2c2a;
    --axis:         #383835;
    --border:       rgba(255,255,255,0.10);
    --good-text:    #0ca30c;

    --s1: #3987e5;
    --s2: #d95926;
    --s3: #199e70;
    --s4: #a98ce8;

    --st-good: #0ca30c;
    --st-warning: #fab219;
    --st-critical: #e66767;
  }

  .dash-root{
    background: var(--bg-page);
    color: var(--text-primary);
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    padding: 20px clamp(12px, 3vw, 28px) 50px;
    box-sizing: border-box;
    min-height: 100%;
  }
  .dash-root *{ box-sizing: border-box; }
  .dash-root :focus-visible{ outline: 2px solid var(--s1); outline-offset: 2px; }

  .stat-row{
    display:grid; grid-template-columns: repeat(5, 1fr);
    gap:12px; margin-bottom:26px;
  }
  @media (max-width: 900px){
    .stat-row{ grid-template-columns: none; grid-auto-flow: column; grid-auto-columns: minmax(150px,1fr); overflow-x:auto; }
  }
  .stat-tile{
    background:var(--surface); border:1px solid var(--border); border-radius:14px;
    padding:16px 18px;
  }
  .stat-tile .label{ font-size:11.5px; color:var(--text-muted); font-weight:600; }
  .stat-tile .value{
    font-size:26px; font-weight:650; margin:4px 0 2px; letter-spacing:-.01em;
    font-variant-numeric: tabular-nums;
  }
  .stat-tile .delta{ font-size:12px; color:var(--text-secondary); }
  .stat-tile .delta.up{ color:var(--good-text); font-weight:600; }
  .stat-tile .delta.warn{ color:var(--st-warning); font-weight:600; }

  section.block{ margin-bottom: 34px; }
  .block-head{ display:flex; align-items:baseline; gap:10px; margin-bottom:14px; }
  .block-head h2{ font-size:16.5px; font-weight:650; margin:0; }
  .block-head .sub{ font-size:12.5px; color:var(--text-muted); }

  .grid-2{ display:grid; grid-template-columns: 1.4fr 1fr; gap:16px; }
  .grid-2-even{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
  @media (max-width: 860px){ .grid-2, .grid-2-even{ grid-template-columns: 1fr; } }

  .card{
    background:var(--surface); border:1px solid var(--border); border-radius:16px;
    padding:18px 18px 14px; position:relative;
  }
  .card h3{ font-size:13.5px; font-weight:650; margin:0 0 12px; }

  .legend{ display:flex; gap:14px; flex-wrap:wrap; font-size:11.5px; color:var(--text-secondary); margin: 2px 0 10px; }
  .legend .item{ display:flex; align-items:center; gap:6px; }
  .legend .swatch{ width:10px; height:10px; border-radius:3px; flex:none; }
  .legend .swatch.line{ border-radius:0; height:2px; width:14px; }
  .legend .swatch.dashed{ background:none; border-top:2px dashed currentColor; height:0; width:14px; }

  .chart-wrap{ position:relative; overflow-x:auto; }
  .chart-wrap svg{ display:block; width:100%; height:230px; overflow:visible; }
  .axis-label{ font-size:9.5px; fill: var(--text-muted); }
  .grid-line{ stroke: var(--grid); stroke-width:1; }

  .tooltip{
    position:absolute; pointer-events:none; z-index:5;
    background:var(--text-primary); color:var(--bg-page);
    font-size:11.5px; line-height:1.4; padding:7px 10px; border-radius:8px;
    box-shadow:0 4px 16px rgba(0,0,0,.18);
    opacity:0; transform:translate(-50%, calc(-100% - 10px)); transition:opacity .1s;
    white-space:nowrap;
  }
  .tooltip.show{ opacity:1; }
  .tooltip b{ font-weight:700; }
  .tooltip .row{ display:flex; align-items:center; gap:6px; }
  .tooltip .dot{ width:7px; height:7px; border-radius:50%; flex:none; }

  .dept-bars{ display:flex; align-items:flex-end; gap:14px; height:230px; padding:0 4px; border-bottom:1px solid var(--axis); }
  .dept-bars .bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
  .dept-bars .bar{ width:60%; border-radius:6px 6px 0 0; cursor:pointer; min-height:2px; }
  .dept-labels{ display:flex; gap:14px; padding:6px 4px 0; }
  .dept-labels span{ flex:1; text-align:center; font-size:10.5px; color:var(--text-secondary); font-weight:600; }

  /* ── Matriz de coste laboral por persona ─────────────────────────── */
  .matrix-scroll{ overflow-x:auto; }
  .matrix{ font-size:12.5px; min-width:1040px; }
  .matrix-row{
    display:grid;
    grid-template-columns:
      minmax(170px,1.7fr)   /* Departamento / puesto */
      minmax(96px,0.9fr)    /* Alta / baja */
      minmax(84px,0.9fr)    /* Total real */
      minmax(66px,0.75fr)   /* Desviación */
      minmax(84px,0.9fr)    /* Presupuesto */
      minmax(84px,0.9fr)    /* Ppto bruto */
      repeat(4, minmax(48px,0.55fr)); /* Salario, Bonus dpto/puesto/personal */
    gap:6px; align-items:center;
  }
  .dept-body .matrix-row .name{ display:flex; flex-direction:column; gap:1px; line-height:1.25; }
  .matrix-row .name .cargo-sub{ font-weight:400; font-size:11px; color:var(--text-muted); }
  .matrix-row .col-sm{ font-size:8px; }
  .matrix-row .name .user-link{ color:inherit; text-decoration:none; }
  .matrix-row .name .user-link:hover{ text-decoration:underline; color:var(--s1); }
  .matrix-row.is-deleted .name .user-link:hover{ color:var(--text-deleted); }
  .matrix-row .col-total{ font-weight:650; color:var(--text-primary); }
  .matrix-row .dev-over{ color:var(--st-warning); font-weight:650; }
  .matrix-row .dev-under{ color:var(--good-text); font-weight:650; }
  .matrix-row .dev-flat{ color:var(--text-muted); }
  /* Negrita reservada a las filas de resumen (departamento/total); en el detalle de cada persona
     Total real y Desviación van con el mismo peso que el resto de columnas. */
  .dept-body .matrix-row .col-total{ font-weight:400; }
  .dept-body .matrix-row .dev-over, .dept-body .matrix-row .dev-under{ font-weight:400; }
  .matrix-row.is-deleted, .matrix-row.is-deleted *{ color:var(--text-deleted) !important; font-weight:400 !important; }
  .matrix-head{
    color:var(--text-muted); font-size:11px; font-weight:600; padding:0 0 8px;
    border-bottom:1px solid var(--border);
  }
  .matrix-head span:not(:first-child), .matrix-num{ text-align:right; font-variant-numeric:tabular-nums; }
  .col-fechas{ text-align:right; font-size:8pt; }
  details.dept{ border-bottom:1px solid var(--border); }
  details.dept:last-of-type{ border-bottom:none; }
  details.dept summary{
    list-style:none; cursor:pointer; padding:10px 0; font-weight:650;
  }
  details.dept summary::-webkit-details-marker{ display:none; }
  details.dept summary .matrix-row .name{ display:flex; flex-direction:row; align-items:center; gap:6px; }
  details.dept summary .chev{
    display:inline-block; width:8px; height:8px; border-right:1.6px solid var(--text-muted);
    border-bottom:1.6px solid var(--text-muted); transform:rotate(-45deg); transition:transform .15s;
    flex:none;
  }
  details.dept[open] summary .chev{ transform:rotate(45deg); }
  .dept-body{ padding: 0 0 10px 14px; }
  .dept-body .matrix-row{ padding:5px 0; color:var(--text-secondary); }
  .dept-body .matrix-row .name{ color:var(--text-primary); }
  .dept-note{ font-size:11px; color:var(--text-muted); font-style:italic; padding:4px 0 2px; }
  .matrix-total{
    font-weight:700; padding-top:12px; margin-top:4px; border-top:2px solid var(--axis);
  }
</style>

<div class="dash-root">

  <div class="stat-row" id="stat-row"></div>

  <!-- ───────────────── Costes laborales ───────────────── -->
  <section class="block">
    <div class="block-head">
      <h2>Costes laborales</h2>
      <span class="sub">Coste real (nóminas) vs. presupuesto (contrato + bonus) y desglose por departamento</span>
    </div>
    <div class="grid-2">
      <div class="card">
        <h3>Coste laboral mensual</h3>
        <div class="legend">
          <span class="item"><span class="swatch line" style="color:var(--s1);background:var(--s1);"></span>Coste real</span>
          <span class="item"><span class="swatch dashed" style="color:var(--s2);"></span>Presupuesto</span>
        </div>
        <div class="chart-wrap" id="chart-coste"></div>
      </div>
      <div class="card">
        <h3>Coste acumulado por departamento</h3>
        <div class="chart-wrap" id="chart-depto-coste"></div>
        <div class="dept-labels" id="chart-depto-coste-labels"></div>
      </div>
    </div>
    <div class="card" style="margin-top:16px;">
      <h3>Matriz de coste laboral por persona (hasta el mes de {{ $etiquetaMesCostes }}, mes de última nómina subida)</h3>
      <div class="matrix-scroll"><div class="matrix" id="matrix-salarios"></div></div>
    </div>
  </section>

  <!-- ───────────────── Rotación y evolución de plantilla ───────────────── -->
  <section class="block">
    <div class="block-head">
      <h2>Rotación de plantilla</h2>
      <span class="sub">Altas y bajas mensuales, y evolución de la plantilla por departamento</span>
    </div>
    <div class="grid-2-even">
      <div class="card">
        <h3>Altas y bajas por mes</h3>
        <div class="legend" id="legend-rotacion"></div>
        <div class="chart-wrap" id="chart-rotacion"></div>
      </div>
      <div class="card">
        <h3>Plantilla por departamento</h3>
        <div class="legend" id="legend-depto"></div>
        <div class="chart-wrap" id="chart-plantilla"></div>
      </div>
    </div>
  </section>

  <div class="tooltip" id="tooltip"></div>
</div>

<script>
(function(){
  const root = document.querySelector('.dash-root');
  const tip = document.getElementById('tooltip');
  const svgNS = 'http://www.w3.org/2000/svg';

  function cssVar(name){ return getComputedStyle(root).getPropertyValue(name).trim(); }

  // Posición del tooltip SIEMPRE relativa a .dash-root (que es su antecesor posicionado, ver
  // position:relative arriba) -- antes se calculaba relativo al contenedor de cada gráfico, que
  // no es el mismo elemento que el tooltip usa como referencia, y por eso aparecía desplazado.
  function showTip(ev, html){
    const rect = root.getBoundingClientRect();
    tip.innerHTML = html;
    tip.style.left = (ev.clientX - rect.left) + 'px';
    tip.style.top  = (ev.clientY - rect.top) + 'px';
    tip.classList.add('show');
  }
  function hideTip(){ tip.classList.remove('show'); }

  // ── Datos reales, inyectados desde InformeRrhhController ─────────────
  // MESES = plantilla/altas/bajas, hasta hoy. MESES_COSTES = coste real/presupuesto, hasta el
  // último mes con nómina importada (van por detrás, no siempre coinciden).
  const MESES = @json($mesesLabels);
  const MESES_COSTES = @json($mesesLabelsCostes);

  const costeReal  = @json($costeReal);
  const costePresu = @json($costePresu);

  // Orden = orden de apilado (de abajo arriba): SSCC abajo, Mantenimiento en medio, Limpieza arriba.
  const deptos = [
    { key:'sscc',          name:'SSCC (resto dptos)', color:'--s3', data:@json($plantillaSscc) },
    { key:'mantenimiento', name:'Mantenimiento',      color:'--s2', data:@json($plantillaMantenimiento) },
    { key:'limpieza',      name:'Limpieza',           color:'--s1', data:@json($plantillaLimpieza) },
  ];

  const costeDepto = [
    { name:'Limpieza',  value:{{ $costeDeptoAcumulado['limpieza'] }},      color:'--s1' },
    { name:'Mantenim.', value:{{ $costeDeptoAcumulado['mantenimiento'] }}, color:'--s2' },
    { name:'SSCC',      value:{{ $costeDeptoAcumulado['sscc'] }},          color:'--s3' },
  ];

  const altas = @json($altas);
  const bajas = @json($bajas);
  const plantillaInicioAnio = {{ $plantillaInicioAnio }};
  const plantillaAnioAnterior = @json($plantillaAnioAnterior);
  const anioAnterior = {{ $anioAnterior }};

  // ── Stat tiles ───────────────────────────────────────────────────────
  const stats = @json($statsCards);
  const statRow = document.getElementById('stat-row');
  stats.forEach(s => {
    const elDiv = document.createElement('div');
    elDiv.className = 'stat-tile';
    elDiv.innerHTML = `<div class="label">${s.label}</div><div class="value">${s.value}</div><div class="delta ${s.cls}">${s.delta}</div>`;
    statRow.appendChild(elDiv);
  });

  // ── Helpers de escala ────────────────────────────────────────────────
  function niceMax(v){
    if (!isFinite(v) || v <= 0) return 1;
    const mag = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / mag;
    let step;
    if (n <= 1) step = 1; else if (n <= 2) step = 2; else if (n <= 5) step = 5; else step = 10;
    return Math.ceil(v / (step*mag/5)) * (step*mag/5);
  }

  function makeSVG(w, h){
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    svg.setAttribute('preserveAspectRatio', 'none');
    return svg;
  }
  function el(tag, attrs){
    const e = document.createElementNS(svgNS, tag);
    for (const k in attrs) e.setAttribute(k, attrs[k]);
    return e;
  }

  const PAD = { top:14, right:10, bottom:22, left:34 };
  const W = 620, H = 230;
  const plotW = W - PAD.left - PAD.right;
  const plotH = H - PAD.top - PAD.bottom;

  // xAt/drawGrid parametrizados por el array de meses de CADA gráfico: "Coste laboral mensual"
  // puede tener menos meses que "Plantilla por departamento" / "Altas y bajas" (la nómina va por
  // detrás de hoy), así que no pueden compartir una única escala de meses.
  function nMesesOf(mesesArr){ return Math.max(mesesArr.length, 2); }
  function xAtFor(mesesArr){
    const n = nMesesOf(mesesArr);
    return i => PAD.left + i * (plotW / (n - 1));
  }

  function drawGrid(svg, maxVal, fmt, mesesArr){
    const xAt = xAtFor(mesesArr);
    const steps = 4;
    for (let i = 0; i <= steps; i++){
      const v = maxVal * i / steps;
      const y = PAD.top + plotH - (v / maxVal) * plotH;
      svg.appendChild(el('line', { x1:PAD.left, x2:W-PAD.right, y1:y, y2:y, class:'grid-line' }));
      const t = el('text', { x:PAD.left - 6, y:y+3, class:'axis-label', 'text-anchor':'end' });
      t.textContent = fmt(v);
      svg.appendChild(t);
    }
    mesesArr.forEach((m, i) => {
      const t = el('text', { x:xAt(i), y:H-6, class:'axis-label', 'text-anchor':'middle' });
      t.textContent = m;
      svg.appendChild(t);
    });
  }

  function pathFromPoints(pts){
    return pts.map((p,i) => (i===0?'M':'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  }

  // Formato español (punto de millar, coma decimal) para cualquier cifra en euros mostrada fuera
  // de la matriz (que ya usa su propio eur() con toLocaleString).
  // Formato manual (no toLocaleString): el separador de miles vía Intl depende de los datos ICU
  // del navegador y puede perderse para ciertos valores/entornos. Con un regex propio sale siempre.
  function fmtEs(v, decimals){
    const d = decimals || 0;
    const fixed = Number(v).toFixed(d);
    const [intPart, decPart] = fixed.split('.');
    const withSep = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return decPart ? withSep + ',' + decPart : withSep;
  }

  // ── Chart 1: Coste laboral (línea real + línea presupuesto punteada) ──
  if (MESES_COSTES.length < 2){
    document.getElementById('chart-coste').innerHTML = '<p style="font-size:12px;color:var(--text-muted);">Todavía no hay suficientes meses con nómina importada este año.</p>';
  } else (function(){
    const container = document.getElementById('chart-coste');
    const xAt = xAtFor(MESES_COSTES);
    const maxVal = niceMax(Math.max(...costeReal, ...costePresu, 1) * 1.08);
    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => fmtEs(Math.round(v)) + 'k', MESES_COSTES);

    const yAt = v => PAD.top + plotH - (v / maxVal) * plotH;
    const ptsReal = costeReal.map((v,i) => [xAt(i), yAt(v)]);
    const ptsPresu = costePresu.map((v,i) => [xAt(i), yAt(v)]);

    const last = MESES_COSTES.length - 1;
    const areaPath = pathFromPoints(ptsReal) + ` L${xAt(last).toFixed(1)},${(PAD.top+plotH).toFixed(1)} L${xAt(0).toFixed(1)},${(PAD.top+plotH).toFixed(1)} Z`;
    svg.appendChild(el('path', { d:areaPath, fill:cssVar('--s1'), opacity:'0.10', stroke:'none' }));
    svg.appendChild(el('path', { d:pathFromPoints(ptsPresu), fill:'none', stroke:cssVar('--s2'), 'stroke-width':2, 'stroke-dasharray':'5,4' }));
    svg.appendChild(el('path', { d:pathFromPoints(ptsReal), fill:'none', stroke:cssVar('--s1'), 'stroke-width':2.5, 'stroke-linecap':'round', 'stroke-linejoin':'round' }));

    ptsReal.forEach((p,i) => {
      const hit = el('circle', { cx:p[0], cy:p[1], r:9, fill:'transparent', style:'cursor:pointer;' });
      hit.addEventListener('mousemove', ev => {
        const over = costeReal[i] - costePresu[i];
        showTip(ev,
          `<b>${MESES_COSTES[i]} {{ $anio }}</b><br>
           <div class="row"><span class="dot" style="background:${cssVar('--s1')}"></span>Real: ${fmtEs(costeReal[i],1)}k €</div>
           <div class="row"><span class="dot" style="background:${cssVar('--s2')}"></span>Presupuesto: ${fmtEs(costePresu[i],1)}k €</div>
           <span style="color:${over>0?cssVar('--st-warning'):cssVar('--good-text')}">${over>0?'+':''}${fmtEs(over,1)}k € vs. presupuesto</span>`);
      });
      hit.addEventListener('mouseleave', hideTip);
      svg.appendChild(hit);
      svg.appendChild(el('circle', { cx:p[0], cy:p[1], r:2.5, fill:cssVar('--s1') }));
    });
    container.appendChild(svg);
  })();

  if (MESES.length < 2){
    ['chart-plantilla','chart-rotacion'].forEach(id => {
      document.getElementById(id).innerHTML = '<p style="font-size:12px;color:var(--text-muted);">No hay suficientes meses todavía este año para dibujar la evolución.</p>';
    });
  } else {

  // ── Chart 2: Plantilla por departamento (área apilada) ────────────────
  (function(){
    const container = document.getElementById('chart-plantilla');
    const xAt = xAtFor(MESES);
    const totals = MESES.map((_,i) => deptos.reduce((s,d) => s + d.data[i], 0));
    const maxVal = niceMax(Math.max(...totals, 1) * 1.08);
    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => Math.round(v), MESES);
    const yAt = v => PAD.top + plotH - (v / maxVal) * plotH;

    let cumLower = MESES.map(() => 0);
    deptos.forEach(d => {
      const cumUpper = cumLower.map((v,i) => v + d.data[i]);
      const upperPts = cumUpper.map((v,i) => [xAt(i), yAt(v)]);
      const lowerPts = cumLower.map((v,i) => [xAt(i), yAt(v)]).reverse();
      const path = pathFromPoints(upperPts) + ' ' + pathFromPoints(lowerPts).replace('M','L') + ' Z';
      const band = el('path', { d:path, fill:cssVar(d.color), opacity:'0.85', stroke:cssVar('--surface'), 'stroke-width':2 });
      band.addEventListener('mousemove', ev => {
        const rect = container.getBoundingClientRect();
        const bx = ev.clientX - rect.left;
        const i = Math.max(0, Math.min(MESES.length-1, Math.round((bx - PAD.left) / (plotW/(nMesesOf(MESES)-1)))));
        showTip(ev, `<b>${d.name} · ${MESES[i]}</b><br>${d.data[i]} personas`);
      });
      band.addEventListener('mouseleave', hideTip);
      svg.appendChild(band);
      cumLower = cumUpper;
    });
    container.appendChild(svg);

    const legend = document.getElementById('legend-depto');
    deptos.forEach(d => {
      const span = document.createElement('span');
      span.className = 'item';
      span.innerHTML = `<span class="swatch" style="background:${cssVar(d.color)}"></span>${d.name}`;
      legend.appendChild(span);
    });
  })();

  // ── Chart 4: Altas y bajas por mes, en cascada (variación neta acumulada) + línea de
  // comparación con el total de plantilla del año anterior ──────────────────────────────────
  (function(){
    const container = document.getElementById('chart-rotacion');
    const xAt = xAtFor(MESES);

    let cum = plantillaInicioAnio;
    const cumBefore = [], cumAfter = [];
    MESES.forEach((_,i) => {
      cumBefore.push(cum);
      cum = cum + altas[i] - bajas[i];
      cumAfter.push(cum);
    });

    const maxVal = niceMax(Math.max(plantillaInicioAnio, ...cumBefore, ...cumAfter, ...plantillaAnioAnterior, 1) * 1.1);
    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => Math.round(v), MESES);
    const yAt = v => PAD.top + plotH - (v / maxVal) * plotH;

    // Línea de referencia: plantilla al cierre de {{ $anioAnterior }}, punto de partida de la cascada.
    svg.appendChild(el('line', { x1:PAD.left, x2:W-PAD.right, y1:yAt(plantillaInicioAnio), y2:yAt(plantillaInicioAnio), stroke:cssVar('--axis'), 'stroke-width':1, 'stroke-dasharray':'2,3' }));

    const barW = Math.max(6, Math.min(16, plotW / nMesesOf(MESES) / 1.8));
    MESES.forEach((m,i) => {
      const cx = xAt(i);
      const net = altas[i] - bajas[i];
      const yTop = yAt(Math.max(cumBefore[i], cumAfter[i]));
      const yBot = yAt(Math.min(cumBefore[i], cumAfter[i]));
      const color = net > 0 ? cssVar('--s1') : (net < 0 ? cssVar('--s2') : cssVar('--text-muted'));
      const bar = el('rect', {
        x:(cx-barW/2).toFixed(1), y:yTop.toFixed(1), width:barW.toFixed(1),
        height:Math.max(yBot-yTop,1.5).toFixed(1), fill:color, rx:2, style:'cursor:pointer;',
      });
      bar.addEventListener('mousemove', ev => {
        const signo = net > 0 ? '+' : '';
        showTip(ev, `<b>${m} {{ $anio }}</b><br>
          Altas: ${altas[i]} · Bajas: ${bajas[i]}<br>
          Variación neta: ${signo}${net} (plantilla: ${cumAfter[i]})`);
      });
      bar.addEventListener('mouseleave', hideTip);
      svg.appendChild(bar);
    });

    // Línea de comparación: plantilla total en el mismo mes de {{ $anioAnterior }}.
    const ptsAnt = plantillaAnioAnterior.map((v,i) => [xAt(i), yAt(v)]);
    svg.appendChild(el('path', { d:pathFromPoints(ptsAnt), fill:'none', stroke:cssVar('--s4'), 'stroke-width':2, 'stroke-dasharray':'5,4' }));
    ptsAnt.forEach((p,i) => {
      const hit = el('circle', { cx:p[0], cy:p[1], r:8, fill:'transparent', style:'cursor:pointer;' });
      hit.addEventListener('mousemove', ev => showTip(ev, `<b>${MESES[i]} ${anioAnterior}</b><br>${plantillaAnioAnterior[i]} personas`));
      hit.addEventListener('mouseleave', hideTip);
      svg.appendChild(hit);
      svg.appendChild(el('circle', { cx:p[0], cy:p[1], r:2.2, fill:cssVar('--s4') }));
    });

    container.appendChild(svg);

    const legend = document.getElementById('legend-rotacion');
    legend.innerHTML = `
      <span class="item"><span class="swatch" style="background:${cssVar('--s1')}"></span>Aumento neto</span>
      <span class="item"><span class="swatch" style="background:${cssVar('--s2')}"></span>Disminución neta</span>
      <span class="item"><span class="swatch dashed" style="color:${cssVar('--s4')}"></span>Plantilla ${anioAnterior}</span>`;
  })();

  } // fin if (MESES.length >= 2)

  // ── Chart 3: Coste por departamento (barras verticales) ───────────────
  (function(){
    const container = document.getElementById('chart-depto-coste');
    const labelsRow = document.getElementById('chart-depto-coste-labels');
    const wrap = document.createElement('div');
    wrap.className = 'dept-bars';
    const maxVal = Math.max(...costeDepto.map(d => d.value), 1);
    costeDepto.forEach(d => {
      const col = document.createElement('div');
      col.className = 'bar-col';
      const bar = document.createElement('div');
      bar.className = 'bar';
      bar.style.height = (d.value / maxVal * 100) + '%';
      bar.style.background = cssVar(d.color);
      bar.addEventListener('mousemove', ev => showTip(ev, `<b>${d.name}</b><br>${fmtEs(d.value*1000)} € acumulados`));
      bar.addEventListener('mouseleave', hideTip);
      col.appendChild(bar);
      wrap.appendChild(col);
    });
    container.appendChild(wrap);
    costeDepto.forEach(d => {
      const span = document.createElement('span');
      span.textContent = d.name;
      labelsRow.appendChild(span);
    });
  })();

  // ── Matriz de coste laboral por persona ────────────────────────────
  const plantillaMatriz = @json($plantillaMatriz);

  (function(){
    const container = document.getElementById('matrix-salarios');
    // Formato manual (no Intl/toLocaleString): el separador de miles vía toLocaleString('es-ES')
    // depende de los datos ICU del navegador, y en algunos casos se pierde para ciertos valores.
    // Con un regex propio el punto de millar sale siempre, sin depender del entorno.
    const eur = v => Number(v).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' €';
    const cols = ['anual','plusDpto','plusPuesto','plusPersonal'];
    const fechasCell = emp => {
      if (!emp.fechaAlta) return '—';
      return `${emp.fechaAlta} – ${emp.fechaBaja || 'actual'}`;
    };
    const usuarioFormUrlTemplate = @json($usuarioFormUrlTemplate);
    const nameCell = emp => `<span class="name"><a href="${usuarioFormUrlTemplate.replace('__ID__', emp.id)}" target="_blank" rel="noopener" class="user-link" onclick="event.stopPropagation()">${emp.nombre}</a>${emp.cargo ? `<span class="cargo-sub">${emp.cargo}</span>` : ''}</span>`;

    if (!plantillaMatriz.length){
      container.innerHTML = '<p style="font-size:12px;color:var(--text-muted);">No hay plantilla activa con contrato y nómina para mostrar.</p>';
      return;
    }

    function devCell(real, presu){
      const d = real - presu;
      const cls = Math.abs(d) < 50 ? 'dev-flat' : (d > 0 ? 'dev-over' : 'dev-under');
      const sign = d > 0 ? '+' : (d < 0 ? '−' : '');
      return `<span class="matrix-num ${cls}">${sign}${eur(Math.abs(d))}</span>`;
    }

    const head = document.createElement('div');
    head.className = 'matrix-row matrix-head';
    head.innerHTML = `<span>Departamento / puesto</span><span>Alta / baja</span><span>Total real</span><span>Desviación</span><span>Presupuesto</span><span>Ppto bruto</span><span class="col-sm">Salario</span><span class="col-sm">Bonus dpto</span><span class="col-sm">Bonus puesto</span><span class="col-sm">Bonus personal</span>`;
    container.appendChild(head);

    plantillaMatriz.forEach((dept) => {
      const det = document.createElement('details');
      det.className = 'dept';

      const sum = document.createElement('summary');
      sum.innerHTML = `<div class="matrix-row">
        <span class="name"><span class="chev"></span>${dept.name}${dept.sampled ? ` <span style="font-weight:400;color:var(--text-muted);font-size:10.5px;">(${dept.sampleOf} personas)</span>` : ''}</span>
        <span class="col-fechas"></span>
        <span class="matrix-num col-total">${eur(dept.deptTotal)}</span>
        ${devCell(dept.deptTotal, dept.deptPresupuesto)}
        <span class="matrix-num">${eur(dept.deptPresupuesto)}</span>
        <span class="matrix-num">${eur(dept.deptPptoBruto)}</span>
        <span class="matrix-num col-sm">${eur(dept.deptAnual)}</span>
        <span class="matrix-num col-sm">${eur(dept.deptPlusDpto)}</span>
        <span class="matrix-num col-sm">${eur(dept.deptPlusPuesto)}</span>
        <span class="matrix-num col-sm">${eur(dept.deptPlusPersonal)}</span>
      </div>`;
      det.appendChild(sum);

      const body = document.createElement('div');
      body.className = 'dept-body';
      dept.empleados.forEach(emp => {
        const row = document.createElement('div');
        row.className = 'matrix-row' + (emp.deleted ? ' is-deleted' : '');
        row.innerHTML = nameCell(emp) +
          `<span class="col-fechas">${fechasCell(emp)}</span>` +
          `<span class="matrix-num col-total">${eur(emp.total)}</span>` +
          devCell(emp.total, emp.presupuesto) +
          `<span class="matrix-num">${eur(emp.presupuesto)}</span>` +
          `<span class="matrix-num">${eur(emp.pptoBruto)}</span>` +
          cols.map(c => `<span class="matrix-num col-sm">${emp[c] ? eur(emp[c]) : '—'}</span>`).join('');
        body.appendChild(row);
      });
      if (dept.sampled){
        const note = document.createElement('div');
        note.className = 'dept-note';
        note.textContent = `Muestra de ${dept.empleados.length} sobre ${dept.sampleOf} personas — no representa el subtotal ni la desviación exacta del departamento.`;
        body.appendChild(note);
      }
      det.appendChild(body);
      container.appendChild(det);
    });

    const sumBy = k => plantillaMatriz.reduce((s,d) => s + d[k], 0);
    const grandTotal = sumBy('deptTotal');
    const grandPresu = sumBy('deptPresupuesto');
    const totalRow = document.createElement('div');
    totalRow.className = 'matrix-row matrix-total';
    totalRow.innerHTML = `<span>Total</span>` +
      `<span class="col-fechas"></span>` +
      `<span class="matrix-num col-total">${eur(grandTotal)}</span>` +
      devCell(grandTotal, grandPresu) +
      `<span class="matrix-num">${eur(grandPresu)}</span>` +
      `<span class="matrix-num">${eur(sumBy('deptPptoBruto'))}</span>` +
      `<span class="matrix-num col-sm">${eur(sumBy('deptAnual'))}</span>` +
      `<span class="matrix-num col-sm">${eur(sumBy('deptPlusDpto'))}</span>` +
      `<span class="matrix-num col-sm">${eur(sumBy('deptPlusPuesto'))}</span>` +
      `<span class="matrix-num col-sm">${eur(sumBy('deptPlusPersonal'))}</span>`;
    container.appendChild(totalRow);
  })();

})();
</script>
</x-app-layout>
