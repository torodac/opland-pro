<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
.vmcs-widget{
  --vmcs-bg:#F4F6F8; --vmcs-panel:#FFFFFF; --vmcs-ink:#151A21; --vmcs-ink-soft:#7B8794; --vmcs-line:#E7EBEF;
  --vmcs-arrival:#2E7D63; --vmcs-departure:#C2612B;
  --vmcs-heat-0:#EEF2F6; --vmcs-heat-1:#C9DCEE; --vmcs-heat-2:#8FB6DA; --vmcs-heat-3:#4E85B8; --vmcs-heat-4:#215A8C;
  --vmcs-assigned:#1F4E85; --vmcs-pending:#F0A93B;
  font-family:'Manrope',sans-serif; color:var(--vmcs-ink);
  background:var(--vmcs-panel); border-radius:20px;
  box-shadow:0 1px 2px rgba(21,26,33,.04), 0 12px 32px rgba(21,26,33,.06);
  overflow:hidden;
}
.vmcs-widget *{ box-sizing:border-box; }

.vmcs-header{ display:flex; justify-content:space-between; align-items:flex-end; padding:22px 26px 18px; flex-wrap:wrap; gap:10px; }
.vmcs-header .vmcs-eyebrow{ font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--vmcs-ink-soft); margin:0 0 8px; }
.vmcs-header h2{ font-size:20px; font-weight:800; margin:0; letter-spacing:-.01em; }
.vmcs-week-nav{ display:flex; align-items:center; gap:10px; }
.vmcs-week-nav button{ width:34px;height:34px; border-radius:10px; border:none; background:var(--vmcs-bg); cursor:pointer; display:flex;align-items:center;justify-content:center; }
.vmcs-week-nav button:hover{ background:var(--vmcs-line); }
.vmcs-week-nav button svg{ width:14px; height:14px; }
.vmcs-week-label{ font-size:13px; font-weight:600; min-width:150px; text-align:center; }
.vmcs-today-btn{ font-size:12px; font-weight:700; background:var(--vmcs-bg); border:none; border-radius:10px; padding:8px 13px; cursor:pointer; color:var(--vmcs-ink-soft); }
.vmcs-today-btn:hover{ background:var(--vmcs-line); color:var(--vmcs-ink); }

.vmcs-kpi-row{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; padding:0 26px 22px; }
.vmcs-kpi{ background:var(--vmcs-bg); border-radius:14px; padding:13px 15px; }
.vmcs-kpi .l{ font-size:10px; font-weight:700; color:var(--vmcs-ink-soft); text-transform:uppercase; letter-spacing:.05em; }
.vmcs-kpi .v{ font-size:19px; font-weight:800; margin-top:4px; }
.vmcs-kpi.arrival .v{ color:var(--vmcs-arrival); }
.vmcs-kpi.departure .v{ color:var(--vmcs-departure); }
.vmcs-kpi.pending .v{ color:var(--vmcs-pending); }

.vmcs-section{ padding:6px 26px 24px; border-top:1px solid var(--vmcs-line); }
.vmcs-col-title{ font-size:13.5px; font-weight:800; margin:18px 0 2px; }
.vmcs-col-sub{ font-size:11px; color:var(--vmcs-ink-soft); margin:0 0 14px; }

.vmcs-card-row{ display:grid; grid-template-columns:repeat(7,1fr); gap:9px; }
.vmcs-card{ border-radius:14px; border:1px solid var(--vmcs-line); background:var(--vmcs-panel); overflow:hidden; display:flex; flex-direction:column; }
.vmcs-card.today{ outline:2px solid var(--vmcs-ink); outline-offset:2px; }

.vmcs-card-top{ padding:12px 10px 9px; }
.vmcs-card-top .dow{ font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--vmcs-ink); opacity:.75; }
.vmcs-card-top .dnum{ font-size:9px; opacity:.55; margin-bottom:11px; color:var(--vmcs-ink-soft); }

