<x-app-layout :project="$project" :breadcrumb="[['label'=>'Desglose contable','url'=>'']]">

@php
    $ejercicioPrev = ((int) substr($ejercicioSel, 0, 4) - 1) . '-' . ((int) substr($ejercicioSel, 5, 4) - 1);
    $ejercicioNext = ((int) substr($ejercicioSel, 0, 4) + 1) . '-' . ((int) substr($ejercicioSel, 5, 4) + 1);
    $matricesJson = collect($matrices)->toJson(JSON_UNESCAPED_UNICODE);
    $chartJson = collect($chart)->toJson(JSON_UNESCAPED_UNICODE);
    $ejerciciosJson = collect($ejercicios)->toJson(JSON_UNESCAPED_UNICODE);
    $routeFichaGasto = route('ficha', [$project->slug, 'gastos', '__ID__']);
    $routeFichaCuota = route('ficha', [$project->slug, 'cuotas', '__ID__']);
    $routeGastosListado = route('listado', [$project->slug, 'gastos']);
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px;">
  <h2 style="font-size:19px;margin:0;font-weight:700">Desglose contable</h2>
  <div style="display:flex;align-items:center;gap:8px;">
    <span style="font-size:12.5px;color:#7e93a1;margin-right:2px;">Ejercicio</span>
    <a class="dc-navbtn" href="{{ route('mb.pyg', [$project->slug, 'ejercicio' => $ejercicioPrev, 'modo' => $modo]) }}">‹</a>
    <div class="dc-yearlabel">{{ $ejercicioSel }}</div>
    <a class="dc-navbtn" href="{{ route('mb.pyg', [$project->slug, 'ejercicio' => $ejercicioNext, 'modo' => $modo]) }}">›</a>
  </div>
</div>
<p style="color:#7e93a1;font-size:12.5px;margin:0 0 16px;">Ingresos, gastos y cuotas de la comunidad — ejercicio fiscal 1 de julio a 30 de junio.</p>

