<x-app-layout :project="$project" :breadcrumb="[['label'=>'Conciliación','url'=>'']]">

<x-slot name="actions"></x-slot>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Conciliación facturas · caja · banco</h2>
  </div>
  <form method="GET" style="display:flex;align-items:center;gap:6px">
    <label style="font-size:11px;font-weight:700;color:#64748b">Año</label>
    <select name="ejercicio" class="conc-input" onchange="this.form.submit()">
      @php $anioActual = now()->year; @endphp
      @for($y = $anioActual + 1; $y >= 2022; $y--)
        <option value="{{ $y }}" {{ (int)$ejercicio === $y ? 'selected' : '' }}>{{ $y }}</option>
      @endfor
    </select>
  </form>
</div>

<div class="conc-board">
  <div class="conc-card-col">
    <div class="conc-col-head">
      <div><div class="conc-col-title">Facturas</div><div class="conc-col-sub">pendientes / emitidas</div></div>
      <div class="conc-col-head-right">
        <span class="conc-chip conc-chip-neutral" id="badge-facturas">—</span>
        <div class="conc-slicer" data-col="facturas">
          <button class="active" data-val="pendiente" onclick="setSlicer('facturas','pendiente',this)">Sin conciliar</button>
          <button data-val="todo" onclick="setSlicer('facturas','todo',this)">Todo</button>
        </div>
      </div>
    </div>
    <div class="conc-list" id="col-facturas"></div>
  </div>
  <div class="conc-card-col">
    <div class="conc-col-head">
      <div><div class="conc-col-title">Caja</div><div class="conc-col-sub">cobros registrados</div></div>
      <div class="conc-col-head-right">
        <span class="conc-chip conc-chip-neutral" id="badge-caja">—</span>
        <div class="conc-slicer" data-col="caja">
          <button class="active" data-val="pendiente" onclick="setSlicer('caja','pendiente',this)">Sin conciliar</button>
          <button data-val="todo" onclick="setSlicer('caja','todo',this)">Todo</button>
        </div>
      </div>
    </div>
    <div class="conc-list" id="col-caja"></div>
  </div>
  <div class="conc-card-col">
    <div class="conc-col-head">
      <div><div class="conc-col-title">Banco</div><div class="conc-col-sub">extracto real</div></div>
      <div class="conc-col-head-right">
        <span class="conc-chip conc-chip-neutral" id="badge-banco">—</span>
        <div class="conc-slicer" data-col="banco">
          <button class="active" data-val="pendiente" onclick="setSlicer('banco','pendiente',this)">Sin conciliar</button>
          <button data-val="todo" onclick="setSlicer('banco','todo',this)">Todo</button>
        </div>
      </div>
    </div>
    <div class="conc-list" id="col-banco"></div>
  </div>
</div>

