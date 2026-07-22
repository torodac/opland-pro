<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
$year_min = now()->year - 3;
$year_max = now()->year + 1;
@endphp

<style>
.nov-wrap      { display:flex; gap:16px; align-items:flex-start; min-height:600px; }
.nov-left      { flex:0 0 340px; display:flex; flex-direction:column; gap:12px; }
.nov-right     { flex:1; min-width:0; }

.nov-card      { background:#fff; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.07); padding:14px 16px; }

.nov-filters   { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.nov-filters select { font-size:.82rem; border:1px solid #e2e8f0; border-radius:6px; padding:5px 8px; }
.nov-notice    { font-size:.75rem; color:#888; margin-top:6px; }

.nov-reserva-item { padding:8px 10px; border-radius:6px; cursor:pointer; border:1px solid transparent;
                    margin-bottom:4px; transition:all .15s; }
.nov-reserva-item:hover  { background:#f0f4ff; border-color:#c7d2fe; }
.nov-reserva-item.active { background:#eef2ff; border-color:#818cf8; }
.nov-reserva-item .guest { font-size:.84rem; font-weight:600; color:#333; }
.nov-reserva-item .dates { font-size:.75rem; color:#888; margin-top:1px; }
.nov-reserva-item .bid   { font-size:.72rem; color:#aaa; }

.nov-empty { color:#aaa; font-size:.85rem; padding:40px; text-align:center; }

/* Bloque importes */
.bloque-title { display:flex; justify-content:space-between; align-items:center;
                font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
                color:#888; margin-bottom:6px; padding-bottom:4px; border-bottom:1px solid #f0f0f0; }
.bloque-title .total-all { font-size:.82rem; font-weight:700; color:#185FA5; }

.imp-row      { display:flex; align-items:center; gap:8px; padding:4px 0;
                border-bottom:1px solid #f7f8fa; font-size:.82rem; }
.imp-row:last-child { border-bottom:none; }
.imp-texto    { flex:1; color:#444; }
.imp-importe  { min-width:80px; text-align:right; font-weight:600; color:#333; }
.imp-importe.neg { color:#dc2626; }

/* Toggle */
.toggle-wrap { position:relative; display:inline-block; width:36px; height:20px; flex-shrink:0; }
.toggle-wrap input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:#d1d5db;
                 border-radius:20px; transition:.2s; }
.toggle-slider:before { content:''; position:absolute; width:14px; height:14px;
                        left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-wrap input:checked + .toggle-slider { background:#185FA5; }
.toggle-wrap input:checked + .toggle-slider:before { transform:translateX(16px); }

/* Panel cálculo */
.calc-row     { display:flex; justify-content:space-between; align-items:center;
                font-size:.83rem; padding:3px 0; color:#444; }
.calc-row.total { font-weight:700; font-size:.9rem; color:#185FA5;
                  border-top:2px solid #e5e7eb; margin-top:4px; padding-top:6px; }
.calc-row.base  { font-weight:700; font-size:.95rem; color:#059669;
                  border-top:2px solid #d1fae5; margin-top:6px; padding-top:6px; }
.calc-row .neg  { color:#dc2626; font-weight:600; }
.calc-row .val  { font-weight:600; }

.iva-wrap { display:flex; align-items:center; gap:6px; font-size:.82rem; color:#666; }
.iva-wrap input { width:52px; border:1px solid #e2e8f0; border-radius:5px;
                  padding:3px 6px; font-size:.82rem; text-align:center; }

.com-row  { display:flex; align-items:center; gap:8px; padding:4px 0;
            border-bottom:1px solid #f7f8fa; font-size:.82rem; }
.com-row:last-child { border-bottom:none; }
.com-texto  { flex:1; color:#444; }
.com-input  { width:90px; border:1px solid #e2e8f0; border-radius:5px;
              padding:3px 6px; font-size:.82rem; text-align:right; }
.com-importe { min-width:80px; text-align:right; font-weight:600; color:#555; }

.split-box  { display:flex; gap:12px; margin-top:12px; }
.split-item { flex:1; border-radius:8px; padding:12px; text-align:center; }
.split-item.vm   { background:#eef2ff; }
.split-item.prop { background:#f0fdf4; }
.split-item .lbl { font-size:.72rem; font-weight:700; text-transform:uppercase;
                   letter-spacing:.05em; color:#888; margin-bottom:4px; }
.split-item .amt { font-size:1.05rem; font-weight:800; }
.split-item.vm .amt   { color:#4f46e5; }
.split-item.prop .amt { color:#059669; }
.split-item .pct { font-size:.73rem; color:#aaa; margin-top:2px; }

.badge-novada { font-size:.7rem; padding:2px 7px; border-radius:4px; font-weight:600;
                background:#d1fae5; color:#065f46; }

.btn-guardar { background:#059669; color:#fff; border:none; border-radius:6px;
               padding:7px 18px; font-size:.85rem; font-weight:600; cursor:pointer;
               margin-top:16px; width:100%; transition:background .15s; }
.btn-guardar:hover { background:#047857; }
.btn-guardar:disabled { background:#9ca3af; cursor:default; }

.btn-documento { background:#185FA5; color:#fff; border:none; border-radius:6px;
                 padding:6px 16px; font-size:.82rem; font-weight:600; cursor:pointer;
                 transition:background .15s; white-space:nowrap; }
.btn-documento:hover { background:#154e8c; }

.btn-sincronizar { background:#fff; color:#185FA5; border:1px solid #185FA5; border-radius:6px;
                   padding:6px 16px; font-size:.82rem; font-weight:600; cursor:pointer;
                   transition:all .15s; white-space:nowrap; }
.btn-sincronizar:hover    { background:#eef4fb; }
.btn-sincronizar:disabled{ opacity:.6; cursor:default; }
.btn-sincronizar.syncing { animation: spin-icon 1s linear infinite; }
@keyframes spin-icon { 0%{opacity:.5;} 50%{opacity:1;} 100%{opacity:.5;} }

.sep-row { border-top:1px dashed #e5e7eb; margin:6px 0; }

/* Tarjeta Gastos */
.gastos-card { background:#fff; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.07);
               padding:12px 16px; cursor:pointer; border:2px solid transparent;
               transition:all .15s; display:flex; align-items:center; gap:10px; }
.gastos-card:hover  { border-color:#c7d2fe; background:#f0f4ff; }
.gastos-card.active { border-color:#818cf8; background:#eef2ff; }
.gastos-card .gc-label { font-size:.88rem; font-weight:700; color:#333; }
.gastos-card .gc-sub   { font-size:.74rem; color:#888; }

/* Modal crear tarea */
.nov-modal-bg { position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:900;
                display:flex;align-items:center;justify-content:center; }
.nov-modal    { background:#fff;border-radius:10px;padding:24px;width:360px;
                box-shadow:0 8px 32px rgba(0,0,0,.18); }
.nov-modal h3 { font-size:.95rem;font-weight:700;margin-bottom:16px;color:#222; }
.nov-modal label { display:block;font-size:.8rem;color:#666;margin-bottom:2px;margin-top:10px; }
.nov-modal input { width:100%;border:1px solid #e2e8f0;border-radius:6px;
                   padding:6px 10px;font-size:.85rem;box-sizing:border-box; }
.nov-modal-btns { display:flex;gap:8px;margin-top:18px;justify-content:flex-end; }
.btn-modal-cancel { background:#f3f4f6;color:#555;border:none;border-radius:6px;
                    padding:7px 16px;font-size:.83rem;cursor:pointer; }
.btn-modal-ok     { background:#059669;color:#fff;border:none;border-radius:6px;
                    padding:7px 16px;font-size:.83rem;font-weight:600;cursor:pointer; }

/* Panel Gastos */
.gasto-row { display:flex; align-items:center; gap:10px; padding:5px 0;
             border-bottom:1px solid #f7f8fa; font-size:.83rem; }
.gasto-row:last-child { border-bottom:none; }
.gasto-label { flex:1; color:#444; font-weight:500; }
.gasto-input { width:110px; border:1px solid #e2e8f0; border-radius:5px;
               padding:4px 8px; font-size:.83rem; text-align:right; }
input[type="date"].date-empty:not(:focus) { color: transparent; }
input[type="date"].date-empty:not(:focus)::-webkit-datetime-edit { color: transparent; }

/* Tareas mantenimiento */
.tarea-row { padding:7px 0; border-bottom:1px solid #f7f8fa; font-size:.82rem; }
.tarea-row:last-child { border-bottom:none; }
.tarea-header { display:flex; align-items:center; gap:8px; margin-bottom:4px; }
.tarea-nombre { flex:1; color:#333; font-weight:500; }
.tarea-ficha-link { color:#ccc; display:inline-flex; line-height:0; }
.tarea-ficha-link:hover { color:#EF9F27; }
.tarea-fecha  { font-size:.74rem; color:#aaa; white-space:nowrap; }
.tarea-fields { display:flex; gap:8px; padding-left:44px; }
.tarea-input-nombre  { flex:1; border:1px solid #e2e8f0; border-radius:5px;
                        padding:3px 7px; font-size:.8rem; }
.tarea-input-importe { width:100px; border:1px solid #e2e8f0; border-radius:5px;
                        padding:3px 7px; font-size:.8rem; text-align:right; }
</style>

{{-- Filtros --}}
<form method="GET" id="form-nov" class="nov-card mb-4">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div class="nov-filters">
      <select name="year" onchange="document.getElementById('form-nov').submit()">
        @for($y = $year_min; $y <= $year_max; $y++)
          <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
      <select name="month" onchange="document.getElementById('form-nov').submit()">
        @for($m = 1; $m <= 12; $m++)
          <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $meses_es[$m] }}</option>
        @endfor
      </select>
      <select name="prop_id" onchange="document.getElementById('form-nov').submit()">
        @foreach($propiedades as $p)
          <option value="{{ $p->id }}" {{ $p->id == $prop_id ? 'selected' : '' }}>{{ $p->nombre }}</option>
        @endforeach
      </select>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <button type="button" class="btn-sincronizar" id="btn-sincronizar" onclick="sincronizar()">
        ⟳ Sincronizar
      </button>
      <button type="button" class="btn-documento" onclick="generarDocumento()">
        Generar documento
      </button>
    </div>
  </div>
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div class="nov-notice">ℹ️ Solo se muestran propiedades con tipo de renta <strong>Cesión uso</strong></div>
    <div class="nov-notice" id="txt-ultima-sincronizacion">
      @if($ultima_sincronizacion)
        Última sincronización: {{ \Carbon\Carbon::parse($ultima_sincronizacion)->format('d/m/Y H:i') }}
      @else
        Todavía no se ha sincronizado con Icnea
      @endif
    </div>
  </div>
</form>

<div id="badge-revision" style="{{ $tarea_revision ? '' : 'display:none;' }} margin-bottom:14px;">
  <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;font-size:.83rem;color:#9a3412;display:flex;align-items:center;gap:8px;">
    ⚠️ <span>Los importes de <strong>{{ $mes_anterior_label }}</strong> han cambiado desde que se documentó la novación — hay una tarea de revisión abierta para Contabilidad.</span>
  </div>
</div>

<div class="nov-wrap">

  {{-- Lista reservas --}}
  <div class="nov-left">
    <div class="nov-card">
      @if($reservas->isEmpty())
        <div style="color:#aaa;font-size:.83rem;text-align:center;padding:20px 0;">
          Sin reservas con checkout en {{ $meses_es[$month] }} {{ $year }}
        </div>
      @else
        <div style="font-size:.75rem;color:#888;margin-bottom:10px;">{{ $reservas->count() }} reserva(s)</div>
        @foreach($reservas as $r)
          <div class="nov-reserva-item" onclick="loadImportes('{{ $r->booking_id }}')" id="item-{{ $r->booking_id }}">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <div class="guest">{{ $r->guest_name }}</div>
              @if($r->novacion)
                <span class="badge-novada" style="margin-left:6px;flex-shrink:0;">✓ Novada</span>
              @endif
            </div>
            <div class="dates">
              {{ \Carbon\Carbon::parse($r->check_in_date)->format('d/m') }}
              → {{ \Carbon\Carbon::parse($r->check_out_date)->format('d/m/Y') }}
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2px;">
              <span class="bid">#{{ $r->booking_id }}</span>
              @if($r->novacion && $r->base_propietario !== null)
                <span class="prop-amt" style="font-size:.78rem;font-weight:700;color:#059669;">
                  {{ number_format($r->base_propietario, 2, ',', '.') }} €
                </span>
              @endif
            </div>
          </div>
        @endforeach
      @endif
    </div>
    <div class="gastos-card" id="gastos-card" onclick="loadGastos()">
      <div style="flex:1;">
        <div class="gc-label">Gastos</div>
      </div>
      <div id="gastos-totales" style="text-align:right;">
        <div style="font-size:.72rem;color:#888;line-height:1.6;">
          <span>Suministros:</span> <strong id="gc-tot-sumi" style="color:#185FA5;">—</strong><br>
          <span>Mantenimiento:</span> <strong id="gc-tot-mant" style="color:#185FA5;">—</strong><br>
          <span style="color:#333;">Total:</span> <strong id="gc-tot-total" style="color:#059669;font-size:.82rem;">—</strong>
        </div>
      </div>
    </div>
  </div>

  {{-- Panel detalle --}}
  <div class="nov-right">
    <div class="nov-card" id="panel-detalle">
      <div class="nov-empty">← Selecciona una reserva</div>
    </div>
  </div>

</div>

{{-- Histórico de documentos generados --}}
<div class="nov-card" style="margin-top:16px;">
  <div class="bloque-title"><span>Histórico de documentos — {{ $meses_es[$month] }} {{ $year }}</span></div>
  @if($historial->isEmpty())
    <div style="color:#aaa;font-size:.83rem;text-align:center;padding:16px 0;">
      Todavía no se ha generado ningún documento para este mes.
    </div>
  @else
    <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
      <thead>
        <tr style="text-align:left;color:#888;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
          <th style="padding:6px 8px;">Generado por</th>
          <th style="padding:6px 8px;">Fecha</th>
          <th style="padding:6px 8px;text-align:right;">Importe propietario</th>
          <th style="padding:6px 8px;text-align:right;">Importe VM</th>
          <th style="padding:6px 8px;text-align:right;">Total gastos</th>
          <th style="padding:6px 8px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($historial as $h)
          <tr style="border-top:1px solid #f0f0f0;">
            <td style="padding:6px 8px;">{{ $h->createuser_nombre ?? '—' }}</td>
            <td style="padding:6px 8px;color:#888;">{{ \Carbon\Carbon::parse($h->createdat)->format('d/m/Y H:i') }}</td>
            <td style="padding:6px 8px;text-align:right;font-weight:600;">{{ number_format($h->importe_propietario, 2, ',', '.') }} €</td>
            <td style="padding:6px 8px;text-align:right;font-weight:600;color:#4f46e5;">{{ number_format($h->importe_vm, 2, ',', '.') }} €</td>
            <td style="padding:6px 8px;text-align:right;">{{ number_format($h->total_gastos, 2, ',', '.') }} €</td>
            <td style="padding:6px 8px;text-align:right;">
              <a href="{{ route('novaciones.ver-documento', [$project->slug, $h->id]) }}" target="_blank" style="color:#185FA5;font-weight:600;text-decoration:none;">Abrir ↗</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- Modal crear tarea --}}
<div id="modal-crear-tarea" class="nov-modal-bg" style="display:none;" onclick="if(event.target===this)cerrarModal()">
  <div class="nov-modal">
    <h3>Nueva tarea de mantenimiento</h3>
    <label>Nombre novación</label>
    <input type="text" id="modal-nombre" placeholder="Descripción para la novación">
    <label>Importe</label>
    <input type="number" id="modal-importe" placeholder="0,00" min="0" step="0.01">
    <div class="nov-modal-btns">
      <button class="btn-modal-cancel" onclick="cerrarModal()">Cancelar</button>
      <button class="btn-modal-ok" onclick="crearTarea()">Guardar</button>
    </div>
  </div>
</div>

<script>
const ROUTE_IMPORTES    = "{{ route('novaciones.importes',       $project->slug) }}";
const ROUTE_TOGGLE      = "{{ route('novaciones.toggle',         $project->slug) }}";
const ROUTE_UPDATE      = "{{ route('novaciones.update-importe', $project->slug) }}";
const ROUTE_COMISION    = "{{ route('novaciones.comision-bancos',$project->slug) }}";
const ROUTE_GUARDAR     = "{{ route('novaciones.guardar',        $project->slug) }}";
const ROUTE_SINCRONIZAR = "{{ route('novaciones.sincronizar',    $project->slug) }}";
const ROUTE_GASTOS      = "{{ route('novaciones.gastos',         $project->slug) }}";
const ROUTE_GASTOS_SAVE = "{{ route('novaciones.gastos.save',    $project->slug) }}";
const ROUTE_UPD_TAREA   = "{{ route('novaciones.update-tarea',   $project->slug) }}";
const ROUTE_CRE_TAREA   = "{{ route('novaciones.create-tarea',  $project->slug) }}";
const ROUTE_TAREA_FICHA = "{{ route('vm.tarea', ['project'=>$project->slug,'tipo'=>'__TIPO__','id'=>'__ID__']) }}";
const CSRF              = "{{ csrf_token() }}";
const PROP_ID           = {{ $prop_id }};
const YEAR_SEL          = {{ $year }};
const MONTH_SEL         = {{ $month }};
const DEFAULT_MONTH     = `{{ $year }}-{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}-01`;

// Textos que van al bloque COMISIONES (no en importes)
const TEXTOS_COMISION = ['Comisión canal', 'Management Fee', 'Comisión Bancos'];
// Textos que van al final del bloque importes
const TEXTOS_FIN      = ['cleaning_fee', 'booking_fee'];

let currentBookingId = null;
let importesData     = [];
let porcHonorarios   = 0;

function abrirIcnea(url) {
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.getElementById('icnea-btn');
    if (btn) { btn.title = '¡URL copiada!'; setTimeout(() => btn.title = 'Copiar enlace de Icnea', 1500); }
  });
}

function fmt(v) {
  const n = parseFloat(v);
  if (isNaN(n)) return '— €';
  const neg = n < 0;
  const [int, dec] = Math.abs(n).toFixed(2).split('.');
  const intFmt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return (neg ? '-' : '') + intFmt + ',' + dec + ' €';
}
function fmtDate(d) {
  if (!d) return '';
  const p = d.split('-');
  return p[2]+'/'+p[1]+'/'+p[0];
}

async function loadImportes(bookingId) {
  document.querySelectorAll('.nov-reserva-item').forEach(el => el.classList.remove('active'));
  document.getElementById('gastos-card')?.classList.remove('active');
  document.getElementById('item-' + bookingId)?.classList.add('active');
  currentBookingId = bookingId;

  const panel = document.getElementById('panel-detalle');
  panel.innerHTML = '<div class="nov-empty">Cargando...</div>';

  const res  = await fetch(ROUTE_IMPORTES + '?booking_id=' + bookingId);
  const data = await res.json();
  importesData   = data.importes;
  porcHonorarios = data.porc_honorarios;
  renderPanel(data.reserva);
}

function renderPanel(reserva) {
  const normales  = importesData.filter(i => !TEXTOS_COMISION.includes(i.texto) && !TEXTOS_FIN.includes(i.texto));
  const finBloque = importesData.filter(i => TEXTOS_FIN.includes(i.texto));
  const comisiones = importesData.filter(i => TEXTOS_COMISION.includes(i.texto));

  const todosImportes = [...normales, ...finBloque];
  const totalAll = todosImportes.reduce((s,i) => s + parseFloat(i.importe), 0);

  const LABELS = {'cleaning_fee':'Limpiezas','booking_fee':'Booking fee',
                  'Comisión canal':'Comisión canal','Management Fee':'Management Fee',
                  'Comisión Bancos':'Comisión Bancos'};

  const panel = document.getElementById('panel-detalle');
  const icneaUrl = `https://gero.icnea.net/HosOrdReservaDetall.aspx?res=${reserva.booking_id}`;
  panel.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:#222;">${reserva.guest_name}</div>
        <div style="font-size:.78rem;color:#888;">#${reserva.booking_id} &bull; ${fmtDate(reserva.check_in_date)} → ${fmtDate(reserva.check_out_date)}</div>
      </div>
      <a id="icnea-btn" href="javascript:void(0)" onclick="abrirIcnea('${icneaUrl}')"
         title="Copiar enlace de Icnea"
         style="display:inline-flex;align-items:center;text-decoration:none;cursor:pointer;
                background:#fff;border:1px solid #e2e8f0;border-radius:4px;
                padding:2px 5px;transition:box-shadow .15s;flex-shrink:0;margin-left:10px;"
         onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.12)'"
         onmouseout="this.style.boxShadow='none'">
        <img src="/img/icnea-logo.png" alt="Icnea"
             style="height:14px;width:auto;"
             onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
        <span style="display:none;font-size:.65rem;font-weight:800;color:#0d2d3e;letter-spacing:-.02em;">icnea</span>
      </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">

      <div>
        {{-- Bloque importes --}}
        <div class="bloque-title">
          <span>Importes reserva</span>
          <span class="total-all">${fmt(totalAll)}</span>
        </div>
        ${normales.map(i => importeRow(i, LABELS[i.texto] || i.texto)).join('')}
        ${finBloque.length ? '<div class="sep-row"></div>' : ''}
        ${finBloque.map(i => importeRow(i, LABELS[i.texto] || i.texto)).join('')}

        {{-- Bloque comisiones --}}
        <div class="bloque-title" style="margin-top:16px;">
          <span>Comisiones</span>
        </div>
        ${comisionRow(comisiones.find(i=>i.texto==='Comisión canal'), 'Comisión canal')}
        ${mgmtFeeRow(comisiones.find(i=>i.texto==='Management Fee'))}
        ${comisionBancosRow(comisiones.find(i=>i.texto==='Comisión Bancos'))}
      </div>

      <div>
        <div class="bloque-title"><span>Cálculo</span></div>

        <div class="calc-row">
          <span>Bruto seleccionado</span>
          <span class="val" id="val-bruto">—</span>
        </div>
        <div class="calc-row" style="align-items:center;gap:8px;flex-wrap:wrap;">
          <span>IVA</span>
          <div class="iva-wrap">
            <input type="number" id="iva-pct" value="10" min="0" max="100" step="0.1" oninput="recalcular()"> %
          </div>
          <span class="neg" id="val-iva">—</span>
        </div>
        <div class="calc-row total">
          <span>Base sin IVA</span>
          <span id="val-base-iva">—</span>
        </div>

        <div style="margin-top:12px;">
          <div class="bloque-title"><span>Comisiones aplicadas</span></div>
          <div class="calc-row"><span>Comisión canal</span><span class="neg" id="calc-cc">—</span></div>
          <div class="calc-row"><span>Management Fee</span><span class="neg" id="calc-mgmt">—</span></div>
          <div class="calc-row"><span>Comisión Bancos</span><span class="neg" id="calc-cb">—</span></div>
        </div>

        <div class="calc-row base">
          <span>Base de cálculo</span>
          <span id="val-base-calc">—</span>
        </div>

        <div class="split-box">
          <div class="split-item prop">
            <div class="lbl">Propietario</div>
            <div class="amt" id="val-propietario">—</div>
            <div class="pct" id="pct-propietario">—</div>
          </div>
          <div class="split-item vm">
            <div class="lbl">VM</div>
            <div class="amt" id="val-vm">—</div>
            <div class="pct">${porcHonorarios}%</div>
          </div>
        </div>

        <button class="btn-guardar" id="btn-guardar" onclick="guardarNovacion()">
          Guardar novación
        </button>
      </div>
    </div>
  `;

  recalcular();
}

function importeRow(imp, label) {
  const neg = parseFloat(imp.importe) < 0 ? ' neg' : '';
  if (imp.deleted) {
    return `
      <div class="imp-row" style="opacity:.5;" title="Icnea ya no devuelve esta línea — no cuenta en los totales">
        <span class="toggle-wrap"></span>
        <span class="imp-texto" style="text-decoration:line-through;color:#999;">${label}</span>
        <span class="imp-importe${neg}" style="color:#999;">${fmt(imp.importe)}</span>
      </div>`;
  }
  return `
    <div class="imp-row">
      <label class="toggle-wrap">
        <input type="checkbox" ${imp.propietario ? 'checked' : ''} onchange="toggleImporte(${imp.id}, this)">
        <span class="toggle-slider"></span>
      </label>
      <span class="imp-texto">${label}</span>
      <span class="imp-importe${neg}">${fmt(imp.importe)}</span>
    </div>`;
}

function comisionRow(imp, label) {
  if (!imp) return `<div class="com-row"><span class="com-texto">${label}</span><span class="com-importe" style="color:#ccc;">—</span></div>`;
  return `
    <div class="com-row">
      <label class="toggle-wrap">
        <input type="checkbox" ${imp.propietario ? 'checked' : ''} onchange="toggleImporte(${imp.id}, this)">
        <span class="toggle-slider"></span>
      </label>
      <span class="com-texto">${label}</span>
      <span class="com-importe">${fmt(imp.importe)}</span>
    </div>`;
}

function mgmtFeeRow(imp) {
  const val = imp ? parseFloat(imp.importe) : 0;
  const id  = imp ? imp.id : null;
  return `
    <div class="com-row">
      <label class="toggle-wrap">
        <input type="checkbox" id="toggle-mgmt" ${imp && imp.propietario ? 'checked' : ''} onchange="toggleImporte(${id}, this)" ${!id ? 'disabled' : ''}>
        <span class="toggle-slider"></span>
      </label>
      <span class="com-texto">Management Fee</span>
      <input type="number" class="com-input" id="input-mgmt" value="${val}" min="0" step="0.01"
             onchange="saveImporte(${id}, this.value, 'mgmt')" oninput="recalcular()">
    </div>`;
}

function comisionBancosRow(imp) {
  const val = imp ? parseFloat(imp.importe) : 0;
  const id  = imp ? imp.id : null;
  return `
    <div class="com-row">
      <label class="toggle-wrap">
        <input type="checkbox" id="toggle-cb" ${imp && imp.propietario ? 'checked' : ''} onchange="toggleImporte(${id}, this)" ${!id ? 'disabled' : ''}>
        <span class="toggle-slider"></span>
      </label>
      <span class="com-texto">Comisión Bancos</span>
      <input type="number" class="com-input" id="input-cb" value="${val}" min="0" step="0.01"
             onchange="saveComisionBancos(this.value)" oninput="recalcular()">
    </div>`;
}

async function toggleImporte(id, checkbox) {
  if (!id) return;
  const res  = await fetch(ROUTE_TOGGLE, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({id}),
  });
  const data = await res.json();
  const imp = importesData.find(i => i.id === id);
  if (imp) imp.propietario = data.propietario;
  recalcular();
}

async function saveImporte(id, valor, tipo) {
  if (!id) return;
  const importe = parseFloat(valor) || 0;
  await fetch(ROUTE_UPDATE, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({id, importe}),
  });
  const imp = importesData.find(i => i.id === id);
  if (imp) imp.importe = importe;
  recalcular();
}

async function saveComisionBancos(valor) {
  const importe = parseFloat(valor) || 0;
  const res = await fetch(ROUTE_COMISION, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({booking_id: currentBookingId, importe}),
  });
  const data = await res.json();
  const idx = importesData.findIndex(i => i.texto === 'Comisión Bancos');
  if (idx >= 0) {
    importesData[idx].importe = data.importe;
    importesData[idx].id      = data.id;
  } else {
    importesData.push({id:data.id, texto:'Comisión Bancos', importe:data.importe, propietario:data.propietario});
  }
  const toggleCb = document.getElementById('toggle-cb');
  if (toggleCb) { toggleCb.disabled = false; toggleCb.onchange = () => toggleImporte(data.id, toggleCb); }
  recalcular();
}

function recalcular() {
  const ivaPct = parseFloat(document.getElementById('iva-pct')?.value || 10) / 100;

  // Bruto = importes (sin comisiones) con propietario=1
  const bruto = importesData
    .filter(i => !TEXTOS_COMISION.includes(i.texto) && i.propietario && !i.deleted)
    .reduce((s, i) => s + parseFloat(i.importe), 0);

  const ivaAmt  = bruto - bruto / (1 + ivaPct);
  const baseIva = bruto / (1 + ivaPct);

  setText('val-bruto',   fmt(bruto));
  setText('val-iva',     '- ' + fmt(ivaAmt));
  setText('val-base-iva', fmt(baseIva));

  // Comisiones
  const getComision = (texto, inputId, toggleId) => {
    const toggle = document.getElementById(toggleId);
    if (toggle && !toggle.checked) return 0;
    const imp = importesData.find(i => i.texto === texto);
    if (inputId) {
      const input = document.getElementById(inputId);
      return input ? (parseFloat(input.value) || 0) : 0;
    }
    return imp ? parseFloat(imp.importe) : 0;
  };

  const cc   = getComision('Comisión canal',  null,       'toggle-cc-inner');
  const mgmt = getComision('Management Fee',  'input-mgmt','toggle-mgmt');
  const cb   = getComision('Comisión Bancos', 'input-cb', 'toggle-cb');

  // Para CC el toggle está inline generado
  const ccImp = importesData.find(i => i.texto === 'Comisión canal');
  const ccVal = ccImp && ccImp.propietario ? parseFloat(ccImp.importe) : 0;

  const toggleMgmt = document.getElementById('toggle-mgmt');
  const mgmtVal    = toggleMgmt && toggleMgmt.checked
    ? (parseFloat(document.getElementById('input-mgmt')?.value) || 0) : 0;

  const toggleCb = document.getElementById('toggle-cb');
  const cbVal    = toggleCb && toggleCb.checked
    ? (parseFloat(document.getElementById('input-cb')?.value) || 0) : 0;

  setText('calc-cc',   ccVal   > 0 ? fmt(ccVal)   : '—');
  setText('calc-mgmt', mgmtVal > 0 ? fmt(mgmtVal) : '—');
  setText('calc-cb',   cbVal   > 0 ? fmt(cbVal)   : '—');

  const totalComisiones = ccVal + mgmtVal + cbVal;
  const baseCalc  = baseIva - totalComisiones;
  const vmAmt     = baseCalc * (porcHonorarios / 100);
  const propAmt   = baseCalc - vmAmt;
  const propPct   = 100 - porcHonorarios;

  setText('val-base-calc',   fmt(baseCalc));
  setText('val-vm',          fmt(vmAmt));
  setText('val-propietario', fmt(propAmt));
  setText('pct-propietario', propPct.toFixed(1) + '%');
}

const HISTORIAL_COUNT = {{ $historial->count() }};

function generarDocumento() {
  const items = document.querySelectorAll('.nov-reserva-item');
  const sinNovar = [...items].filter(el => !el.querySelector('.badge-novada'));
  if (sinNovar.length > 0) {
    alert(`Hay ${sinNovar.length} reserva(s) sin novar. Completa todas las novaciones antes de generar el documento.`);
    return;
  }
  if (HISTORIAL_COUNT > 0) {
    alert(`Ya existe${HISTORIAL_COUNT > 1 ? 'n' : ''} ${HISTORIAL_COUNT} documento(s) generado(s) para este mes. Se generará uno nuevo adicional.`);
  }
  const url = "{{ route('novaciones.pdf', $project->slug) }}"
    + `?prop_id=${PROP_ID}&year=${YEAR_SEL}&month=${MONTH_SEL}`;
  window.open(url, '_blank');
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

async function sincronizar() {
  const btn = document.getElementById('btn-sincronizar');
  const txt = document.getElementById('txt-ultima-sincronizacion');
  btn.disabled = true;
  btn.classList.add('syncing');
  btn.textContent = '⟳ Sincronizando…';

  try {
    const res = await fetch(ROUTE_SINCRONIZAR, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
      body: JSON.stringify({ prop_id: PROP_ID, year: YEAR_SEL, month: MONTH_SEL }),
    });
    const data = await res.json();

    if (data.ok) {
      txt.textContent = `Última sincronización: ${data.ultima_sincronizacion}`
        + (data.errores > 0 ? ` (${data.errores} reserva(s) con error)` : '');
      // recarga la reserva abierta, si hay alguna, para reflejar los importes ya sincronizados
      if (currentBookingId) loadImportes(currentBookingId);
      if (data.tarea_revision && data.tarea_revision.creada) {
        document.getElementById('badge-revision').style.display = '';
      }
    } else {
      alert('No se ha podido sincronizar. Revisa el log del servidor.');
    }
  } catch (e) {
    alert('Error de conexión al sincronizar con Icnea.');
  } finally {
    btn.disabled = false;
    btn.classList.remove('syncing');
    btn.textContent = '⟳ Sincronizar';
  }
}

// ── GASTOS ─────────────────────────────────────────────────────────────────

let gastosData = null;

async function loadGastos() {
  // Marcar tarjeta activa, desmarcar reservas
  document.querySelectorAll('.nov-reserva-item').forEach(el => el.classList.remove('active'));
  document.getElementById('gastos-card').classList.add('active');
  currentBookingId = null;

  const panel = document.getElementById('panel-detalle');
  panel.innerHTML = '<div class="nov-empty">Cargando gastos...</div>';

  const url = ROUTE_GASTOS + `?prop_id=${PROP_ID}&year=${YEAR_SEL}&month=${MONTH_SEL}`;
  const res  = await fetch(url);
  gastosData = await res.json();
  renderGastos();
}

function tareaRow(t, tabla) {
  const tipoFicha = tabla === 'piscinas' ? 'piscina' : tabla;
  const fichaUrl  = ROUTE_TAREA_FICHA.replace('__TIPO__', tipoFicha).replace('__ID__', t.id);
  return `
    <div class="tarea-row" id="tarea-${tabla}-${t.id}">
      <div class="tarea-header">
        <label class="toggle-wrap">
          <input type="checkbox" ${t.importe_novacion !== null ? 'checked' : ''}
                 onchange="toggleTarea(${t.id},'${tabla}',this)">
          <span class="toggle-slider"></span>
        </label>
        <span class="tarea-nombre">${t.nombre ?? '—'}</span>
        <a href="${fichaUrl}" target="_blank" rel="noopener" title="Abrir en una nueva pestaña"
           class="tarea-ficha-link" onclick="event.stopPropagation()">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
          </svg>
        </a>
        <span class="tarea-fecha">${fmtDate(t.fecha_finalizacion)}</span>
      </div>
      <div class="tarea-fields">
        <input type="text" class="tarea-input-nombre" placeholder="Nombre novación"
               value="${escHtml(t.nombre_novacion ?? '')}"
               onchange="saveTarea(${t.id},'${tabla}',{nombre_novacion:this.value})">
        <input type="number" class="tarea-input-importe" placeholder="Importe"
               value="${t.importe_novacion ?? ''}" min="0" step="0.01"
               onchange="saveTarea(${t.id},'${tabla}',{importe_novacion:this.value});actualizarTotalesGastos()"
               ${t.importe_novacion === null ? 'disabled' : ''}>
      </div>
    </div>`;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
}

function renderGastos() {
  const g     = gastosData.gastos || {};
  const mant  = gastosData.mantenimiento || [];
  const pisc  = gastosData.piscinas || [];
  const fecha = gastosData.fecha_novacion;

  const SUMI = [
    {key:'electricidad', label:'Electricidad'},
    {key:'agua',         label:'Agua'},
    {key:'internet',     label:'Internet'},
    {key:'alarma',       label:'Alarma'},
    {key:'jardineria',   label:'Jardinería'},
  ];

  const sumiRows = SUMI.map(s => `
    <div class="gasto-row">
      <span class="gasto-label">${s.label}</span>
      <input type="date" class="gasto-input ${!g['fecha_'+s.key] ? 'date-empty' : ''}" id="sumi-fecha-${s.key}"
             value="${g['fecha_'+s.key] || DEFAULT_MONTH}" style="width:120px;"
             data-has-value="${g['fecha_'+s.key] ? '1' : '0'}"
             onchange="this.dataset.hasValue='1';this.classList.remove('date-empty');autoSaveGastos()"
             onfocus="if(!this.dataset.hasValue||this.dataset.hasValue==='0'){this.classList.remove('date-empty')}"
             onblur="if(this.dataset.hasValue!=='1'){this.classList.add('date-empty');}">
      <input type="number" class="gasto-input" id="sumi-${s.key}"
             value="${g[s.key] ?? ''}" min="0" step="0.01" placeholder="0,00"
             oninput="autoSaveGastos();actualizarTotalesGastos()">
    </div>`).join('');

  const mantRows = mant.map(t => tareaRow(t, 'mantenimiento')).join('');
  const piscRows = pisc.map(t => tareaRow(t, 'piscinas')).join('');
  const sinTareas = (mant.length + pisc.length) === 0
    ? '<div style="color:#ccc;font-size:.8rem;padding:8px 0;">Sin tareas en este período</div>' : '';

  document.getElementById('panel-detalle').innerHTML = `
    <div style="margin-bottom:14px;">
      <div style="font-size:.95rem;font-weight:700;color:#222;">Gastos del período</div>
      <div style="font-size:.78rem;color:#888;">Novación fecha: ${fmtDate(fecha)}</div>
    </div>

    <div class="bloque-title"><span>Suministros</span></div>
    ${sumiRows}
    <button class="btn-guardar" style="margin-top:12px;" onclick="saveGastos()">
      Guardar suministros
    </button>

    <div class="bloque-title" style="margin-top:20px;">
      <span>Mantenimiento <span style="font-size:.7rem;font-weight:400;color:#999;">(no se muestran tareas Descartadas o Canceladas)</span></span>
      <button onclick="abrirModal()" style="background:none;border:none;cursor:pointer;
              font-size:1rem;color:#185FA5;font-weight:700;line-height:1;padding:0 2px;"
              title="Crear tarea">＋</button>
    </div>
    <div id="lista-tareas-mant">
      ${mantRows}
      ${piscRows}
      ${sinTareas}
    </div>
  `;

  actualizarTotalesGastos();
}

async function initGastosTotales() {
  if (!PROP_ID) return;
  const url = ROUTE_GASTOS + `?prop_id=${PROP_ID}&year=${YEAR_SEL}&month=${MONTH_SEL}`;
  const res  = await fetch(url);
  gastosData = await res.json();
  actualizarTotalesGastosDesdeData();
}

function actualizarTotalesGastosDesdeData() {
  if (!gastosData) return;
  const g    = gastosData.gastos || {};
  const mant = [...(gastosData.mantenimiento||[]), ...(gastosData.piscinas||[])];

  const SUMI_KEYS = ['electricidad','agua','internet','alarma','jardineria'];
  const totalSumi = SUMI_KEYS.reduce((s,k) => s + (parseFloat(g[k])||0), 0);
  const totalMant = mant.reduce((s,t) => s + (t.importe_novacion !== null ? (parseFloat(t.importe_novacion)||0) : 0), 0);

  const setTxt = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = fmt(val); };
  setTxt('gc-tot-sumi',  totalSumi);
  setTxt('gc-tot-mant',  totalMant);
  setTxt('gc-tot-total', totalSumi + totalMant);
}

function actualizarTotalesGastos() {
  const SUMI_KEYS = ['electricidad','agua','internet','alarma','jardineria'];
  let totalSumi = 0;
  SUMI_KEYS.forEach(k => {
    const v = parseFloat(document.getElementById('sumi-' + k)?.value || 0);
    if (!isNaN(v)) totalSumi += v;
  });

  let totalMant = 0;
  document.querySelectorAll('.tarea-input-importe:not(:disabled)').forEach(inp => {
    const v = parseFloat(inp.value || 0);
    if (!isNaN(v)) totalMant += v;
  });

  const setTxt = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = fmt(val); };
  setTxt('gc-tot-sumi',  totalSumi);
  setTxt('gc-tot-mant',  totalMant);
  setTxt('gc-tot-total', totalSumi + totalMant);
}

function abrirModal() {
  document.getElementById('modal-nombre').value  = '';
  document.getElementById('modal-importe').value = '';
  document.getElementById('modal-crear-tarea').style.display = 'flex';
  document.getElementById('modal-nombre').focus();
}

function cerrarModal() {
  document.getElementById('modal-crear-tarea').style.display = 'none';
}

async function crearTarea() {
  const nombre  = document.getElementById('modal-nombre').value.trim();
  const importe = document.getElementById('modal-importe').value;

  const btn = document.querySelector('.btn-modal-ok');
  btn.disabled = true; btn.textContent = 'Guardando...';

  const res  = await fetch(ROUTE_CRE_TAREA, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({prop_id:PROP_ID, year:YEAR_SEL, month:MONTH_SEL,
                          nombre_novacion: nombre || null,
                          importe_novacion: importe !== '' ? importe : null}),
  });
  const data = await res.json();

  btn.disabled = false; btn.textContent = 'Guardar';
  cerrarModal();

  if (data.tarea) {
    // Añadir la nueva tarea a la lista y a gastosData
    gastosData.mantenimiento.push(data.tarea);
    const lista = document.getElementById('lista-tareas-mant');
    // Quitar el mensaje "sin tareas" si existe
    lista.querySelectorAll('div').forEach(el => { if(el.style.color==='rgb(204,204,204)') el.remove(); });
    lista.insertAdjacentHTML('beforeend', tareaRow(data.tarea, 'mantenimiento'));
    actualizarTotalesGastos();
  }
}

async function saveGastos() {
  const SUMI = ['electricidad','agua','internet','alarma','jardineria'];
  const body = { prop_id: PROP_ID, fecha_novacion: gastosData.fecha_novacion };
  SUMI.forEach(k => {
    const vEl  = document.getElementById('sumi-' + k);
    const fdEl = document.getElementById('sumi-fecha-' + k);
    body[k]            = (vEl?.value  !== '' && vEl?.value  != null) ? vEl.value  : null;
    const hasVal = fdEl?.dataset?.hasValue === '1';
    body['fecha_' + k] = (hasVal && fdEl?.value) ? fdEl.value : null;
  });
  await fetch(ROUTE_GASTOS_SAVE, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify(body),
  });
}

// Autoguarda suministros al cambiar cualquier campo (debounced)
let _gastosTimer = null;
function autoSaveGastos() {
  clearTimeout(_gastosTimer);
  _gastosTimer = setTimeout(saveGastos, 800);
}

async function saveTarea(id, tabla, fields) {
  await fetch(ROUTE_UPD_TAREA, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({id, tabla, ...fields}),
  });
}

// Arranque
document.addEventListener('DOMContentLoaded', initGastosTotales);

function toggleTarea(id, tabla, checkbox) {
  const row = document.getElementById(`tarea-${tabla}-${id}`);
  const importeInput = row?.querySelector('.tarea-input-importe');
  if (!importeInput) return;

  if (checkbox.checked) {
    importeInput.disabled = false;
    if (importeInput.value === '') importeInput.value = '0';
    saveTarea(id, tabla, { importe_novacion: importeInput.value });
  } else {
    importeInput.disabled = true;
    saveTarea(id, tabla, { importe_novacion: null });
  }
  actualizarTotalesGastos();
}

async function guardarNovacion() {
  const btn = document.getElementById('btn-guardar');
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  const parseAmt = id => {
    const el = document.getElementById(id);
    return el ? (parseFloat(el.textContent.replace(/\./g,'').replace(',','.')) || 0) : 0;
  };
  const propNum = parseAmt('val-propietario');
  const calcNum = parseAmt('val-base-calc');

  await fetch(ROUTE_GUARDAR, {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    body: JSON.stringify({booking_id: currentBookingId, base_propietario: propNum, base_calculo: calcNum}),
  });

  btn.textContent = '✓ Guardado';

  // Actualizar item en la lista
  const item = document.getElementById('item-' + currentBookingId);
  if (item) {
    // Badge junto al nombre
    if (!item.querySelector('.badge-novada')) {
      const guestEl = item.querySelector('.guest');
      const badge   = document.createElement('span');
      badge.className  = 'badge-novada';
      badge.style.marginLeft = '6px';
      badge.style.flexShrink = '0';
      badge.textContent = '✓ Novada';
      guestEl.parentNode.appendChild(badge);
    }
    // Importe propietario abajo a la derecha
    const bidRow = item.querySelector('.bid').parentNode;
    let impEl = bidRow.querySelector('.prop-amt');
    if (!impEl) {
      impEl = document.createElement('span');
      impEl.className = 'prop-amt';
      impEl.style.cssText = 'font-size:.78rem;font-weight:700;color:#059669;';
      bidRow.appendChild(impEl);
    }
    impEl.textContent = fmt(propNum);
  }
}
</script>

</x-app-layout>