<style>
.dc-navbtn{width:26px;height:26px;border-radius:7px;border:1px solid #dce6ee;background:#fff;color:#52697a;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.dc-navbtn:hover{background:#f7fafc}
.dc-yearlabel{font-size:13px;font-weight:700;color:#16232b;min-width:70px;text-align:center}

.dc-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
@media (max-width:900px){ .dc-stats{grid-template-columns:repeat(2,1fr)} }
.dc-pill{border-radius:12px;padding:13px 15px;border:1px solid #eaf1f6;background:#f7fafc}
.dc-pill__num{font-size:1.4rem;font-weight:700}
.dc-pill__label{font-size:11.5px;font-weight:500;margin-top:2px;color:#52697a}

.dc-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden;margin-bottom:18px}
.dc-card__head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.dc-card__title{font-size:13.5px;font-weight:700;color:#16232b}
.dc-legend{display:flex;gap:12px}
.dc-legend__item{display:flex;align-items:center;gap:5px;font-size:11.5px;color:#52697a}
.dc-legend__sw{width:9px;height:9px;border-radius:2px;display:inline-block}

.dc-seg{display:inline-flex;border:1px solid #dce6ee;border-radius:8px;overflow:hidden}
.dc-seg__btn{font-size:12.5px;font-weight:500;padding:6px 14px;border:none;background:#fff;color:#52697a;cursor:pointer;font-family:inherit}
.dc-seg__btn.active{background:#f97316;color:#fff}

.dc-pivot-scroll{overflow-x:auto}
table.dc-pivot{min-width:640px;width:100%;border-collapse:collapse;font-size:12.5px}
table.dc-pivot th,table.dc-pivot td{white-space:nowrap;padding:9px 14px}
table.dc-pivot thead th{text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#7e93a1;background:#f7fafc;border-bottom:1px solid #dce6ee}
table.dc-pivot thead th:first-child{text-align:left}
table.dc-pivot .rowlabel{min-width:260px;text-align:left}
table.dc-pivot .val{text-align:right;font-variant-numeric:tabular-nums;cursor:pointer;border-radius:4px}
table.dc-pivot .val:hover{background:#fff7ed;color:#c2410c}
tr.dc-row-top td{font-weight:700;color:#16232b;background:#f7fafc}
tr.dc-row-grupo td{font-weight:600;color:#374151}
tr.dc-row-cuenta td{color:#6b7280;font-size:12px}
tr.dc-row-neto td{font-weight:700;border-top:2px solid #dce6ee}
.dc-toggle{display:inline-flex;align-items:center;gap:6px;cursor:pointer;user-select:none;background:none;border:none;font:inherit;padding:0;color:inherit}
.dc-chevron{width:11px;height:11px;flex-shrink:0;transition:transform .12s;color:#9ca3af}
.dc-chevron.open{transform:rotate(90deg)}
.dc-indent{padding-left:28px !important}

.dc-modal-backdrop{position:fixed;inset:0;background:rgba(28,36,48,.35);display:flex;align-items:center;justify-content:center;z-index:50}
.dc-modal-backdrop.hidden{display:none}
.dc-modal-card{background:#fff;border-radius:12px;box-shadow:0 12px 32px -10px rgba(28,36,48,.22);width:880px;max-width:95vw;max-height:70vh;display:flex;flex-direction:column}
.dc-modal-body tbody tr.aig-mismatch{background:#fef2f2}
.dc-modal-body tbody tr.aig-mismatch:hover{background:#fee2e2}
.dc-modal-head{padding:16px 18px;border-bottom:1px solid #eaf1f6;display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.dc-modal-head h3{font-size:14.5px;margin:0;color:#16232b}
.dc-modal-head p{font-size:11.5px;color:#7e93a1;margin:2px 0 0}
.dc-modal-close{background:none;border:none;color:#9ca3af;cursor:pointer;font-size:17px;line-height:1}
.dc-modal-body{padding:8px 18px;overflow-y:auto}
.dc-modal-body table{width:100%;font-size:12px;border-collapse:collapse}
.dc-modal-body th{text-align:left;font-size:10.5px;color:#9ca3af;padding:6px 4px;border-bottom:1px solid #eaf1f6;white-space:nowrap}
.dc-modal-body td{padding:6px 4px;border-bottom:1px solid #f3f4f6;white-space:nowrap}
.dc-modal-body td.num{text-align:right;font-variant-numeric:tabular-nums}
.dc-modal-body tbody tr{cursor:pointer}
.dc-modal-body tbody tr:hover{background:#fff7ed}
.dc-modal-foot{padding:12px 18px;border-top:1px solid #eaf1f6;display:flex;align-items:center;justify-content:space-between;font-size:12.5px;color:#52697a;gap:10px}
.dc-modal-verlink{font-size:12px;color:#c2410c;font-weight:600;text-decoration:none;white-space:nowrap}
.dc-modal-verlink:hover{text-decoration:underline}
</style>

<div class="dc-stats" id="dc-stats"></div>

<div class="dc-card">
  <div class="dc-card__head">
    <div class="dc-card__title">Ingresos y gastos mensuales — {{ $ejercicioSel }}</div>
    <div class="dc-legend">
      <span class="dc-legend__item"><span class="dc-legend__sw" style="background:#3B82F6"></span>Ingresos</span>
      <span class="dc-legend__item"><span class="dc-legend__sw" style="background:#F43F5E"></span>Gastos</span>
      <span class="dc-legend__item"><span class="dc-legend__sw" style="background:#6366F1"></span>Saldo acumulado</span>
    </div>
  </div>
  <div style="padding:14px 16px 4px;">
    <svg viewBox="0 0 780 230" style="display:block;width:100%;height:auto" id="dc-chart"></svg>
  </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
  <div class="dc-card__title" id="dc-pivot-title">Matriz contable</div>
  <div class="dc-seg" id="dc-modo-toggle">
    <button class="dc-seg__btn {{ $modo === 'ordinario' ? 'active' : '' }}" data-modo="ordinario">Ordinario</button>
    <button class="dc-seg__btn {{ $modo === 'extraordinario' ? 'active' : '' }}" data-modo="extraordinario">Extraordinario</button>
  </div>
</div>
<p style="font-size:12px;color:#9ca3af;margin:0 0 10px;">Haz clic en ▸ para desplegar una agrupación. Haz clic en un importe para ver sus movimientos.</p>

<div class="dc-card">
  <div class="dc-pivot-scroll">
    <table class="dc-pivot">
      <thead><tr id="dc-head-row"></tr></thead>
      <tbody id="dc-body"></tbody>
    </table>
  </div>
</div>

<div class="dc-modal-backdrop hidden" id="dc-modal-backdrop">
  <div class="dc-modal-card">
    <div class="dc-modal-head">
      <div><h3 id="dc-modal-title"></h3><p id="dc-modal-sub"></p></div>
      <button class="dc-modal-close" id="dc-modal-close">✕</button>
    </div>
    <div class="dc-modal-body" id="dc-modal-body">Cargando…</div>
    <div class="dc-modal-foot">
      <span id="dc-modal-count"></span>
      <a class="dc-modal-verlink hidden" id="dc-modal-ver-movimientos" href="#" target="_blank">Ver movimientos ›</a>
      <strong id="dc-modal-total"></strong>
    </div>
  </div>
</div>

<script>
(function(){
  const MATRICES = {!! $matricesJson !!};
  const CHART = {!! $chartJson !!};
  const EJERCICIOS = {!! $ejerciciosJson !!};
  const STATS_INICIAL = @json($stats);
  const EJERCICIO_SEL = @json($ejercicioSel);
  const ROUTE_MOVIMIENTOS = @json(route('mb.pyg.movimientos', $project->slug));
  const ROUTE_FICHA_GASTO = @json($routeFichaGasto);
  const ROUTE_FICHA_CUOTA = @json($routeFichaCuota);
  const ROUTE_GASTOS_LISTADO = @json($routeGastosListado);
  const CSRF = @json(csrf_token());

  let modo = @json($modo);
  const expandido = {};

  const euro = n => Number(n).toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
  const euroCompact = n => {
    const abs = Math.abs(n);
    if (abs >= 1000) return (n/1000).toLocaleString('es-ES',{maximumFractionDigits:1}) + ' mil €';
    return Math.round(n).toLocaleString('es-ES') + ' €';
  };

  function renderStats(){
    document.getElementById('dc-stats').innerHTML = `
      <div class="dc-pill"><div class="dc-pill__num" style="color:#1d4ed8">${euro(STATS_INICIAL.ingresos)}</div><div class="dc-pill__label">Ingresos ${EJERCICIO_SEL}</div></div>
      <div class="dc-pill"><div class="dc-pill__num" style="color:#be123c">${euro(STATS_INICIAL.gastos)}</div><div class="dc-pill__label">Gastos ${EJERCICIO_SEL}</div></div>
      <div class="dc-pill"><div class="dc-pill__num">${euro(STATS_INICIAL.neto)}</div><div class="dc-pill__label">Neto — margen ${STATS_INICIAL.margen.toFixed(0)}%</div></div>
      <div class="dc-pill"><div class="dc-pill__num" style="color:${STATS_INICIAL.pctImpago > 10 ? '#b91c1c' : '#a16207'}">${STATS_INICIAL.pctImpago.toFixed(1)}%</div><div class="dc-pill__label">Impago cuotas año en curso</div></div>
    `;
  }

  function el(tag, attrs){
    const e = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const k in attrs) e.setAttribute(k, attrs[k]);
    return e;
  }

  function renderChart(){
    const svg = document.getElementById('dc-chart');
    svg.innerHTML = '';
    const meses = CHART.meses, ingresos = CHART.ingresos, gastos = CHART.gastos;
    const MONTHS_SHORT = meses.map(m => ({'01':'Ene','02':'Feb','03':'Mar','04':'Abr','05':'May','06':'Jun','07':'Jul','08':'Ago','09':'Sep','10':'Oct','11':'Nov','12':'Dic'}[m.slice(5,7)]));
    const W=780,H=230,padL=46,padR=10,padT=10,padB=22;
    const plotW=W-padL-padR, plotH=H-padT-padB;
    const max = Math.max(...ingresos, ...gastos, 1) * 1.15;

    let running=0; const cum = ingresos.map((v,i)=>{ running += v - gastos[i]; return running; });
    const minC = Math.min(0,...cum), maxC = Math.max(0,...cum), spanC = (maxC-minC)||1;
    const yForCum = v => padT+plotH-((v-minC)/spanC)*plotH;

    [0,0.5,1].forEach(f=>{
      const y = padT+plotH*(1-f);
      svg.appendChild(el('line',{x1:padL,x2:W-padR,y1:y,y2:y,stroke:'#e5e7eb','stroke-width':1}));
      const t = el('text',{x:padL-6,y:y+3,'font-size':9,fill:'#9ca3af','text-anchor':'end'}); t.textContent=euroCompact(max*f); svg.appendChild(t);
    });

    const n = meses.length, groupW = plotW/n, barW = Math.min(15, groupW*0.32), gap=3;
    for (let i=0;i<n;i++){
      const gx = padL+i*groupW, inV=ingresos[i], outV=gastos[i], baseY=padT+plotH;
      const inH=(inV/max)*plotH, outH=(outV/max)*plotH;
      svg.appendChild(el('rect',{x:gx+groupW/2-gap/2-barW, y:baseY-inH, width:barW, height:inH, rx:2, fill:'#3B82F6'}));
      svg.appendChild(el('rect',{x:gx+groupW/2+gap/2, y:baseY-outH, width:barW, height:outH, rx:2, fill:'#F43F5E'}));
      const lab = el('text',{x:gx+groupW/2, y:H-6, 'font-size':10,'font-weight':600,fill:'#6b7280','text-anchor':'middle'}); lab.textContent=MONTHS_SHORT[i]; svg.appendChild(lab);
    }

    const pts = cum.map((v,i)=>[padL+i*groupW+groupW/2, yForCum(v)]);
    const path = pts.map((p,i)=>(i===0?'M':'L')+p[0].toFixed(1)+','+p[1].toFixed(1)).join(' ');
    svg.appendChild(el('path',{d:path, fill:'none', stroke:'#6366F1','stroke-width':2,'stroke-linejoin':'round','stroke-linecap':'round'}));
    pts.forEach(p => svg.appendChild(el('circle',{cx:p[0],cy:p[1],r:2.5,fill:'#6366F1'})));
  }

  function fmtKey(k){ return k ? JSON.stringify(k) : ''; }

  function renderPivotHead(){
    document.getElementById('dc-head-row').innerHTML = '<th class="rowlabel">Cuenta</th>' +
      EJERCICIOS.map(e => `<th>${e}</th>`).join('');
  }

  function buildRows(){
    const dataPorEjercicio = EJERCICIOS.map(e => MATRICES[modo][e]);
    document.getElementById('dc-pivot-title').textContent = modo === 'ordinario' ? 'Ordinario — Ingreso/Gasto × Agrupación × Cuenta' : 'Extraordinario — Ingreso/Gasto × Proyecto';

    function valorEnEjercicio(dataEj, seccion, groupLabel, accLabel){
      const groups = dataEj[seccion].groups;
      const g = groups.find(x => x.label === groupLabel);
      if (!g) return null;
      if (accLabel === null) return { total: g.total, clickKey: g.clickKey };
      const a = g.accounts.find(x => x.label === accLabel);
      return a ? { total: a.total, clickKey: a.clickKey } : null;
    }

    function fila(seccion, groupLabel, accLabel, label, cls){
      const cells = dataPorEjercicio.map(dataEj => {
        const v = valorEnEjercicio(dataEj, seccion, groupLabel, accLabel);
        if (!v) return '<td class="val">—</td>';
        const key = v.clickKey ? `data-key='${JSON.stringify(v.clickKey).replace(/'/g,"&apos;")}'` : '';
        return `<td class="val" ${key}>${euro(v.total)}</td>`;
      }).join('');
      return `<tr class="${cls}"><td class="${cls==='dc-row-grupo'?'':'dc-indent'}">${label}</td>${cells}</tr>`;
    }

    let html = '';
    ['ingresos','gastos'].forEach(seccion => {
      const principal = dataPorEjercicio[0][seccion];
      html += `<tr class="dc-row-top"><td colspan="${1+EJERCICIOS.length}">${seccion === 'ingresos' ? 'Ingresos' : 'Gastos'} — ${euro(principal.total)}</td></tr>`;

      principal.groups.forEach(g => {
        const gid = seccion + '::' + g.label;
        const open = !!expandido[gid];
        const hasAccounts = g.accounts && g.accounts.length > 0;
        const chevron = hasAccounts ? `<button class="dc-toggle" data-toggle="${gid}"><svg class="dc-chevron ${open?'open':''}" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l6 6-6 6"/></svg> ${g.label}</button>` : g.label;
        html += fila(seccion, g.label, null, chevron, 'dc-row-grupo');

        if (hasAccounts && open){
          g.accounts.forEach(a => {
            html += fila(seccion, g.label, a.label, a.label, 'dc-row-cuenta');
          });
        }
      });
    });

    const netos = dataPorEjercicio.map(d => d.ingresos.total - d.gastos.total);
    html += `<tr class="dc-row-neto"><td>Neto</td>${netos.map(n => `<td class="val" style="color:${n>=0?'#1d4ed8':'#be123c'}">${euro(n)}</td>`).join('')}</tr>`;

    document.getElementById('dc-body').innerHTML = html;

    document.querySelectorAll('#dc-body [data-toggle]').forEach(btn => {
      btn.addEventListener('click', () => { expandido[btn.dataset.toggle] = !expandido[btn.dataset.toggle]; buildRows(); });
    });
    document.querySelectorAll('#dc-body .val[data-key]').forEach(td => {
      td.addEventListener('click', () => abrirModal(JSON.parse(td.dataset.key.replace(/&apos;/g,"'"))));
    });
  }

  async function abrirModal(key){
    document.getElementById('dc-modal-backdrop').classList.remove('hidden');
    document.getElementById('dc-modal-body').innerHTML = 'Cargando…';
    document.getElementById('dc-modal-total').textContent = '';
    document.getElementById('dc-modal-count').textContent = '';

    const params = new URLSearchParams({ ...key, ejercicio: EJERCICIO_SEL, modo });
    const res = await fetch(ROUTE_MOVIMIENTOS + '?' + params.toString(), { headers: { 'Accept':'application/json' } });
    if (!res.ok){ document.getElementById('dc-modal-body').innerHTML = 'Error al cargar movimientos.'; return; }
    const data = await res.json();
    const rows = data.rows || [];

    document.getElementById('dc-modal-title').textContent = key.tipo === 'proyecto' ? 'Movimientos del proyecto' : (key.tipo === 'cuotas' ? 'Cuotas' : 'Movimientos de la cuenta');
    document.getElementById('dc-modal-sub').textContent = EJERCICIO_SEL;

    const verBtn = document.getElementById('dc-modal-ver-movimientos');
    if (key.tipo === 'cuotas'){
      document.getElementById('dc-modal-body').innerHTML = `<table><thead><tr><th>Vivienda</th><th>Tipo</th><th>Ejercicio</th><th>Fecha pago</th><th class="num">Cobrado</th></tr></thead><tbody>${
        rows.map(r=>`<tr><td>${r.vivienda||'—'}</td><td>${r.tipo_cuota}</td><td>${r.ejercicio}</td><td>${r.fecha_pago||'—'}</td><td class="num">${euro(r.importe_cobrado)}</td></tr>`).join('')
      }</tbody></table>`;
      const total = rows.reduce((a,r)=>a+Number(r.importe_cobrado),0);
      document.getElementById('dc-modal-total').textContent = euro(total);
      document.querySelectorAll('#dc-modal-body tbody tr').forEach((tr, i) => {
        tr.addEventListener('click', () => window.open(ROUTE_FICHA_CUOTA.replace('__ID__', rows[i].id), '_blank'));
      });
      verBtn.classList.add('hidden');
    } else {
      document.getElementById('dc-modal-body').innerHTML = `<table><thead><tr><th>Fecha emisión</th><th>Fecha devengo</th><th>Concepto</th><th>Cuenta AIG</th><th class="num">Importe</th></tr></thead><tbody>${
        rows.map(r=>`<tr class="${r.aig_mismatch ? 'aig-mismatch' : ''}"><td>${r.fecha_emision||'—'}</td><td>${r.fecha_devengo||'—'}</td><td>${r.nombre}</td><td>${r.aig_codigo||'—'}</td><td class="num">${euro(r.importe_neto)}</td></tr>`).join('')
      }</tbody></table>`;
      const total = rows.reduce((a,r)=>a+Number(r.importe_neto),0);
      document.getElementById('dc-modal-total').textContent = euro(total);
      document.querySelectorAll('#dc-modal-body tbody tr').forEach((tr, i) => {
        tr.addEventListener('click', () => window.open(ROUTE_FICHA_GASTO.replace('__ID__', rows[i].id), '_blank'));
      });
      const filtroParam = key.tipo === 'proyecto' ? 'f_id_proyectos' : 'f_id_gastos_cuentas';
      verBtn.href = ROUTE_GASTOS_LISTADO + '?' + filtroParam + '=' + key.id;
      verBtn.classList.remove('hidden');
    }
    document.getElementById('dc-modal-count').textContent = rows.length + ' movimiento' + (rows.length===1?'':'s');
  }
  function cerrarModal(){ document.getElementById('dc-modal-backdrop').classList.add('hidden'); }
  document.getElementById('dc-modal-close').addEventListener('click', cerrarModal);
  document.getElementById('dc-modal-backdrop').addEventListener('click', e => { if (e.target.id === 'dc-modal-backdrop') cerrarModal(); });

  document.querySelectorAll('#dc-modo-toggle .dc-seg__btn').forEach(b => {
    b.addEventListener('click', () => {
      if (modo === b.dataset.modo) return;
      modo = b.dataset.modo;
      document.querySelectorAll('#dc-modo-toggle .dc-seg__btn').forEach(x => x.classList.toggle('active', x===b));
      buildRows();
    });
  });

  renderStats();
  renderChart();
  renderPivotHead();
  buildRows();
})();
</script>

</x-app-layout>