<!-- Modal: crear movimiento de caja desde un apunte de banco -->
<div class="conc-modal-overlay" id="crear-modal">
  <div class="conc-modal">
    <div class="conc-modal-head">Nuevo movimiento en Caja</div>
    <div class="conc-modal-body">
      <div class="conc-field">
        <label>Nombre</label>
        <input type="text" class="conc-input" id="cm-nombre" style="width:100%">
      </div>
      <div class="conc-field" style="margin-bottom:0">
        <label>Tipo</label>
        <select class="conc-input" id="cm-tipo" style="width:100%">
          @foreach($tiposCaja as $t)
            <option value="{{ $t->id }}" {{ $t->nombre === 'Cobro' ? 'selected' : '' }}>{{ $t->nombre }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="conc-modal-foot">
      <button class="conc-btn" onclick="closeCrearModal()">Cancelar</button>
      <button class="conc-btn conc-btn-primary" onclick="confirmCrearDesdeBanco()">Crear</button>
    </div>
  </div>
</div>

<style>
.conc-input{font-family:inherit;font-size:12px;border:1px solid #dce6ee;background:#fff;color:#16232b;border-radius:6px;padding:7px 10px}
.conc-board{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:start}
@media (max-width:1150px){ .conc-board{grid-template-columns:1fr} }
.conc-card-col{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);display:flex;flex-direction:column;overflow:hidden}
.conc-col-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:11px 14px;border-bottom:1px solid #dce6ee}
.conc-col-title{font-size:12.5px;font-weight:800;color:#16232b}
.conc-col-sub{font-size:10.5px;color:#7e93a1;margin-top:1px}
.conc-col-head-right{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.conc-slicer{display:flex;border:1px solid #dce6ee;border-radius:99px;padding:2px;background:#f3f7fb}
.conc-slicer button{border:none;background:none;font-size:10px;font-weight:700;padding:3px 9px;border-radius:99px;cursor:pointer;color:#52697a}
.conc-slicer button.active{background:#1b5d73;color:#fff}
.conc-chip{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:99px;border:1px solid transparent;white-space:nowrap}
.conc-chip-neutral{background:#eaf1f6;color:#52697a;border-color:#dce6ee}
.conc-list{padding:10px;max-height:calc(100vh - 320px);min-height:200px;overflow-y:auto}
.conc-item{border:1px solid #dce6ee;border-radius:6px;padding:10px 11px;margin-bottom:8px;cursor:grab;background:#fff;transition:box-shadow .15s,opacity .15s,border-color .15s,background .15s}
.conc-item:hover{box-shadow:0 2px 10px rgba(18,63,79,.09);border-color:#7e93a1}
.conc-item.dragging{opacity:.35;cursor:grabbing}
.conc-item.drop-ready{border-style:dashed;border-color:#1b5d73}
.conc-item.drop-over{border-color:#1b5d73;background:#e7f0f4;box-shadow:0 0 0 3px rgba(27,93,115,.15)}
.conc-item.matched{border-color:#bce3cc;background:#e4f5ec}
.conc-top{display:flex;align-items:baseline;gap:7px;flex-wrap:wrap}
.conc-ext{color:#7e93a1;flex-shrink:0;display:inline-flex;line-height:0;margin-right:1px}
.conc-ext:hover{color:#1b5d73}
.conc-date{font-size:10.5px;color:#7e93a1;font-family:ui-monospace,Consolas,monospace;flex-shrink:0}
.conc-name{font-size:12px;font-weight:700;flex:1;min-width:80px;color:#16232b}
.conc-amount{font-family:ui-monospace,Consolas,monospace;font-weight:800;font-size:12.5px;white-space:nowrap}
.conc-sub{font-size:11px;color:#52697a;margin-top:2px}
.conc-links{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
.conc-link-chip{font-size:10px;font-weight:700;background:#e4f5ec;color:#1f8a5f;border:1px solid #bce3cc;border-radius:99px;padding:2px 4px 2px 8px;display:inline-flex;align-items:center;gap:4px}
.conc-link-chip a{color:inherit;text-decoration:none}
.conc-link-chip a:hover{text-decoration:underline}
.conc-link-chip button{background:none;border:none;color:inherit;cursor:pointer;font-weight:800;padding:0 2px;font-size:11px;line-height:1}
.conc-empty-link{font-size:10.5px;color:#7e93a1}
.conc-new-drop{border:1.5px dashed #dce6ee;border-radius:6px;padding:12px 11px;margin-bottom:10px;text-align:center;font-size:11px;font-weight:600;color:#7e93a1;transition:border-color .15s,background .15s,color .15s}
.conc-new-drop.drop-ready{border-color:#1b5d73;color:#1b5d73}
.conc-new-drop.drop-over{border-color:#1b5d73;background:#e7f0f4;color:#123f4f}

.conc-modal-overlay{position:fixed;inset:0;background:rgba(14,22,27,.45);display:none;align-items:center;justify-content:center;z-index:100}
.conc-modal-overlay.open{display:flex}
.conc-modal{background:#fff;border-radius:10px;width:340px;max-width:92vw;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid #dce6ee}
.conc-modal-head{padding:16px 18px;border-bottom:1px solid #dce6ee;font-weight:800;font-size:14px}
.conc-modal-body{padding:16px 18px}
.conc-modal-foot{padding:14px 18px;border-top:1px solid #dce6ee;display:flex;justify-content:flex-end;gap:8px}
.conc-field{margin-bottom:12px}
.conc-field label{display:block;font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#7e93a1;margin-bottom:4px}
.conc-btn{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;padding:8px 14px;border-radius:6px;border:1px solid #dce6ee;background:#fff;color:#16232b;cursor:pointer}
.conc-btn-primary{background:#1b5d73;border-color:#1b5d73;color:#fff}
</style>

<script>
const PROJECT_SLUG = @json($project->slug);
const RECORD_URL = {
  factura: "{{ route('ficha', [$project->slug, 'facturas', '__ID__']) }}",
  caja:    "{{ route('ficha', [$project->slug, 'caja', '__ID__']) }}",
  banco:   "{{ route('ficha', [$project->slug, 'banco', '__ID__']) }}",
};
const ROUTE_VINCULAR   = @json(route('opland.conciliacion.vincular', $project->slug));
const ROUTE_DESVINCULAR= @json(route('opland.conciliacion.desvincular', $project->slug));
const ROUTE_CREAR      = @json(route('opland.conciliacion.crear-desde-banco', $project->slug));
const CSRF = @json(csrf_token());

@php
    $facturasJs = $facturas->map(fn($f) => ['id'=>$f->id,'fecha'=>$f->fecha_emision,'num'=>$f->num_fact,'cliente'=>$f->cliente,'importe'=>(float)$f->total_a_pagar])->values();
    $cajaJs = $caja->map(fn($c) => ['id'=>$c->id,'fecha'=>$c->fecha_movimiento,'nombre'=>$c->nombre,'importe'=>(float)$c->importe,'id_facturas'=>$c->id_facturas,'id_banco'=>$c->id_banco])->values();
    $bancoJs = $banco->map(fn($b) => ['id'=>$b->id,'fecha'=>$b->fecha_contable,'nombre'=>$b->nombre,'importe'=>(float)$b->importe])->values();
@endphp
const FACTURAS = @json($facturasJs);
const CAJA     = @json($cajaJs);
const BANCO    = @json($bancoJs);

const fmtEUR = v => (v<0?'−':'') + Math.abs(v).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
const fmtDate = iso => { const [y,m,d]=iso.split('-'); return d+'/'+m+'/'+y.slice(2); };

function findFactura(id){ return FACTURAS.find(f=>f.id===id); }
function findCaja(id){ return CAJA.find(c=>c.id===id); }
function findBanco(id){ return BANCO.find(b=>b.id===id); }

function cajaLinkedToFactura(facturaId){ return CAJA.filter(c => c.id_facturas === facturaId); }
function cajaLinkedToBanco(bancoId){ return CAJA.filter(c => c.id_banco === bancoId); }

function isMatched(kind, rec){
  if (kind === 'factura') return cajaLinkedToFactura(rec.id).length > 0;
  if (kind === 'banco') return cajaLinkedToBanco(rec.id).length > 0;
  return !!rec.id_facturas && !!rec.id_banco;
}
function pairAllowed(kindA, kindB){
  if (kindA === kindB) return false;
  return [kindA, kindB].sort().join('-') !== 'banco-factura';
}
const extLinkSvg = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
</svg>`;
function recordUrl(kind, id){ return RECORD_URL[kind].replace('__ID__', id); }

let dragPayload = null;
let slicerState = { facturas:'pendiente', caja:'pendiente', banco:'pendiente' };
function setSlicer(col, val, btn){
  slicerState[col] = val;
  btn.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('active', b===btn));
  renderAll();
}

function chipsForFactura(f){
  const rows = cajaLinkedToFactura(f.id);
  if (!rows.length) return `<span class="conc-empty-link">Sin conciliar — arrastra hacia Caja</span>`;
  return rows.map(c => `<span class="conc-link-chip">
      <a href="${recordUrl('caja', c.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()">💶 ${(c.nombre||'').length>16?c.nombre.slice(0,15)+'…':c.nombre} · ${fmtEUR(c.importe)}</a>
      <button onclick="event.stopPropagation();desvincular(${c.id},'id_facturas')" title="Quitar vínculo">×</button></span>`).join('');
}
function chipsForBanco(b){
  const rows = cajaLinkedToBanco(b.id);
  if (!rows.length) return `<span class="conc-empty-link">Sin conciliar — arrastra hacia Caja</span>`;
  return rows.map(c => `<span class="conc-link-chip">
      <a href="${recordUrl('caja', c.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()">💶 ${(c.nombre||'').length>16?c.nombre.slice(0,15)+'…':c.nombre} · ${fmtEUR(c.importe)}</a>
      <button onclick="event.stopPropagation();desvincular(${c.id},'id_banco')" title="Quitar vínculo">×</button></span>`).join('');
}
function chipsForCaja(c){
  const chips = [];
  if (c.id_facturas) {
    const f = findFactura(c.id_facturas);
    if (f) chips.push(`<span class="conc-link-chip">
        <a href="${recordUrl('factura', f.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()">📄 ${f.num} · ${fmtEUR(f.importe)}</a>
        <button onclick="event.stopPropagation();desvincular(${c.id},'id_facturas')" title="Quitar vínculo">×</button></span>`);
  }
  if (c.id_banco) {
    const b = findBanco(c.id_banco);
    if (b) chips.push(`<span class="conc-link-chip">
        <a href="${recordUrl('banco', b.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()">🏦 ${(b.nombre||'').length>16?b.nombre.slice(0,15)+'…':b.nombre} · ${fmtEUR(b.importe)}</a>
        <button onclick="event.stopPropagation();desvincular(${c.id},'id_banco')" title="Quitar vínculo">×</button></span>`);
  }
  return chips.length ? chips.join('') : `<span class="conc-empty-link">Sin conciliar — arrastra hacia Factura o Banco</span>`;
}

function reconCard(rec, kind){
  const matched = isMatched(kind, rec);
  const label = kind === 'factura' ? rec.num : rec.nombre;
  const sub = kind === 'factura' ? (rec.cliente || '—') : (kind === 'caja' ? 'Caja' : 'Extracto bancario');
  const chips = kind === 'factura' ? chipsForFactura(rec) : (kind === 'banco' ? chipsForBanco(rec) : chipsForCaja(rec));

  return `<div class="conc-item ${matched?'matched':''}" data-id="${rec.id}" data-kind="${kind}"
      draggable="true"
      ondragstart="dragStart(event,${rec.id},'${kind}')"
      ondragend="dragEndFn(event)"
      ondragover="dragOver(event)"
      ondragleave="dragLeave(event)"
      ondrop="dropOn(event,${rec.id},'${kind}')">
    <div class="conc-top">
      <a class="conc-ext" href="${recordUrl(kind, rec.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()" title="Abrir en una nueva pestaña">${extLinkSvg}</a>
      <span class="conc-date">${fmtDate(rec.fecha)}</span>
      <span class="conc-name">${label}</span>
      <span class="conc-amount" style="color:${rec.importe<0?'#c1443a':'#16232b'}">${fmtEUR(rec.importe)}</span>
    </div>
    <div class="conc-sub">${sub}</div>
    <div class="conc-links">${chips}</div>
  </div>`;
}
function newDropZone(){
  return `<div class="conc-new-drop" data-newdrop="caja"
      ondragover="dragOverNewDrop(event)" ondragleave="dragLeave(event)" ondrop="dropOnNewCaja(event)">
    + Arrastra un movimiento de banco aquí para crear un cobro en Caja
  </div>`;
}

function visible(list, col, kind){
  return list.filter(r => slicerState[col] === 'todo' || !isMatched(kind, r));
}

function renderAll(){
  const vF = visible(FACTURAS, 'facturas', 'factura');
  const vC = visible(CAJA, 'caja', 'caja');
  const vB = visible(BANCO, 'banco', 'banco');

  document.getElementById('col-facturas').innerHTML = vF.map(f=>reconCard(f,'factura')).join('')
    || `<div style="padding:26px 10px;text-align:center;color:#7e93a1;font-size:11.5px">Nada que mostrar con este filtro</div>`;
  document.getElementById('col-caja').innerHTML = newDropZone() + (vC.map(c=>reconCard(c,'caja')).join('')
    || `<div style="padding:10px 10px 20px;text-align:center;color:#7e93a1;font-size:11.5px">Nada que mostrar con este filtro</div>`);
  document.getElementById('col-banco').innerHTML = vB.map(b=>reconCard(b,'banco')).join('')
    || `<div style="padding:26px 10px;text-align:center;color:#7e93a1;font-size:11.5px">Nada que mostrar con este filtro</div>`;

  document.getElementById('badge-facturas').textContent = vF.length + ' / ' + FACTURAS.length;
  document.getElementById('badge-caja').textContent = CAJA.length + ' movimientos';
  document.getElementById('badge-banco').textContent = BANCO.length + ' movimientos';
}

function dragStart(ev, id, kind){
  dragPayload = {id, kind};
  ev.dataTransfer.effectAllowed = 'link';
  ev.dataTransfer.setData('text/plain', String(id));
  ev.currentTarget.classList.add('dragging');
  document.querySelectorAll('.conc-item').forEach(el => {
    if (pairAllowed(el.dataset.kind, kind)) el.classList.add('drop-ready');
  });
  if (kind === 'banco') {
    const dz = document.querySelector('.conc-new-drop');
    if (dz) dz.classList.add('drop-ready');
  }
}
function dragEndFn(ev){
  ev.currentTarget.classList.remove('dragging');
  document.querySelectorAll('.conc-item').forEach(el => el.classList.remove('drop-ready','drop-over'));
  const dz = document.querySelector('.conc-new-drop');
  if (dz) dz.classList.remove('drop-ready','drop-over');
  dragPayload = null;
}
function dragOver(ev){
  if (!dragPayload) return;
  const kind = ev.currentTarget.dataset.kind;
  if (!pairAllowed(kind, dragPayload.kind)) return;
  ev.preventDefault();
  ev.dataTransfer.dropEffect = 'link';
  ev.currentTarget.classList.add('drop-over');
}
function dragOverNewDrop(ev){
  if (!dragPayload || dragPayload.kind !== 'banco') return;
  ev.preventDefault();
  ev.dataTransfer.dropEffect = 'copy';
  ev.currentTarget.classList.add('drop-over');
}
function dragLeave(ev){
  if (!ev.currentTarget.contains(ev.relatedTarget)) ev.currentTarget.classList.remove('drop-over');
}

async function postJson(url, body){
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
    body: JSON.stringify(body),
  });
  if (!res.ok) { alert('Error al guardar. Recarga la página e inténtalo de nuevo.'); throw new Error('http ' + res.status); }
  return res.json();
}

async function vincularCajaCon(cajaId, campo, valorId){
  await postJson(ROUTE_VINCULAR, { caja_id: cajaId, campo, valor_id: valorId });
  const c = findCaja(cajaId);
  if (c) c[campo] = valorId;
  renderAll();
}
async function desvincular(cajaId, campo){
  await postJson(ROUTE_DESVINCULAR, { caja_id: cajaId, campo });
  const c = findCaja(cajaId);
  if (c) c[campo] = null;
  renderAll();
}

function dropOn(ev, targetId, targetKind){
  ev.preventDefault();
  ev.currentTarget.classList.remove('drop-over','drop-ready');
  if (!dragPayload || !pairAllowed(dragPayload.kind, targetKind)) return;
  const { id: sourceId, kind: sourceKind } = dragPayload;

  // Siempre resolvemos a: que fila de caja se actualiza, y con que campo/valor.
  if (sourceKind === 'caja') {
    const campo = targetKind === 'factura' ? 'id_facturas' : 'id_banco';
    vincularCajaCon(sourceId, campo, targetId);
  } else if (targetKind === 'caja') {
    const campo = sourceKind === 'factura' ? 'id_facturas' : 'id_banco';
    vincularCajaCon(targetId, campo, sourceId);
  }
}

let pendingBancoId = null;
function dropOnNewCaja(ev){
  ev.preventDefault();
  ev.currentTarget.classList.remove('drop-over','drop-ready');
  if (!dragPayload || dragPayload.kind !== 'banco') return;
  const banco = findBanco(dragPayload.id);
  pendingBancoId = banco.id;
  document.getElementById('cm-nombre').value = 'Cobro (banco: ' + banco.nombre + ')';
  document.getElementById('crear-modal').classList.add('open');
}
function closeCrearModal(){
  document.getElementById('crear-modal').classList.remove('open');
  pendingBancoId = null;
}
async function confirmCrearDesdeBanco(){
  if (!pendingBancoId) return;
  const nombre = document.getElementById('cm-nombre').value.trim() || 'Cobro';
  const idTipoCaja = document.getElementById('cm-tipo').value;
  const data = await postJson(ROUTE_CREAR, { banco_id: pendingBancoId, nombre, id_tipo_caja: idTipoCaja });
  const banco = findBanco(pendingBancoId);
  CAJA.push({ id: data.id, fecha: banco.fecha, nombre, importe: banco.importe, id_facturas: null, id_banco: banco.id });
  closeCrearModal();
  renderAll();
}

renderAll();
</script>

</x-app-layout>
