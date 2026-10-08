<x-app-layout :project="$project" :breadcrumb="[['label'=>'Informe balance','url'=>'']]">

{{-- Chart.js completo vía CDN (el bundle Vite solo registra Bar+Line) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<x-slot name="actions">
  <button class="rv-btn" onclick="abrirModal()">+ Registrar balance</button>
</x-slot>

<style>
  .bal-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
  .bal-kpi  { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
              border-radius:12px; padding:18px 22px; }
  .bal-kpi-label  { font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;
                    letter-spacing:.05em; margin-bottom:6px; }
  .bal-kpi-value  { font-size:22px; font-weight:800; line-height:1.1; }
  .bal-kpi-sub    { font-size:11px; color:#94a3b8; margin-top:5px; }
  .bal-kpi.green  { border-left:4px solid #10b981; }
  .bal-kpi.red    { border-left:4px solid #ef4444; }
  .bal-kpi.blue   { border-left:4px solid #3b82f6; }

  .bal-charts { display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:24px; }
  .bal-card   { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
                border-radius:12px; padding:20px; }
  .bal-card h3 { font-size:13px; font-weight:700; color:#374151; margin:0 0 16px; }
  .bal-chart-wrap { position:relative; height:260px; }

  .bal-tabla-wrap { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
                    border-radius:12px; overflow:hidden; }
  .bal-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
  .bal-tabla th { background:#f8fafc; padding:8px 12px; text-align:right; font-size:11px;
                  font-weight:700; color:#64748b; border-bottom:2px solid var(--border,#e5e7eb);
                  white-space:nowrap; }
  .bal-tabla th:first-child { text-align:left; min-width:180px; }
  .bal-tabla td { padding:6px 12px; text-align:right; border-bottom:1px solid #f3f4f6;
                  color:#374151; white-space:nowrap; }
  .bal-tabla td:first-child { text-align:left; color:#6b7280; padding-left:24px; }
  .bal-tabla tr.bal-tr-grupo td { background:#f8fafc; font-weight:700; font-size:11.5px;
                                   text-transform:uppercase; letter-spacing:.04em;
                                   padding-left:12px; border-top:2px solid var(--border,#e5e7eb); }
  .bal-tabla tr.bal-tr-grupo td:first-child { display:flex; align-items:center; gap:7px; }
  .bal-tabla tr.bal-tr-subtotal td { font-weight:700; background:#f1f5f9; font-size:12px; }
  .bal-tabla tr.bal-tr-subtotal td:first-child { padding-left:12px; }
  .bal-tabla tr.bal-tr-total td { font-weight:800; font-size:13px;
                                   border-top:3px solid var(--border,#e5e7eb); }
  .bal-tabla tr.bal-tr-total td:first-child { padding-left:12px; }
  .bal-tabla tr:last-child td { border-bottom:none; }
  .bal-dot { display:inline-block; width:8px; height:8px; border-radius:50%; flex-shrink:0; }

  .bal-period-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .bal-period-btn { padding:5px 14px; border-radius:20px; border:1.5px solid var(--border,#e5e7eb);
                    background:none; font-size:12px; cursor:pointer; color:#6b7280; transition:.15s; }
  .bal-period-btn.active { background:#3b82f6; color:#fff; border-color:#3b82f6; font-weight:700; }

  /* Modal */
  .bal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
                 z-index:500; align-items:center; justify-content:center; padding:16px; }
  .bal-overlay.open { display:flex; }
  .bal-modal  { background:var(--surface,#fff); border-radius:16px; width:100%;
                max-width:660px; max-height:90vh; display:flex; flex-direction:column;
                box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .bal-modal-head { padding:20px 24px 14px; border-bottom:1px solid var(--border,#e5e7eb);
                    display:flex; align-items:center; justify-content:space-between; }
  .bal-modal-head h2 { font-size:16px; font-weight:800; margin:0; }
  .bal-modal-body { overflow-y:auto; padding:0 24px 12px; flex:1; }
  .bal-modal-foot { padding:14px 24px; border-top:1px solid var(--border,#e5e7eb);
                    display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .bal-fecha-wrap { display:flex; align-items:center; gap:10px; }
  .bal-grupo-title { font-size:11px; font-weight:700; text-transform:uppercase;
                     letter-spacing:.05em; padding:14px 0 6px; }
  .bal-grid { display:grid; grid-template-columns:1fr 130px; }
  .bal-grid-head { font-size:11px; color:#9ca3af; font-weight:600; padding:0 0 6px; }
  .bal-grid-row { display:contents; }
  .bal-grid-row span { padding:6px 0; font-size:12.5px; color:#6b7280;
                       border-bottom:1px solid #f3f4f6; display:flex; align-items:center; }
  .bal-grid-row input {
    border:1px solid var(--border,#e5e7eb); border-radius:6px; padding:5px 10px;
    text-align:right; width:100%; background:var(--bg,#f9fafb); color:var(--text,#111);
    font-size:13px; font-weight:600; outline:none; -moz-appearance:textfield;
    margin:3px 0; border-bottom:1px solid #f3f4f6;
  }
  .bal-grid-row input:focus { border-color:#3b82f6; background:#fff; }
  .bal-grid-row input::-webkit-outer-spin-button,
  .bal-grid-row input::-webkit-inner-spin-button { -webkit-appearance:none; }
  .btn-bal-save   { background:#3b82f6; color:#fff; border:none; border-radius:8px;
                    padding:9px 22px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn-bal-cancel { background:none; border:1.5px solid var(--border,#e5e7eb); border-radius:8px;
                    padding:9px 16px; font-size:13px; cursor:pointer; color:#6b7280; }
  .bal-saving { opacity:.6; pointer-events:none; }
  .bal-eye { background:none; border:none; cursor:pointer; padding:0 5px 0 0;
             line-height:1; vertical-align:middle; display:inline-flex; align-items:center; }
  .bal-eye svg { width:15px; height:15px; display:block; transition:color .15s; }
  .bal-eye.included svg { color:#d1d5db; }
  .bal-eye.excluded svg { color:#374151; }
  .bal-eye:hover svg { color:#6b7280; }
  .bal-tr-excluded td:not(:first-child) { opacity:.25; }
</style>

@php
$grupoLabels = ['activo_corto'=>'Activo a corto','activo_medio'=>'Activo a medio',
                'activo_largo'=>'Activo a largo','pasivo'=>'Pasivo'];
$grupoColors = ['activo_corto'=>'#3b82f6','activo_medio'=>'#8b5cf6',
                'activo_largo'=>'#10b981','pasivo'=>'#ef4444'];
@endphp

{{-- Selector de periodo --}}
<div class="bal-period-row">
  <span style="font-size:11px;color:#9ca3af;font-weight:700;letter-spacing:.05em">PERIODO</span>
  <div id="bal-period-btns"></div>
</div>

{{-- KPI cards --}}
<div class="bal-kpis" style="margin-bottom:20px">
  <div class="bal-kpi green">
    <div class="bal-kpi-label">Activo total</div>
    <div class="bal-kpi-value" id="kpi-activo">—</div>
    <div class="bal-kpi-sub" id="kpi-activo-sub"></div>
  </div>
  <div class="bal-kpi red">
    <div class="bal-kpi-label">Pasivo total</div>
    <div class="bal-kpi-value" id="kpi-pasivo">—</div>
    <div class="bal-kpi-sub" id="kpi-pasivo-sub"></div>
  </div>
  <div class="bal-kpi blue">
    <div class="bal-kpi-label">Patrimonio neto</div>
    <div class="bal-kpi-value" id="kpi-patrimonio">—</div>
    <div class="bal-kpi-sub" id="kpi-patrimonio-sub"></div>
  </div>
</div>

{{-- Gráficos --}}
<div class="bal-charts">
  <div class="bal-card">
    <h3>Evolución del patrimonio</h3>
    <div class="bal-chart-wrap"><canvas id="chart-evolucion"></canvas></div>
  </div>
  <div class="bal-card">
    <h3>Distribución del activo</h3>
    <div class="bal-chart-wrap"><canvas id="chart-donut"></canvas></div>
  </div>
</div>

{{-- Desglose por grupo --}}
<div class="bal-tabla-wrap"><table class="bal-tabla" id="bal-desglose"></table></div>

{{-- Modal --}}
<div class="bal-overlay" id="bal-overlay" onclick="if(event.target===this)cerrarModal()">
  <div class="bal-modal">
    <div class="bal-modal-head">
      <h2>Registrar balance</h2>
      <button class="btn-bal-cancel" style="padding:4px 10px" onclick="cerrarModal()">✕</button>
    </div>
    <div class="bal-modal-body" id="bal-modal-body">
      <div class="bal-fecha-wrap" style="padding:14px 0 4px">
        <label style="font-size:12.5px;font-weight:700;color:#374151;white-space:nowrap">Fecha del balance:</label>
        <input type="month" id="bal-fecha" class="rv-select" style="max-width:160px" onchange="autoCargarFecha()">
      </div>
      <div id="bal-grid-wrap"></div>
    </div>
    <div class="bal-modal-foot">
      <span id="bal-save-msg" style="font-size:12px;color:#10b981;display:none">✓ Guardado</span>
      <div style="margin-left:auto;display:flex;gap:10px">
        <button class="btn-bal-cancel" onclick="cerrarModal()">Cancelar</button>
        <button class="btn-bal-save" id="btn-guardar" onclick="guardarBalance()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BAL_DATA  = @json($chartData);
const BAL_ITEMS = @json($items);
const STORE_URL = "{{ route('rodcar.balance.store', $project->slug) }}";
const LOAD_URL  = "{{ route('rodcar.balance.load',  $project->slug) }}";
const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';

const GRUPO_LABELS = {activo_corto:'Activo a corto',activo_medio:'Activo a medio',
                      activo_largo:'Activo a largo',pasivo:'Pasivo'};
const GRUPO_COLORS = {activo_corto:'#3b82f6',activo_medio:'#8b5cf6',
                      activo_largo:'#10b981',pasivo:'#ef4444'};

let selectedIdx = BAL_DATA.length - 1;
let chartEvol = null, chartDonut = null;
const hiddenItems = new Set();

function computedData() {
  return BAL_DATA.map(d => {
    const sum = (g) => BAL_ITEMS
      .filter(it => it.grupo === g && !hiddenItems.has(it.nombre))
      .reduce((s, it) => s + (d.detalles?.[it.nombre] ?? 0), 0);
    const ac = sum('activo_corto'), am = sum('activo_medio'),
          al = sum('activo_largo'), pa = sum('pasivo');
    return {...d, activo_corto:ac, activo_medio:am, activo_largo:al,
            pasivo:pa, activo_total:ac+am+al, patrimonio:ac+am+al-pa};
  });
}

function toggleItem(nombre) {
  hiddenItems.has(nombre) ? hiddenItems.delete(nombre) : hiddenItems.add(nombre);
  refreshAll();
}

function fmt(n) {
  return new Intl.NumberFormat('es-ES',{minimumFractionDigits:0,maximumFractionDigits:0}).format(n)+' €';
}
function fmtDiff(n) {
  return (n>=0?'+':'')+new Intl.NumberFormat('es-ES',{minimumFractionDigits:0,maximumFractionDigits:0}).format(n)+' €';
}

// ── Periodo ────────────────────────────────────────────────────────────────────
function buildPeriodBtns() {
  const wrap = document.getElementById('bal-period-btns');
  wrap.innerHTML = '';
  BAL_DATA.forEach((d,i) => {
    const b = document.createElement('button');
    b.className = 'bal-period-btn' + (i===selectedIdx?' active':'');
    b.textContent = d.label;
    b.onclick = () => { selectedIdx=i; refreshAll(); };
    wrap.appendChild(b);
  });
}

// ── KPIs ───────────────────────────────────────────────────────────────────────
function updateKpis() {
  const cd  = computedData();
  const cur = cd[selectedIdx];
  const prv = cd[selectedIdx-1];
  document.getElementById('kpi-activo').textContent     = fmt(cur.activo_total);
  document.getElementById('kpi-pasivo').textContent     = fmt(cur.pasivo);
  document.getElementById('kpi-patrimonio').textContent = fmt(cur.patrimonio);
  if (prv) {
    document.getElementById('kpi-activo-sub').textContent     = fmtDiff(cur.activo_total-prv.activo_total)+' vs '+prv.label;
    document.getElementById('kpi-pasivo-sub').textContent     = fmtDiff(cur.pasivo-prv.pasivo)+' vs '+prv.label;
    document.getElementById('kpi-patrimonio-sub').textContent = fmtDiff(cur.patrimonio-prv.patrimonio)+' vs '+prv.label;
  } else {
    ['kpi-activo-sub','kpi-pasivo-sub','kpi-patrimonio-sub'].forEach(id=>document.getElementById(id).textContent='');
  }
}

// ── Gráfico evolución (barras apiladas) ────────────────────────────────────────
function buildEvolucion() {
  const ctx = document.getElementById('chart-evolucion').getContext('2d');
  if (chartEvol) chartEvol.destroy();
  const cd = computedData();
  chartEvol = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: cd.map(d=>d.label),
      datasets: [
        {label:'Activo corto', data:cd.map(d=>d.activo_corto), backgroundColor:'#3b82f6', stack:'activo', borderRadius:3, borderSkipped:false},
        {label:'Activo medio', data:cd.map(d=>d.activo_medio), backgroundColor:'#8b5cf6', stack:'activo', borderRadius:3, borderSkipped:false},
        {label:'Activo largo', data:cd.map(d=>d.activo_largo), backgroundColor:'#10b981', stack:'activo', borderRadius:3, borderSkipped:false},
        {label:'Pasivo',       data:cd.map(d=>-d.pasivo),      backgroundColor:'#ef444499', stack:'pasivo', borderRadius:3, borderSkipped:false},
        {label:'Patrimonio',   data:cd.map(d=>d.patrimonio),   type:'line',
         borderColor:'#1e40af', backgroundColor:'transparent', pointBackgroundColor:'#1e40af',
         pointRadius:5, borderWidth:2.5, tension:.3, yAxisID:'y'},
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},
        tooltip:{callbacks:{label(c){return c.dataset.label+': '+fmt(Math.abs(c.parsed.y));}}}
      },
      scales:{
        x:{grid:{display:false}},
        y:{stacked:true,grid:{color:'#f1f5f9'},ticks:{callback(v){return fmt(Math.abs(v));},font:{size:10}}}
      }
    }
  });
}

// ── Gráfico donut ──────────────────────────────────────────────────────────────
function updateDonut() {
  const cur = computedData()[selectedIdx];
  const grupos = ['activo_corto','activo_medio','activo_largo'];
  const data   = grupos.map(g=>cur[g]);
  if (!chartDonut) {
    const ctx = document.getElementById('chart-donut').getContext('2d');
    chartDonut = new Chart(ctx, {
      type:'doughnut',
      data:{labels:grupos.map(g=>GRUPO_LABELS[g]), datasets:[{data, backgroundColor:grupos.map(g=>GRUPO_COLORS[g]), borderWidth:2, borderColor:'#fff', hoverOffset:6}]},
      options:{responsive:true,maintainAspectRatio:false,cutout:'62%',
        plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:11}}},
                 tooltip:{callbacks:{label(c){return c.label+': '+fmt(c.parsed);}}}}}
    });
  } else {
    chartDonut.data.datasets[0].data = data;
    chartDonut.update();
  }
}

// ── Desglose ───────────────────────────────────────────────────────────────────
function updateDesglose() {
  const tbl  = document.getElementById('bal-desglose');
  const cols  = BAL_DATA.slice(-5);
  const cd    = computedData().slice(-5);
  const fmtV  = v => (v !== undefined && v !== null) ? fmt(v) : '<span style="color:#d1d5db">—</span>';
  const EYE_SVG = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
  const eyeIcon = (nombre) => {
    const excluded = hiddenItems.has(nombre);
    const cls = excluded ? 'bal-eye excluded' : 'bal-eye included';
    const tip = excluded ? 'Incluir en gráficos' : 'Excluir de gráficos';
    return `<button class="${cls}" data-nombre="${nombre.replace(/"/g,'&quot;')}" title="${tip}">${EYE_SVG}</button>`;
  };

  let html = '<thead><tr><th>Partida</th>';
  cols.forEach(d => { html += `<th>${d.label}</th>`; });
  html += '</tr></thead><tbody>';

  ['activo_corto','activo_medio','activo_largo','pasivo'].forEach(g => {
    const color = GRUPO_COLORS[g];
    const label = GRUPO_LABELS[g];
    const items = BAL_ITEMS.filter(it => it.grupo === g);

    html += `<tr class="bal-tr-grupo"><td style="color:${color}"><span class="bal-dot" style="background:${color}"></span>${label}</td>`;
    cols.forEach(() => { html += '<td></td>'; });
    html += '</tr>';

    items.forEach(it => {
      const isExcluded = hiddenItems.has(it.nombre);
      html += `<tr class="${isExcluded?'bal-tr-excluded':''}"><td>${eyeIcon(it.nombre)}${it.nombre}</td>`;
      cols.forEach(d => { html += `<td>${fmtV(d.detalles?.[it.nombre])}</td>`; });
      html += '</tr>';
    });

    html += `<tr class="bal-tr-subtotal"><td style="color:${color}">Total ${label}</td>`;
    cd.forEach(d => { html += `<td style="color:${color}">${fmt(d[g] ?? 0)}</td>`; });
    html += '</tr>';
  });

  html += `<tr class="bal-tr-total"><td>Activo total</td>`;
  cd.forEach(d => { html += `<td style="color:#10b981">${fmt(d.activo_total)}</td>`; });
  html += '</tr>';

  html += `<tr class="bal-tr-total"><td>Patrimonio neto</td>`;
  cd.forEach(d => {
    const color = d.patrimonio >= 0 ? '#3b82f6' : '#ef4444';
    html += `<td style="color:${color}">${fmt(d.patrimonio)}</td>`;
  });
  html += '</tr>';

  html += '</tbody>';
  tbl.innerHTML = html;
}

function refreshAll() {
  document.querySelectorAll('.bal-period-btn').forEach((b,i)=>b.classList.toggle('active',i===selectedIdx));
  updateKpis();
  buildEvolucion();
  updateDonut();
  updateDesglose();
}

// ── Modal ──────────────────────────────────────────────────────────────────────
function abrirModal() {
  // Fecha por defecto: último periodo conocido
  const last = BAL_DATA[BAL_DATA.length-1];
  let fechaVal = '';
  if (last) {
    const m = last.fecha.match(/^(\d+)-(\d+)-(\d+)/);
    if (m) fechaVal = `${m[3]}-${m[2].padStart(2,'0')}`;
  }
  if (!fechaVal) {
    const now = new Date();
    fechaVal = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  }
  document.getElementById('bal-fecha').value = fechaVal;
  buildModalGrid({});
  autoCargarFecha();
  document.getElementById('bal-overlay').classList.add('open');
}

function cerrarModal() {
  document.getElementById('bal-overlay').classList.remove('open');
  document.getElementById('bal-save-msg').style.display = 'none';
}

function buildModalGrid(prefill) {
  const wrap = document.getElementById('bal-grid-wrap');
  wrap.innerHTML = '';
  ['activo_corto','activo_medio','activo_largo','pasivo'].forEach(g => {
    const items = BAL_ITEMS.filter(it => it.grupo === g);
    if (!items.length) return;
    const title = document.createElement('div');
    title.className = 'bal-grupo-title';
    title.style.color = GRUPO_COLORS[g];
    title.innerHTML = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${GRUPO_COLORS[g]};margin-right:6px;vertical-align:middle"></span>${GRUPO_LABELS[g]}`;
    wrap.appendChild(title);
    const grid = document.createElement('div');
    grid.className = 'bal-grid';
    const h1 = document.createElement('div'); h1.className='bal-grid-head'; h1.textContent='Partida';
    const h2 = document.createElement('div'); h2.className='bal-grid-head'; h2.style.textAlign='right'; h2.textContent='Importe (€)';
    grid.appendChild(h1); grid.appendChild(h2);
    items.forEach(it => {
      const row  = document.createElement('div'); row.className='bal-grid-row';
      const span = document.createElement('span'); span.textContent = it.nombre;
      const inp  = document.createElement('input');
      inp.type='number'; inp.step='0.01'; inp.min='0';
      inp.dataset.nombre = it.nombre;
      inp.placeholder = '0,00';
      const v = prefill[it.nombre];
      if (v !== undefined && v !== null) inp.value = parseFloat(v);
      row.appendChild(span); row.appendChild(inp);
      grid.appendChild(row);
    });
    wrap.appendChild(grid);
  });
}

async function autoCargarFecha() {
  const fecha_input = document.getElementById('bal-fecha').value;
  if (!fecha_input) return;
  const [y,m] = fecha_input.split('-');
  const fechaStr = `01-${m.padStart(2,'0')}-${y} 12:00`;
  try {
    const res  = await fetch(`${LOAD_URL}?fecha=${encodeURIComponent(fechaStr)}`);
    const data = await res.json();
    buildModalGrid(data);
  } catch(e) { buildModalGrid({}); }
}

async function guardarBalance() {
  const fecha = document.getElementById('bal-fecha').value;
  if (!fecha) { alert('Selecciona una fecha'); return; }
  const valores = {};
  document.querySelectorAll('#bal-grid-wrap input[data-nombre]').forEach(inp => {
    if (inp.value !== '') valores[inp.dataset.nombre] = inp.value;
  });
  const btn = document.getElementById('btn-guardar');
  btn.classList.add('bal-saving');
  try {
    const res  = await fetch(STORE_URL, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({fecha, valores}),
    });
    const json = await res.json();
    if (json.ok) {
      document.getElementById('bal-save-msg').style.display = 'inline';
      setTimeout(() => { cerrarModal(); location.reload(); }, 800);
    } else alert('Error al guardar');
  } catch(e) { alert('Error de red'); }
  finally { btn.classList.remove('bal-saving'); }
}

// ── Init ───────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Listener único para botones ojo — evita acumulación al redibujar la tabla
  document.getElementById('bal-desglose').addEventListener('click', function(e) {
    const btn = e.target.closest('.bal-eye');
    if (!btn) return;
    toggleItem(btn.dataset.nombre);
  });

  if (!BAL_DATA.length) {
    document.querySelector('.bal-kpis').insertAdjacentHTML('beforebegin',
      '<p style="color:#9ca3af;font-size:13px;text-align:center;padding:40px 0">Sin datos. Pulsa "+ Registrar balance" para empezar.</p>');
    return;
  }
  buildPeriodBtns();
  buildEvolucion();
  refreshAll();
});
</script>

</x-app-layout>