.vmcs-flow-pair{ display:flex; align-items:center; justify-content:space-between; }
.vmcs-flow-item{
  display:flex; align-items:center; gap:5px; font-size:15px; font-weight:800;
  background:none; border:none; padding:2px 4px; margin:-2px -4px; border-radius:6px;
  cursor:pointer; font-family:inherit;
}
.vmcs-flow-item:hover{ background:rgba(21,26,33,.06); }
.vmcs-flow-item svg{ width:13px; height:13px; }
.vmcs-flow-item.in{ color:var(--vmcs-arrival); }
.vmcs-flow-item.out{ color:var(--vmcs-departure); }

.vmcs-card-load{ padding:11px 10px 13px; margin-top:auto; }
.vmcs-card-load .load-figure{ font-size:12px; font-weight:800; margin-bottom:7px; }
.vmcs-load-track{ position:relative; height:8px; border-radius:4px; overflow:visible; background:rgba(255,255,255,.35); }
.vmcs-card-load.is-light .vmcs-load-track{ background:rgba(21,26,33,.10); }
.vmcs-load-fill-assigned{ position:absolute; left:0; top:0; bottom:0; border-radius:4px 0 0 4px; background:var(--vmcs-assigned); }
.vmcs-load-fill-pending{ position:absolute; top:0; bottom:0; background:var(--vmcs-pending); }
.vmcs-card-load.is-dark .vmcs-load-fill-assigned,
.vmcs-card-load.is-dark .vmcs-load-fill-pending{ box-shadow:0 0 0 1px rgba(255,255,255,.3) inset; }

.vmcs-scale{ display:flex; align-items:center; gap:6px; margin-top:15px; font-size:10px; color:var(--vmcs-ink-soft); flex-wrap:wrap; }
.vmcs-scale i{ width:16px; height:8px; border-radius:3px; display:inline-block; }

.vmcs-bar-legend-note{ display:flex; gap:16px; margin-top:9px; font-size:10px; color:var(--vmcs-ink-soft); }
.vmcs-bar-legend-note span{ display:flex; align-items:center; gap:6px; }
.vmcs-bar-legend-note i{ width:9px; height:9px; border-radius:3px; display:inline-block; }
.vmcs-bln-assigned{ background:var(--vmcs-assigned); } .vmcs-bln-pending{ background:var(--vmcs-pending); }

.vmcs-chart-title-row{ display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px; flex-wrap:wrap; gap:8px; }
.vmcs-chart-legend{ display:flex; gap:14px; font-size:10px; font-weight:600; color:var(--vmcs-ink-soft); }
.vmcs-chart-legend span{ display:flex; align-items:center; gap:6px; }
.vmcs-chart-legend i{ width:9px; height:9px; border-radius:3px; display:inline-block; }
.vmcs-li-in{ background:var(--vmcs-arrival); } .vmcs-li-out{ background:var(--vmcs-departure); }
.vmcs-li-as{ background:var(--vmcs-assigned); } .vmcs-li-pe{ background:var(--vmcs-pending); }
.vmcs-chart-wrap canvas{ max-height:260px; }

