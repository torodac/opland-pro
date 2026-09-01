<x-app-layout :breadcrumb="$breadcrumb" :project="$project">
<style>
  .dash-root{
    color-scheme: light;
    --bg-page:      #f9f9f7;
    --surface:      #fcfcfb;
    --surface-2:    #f3f2ee;
    --text-primary: #0b0b0b;
    --text-secondary:#52514e;
    --text-muted:   #898781;
    --grid:         #e1e0d9;
    --axis:         #c3c2b7;
    --border:       rgba(11,11,11,0.10);
    --good-text:    #006300;

    --s1: #2a78d6; /* blue   - Limpieza / Coste real / Altas */
    --s2: #eb6834; /* orange - Mantenimiento / Presupuesto / Bajas */
    --s3: #1baf7a; /* aqua   - SSCC */

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
      --grid:         #2c2c2a;
      --axis:         #383835;
      --border:       rgba(255,255,255,0.10);
      --good-text:    #0ca30c;

      --s1: #3987e5;
      --s2: #d95926;
      --s3: #199e70;

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
    --grid:         #2c2c2a;
    --axis:         #383835;
    --border:       rgba(255,255,255,0.10);
    --good-text:    #0ca30c;

    --s1: #3987e5;
    --s2: #d95926;
    --s3: #199e70;

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
  .chart-wrap svg{ display:block; width:100%; height:auto; overflow:visible; }
  .axis-label{ font-size:9.5px; fill: var(--text-muted); }
  .grid-line{ stroke: var(--grid); stroke-width:1; }

  .tooltip{
    position:absolute; pointer-events:none; z-index:5;
    background:var(--text-primary); color:var(--bg-page);
    font-size:11.5px; line-height:1.4; padding:7px 10px; border-radius:8px;
    box-shadow:0 4px 16px rgba(0,0,0,.18);
    opacity:0; transform:translate(-50%,-8px); transition:opacity .1s;
    white-space:nowrap;
  }
  .tooltip.show{ opacity:1; }
  .tooltip b{ font-weight:700; }
  .tooltip .row{ display:flex; align-items:center; gap:6px; }
  .tooltip .dot{ width:7px; height:7px; border-radius:50%; flex:none; }

  .dept-bars{ display:flex; align-items:flex-end; gap:14px; height:190px; padding:0 4px; border-bottom:1px solid var(--axis); }
  .dept-bars .bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
  .dept-bars .bar{ width:60%; border-radius:6px 6px 0 0; cursor:pointer; min-height:2px; }
  .dept-labels{ display:flex; gap:14px; padding:6px 4px 0; }
  .dept-labels span{ flex:1; text-align:center; font-size:10.5px; color:var(--text-secondary); font-weight:600; }

  /* ── Matriz de coste laboral por persona ─────────────────────────── */
  .matrix-scroll{ overflow-x:auto; }
  .matrix{ font-size:12.5px; min-width:880px; }
  .matrix-row{
    display:grid;
    grid-template-columns: minmax(180px,1.8fr) repeat(7, minmax(68px,1fr));
    gap:6px; align-items:center;
  }
  .matrix-row .col-total{ font-weight:650; color:var(--text-primary); }
  .matrix-row .dev-over{ color:var(--st-warning); font-weight:650; }
  .matrix-row .dev-under{ color:var(--good-text); font-weight:650; }
  .matrix-row .dev-flat{ color:var(--text-muted); }
  .matrix-head{
    color:var(--text-muted); font-size:11px; font-weight:600; padding:0 0 8px;
    border-bottom:1px solid var(--border);
  }
  .matrix-head span:not(:first-child), .matrix-num{ text-align:right; font-variant-numeric:tabular-nums; }
  details.dept{ border-bottom:1px solid var(--border); }
  details.dept:last-of-type{ border-bottom:none; }
  details.dept summary{
    list-style:none; cursor:pointer; padding:10px 0; font-weight:650;
  }
  details.dept summary::-webkit-details-marker{ display:none; }
  details.dept summary .matrix-row .name{ display:flex; align-items:center; gap:6px; }
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
      <h3>Matriz de coste laboral por persona</h3>
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
        <div class="legend">
          <span class="item"><span class="swatch" style="background:var(--s1);"></span>Altas</span>
          <span class="item"><span class="swatch" style="background:var(--s2);"></span>Bajas</span>
        </div>
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

  function showTip(x, y, html){
    tip.innerHTML = html;
    tip.style.left = x + 'px';
    tip.style.top  = y + 'px';
    tip.classList.add('show');
  }
  function hideTip(){ tip.classList.remove('show'); }

  // ── Datos reales, inyectados desde InformeRrhhController ─────────────
  const MESES = @json($mesesLabels);

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
  const nMeses = Math.max(MESES.length, 2);
  const xAt = i => PAD.left + i * (plotW / (nMeses - 1));

  function drawGrid(svg, maxVal, fmt){
    const steps = 4;
    for (let i = 0; i <= steps; i++){
      const v = maxVal * i / steps;
      const y = PAD.top + plotH - (v / maxVal) * plotH;
      svg.appendChild(el('line', { x1:PAD.left, x2:W-PAD.right, y1:y, y2:y, class:'grid-line' }));
      const t = el('text', { x:PAD.left - 6, y:y+3, class:'axis-label', 'text-anchor':'end' });
      t.textContent = fmt(v);
      svg.appendChild(t);
    }
    MESES.forEach((m, i) => {
      const t = el('text', { x:xAt(i), y:H-6, class:'axis-label', 'text-anchor':'middle' });
      t.textContent = m;
      svg.appendChild(t);
    });
  }

  function pathFromPoints(pts){
    return pts.map((p,i) => (i===0?'M':'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  }

  if (MESES.length < 2){
    ['chart-coste','chart-plantilla','chart-rotacion'].forEach(id => {
      document.getElementById(id).innerHTML = '<p style="font-size:12px;color:var(--text-muted);">No hay suficientes meses todavía este año para dibujar la evolución.</p>';
    });
  } else {

  // ── Chart 1: Coste laboral (línea real + línea presupuesto punteada) ──
  (function(){
    const container = document.getElementById('chart-coste');
    const maxVal = niceMax(Math.max(...costeReal, ...costePresu, 1) * 1.08);
    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => Math.round(v) + 'k');

    const yAt = v => PAD.top + plotH - (v / maxVal) * plotH;
    const ptsReal = costeReal.map((v,i) => [xAt(i), yAt(v)]);
    const ptsPresu = costePresu.map((v,i) => [xAt(i), yAt(v)]);

    const last = MESES.length - 1;
    const areaPath = pathFromPoints(ptsReal) + ` L${xAt(last).toFixed(1)},${(PAD.top+plotH).toFixed(1)} L${xAt(0).toFixed(1)},${(PAD.top+plotH).toFixed(1)} Z`;
    svg.appendChild(el('path', { d:areaPath, fill:cssVar('--s1'), opacity:'0.10', stroke:'none' }));
    svg.appendChild(el('path', { d:pathFromPoints(ptsPresu), fill:'none', stroke:cssVar('--s2'), 'stroke-width':2, 'stroke-dasharray':'5,4' }));
    svg.appendChild(el('path', { d:pathFromPoints(ptsReal), fill:'none', stroke:cssVar('--s1'), 'stroke-width':2.5, 'stroke-linecap':'round', 'stroke-linejoin':'round' }));

    ptsReal.forEach((p,i) => {
      const hit = el('circle', { cx:p[0], cy:p[1], r:9, fill:'transparent', style:'cursor:pointer;' });
      hit.addEventListener('mousemove', ev => {
        const rect = container.getBoundingClientRect();
        const over = costeReal[i] - costePresu[i];
        showTip(ev.clientX - rect.left, ev.clientY - rect.top,
          `<b>${MESES[i]} {{ $anio }}</b><br>
           <div class="row"><span class="dot" style="background:${cssVar('--s1')}"></span>Real: ${costeReal[i].toFixed(1)}k €</div>
           <div class="row"><span class="dot" style="background:${cssVar('--s2')}"></span>Presupuesto: ${costePresu[i].toFixed(1)}k €</div>
           <span style="color:${over>0?cssVar('--st-warning'):cssVar('--good-text')}">${over>0?'+':''}${over.toFixed(1)}k € vs. presupuesto</span>`);
      });
      hit.addEventListener('mouseleave', hideTip);
      svg.appendChild(hit);
      svg.appendChild(el('circle', { cx:p[0], cy:p[1], r:2.5, fill:cssVar('--s1') }));
    });
    container.appendChild(svg);
  })();

  // ── Chart 2: Plantilla por departamento (área apilada) ────────────────
  (function(){
    const container = document.getElementById('chart-plantilla');
    const totals = MESES.map((_,i) => deptos.reduce((s,d) => s + d.data[i], 0));
    const maxVal = niceMax(Math.max(...totals, 1) * 1.08);
    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => Math.round(v));
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
        const i = Math.max(0, Math.min(MESES.length-1, Math.round((bx - PAD.left) / (plotW/(nMeses-1)))));
        showTip(bx, ev.clientY - rect.top, `<b>${d.name} · ${MESES[i]}</b><br>${d.data[i]} personas`);
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

  // ── Chart 4: Altas y bajas por mes (barras agrupadas) ─────────────────
  (function(){
    const container = document.getElementById('chart-rotacion');
    const maxVal = niceMax(Math.max(...altas, ...bajas, 1) * 1.2);
    const yAt = v => PAD.top + plotH - (v / maxVal) * plotH;

    const svg = makeSVG(W, H);
    drawGrid(svg, maxVal, v => Math.round(v));

    const barW = Math.max(4, Math.min(9, plotW / nMeses / 3)), gap = 2;
    const baseline = PAD.top + plotH;
    MESES.forEach((m,i) => {
      const cx = xAt(i);
      const yA = yAt(altas[i]), yB = yAt(bajas[i]);
      const rA = el('rect', { x:(cx-barW-gap/2).toFixed(1), y:yA.toFixed(1), width:barW, height:Math.max(baseline-yA,1).toFixed(1), fill:cssVar('--s1'), rx:2, style:'cursor:pointer;' });
      const rB = el('rect', { x:(cx+gap/2).toFixed(1), y:yB.toFixed(1), width:barW, height:Math.max(baseline-yB,1).toFixed(1), fill:cssVar('--s2'), rx:2, style:'cursor:pointer;' });
      [[rA,'Altas',altas[i]],[rB,'Bajas',bajas[i]]].forEach(([r,label,val]) => {
        r.addEventListener('mousemove', ev => {
          const rect = container.getBoundingClientRect();
          showTip(ev.clientX - rect.left, ev.clientY - rect.top, `<b>${m} · ${label}</b><br>${val} personas`);
        });
        r.addEventListener('mouseleave', hideTip);
      });
      svg.appendChild(rA); svg.appendChild(rB);
    });

    container.appendChild(svg);
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
      bar.addEventListener('mousemove', ev => {
        const rect = container.getBoundingClientRect();
        showTip(ev.clientX - rect.left, ev.clientY - rect.top, `<b>${d.name}</b><br>${d.value}.000 € acumulados`);
      });
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
    const eur = v => Number(v).toLocaleString('es-ES', {maximumFractionDigits:0}) + ' €';
    const cols = ['anual','plusDpto','plusPuesto','plusPersonal','total'];

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
    head.innerHTML = `<span>Departamento / puesto</span><span>Salario (año en curso)</span><span>Plus dpto</span><span>Plus puesto</span><span>Plus personal</span><span>Total real</span><span>Presupuesto</span><span>Desviación</span>`;
    container.appendChild(head);

    plantillaMatriz.forEach((dept) => {
      const det = document.createElement('details');
      det.className = 'dept';

      const sum = document.createElement('summary');
      sum.innerHTML = `<div class="matrix-row">
        <span class="name"><span class="chev"></span>${dept.name}${dept.sampled ? ` <span style="font-weight:400;color:var(--text-muted);font-size:10.5px;">(${dept.sampleOf} personas)</span>` : ''}</span>
        <span class="matrix-num">${eur(dept.deptAnual)}</span>
        <span class="matrix-num">${eur(dept.deptPlusDpto)}</span>
        <span class="matrix-num">${eur(dept.deptPlusPuesto)}</span>
        <span class="matrix-num">${eur(dept.deptPlusPersonal)}</span>
        <span class="matrix-num col-total">${eur(dept.deptTotal)}</span>
        <span class="matrix-num">${eur(dept.deptPresupuesto)}</span>
        ${devCell(dept.deptTotal, dept.deptPresupuesto)}
      </div>`;
      det.appendChild(sum);

      const body = document.createElement('div');
      body.className = 'dept-body';
      dept.empleados.forEach(emp => {
        const row = document.createElement('div');
        row.className = 'matrix-row';
        row.innerHTML = `<span class="name">${emp.puesto}</span>` +
          cols.map(c => `<span class="matrix-num${c==='total'?' col-total':''}">${emp[c] ? eur(emp[c]) : '—'}</span>`).join('') +
          `<span class="matrix-num">${eur(emp.presupuesto)}</span>` +
          devCell(emp.total, emp.presupuesto);
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
      `<span class="matrix-num">${eur(sumBy('deptAnual'))}</span>` +
      `<span class="matrix-num">${eur(sumBy('deptPlusDpto'))}</span>` +
      `<span class="matrix-num">${eur(sumBy('deptPlusPuesto'))}</span>` +
      `<span class="matrix-num">${eur(sumBy('deptPlusPersonal'))}</span>` +
      `<span class="matrix-num col-total">${eur(grandTotal)}</span>` +
      `<span class="matrix-num">${eur(grandPresu)}</span>` +
      devCell(grandTotal, grandPresu);
    container.appendChild(totalRow);
  })();

})();
</script>
</x-app-layout>
