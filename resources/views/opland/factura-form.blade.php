<x-app-layout :project="$project" :breadcrumb="[['label'=>'Facturas','url'=>route('listado',[$project->slug,'facturas'])],['label'=>'Emisión','url'=>'']]">

<x-slot name="actions">
    <a href="{{ route('ficha', [$project->slug, 'facturas', $f->id]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors"
       title="Ver ficha estándar">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </a>
    <a href="{{ route('opland.factura_form.nueva', $project->slug) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        + Nuevo
    </a>
    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors" onclick="duplicarFactura()">
        Duplicar
    </button>
    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors" onclick="abrirPreview()">
        Previsualizar
    </button>
    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors" onclick="emitirFactura()" id="btn-emitir">
        Emitir y descargar PDF
    </button>
</x-slot>

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Emisión de facturas</h2>
</div>

<div class="fact-card" style="margin-bottom:16px">
  <div class="fact-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 16px">
      <div class="fact-field">
        <div class="fact-label">Cliente</div>
        @include('opland.partials.fk-combo', ['id' => 'in-cliente', 'opciones' => $clientes->pluck('nombre','id'), 'seleccionado' => $f->id_clientes, 'onChangeJs' => 'onHeaderChange()'])
      </div>
      <div class="fact-field">
        <div class="fact-label">Proyecto</div>
        @include('opland.partials.fk-combo', ['id' => 'in-proyecto', 'opciones' => $proyectos->pluck('nombre','id'), 'seleccionado' => $f->id_proyectos, 'onChangeJs' => 'onHeaderChange()'])
      </div>
      <div class="fact-field">
        <div class="fact-label">Descripción (texto libre, aparece en la factura)</div>
        <input class="fact-input" id="in-descripcion" style="width:100%" value="{{ $f->descripcion }}" oninput="onDescripcionInput(this.value)" onchange="onHeaderChange()">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
        <div class="fact-field">
          <div class="fact-label">Nº factura</div>
          <input class="fact-input" id="in-numfact" style="width:100%" disabled>
        </div>
        <div class="fact-field">
          <div class="fact-label">Fecha</div>
          <input class="fact-input" type="date" id="in-fecha" style="width:100%" value="{{ $f->fecha_emision }}" {{ $f->num_fact ? 'disabled' : '' }}>
        </div>
        <div class="fact-field" style="margin-bottom:0">
          <div class="fact-label">Dto. factura (€)</div>
          <input type="hidden" id="in-dtofactura" value="{{ $f->dtototae ?? 0 }}">
          <input class="fact-input" type="text" inputmode="decimal" id="in-dtofactura-disp" style="width:100%"
                 value="{{ number_format($f->dtototae ?? 0, 2, ',', '.') }}"
                 oninput="document.getElementById('in-dtofactura').value=this.value.replace(/\./g,'').replace(',','.')"
                 onblur="commitFormattedNumber('in-dtofactura', 2); onHeaderChange()">
        </div>
      </div>
    </div>
  </div>
</div>

<div class="fact-board" id="fact-board">
  <div class="fact-card" id="panel-lineas">
    <div class="fact-col-head">
      <div>
        <div class="fact-col-title">Líneas de factura</div>
        <div class="fact-col-sub" id="lineas-sub"></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <button class="fact-btn fact-btn-sm" onclick="toggleImpPanel()" id="btn-toggle-imp" title="Ocultar panel de imputaciones">Ocultar imputaciones</button>
        <button class="fact-btn fact-btn-sm" onclick="addLinea()">+ Añadir línea</button>
      </div>
    </div>
    <div class="fact-list fact-list-auto" id="col-lineas"></div>
    <div style="padding:10px 14px 14px;border-top:1px solid #dce6ee;display:flex;flex-direction:column;gap:5px;font-size:12.5px">
      <div style="display:flex;justify-content:space-between"><span style="color:#52697a">Base (líneas)</span><span id="sum-base"></span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#52697a">Dto. factura</span><span id="sum-dtofactura"></span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:#52697a" id="sum-iva-label">IVA</span><span id="sum-iva"></span></div>
      <div style="display:flex;justify-content:space-between;font-weight:800;font-size:14px"><span>Total factura</span><span id="sum-total"></span></div>
    </div>
  </div>

  <div class="fact-card" id="panel-imputaciones">
    <div class="fact-col-head">
      <div>
        <div class="fact-col-title">Imputaciones facturables sin facturar</div>
        <div class="fact-col-sub">Filtradas por el proyecto de esta factura</div>
      </div>
      <span class="fact-chip" id="badge-imputaciones">—</span>
    </div>
    <div class="fact-list" id="col-imputaciones"></div>
  </div>