.vmcs-prop-tooltip{
  position:fixed; z-index:9999; background:#FFFFFF; color:var(--vmcs-ink); border-radius:12px;
  box-shadow:0 4px 12px rgba(21,26,33,.08), 0 16px 40px rgba(21,26,33,.18);
  padding:12px 14px; width:max-content; min-width:228px; max-width:min(346px, calc(100vw - 24px));
  display:none; font-family:'Manrope',sans-serif;
}
.vmcs-prop-tooltip.visible{ display:block; }
.vmcs-prop-tooltip .pt-title{ font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#EA580C; background:#FFEDD5; margin:-12px -14px 8px; padding:8px 14px; border-radius:12px 12px 0 0; display:flex; align-items:center; gap:6px; white-space:nowrap; }
.vmcs-prop-tooltip .pt-title svg{ width:12px; height:12px; flex-shrink:0; }
.vmcs-prop-tooltip ul{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:7px; max-height:min(320px, 60vh); overflow-y:auto; }
.vmcs-prop-tooltip li{ display:flex; justify-content:space-between; align-items:baseline; gap:10px; font-size:12.5px; }
.vmcs-prop-tooltip li .pname{ font-weight:600; }
.vmcs-prop-tooltip li a.pname{ color:inherit; text-decoration:none; }
.vmcs-prop-tooltip li a.pname:hover{ text-decoration:underline; }
.vmcs-prop-tooltip li .phrs{ font-size:11px; font-weight:700; color:var(--vmcs-ink-soft); white-space:nowrap; }
.vmcs-prop-tooltip .pt-empty{ font-size:12px; color:var(--vmcs-ink-soft); }
.vmcs-prop-tooltip li.no-task{ background:#FDECEC; margin:0 -6px; padding:2px 6px; border-radius:6px; }
.vmcs-loading{ padding:40px; text-align:center; color:var(--vmcs-ink-soft); font-size:13px; }

@media (max-width:900px){
  .vmcs-kpi-row{ grid-template-columns:1fr 1fr; }
  .vmcs-card-row{ grid-template-columns:repeat(7,minmax(96px,1fr)); overflow-x:auto; }
}
</style>

<div class="vmcs-widget" id="vmcsWidget">
  <div class="vmcs-header">
    <div>
      <p class="vmcs-eyebrow">Operaciones · Portfolio de villas</p>
      <h2>Flujo semanal y carga de limpieza</h2>
    </div>
    <div class="vmcs-week-nav">
      <button type="button" id="vmcsPrevWeek" aria-label="Semana anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M15 18l-6-6 6-6"/></svg></button>
      <span class="vmcs-week-label" id="vmcsWeekLabel">—</span>
      <button type="button" id="vmcsNextWeek" aria-label="Semana siguiente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M9 18l6-6-6-6"/></svg></button>
      <button type="button" class="vmcs-today-btn" id="vmcsTodayBtn">Hoy</button>
    </div>
  </div>

  <div class="vmcs-kpi-row">
    <div class="vmcs-kpi arrival"><div class="l">Check-ins</div><div class="v" id="vmcsKpiArr">0</div></div>
    <div class="vmcs-kpi departure"><div class="l">Check-outs</div><div class="v" id="vmcsKpiDep">0</div></div>
    <div class="vmcs-kpi"><div class="l">Horas totales</div><div class="v" id="vmcsKpiHrs">0.0h</div></div>
    <div class="vmcs-kpi pending"><div class="l">Por asignar</div><div class="v" id="vmcsKpiPend">0.0h</div></div>
  </div>

  <div class="vmcs-section" style="border-top:none; padding-top:0;">
    <p class="vmcs-col-title" style="margin-top:0;">Detalle por día</p>
    <p class="vmcs-col-sub">Toca el número de check-ins o check-outs para ver los inmuebles · el color inferior compara la carga entre los días de la semana</p>
    <div class="vmcs-card-row" id="vmcsCardRow"><div class="vmcs-loading">Cargando…</div></div>
    <div class="vmcs-scale">
      <span>Menos carga</span>
      <i style="background:var(--vmcs-heat-0)"></i><i style="background:var(--vmcs-heat-1)"></i><i style="background:var(--vmcs-heat-2)"></i><i style="background:var(--vmcs-heat-3)"></i><i style="background:var(--vmcs-heat-4)"></i>
      <span>Más carga</span>
    </div>
    <div class="vmcs-bar-legend-note">
      <span><i class="vmcs-bln-assigned"></i>Asignada</span>
      <span><i class="vmcs-bln-pending"></i>Pendiente</span>
    </div>
  </div>

  <div class="vmcs-section">
    <div class="vmcs-chart-title-row">
      <div>
        <p class="vmcs-col-title" style="margin:0;">Check-ins, check-outs y carga por día</p>
        <p class="vmcs-col-sub" style="margin:2px 0 0;">Carga = horas asignadas + pendientes generadas por los check-outs</p>
      </div>
      <div class="vmcs-chart-legend">
        <span><i class="vmcs-li-in"></i>Check-ins</span>
        <span><i class="vmcs-li-out"></i>Check-outs</span>
        <span><i class="vmcs-li-as"></i>Asignada</span>
        <span><i class="vmcs-li-pe"></i>Pendiente</span>
      </div>
    </div>
    <div class="vmcs-chart-wrap"><canvas id="vmcsBarChart" height="90"></canvas></div>
  </div>
</div>

<div class="vmcs-prop-tooltip" id="vmcsPropTooltip"></div>

<script>
(function () {
  const BASE_URL = '{{ route("vm.dashboard", $project->slug) }}' + '/carga-semanal';
  const TAREA_LIMPIEZA_BASE = '{{ url($project->slug . "/tareas_limpieza_form") }}';

  function iconLogIn(){ return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>`; }
  function iconLogOut(){ return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>`; }

  const DOW = ['DOM','LUN','MAR','MIÉ','JUE','VIE','SÁB'];
  const MONTHS = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  let currentOffset = 0, barChart = null, currentWeek = [];

  function parseISODate(iso){ const [y,m,d] = iso.split('-').map(Number); return new Date(y, m-1, d); }
  function fmtDate(d){ return `${String(d.getDate()).padStart(2,'0')} ${MONTHS[d.getMonth()]}`; }
  function isSameDay(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate(); }

  function heatColor(total, max){
    const pct = max>0 ? total/max : 0;
    if(pct<=0.02) return {bg:'var(--vmcs-heat-0)', fg:'#5C6773', dark:false};
    if(pct<0.3)  return {bg:'var(--vmcs-heat-1)', fg:'#153B5C', dark:false};
    if(pct<0.55) return {bg:'var(--vmcs-heat-2)', fg:'#0F2E48', dark:false};
    if(pct<0.8)  return {bg:'var(--vmcs-heat-3)', fg:'#FFFFFF', dark:true};
    return        {bg:'var(--vmcs-heat-4)', fg:'#FFFFFF', dark:true};
  }

  const tooltipEl = document.getElementById('vmcsPropTooltip');
  function closeTooltip(){ tooltipEl.classList.remove('visible'); }
  function showTooltip(anchorEl, title, iconSvg, items, showHours = true){
    const listHtml = items.length
      ? `<ul>${items.map(it=>{
          const name = it.taskId
            ? `<a class="pname" href="${TAREA_LIMPIEZA_BASE}/${it.taskId}" target="_blank" rel="noopener">${it.property}</a>`
            : `<span class="pname">${it.property}</span>`;
          const hrs = showHours ? `<span class="phrs">${it.hours.toFixed(1)}h</span>` : '';
          return `<li${it.noTask ? ' class="no-task"' : ''}>${name}${hrs}</li>`;
        }).join('')}</ul>`
      : `<p class="pt-empty">Sin movimientos.</p>`;
    tooltipEl.innerHTML = `<p class="pt-title">${iconSvg}${title}</p>${listHtml}`;
    const r = anchorEl.getBoundingClientRect();
    tooltipEl.classList.add('visible');
    const tw = tooltipEl.offsetWidth, th = tooltipEl.offsetHeight;
    let left = r.left + r.width/2 - tw/2;
    left = Math.max(10, Math.min(left, window.innerWidth - tw - 10));
    let top = r.bottom + 8;
    if(top + th > window.innerHeight - 10){ top = r.top - th - 8; }
    tooltipEl.style.left = `${left}px`;
    tooltipEl.style.top = `${top}px`;
  }
  document.addEventListener('click', (e)=>{
    if(e.target.closest('.vmcs-flow-item') || e.target.closest('.vmcs-prop-tooltip')) return;
    closeTooltip();
  });

  async function fetchWeek(offset){
    const res = await fetch(`${BASE_URL}?offset=${offset}`, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('No se pudo cargar la carga semanal.');
    return data.days.map(d => ({
      date: parseISODate(d.date),
      arrivals: d.arrivals,
      arrivalProps: d.arrival_props,
      departures: d.departures,
      tasks: d.tasks.map(t => ({ property: t.property, hours: t.hours, assigned: t.assigned, hasTask: t.has_task, taskId: t.task_id })),
      assignedHours: d.assigned_hours,
      pendingHours: d.pending_hours,
    }));
  }

  async function render(){
    let week;
    try {
      week = await fetchWeek(currentOffset);
    } catch (e) {
      document.getElementById('vmcsCardRow').innerHTML = `<div class="vmcs-loading">No se ha podido cargar la información.</div>`;
      return;
    }
    currentWeek = week;
    const today = new Date(); today.setHours(0,0,0,0);
    const first = week[0].date, last = week[6].date;
    document.getElementById('vmcsWeekLabel').textContent = currentOffset === 0 ? `Esta semana · ${fmtDate(first)}–${fmtDate(last)}` : `${fmtDate(first)} – ${fmtDate(last)}`;
    closeTooltip();

    const totalArr = week.reduce((s,d)=>s+d.arrivals,0);
    const totalDep = week.reduce((s,d)=>s+d.departures,0);
    const totalAssigned = week.reduce((s,d)=>s+d.assignedHours,0);
    const totalPending = week.reduce((s,d)=>s+d.pendingHours,0);
    document.getElementById('vmcsKpiArr').textContent = totalArr;
    document.getElementById('vmcsKpiDep').textContent = totalDep;
    document.getElementById('vmcsKpiHrs').textContent = `${(totalAssigned+totalPending).toFixed(1)}h`;
    document.getElementById('vmcsKpiPend').textContent = `${totalPending.toFixed(1)}h`;

    // Escala común de la semana: compara la carga de cada día frente al máximo de esos 7 días.
    const maxLoad = Math.max(1, ...week.map(d=>d.assignedHours+d.pendingHours));

    document.getElementById('vmcsCardRow').innerHTML = week.map((d,idx)=>{
      const total = d.assignedHours + d.pendingHours;
      const c = heatColor(total, maxLoad);
      const isToday = isSameDay(d.date, today);
      const assignedPct = total>0 ? (d.assignedHours/total)*100 : 0;
      const pendingPct = total>0 ? (d.pendingHours/total)*100 : 0;
      return `
        <div class="vmcs-card ${isToday?'today':''}">
          <div class="vmcs-card-top">
            <div class="dow">${DOW[d.date.getDay()]}</div>
            <div class="dnum">${fmtDate(d.date)}</div>
            <div class="vmcs-flow-pair">
              <button type="button" class="vmcs-flow-item in" data-day="${idx}" data-kind="in" aria-label="Ver inmuebles con check-in">${iconLogIn()}${d.arrivals}</button>
              <button type="button" class="vmcs-flow-item out" data-day="${idx}" data-kind="out" aria-label="Ver inmuebles con check-out">${iconLogOut()}${d.departures}</button>
            </div>
          </div>
          <div class="vmcs-card-load ${c.dark?'is-dark':'is-light'}" style="background:${c.bg}; color:${c.fg}">
            <div class="load-figure">${total.toFixed(1)}h</div>
            ${total>0 ? `
              <div class="vmcs-load-track">
                <div class="vmcs-load-fill-assigned" style="width:${assignedPct}%"></div>
                <div class="vmcs-load-fill-pending" style="left:${assignedPct}%; width:${pendingPct}%"></div>
              </div>
            ` : ''}
          </div>
        </div>
      `;
    }).join('');

    document.querySelectorAll('.vmcs-flow-item').forEach(btn=>{
      btn.addEventListener('click', (e)=>{
        e.stopPropagation();
        const dayIdx = +btn.dataset.day;
        const kind = btn.dataset.kind;
        const d = currentWeek[dayIdx];
        const dayLabel = `${DOW[d.date.getDay()]} ${fmtDate(d.date)}`;
        if(kind==='in'){
          showTooltip(btn, `Check-ins · ${dayLabel}`, iconLogIn(), d.arrivalProps, false);
        } else {
          showTooltip(btn, `Check-outs · ${dayLabel}`, iconLogOut(), d.tasks.map(t=>({property:t.property, hours:t.hours, noTask:!t.hasTask, taskId:t.taskId})));
        }
      });
    });

    const ctx = document.getElementById('vmcsBarChart').getContext('2d');
    const chartData = {
      labels: week.map(d=>`${DOW[d.date.getDay()]} ${fmtDate(d.date)}`),
      datasets:[
        {label:'Check-ins', data:week.map(d=>d.arrivals), backgroundColor:'#2E7D63', stack:'in', yAxisID:'y', barPercentage:0.7, categoryPercentage:0.8, borderRadius:4},
        {label:'Check-outs', data:week.map(d=>d.departures), backgroundColor:'#C2612B', stack:'out', yAxisID:'y', barPercentage:0.7, categoryPercentage:0.8, borderRadius:4},
        {label:'Asignada', data:week.map(d=>d.assignedHours), backgroundColor:'#1F4E85', stack:'load', yAxisID:'yHoras', barPercentage:0.7, categoryPercentage:0.8, borderRadius:{topLeft:0,topRight:0,bottomLeft:4,bottomRight:4}},
        {label:'Pendiente', data:week.map(d=>d.pendingHours), backgroundColor:'#F0A93B', stack:'load', yAxisID:'yHoras', barPercentage:0.7, categoryPercentage:0.8, borderRadius:{topLeft:4,topRight:4,bottomLeft:0,bottomRight:0}}
      ]
    };
    if(barChart){ barChart.data = chartData; barChart.update(); }
    else {
      barChart = new Chart(ctx, { type:'bar', data:chartData, options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false}, tooltip:{
          backgroundColor:'#151A21', padding:9, cornerRadius:8, bodyFont:{family:'Manrope', size:11}, titleFont:{family:'Manrope', size:11, weight:'700'},
          callbacks:{ label:(c)=>{
            const unit = (c.dataset.label==='Asignada'||c.dataset.label==='Pendiente') ? 'h' : '';
            return ` ${c.dataset.label}: ${c.parsed.y}${unit}`;
          }}
        }},
        scales:{
          x:{ grid:{display:false}, ticks:{font:{family:'Manrope', size:10.5, weight:'600'}, color:'#7B8794'} },
          y:{ beginAtZero:true, position:'left', grid:{color:'#EEF1F4'}, ticks:{font:{family:'Manrope', size:10.5}, color:'#7B8794', precision:0}, title:{display:true, text:'Check-ins / Check-outs', font:{family:'Manrope', size:10, weight:'600'}, color:'#7B8794'} },
          yHoras:{ beginAtZero:true, position:'right', grid:{display:false}, ticks:{font:{family:'Manrope', size:10.5}, color:'#7B8794', callback:(v)=>v+'h'}, title:{display:true, text:'Horas de limpieza', font:{family:'Manrope', size:10, weight:'600'}, color:'#7B8794'} }
        }
      }});
    }
  }

  document.getElementById('vmcsPrevWeek').addEventListener('click', ()=>{ currentOffset--; render(); });
  document.getElementById('vmcsNextWeek').addEventListener('click', ()=>{ currentOffset++; render(); });
  document.getElementById('vmcsTodayBtn').addEventListener('click', ()=>{ currentOffset=0; render(); });
  render();
})();
</script>