</div>

<!-- Modal de previsualización -->
<div class="fact-modal-overlay" id="preview-overlay">
  <div class="fact-modal fact-modal-wide">
    <div class="fact-modal-head" style="display:flex;justify-content:space-between;align-items:center">
      <span>Previsualización</span>
      <div style="display:flex;gap:8px">
        <a class="fact-btn fact-btn-sm" id="btn-descargar-pdf" href="{{ route('opland.factura_form.pdf', [$project->slug, $f->id]) }}">⬇ Descargar PDF</a>
        <button class="fact-btn fact-btn-sm" onclick="cerrarPreview()">✕</button>
      </div>
    </div>
    <div class="fact-modal-body" style="max-height:80vh;overflow-y:auto">
      <div class="doc-sheet">
        <div class="doc-header">
          <div class="doc-blob">
            <div class="doc-logo">
              <img src="{{ asset('projects/opland/logo-factura-blanco.png') }}" alt="Opland" class="doc-logo-img">
            </div>
            <div class="doc-company">
              OPLAND ZERO PAPER S.L<br>
              B-10526911<br>
              Av. Aragón, 40<br>
              46021 Valencia<br>
              601 41 36 35
            </div>
          </div>
          <div class="doc-client">
            <div class="doc-client-lbl">Datos cliente</div>
            <div class="doc-client-name" id="pv-cliente-nombre">—</div>
            <div class="doc-client-addr" id="pv-cliente-addr">—</div>
          </div>
          <div class="doc-meta">
            <div class="doc-meta-lbl">Nº factura</div>
            <div class="doc-meta-val" id="pv-numfact">—</div>
            <div class="doc-meta-lbl">Fecha</div>
            <div class="doc-meta-val" id="pv-fecha">—</div>
          </div>
        </div>

        <div class="doc-body">
          <div class="doc-service-title" id="pv-descripcion">—</div>
          <table class="doc-table">
            <colgroup>
              <col style="width:auto">
              <col id="pv-col-precio" style="width:80px"><col id="pv-col-dtop" style="width:56px"><col id="pv-col-dtoe" style="width:64px"><col style="width:92px">
            </colgroup>
            <thead><tr><th>Concepto</th><th id="pv-th-precio" style="text-align:right;white-space:nowrap">Precio</th><th id="pv-th-dtop" style="text-align:right;white-space:nowrap">Dto. %</th><th id="pv-th-dtoe" style="text-align:right;white-space:nowrap">Dto. €</th><th style="text-align:right;white-space:nowrap">Total</th></tr></thead>
            <tbody id="pv-lineas"></tbody>
            <tfoot id="pv-tfoot"></tfoot>
          </table>

          <div class="doc-totals-grid">
            <div class="doc-total-box"><span class="val" id="pv-total-base"></span><span class="lbl">Base imponible</span></div>
            <div class="doc-total-box"><span class="val" id="pv-total-iva"></span><span class="lbl">Total IVA</span></div>
            <div class="doc-total-box"><span class="val" id="pv-total-factura"></span><span class="lbl">Total factura</span></div>
          </div>

          <div class="doc-payment-note">
            <div style="white-space:nowrap">Rogamos hagan efectivo el pago de la presente factura mediante transferencia a la cuenta del Banco BBVA</div>
            <div>ES24 0182 7710 4502 0250 8013</div>
          </div>

          <div class="doc-legal">
            En cumplimiento de lo establecido en el Reglamento General de Protección de Datos (UE) 2016/679 del Parlamento Europeo y del Consejo, de 27 de abril de 2016,
            relativo a la protección de datos personales y la libre circulación de los mismos, le comunicamos que los datos que usted nos facilite quedarán incorporados y serán
            tratados en los ficheros titularidad de OPLAND ZERO PAPER S.L. con el fin de poderle prestar nuestros servicios, así como para mantenerle informado sobre cuestiones
            relativas a la actividad de la empresa. OPLAND ZERO PAPER S.L. se compromete a tratar de forma confidencial los datos de carácter personal facilitados y a no comunicar
            o ceder dicha información a terceros. De acuerdo con dicha Ley, tiene derecho a ejercer los derechos de acceso, rectificación, cancelación, limitación, oposición y
            portabilidad de manera gratuita mediante correo electrónico a opland@opland.es
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.fact-btn{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;padding:8px 14px;border-radius:6px;border:1px solid #dce6ee;background:#fff;color:#16232b;cursor:pointer}
.fact-btn-sm{padding:5px 10px;font-size:11.5px}
.fact-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06)}
.fact-card-body{padding:16px 18px}
.fact-field{margin-bottom:0}
.fact-label{font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#7e93a1;margin-bottom:4px}
.fact-input{font-family:inherit;font-size:12px;border:1px solid #dce6ee;background:#fff;color:#16232b;border-radius:6px;padding:7px 10px}
.fact-input:disabled{background:#f3f7fb;color:#7e93a1}
.fact-board{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start;margin-top:16px}
@media (max-width:1100px){ .fact-board{grid-template-columns:1fr} }
.fact-board.imp-collapsed{grid-template-columns:1fr}
.fact-board.imp-collapsed #panel-imputaciones{display:none}
.fact-col-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:11px 14px;border-bottom:1px solid #dce6ee}
.fact-col-title{font-size:12.5px;font-weight:800;color:#16232b}
.fact-col-sub{font-size:10.5px;color:#7e93a1;margin-top:1px}
.fact-chip{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:99px;background:#eaf1f6;color:#52697a;border:1px solid #dce6ee;white-space:nowrap}
.fact-list{padding:10px;max-height:calc(100vh - 420px);min-height:200px;overflow-y:auto}
.fact-list-auto{max-height:none;min-height:0;overflow-y:visible}

.linea-card{border:1px solid #dce6ee;border-radius:6px;padding:10px 11px;margin-bottom:8px;background:#fff;transition:border-color .15s,background .15s,box-shadow .15s}
.linea-card.drop-ready{border-style:dashed;border-color:#1b5d73}
.linea-card.drop-over{border-color:#1b5d73;background:#e7f0f4;box-shadow:0 0 0 3px rgba(27,93,115,.15)}
.linea-card.dragging{opacity:.35}
.linea-drag-handle{cursor:grab;color:#a3adb3;flex-shrink:0;display:flex;align-items:center;padding:6px 0;user-select:none}
.linea-drag-handle:hover{color:#7e93a1}
.linea-fields{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:6px}
.linea-concepto{flex:1 1 160px;max-width:320px;min-width:120px;font-size:12px;padding:6px 8px}
.linea-catalogo{flex:0 0 180px;min-width:150px}
.linea-num{width:76px;flex:0 0 76px;font-size:12px;padding:6px 6px;text-align:right}
.linea-sub{font-size:12.5px;font-weight:800;width:76px;flex:0 0 76px;text-align:right;padding:6px 0}
.linea-foot{display:flex;align-items:center;justify-content:flex-start;gap:6px;flex-wrap:wrap}
.linea-hours{font-size:10.5px;font-weight:700;color:#fff;background:#f97316;border-radius:99px;padding:2px 9px;flex-shrink:0}
.imp-chip{font-size:10px;font-weight:700;background:#eaf1f6;color:#52697a;border:1px solid #dce6ee;border-radius:99px;padding:2px 4px 2px 8px;display:inline-flex;align-items:center;gap:4px;text-decoration:none;cursor:pointer;transition:border-color .15s,color .15s}
.imp-chip:hover{border-color:#f97316;color:#1b5d73}
.imp-chip button{background:none;border:none;color:inherit;cursor:pointer;font-weight:800;padding:0 2px;font-size:11px;line-height:1}

.imp-card{border:1px solid #dce6ee;border-radius:6px;padding:10px 11px;margin-bottom:8px;cursor:grab;background:#fff;transition:box-shadow .15s,opacity .15s,border-color .15s}
.imp-card:hover{box-shadow:0 2px 10px rgba(18,63,79,.09);border-color:#7e93a1}
.imp-card.dragging{opacity:.35;cursor:grabbing}
.imp-top{display:flex;align-items:baseline;gap:7px;flex-wrap:wrap}
.imp-date{font-size:10.5px;color:#7e93a1;font-family:ui-monospace,Consolas,monospace;flex-shrink:0}
.imp-ext-link{color:#7e93a1;flex-shrink:0;display:inline-flex;line-height:0;margin-right:1px}
.imp-ext-link:hover{color:#1b5d73}
.imp-name{font-size:12px;font-weight:700;flex:1;min-width:80px;color:#16232b}
.imp-horas{font-family:ui-monospace,Consolas,monospace;font-weight:800;font-size:12.5px;white-space:nowrap}
.imp-tarea{font-size:10px;color:#a3adb3;margin-top:2px}

.fact-modal-overlay{position:fixed;inset:0;background:rgba(14,22,27,.45);display:none;align-items:center;justify-content:center;z-index:100}
.fact-modal-overlay.open{display:flex}
.fact-modal{background:#fff;border-radius:10px;width:380px;max-width:92vw;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid #dce6ee}
.fact-modal.fact-modal-wide{width:760px}
.fact-modal-head{padding:16px 18px;border-bottom:1px solid #dce6ee;font-weight:800;font-size:14px}
.fact-modal-body{padding:16px 18px}

.doc-sheet{background:#fff;color:#16232b;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden;border:1px solid #dce6ee}
.doc-header{position:relative;background:#bfdaf2;display:flex;align-items:stretch;min-height:150px;overflow:hidden}
.doc-blob{position:relative;flex:0 0 220px;background:#1b5d73;border-bottom-right-radius:140px;color:#fff;padding:20px 22px;z-index:1}
.doc-logo{display:flex;align-items:center;margin-bottom:14px}
.doc-logo-img{height:24px;width:auto;display:block}
.doc-company{font-size:10.5px;line-height:1.65;font-weight:600;opacity:.95}
.doc-client{flex:1;padding:20px 22px}
.doc-client-lbl{font-size:9.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#3f5a6b;margin-bottom:6px}
.doc-client-name{font-weight:800;font-size:14px;color:#16232b;margin-bottom:6px}
.doc-client-addr{color:#3f5a6b;font-size:11.5px;line-height:1.6}
.doc-meta{flex:0 0 150px;padding:20px 22px 20px 0;text-align:right}
.doc-meta-lbl{font-size:9.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#3f5a6b}
.doc-meta-val{font-weight:800;font-size:15px;color:#16232b;margin:2px 0 12px}
.doc-service-title{font-weight:800;font-size:15px;color:#16232b;margin-bottom:16px;padding-bottom:10px;border-bottom:1.5px solid #16232b}
.doc-body{padding:22px 26px 26px;font-size:12px;color:#16232b}
.doc-table{width:100%;border-collapse:collapse;margin-bottom:22px}
.doc-table th{font-size:9.5px;text-transform:uppercase;letter-spacing:.05em;color:#7e93a1;text-align:left;padding:6px 4px;border-bottom:1.5px solid #16232b}
.doc-table td{padding:9px 4px;font-size:11.5px;border-bottom:1px solid #eaf1f6;vertical-align:top}
.doc-table td.num{text-align:right;white-space:nowrap}
.doc-table tr.doc-base-row td{border-bottom:none;border-top:1.5px solid #16232b;padding-top:11px;white-space:nowrap}
.doc-totals-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.doc-total-box{border:1px solid #dce6ee;border-radius:6px;padding:12px 14px;text-align:left}
.doc-total-box .val{display:block;font-size:16px;font-weight:800;color:#16232b;white-space:nowrap}
.doc-total-box .lbl{font-size:10.5px;color:#7e93a1;margin-top:2px}
.doc-payment-note{text-align:center;font-weight:700;font-size:11.5px;margin-bottom:18px;line-height:1.6}
.doc-legal{padding-top:14px;border-top:1px solid #eaf1f6;font-size:9px;color:#8a9aa5;line-height:1.6;text-align:justify}
</style>

<script>
const PROJECT_SLUG = @json($project->slug);
const FACTURA_ID = {{ $f->id }};
const CSRF = @json(csrf_token());

@php
    $clientesJs = $clientes->map(fn($c) => [
        'id'=>$c->id,'nombre'=>$c->nombre,'nombre_fiscal'=>$c->nombre_fiscal,
        'nif'=>$c->nif,'direccion'=>$c->direccion,'cp'=>$c->cp,'poblacion'=>$c->poblacion,
    ])->values();
    $conceptosJs = $conceptos->values();
@endphp
const CLIENTES = @json($clientesJs);
const CONCEPTOS = @json($conceptosJs);

const ROUTE_ESTADO   = @json(route('opland.factura_form.estado', [$project->slug, $f->id]));
const ROUTE_HEADER   = @json(route('opland.factura_form.update-header', [$project->slug, $f->id]));
const ROUTE_ADD_LINEA= @json(route('opland.factura_form.lineas.add', [$project->slug, $f->id]));
const ROUTE_REORDER_LINEAS = @json(route('opland.factura_form.lineas.reorder', [$project->slug, $f->id]));
const ROUTE_DUPLICAR = @json(route('opland.factura_form.duplicar', [$project->slug, $f->id]));
const ROUTE_EMITIR   = @json(route('opland.factura_form.emitir', [$project->slug, $f->id]));
@php
    $tplUpdateLinea = route('opland.factura_form.lineas.update', [$project->slug, $f->id, '__ID__']);
    $tplRemoveLinea = route('opland.factura_form.lineas.remove', [$project->slug, $f->id, '__ID__']);
    $tplAttachImp = route('opland.factura_form.lineas.attach-imp', [$project->slug, $f->id, '__ID__']);
    $tplDetachImp = route('opland.factura_form.detach-imp', [$project->slug, '__ID__']);
    $tplShow = route('opland.factura_form.show', [$project->slug, '__ID__']);
    $tplImputacionFicha = route('ficha', [$project->slug, 'imputaciones', '__ID__']);
@endphp
const extLinkSvg = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
</svg>`;
function routeImputacionFicha(id){ return @json($tplImputacionFicha).replace('__ID__', id); }
function routeUpdateLinea(id){ return @json($tplUpdateLinea).replace('__ID__', id); }
function routeRemoveLinea(id){ return @json($tplRemoveLinea).replace('__ID__', id); }
function routeAttachImp(lineaId){ return @json($tplAttachImp).replace('__ID__', lineaId); }
function routeDetachImp(impId){ return @json($tplDetachImp).replace('__ID__', impId); }

const fmtEUR = v => (v<0?'−':'') + Math.abs(v).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
const fmtDate = iso => { if(!iso) return '—'; const [y,m,d]=iso.split('-'); return d+'/'+m+'/'+y.slice(2); };

let STATE = null;
let dragImpPayload = null;
let dragLineaPayload = null;

async function apiCall(url, method, body){
  const res = await fetch(url, {
    method,
    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const msg = await res.json().catch(()=>({message:'Error'}));
    alert(msg.message || 'Error al guardar.');
    throw new Error('http ' + res.status);
  }
  return res.json();
}

async function cargarEstado(){
  STATE = await apiCall(ROUTE_ESTADO, 'GET');
  pintarTodo();
}

function clienteNombre(id){ const c = CLIENTES.find(x=>x.id==id); return c ? c.nombre : ''; }
function clientePorId(id){ return CLIENTES.find(x=>x.id==id) || null; }

function pintarTodo(){
  const f = STATE.factura;

  document.getElementById('in-numfact').value = f.emitida ? f.num_fact : (f.num_fact_preview + ' (previsto)');
  document.getElementById('btn-emitir').style.display = f.emitida ? 'none' : '';
  ['in-cliente','in-proyecto','in-descripcion','in-fecha','in-dtofactura'].forEach(id => {
    document.getElementById(id).disabled = !!f.emitida;
  });

  document.getElementById('col-lineas').innerHTML = STATE.lineas.map(lineaCard).join('')
    || `<div style="padding:26px 10px;text-align:center;color:#7e93a1;font-size:11.5px">Sin líneas todavía</div>`;
  document.getElementById('lineas-sub').textContent = STATE.lineas.length + ' líneas';

  document.getElementById('col-imputaciones').innerHTML = STATE.imputaciones_pool.map(impCard).join('')
    || `<div style="padding:26px 10px;text-align:center;color:#7e93a1;font-size:11.5px">No hay imputaciones facturables pendientes para este proyecto</div>`;
  document.getElementById('badge-imputaciones').textContent = STATE.imputaciones_pool.length + ' pendientes';

  const t = STATE.totales;
  document.getElementById('sum-base').textContent = fmtEUR(t.base_bruta);
  document.getElementById('sum-dtofactura').textContent = '-' + fmtEUR(t.dto_total);
  document.getElementById('sum-iva-label').textContent = 'IVA (' + (f.iva ?? 21) + '%)';
  document.getElementById('sum-iva').textContent = fmtEUR(t.iva);
  document.getElementById('sum-total').textContent = fmtEUR(t.total);
}

function numField(id, value, decimals, title, saveJs){
  const raw = value ?? 0;
  const formatted = Number(raw).toLocaleString('es-ES',{minimumFractionDigits:decimals,maximumFractionDigits:decimals});
  return `<input type="hidden" id="${id}" value="${raw}">
    <input class="fact-input linea-num" type="text" inputmode="decimal" title="${title}"
      value="${formatted}"
      oninput="document.getElementById('${id}').value=this.value.replace(/\\./g,'').replace(',','.')"
      onblur="commitFormattedNumber('${id}',${decimals}); ${saveJs}">`;
}
function commitFormattedNumber(id, decimals){
  const hidden = document.getElementById(id);
  let n = parseFloat(hidden.value);
  if (isNaN(n)) n = 0;
  hidden.value = n;
  const disp = hidden.nextElementSibling;
  if (disp) disp.value = n.toLocaleString('es-ES',{minimumFractionDigits:decimals,maximumFractionDigits:decimals});
}

function fkComboJs(id, opciones, seleccionado, onChangeJs){
  const sel = opciones.find(o => o.id == seleccionado);
  const labelText = sel ? sel.nombre : '— Selecciona —';
  const labelColor = sel ? '#111827' : '#9ca3af';
  const items = opciones.map(o => `<li data-id="${o.id}" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#374151;">${o.nombre}</li>`).join('');
  return `<div class="fk-combo" style="position:relative;">
    <button type="button" class="fk-combo-toggle" style="width:100%;text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;border-radius:0.5rem;background:#fff;font-size:0.75rem;display:flex;justify-content:space-between;align-items:center;gap:6px;cursor:pointer;color:${labelColor};">
      <span class="fk-combo-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${labelText}</span>
      <span style="flex-shrink:0;color:#9ca3af;">▾</span>
    </button>
    <div class="fk-combo-panel" style="display:none;position:absolute;z-index:30;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;box-shadow:0 6px 16px rgba(0,0,0,.12);">
      <input type="text" class="fk-combo-search" placeholder="Buscar…" style="width:100%;box-sizing:border-box;padding:0.5rem 0.75rem;border:none;border-bottom:1px solid #f3f4f6;font-size:0.75rem;outline:none;border-radius:0.5rem 0.5rem 0 0;">
      <ul class="fk-combo-list" style="list-style:none;margin:0;padding:2px 0;max-height:12rem;overflow-y:auto;">
        <li data-id="" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#9ca3af;">— Selecciona —</li>
        ${items}
      </ul>
    </div>
    <input type="hidden" id="${id}" class="fk-combo-value" value="${seleccionado ?? ''}" onchange="${onChangeJs}">
  </div>`;
}

function lineaCard(l){
  const idPrecio = 'precio_' + l.id, idDtoP = 'dtop_' + l.id, idDtoE = 'dtoe_' + l.id, idConcepto = 'concepto_' + l.id;
  return `<div class="linea-card" data-linea="${l.id}"
      ondragover="dragOverLinea(event)" ondragleave="dragLeaveLinea(event)" ondrop="dropOnLinea(event,${l.id})">
    <div class="linea-fields">
      <span class="linea-drag-handle" draggable="true" ondragstart="dragStartLinea(event,${l.id})" ondragend="dragEndLinea(event)" title="Arrastrar para reordenar">⠿</span>
      <input class="fact-input linea-concepto" value="${(l.nombre||'').replace(/"/g,'&quot;')}" onchange="onLineaChange(${l.id},'nombre',this.value)">
      <div class="linea-catalogo" title="Concepto (catálogo, opcional)">${fkComboJs(idConcepto, CONCEPTOS, l.id_conceptos, `onLineaChange(${l.id},'id_conceptos',document.getElementById('${idConcepto}').value)`)}</div>
      ${numField(idPrecio, l.precio, 2, 'Precio', `onLineaChange(${l.id},'precio',document.getElementById('${idPrecio}').value)`)}
      ${numField(idDtoP, l.descuentoporc||0, 0, 'Dto. %', `onLineaChange(${l.id},'descuentoporc',document.getElementById('${idDtoP}').value)`)}
      ${numField(idDtoE, l.descuentoe||0, 2, 'Dto. €', `onLineaChange(${l.id},'descuentoe',document.getElementById('${idDtoE}').value)`)}
      <span class="linea-sub">${fmtEUR(l.precio - (l.descuentoe||0))}</span>
      <button class="fact-btn fact-btn-sm" onclick="removeLinea(${l.id})" title="Eliminar línea">✕</button>
    </div>
    ${l.horas>0 ? `<div class="linea-foot">
      <span class="linea-hours">Σ ${l.horas}h imputadas</span>
      ${l.imputaciones.map(i=>`<a class="imp-chip" href="${routeImputacionFicha(i.id)}" target="_blank" rel="noopener" onclick="event.stopPropagation()" title="Abrir imputación en una nueva pestaña">${(i.nombre||'').length>28?i.nombre.slice(0,27)+'…':i.nombre} · ${i.horas}h
        <button onclick="event.stopPropagation();event.preventDefault();detachImp(${i.id})" title="Quitar de esta línea">×</button></a>`).join('')}
    </div>` : ''}
  </div>`;
}

function impCard(imp){
  return `<div class="imp-card" draggable="true" ondragstart="dragStartImp(event,${imp.id})" ondragend="dragEndImp(event)">
    <div class="imp-top">
      <span class="imp-date">${fmtDate(imp.fecha)}</span>
      <a class="imp-ext-link" href="${routeImputacionFicha(imp.id)}" target="_blank" rel="noopener" onmousedown="event.stopPropagation()" title="Abrir en una nueva pestaña">${extLinkSvg}</a>
      <span class="imp-name">${imp.nombre}</span>
      <span class="imp-horas">${imp.horas} h</span>
    </div>
    ${imp.tarea_nombre ? `<div class="imp-tarea">${imp.tarea_nombre}</div>` : ''}
  </div>`;
}

function onDescripcionInput(v){
  const el = document.getElementById('pv-descripcion');
  if (el) el.textContent = v;
}

async function onHeaderChange(){
  const body = {
    id_clientes: document.getElementById('in-cliente').value || null,
    id_proyectos: document.getElementById('in-proyecto').value || null,
    descripcion: document.getElementById('in-descripcion').value,
    dtototae: parseFloat(document.getElementById('in-dtofactura').value) || 0,
  };
  await apiCall(ROUTE_HEADER, 'PATCH', body);
  await cargarEstado();
}

async function addLinea(){
  await apiCall(ROUTE_ADD_LINEA, 'POST');
  await cargarEstado();
}
async function removeLinea(id){
  await apiCall(routeRemoveLinea(id), 'DELETE');
  await cargarEstado();
}
async function onLineaChange(id, field, value){
  let payload;
  if (field === 'nombre') payload = value;
  else if (field === 'id_conceptos') payload = (value === '' || value === null) ? null : parseInt(value);
  else { const n = parseFloat(value); payload = isNaN(n) ? 0 : n; }
  await apiCall(routeUpdateLinea(id), 'PATCH', { [field]: payload });
  await cargarEstado();
}
async function detachImp(impId){
  await apiCall(routeDetachImp(impId), 'DELETE');
  await cargarEstado();
}

function dragStartImp(ev, impId){
  dragImpPayload = impId;
  ev.dataTransfer.effectAllowed = 'link';
  ev.currentTarget.classList.add('dragging');
  document.querySelectorAll('.linea-card').forEach(el => el.classList.add('drop-ready'));
}
function dragEndImp(ev){
  ev.currentTarget.classList.remove('dragging');
  document.querySelectorAll('.linea-card').forEach(el => el.classList.remove('drop-ready','drop-over'));
  dragImpPayload = null;
}
function dragStartLinea(ev, lineaId){
  dragLineaPayload = lineaId;
  ev.dataTransfer.effectAllowed = 'move';
  ev.currentTarget.closest('.linea-card').classList.add('dragging');
}
function dragEndLinea(ev){
  ev.currentTarget.closest('.linea-card').classList.remove('dragging');
  document.querySelectorAll('.linea-card').forEach(el => el.classList.remove('drop-over'));
  dragLineaPayload = null;
}
function dragOverLinea(ev){
  if (!dragImpPayload && !dragLineaPayload) return;
  ev.preventDefault();
  ev.dataTransfer.dropEffect = dragLineaPayload ? 'move' : 'link';
  ev.currentTarget.classList.add('drop-over');
}
function dragLeaveLinea(ev){
  if (!ev.currentTarget.contains(ev.relatedTarget)) ev.currentTarget.classList.remove('drop-over');
}
async function dropOnLinea(ev, lineaId){
  ev.preventDefault();
  ev.currentTarget.classList.remove('drop-over','drop-ready');

  if (dragLineaPayload) {
    const draggedId = dragLineaPayload;
    dragLineaPayload = null;
    if (draggedId === lineaId) return;
    const ids = STATE.lineas.map(l => l.id);
    const from = ids.indexOf(draggedId), to = ids.indexOf(lineaId);
    ids.splice(from, 1);
    ids.splice(to, 0, draggedId);
    await apiCall(ROUTE_REORDER_LINEAS, 'POST', { ids });
    await cargarEstado();
    return;
  }

  if (!dragImpPayload) return;
  const impId = dragImpPayload;
  dragImpPayload = null;
  await apiCall(routeAttachImp(lineaId), 'POST', { imputacion_id: impId });
  await cargarEstado();
}

function toggleImpPanel(){
  const board = document.getElementById('fact-board');
  const collapsed = board.classList.toggle('imp-collapsed');
  const btn = document.getElementById('btn-toggle-imp');
  btn.textContent = collapsed ? 'Mostrar imputaciones' : 'Ocultar imputaciones';
  btn.title = collapsed ? 'Mostrar panel de imputaciones' : 'Ocultar panel de imputaciones';
}

async function duplicarFactura(){
  if (!confirm('¿Duplicar esta factura? Se creará un nuevo borrador con las mismas líneas, sin imputaciones asociadas.')) return;
  const data = await apiCall(ROUTE_DUPLICAR, 'POST');
  window.location.href = @json($tplShow).replace('__ID__', data.id);
}

async function emitirFactura(){
  if (!confirm('Al emitir, el número de factura quedará fijado definitivamente y no podrás editar la cabecera ni las líneas. ¿Continuar?')) return;
  await apiCall(ROUTE_EMITIR, 'POST');
  await cargarEstado();
  alert('Factura emitida: ' + STATE.factura.num_fact);
}

function abrirPreview(){
  const f = STATE.factura;
  const c = clientePorId(f.id_clientes);
  document.getElementById('pv-cliente-nombre').textContent = (c ? (c.nombre_fiscal || c.nombre) : '—');
  const addrLines = [];
  if (c) {
    if (c.nif) addrLines.push(c.nif);
    if (c.direccion) addrLines.push(c.direccion);
    if (c.cp || c.poblacion) addrLines.push([c.cp, c.poblacion].filter(Boolean).join(' '));
  }
  document.getElementById('pv-cliente-addr').innerHTML = addrLines.join('<br>');
  document.getElementById('pv-numfact').textContent = f.emitida ? f.num_fact : f.num_fact_preview;
  document.getElementById('pv-fecha').textContent = fmtDate(document.getElementById('in-fecha').value);
  document.getElementById('pv-descripcion').textContent = document.getElementById('in-descripcion').value || '—';

  const hayDescuento = STATE.lineas.some(l => (l.descuentoe || 0) > 0);
  ['pv-col-precio','pv-col-dtop','pv-col-dtoe','pv-th-precio','pv-th-dtop','pv-th-dtoe'].forEach(id => {
    document.getElementById(id).style.display = hayDescuento ? '' : 'none';
  });

  document.getElementById('pv-lineas').innerHTML = STATE.lineas.map(l => {
    const dtoEur = l.descuentoe || 0;
    const total = l.precio - dtoEur;
    const colsDescuento = hayDescuento
      ? `<td class="num">${fmtEUR(l.precio)}</td><td class="num">${l.descuentoporc||0}%</td><td class="num">${fmtEUR(dtoEur)}</td>`
      : '';
    return `<tr>
      <td>${l.nombre}</td>
      ${colsDescuento}
      <td class="num" style="font-weight:700">${fmtEUR(total)}</td>
    </tr>`;
  }).join('');

  const t = STATE.totales;
  document.getElementById('pv-tfoot').innerHTML = hayDescuento
    ? `<tr class="doc-base-row"><td colspan="2"></td><td class="num"></td><td class="num">${fmtEUR(t.dto_total - t.dto_factura)}</td><td class="num" style="font-weight:800">${fmtEUR(t.base)}</td></tr>`
    : `<tr class="doc-base-row"><td></td><td class="num" style="font-weight:800">${fmtEUR(t.base)}</td></tr>`;
  document.getElementById('pv-total-base').textContent = fmtEUR(t.base_neta);
  document.getElementById('pv-total-iva').textContent = fmtEUR(t.iva);
  document.getElementById('pv-total-factura').textContent = fmtEUR(t.total);

  document.getElementById('preview-overlay').classList.add('open');
}
function cerrarPreview(){
  document.getElementById('preview-overlay').classList.remove('open');
}

cargarEstado();
</script>

</x-app-layout>
