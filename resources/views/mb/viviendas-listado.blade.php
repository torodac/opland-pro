<x-app-layout :project="$project" :breadcrumb="[['label'=>'Viviendas','url'=>'']]">

<x-slot name="actions">
    @if(auth()->user()?->isProjectAdmin($project))
    <a href="{{ route('ficha.create', [$project->slug, 'viviendas']) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo
    </a>
    @endif

    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            Acciones
            <i class="fas fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
        </button>
        <div x-show="open" x-cloak @click="open = false"
             class="absolute right-0 mt-1 w-64 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
            @php $qs = http_build_query(request()->except('page')); @endphp
            <a href="{{ route('mb.viviendas.export', $project->slug) }}?{{ $qs }}"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-filter text-orange-400 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Exportar listado</p>
                    <p class="text-xs text-gray-400">Sin movimientos, una fila por vivienda</p>
                </div>
            </a>
            <a href="{{ route('mb.viviendas.export', $project->slug) }}?movimientos=1&{{ $qs }}"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-list-ul text-blue-400 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Exportar con movimientos</p>
                    <p class="text-xs text-gray-400">Una fila por cuota pendiente/demandada</p>
                </div>
            </a>
        </div>
    </div>
</x-slot>

<form method="GET" style="margin-bottom:14px">
  <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por vivienda o propietario (actual o histórico)…"
         style="font-size:12.5px;border:1px solid #dce6ee;border-radius:8px;padding:7px 11px;width:320px">
</form>

<style>
.vv-stat{border-radius:12px;padding:11px 16px;border:1px solid;display:inline-flex;align-items:center;gap:10px;text-decoration:none;margin-bottom:16px}
.vv-stat-num{font-size:1.25rem;font-weight:700}
.vv-stat-label{font-size:12px;font-weight:500}
.vv-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #dce6ee;border-radius:10px;overflow:hidden}
.vv-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 14px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.vv-table th a{color:inherit;text-decoration:none}
.vv-table th a:hover{color:#374151}
.vv-table td{padding:9px 14px;border-bottom:1px solid #eaf1f6;vertical-align:middle}
.vv-row{cursor:pointer}
.vv-row:hover{background:#f7fafc}
.vv-table td.num{text-align:right;font-variant-numeric:tabular-nums}
.vv-nombre{font-weight:600;color:#16232b;text-decoration:none}
.vv-nombre:hover{text-decoration:underline}
.vv-badge{display:inline-flex;align-items:center;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:99px}
.vv-badge-si{background:#fef2f2;color:#b91c1c}
.vv-badge-no{color:#9ca3af}
.vv-sort-arrow{font-size:9px;color:#f97316}
.vv-toggle{display:inline-flex;align-items:center;gap:6px;color:#9ca3af}
.vv-chevron{width:11px;height:11px;flex-shrink:0;transition:transform .12s}
.vv-chevron.open{transform:rotate(90deg)}
.vv-detail{display:none}
.vv-detail.open{display:table-row}
.vv-detail td{padding:0;background:#fafbfc}
.vv-detail-inner{padding:6px 14px 14px 40px}
.vv-cuotas-table{width:100%;border-collapse:collapse;font-size:12px}
.vv-cuotas-table th{text-align:left;font-size:10px;color:#9ca3af;padding:5px 8px;border-bottom:1px solid #eaf1f6}
.vv-cuotas-table td{padding:5px 8px;border-bottom:1px solid #f3f4f6}
.vv-cuotas-table td.num{text-align:right;font-variant-numeric:tabular-nums}
.vv-estado-pendiente{color:#a16207}
.vv-estado-demandada{color:#b91c1c;font-weight:600}
.vv-estado-entrega{color:#15803d;font-weight:600}
.vv-saldo-entrega{color:#15803d}
.vv-btn-entrega{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:5px;border:1px solid #dce6ee;color:#7e93a1;background:#fff;cursor:pointer;flex-shrink:0;font-size:11px}
.vv-btn-entrega:hover{background:#fff7ed;border-color:#fdba74;color:#c2410c}
.ec-modal-card{position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 45px -12px rgba(15,23,42,.35);width:100%}
.ec-input{width:100%;font-size:13px;border:1px solid #dce6ee;border-radius:8px;padding:8px 10px}
.ec-label{display:block;font-size:11px;color:#7e93a1;margin-bottom:4px}
</style>

@php
    $sortLink = fn ($field) => request()->fullUrlWithQuery(['sort' => $field, 'dir' => ($sortField === $field && $sortDir === 'desc') ? 'asc' : 'desc']);
    $arrow = fn ($field) => $sortField === $field ? ('<span class="vv-sort-arrow">' . ($sortDir === 'asc' ? '▲' : '▼') . '</span>') : '';
@endphp

<a href="{{ request()->fullUrlWithQuery(['stat_a_demandar' => $statADemandarActivo ? null : 1, 'page' => null]) }}"
   class="vv-stat" style="background:{{ $statADemandarActivo ? '#fecaca' : '#fef2f2' }};border-color:#fca5a5;margin-right:8px">
    <span class="vv-stat-num" style="color:#b91c1c">{{ $totalADemandar }}</span>
    <span class="vv-stat-label" style="color:#991b1b">viviendas a demandar — {{ number_format($importeADemandar, 2, ',', '.') }} €</span>
</a>

<a href="{{ request()->fullUrlWithQuery(['stat_adheridas' => $statAdheridasActivo ? null : 1, 'page' => null]) }}"
   class="vv-stat" style="background:{{ $statAdheridasActivo ? '#bfdbfe' : '#eff6ff' }};border-color:#93c5fd">
    <span class="vv-stat-num" style="color:#1d4ed8">{{ $totalAdheridas }}</span>
    <span class="vv-stat-label" style="color:#1e40af">Adheridas</span>
</a>

<div style="overflow-x:auto">
<table class="vv-table">
  <thead>
    <tr>
      <th><a href="{{ $sortLink('nombre') }}">Vivienda {!! $arrow('nombre') !!}</a></th>
      <th><a href="{{ $sortLink('ultimo_propietario') }}">Último propietario {!! $arrow('ultimo_propietario') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('deuda_acumulada') }}">Deuda acumulada {!! $arrow('deuda_acumulada') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('cuotas_ptes') }}">Cuotas ptes. {!! $arrow('cuotas_ptes') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('cuotas_demandadas') }}">Cuotas demandadas {!! $arrow('cuotas_demandadas') !!}</a></th>
      <th><a href="{{ $sortLink('a_demandar') }}">A demandar {!! $arrow('a_demandar') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('voto') }}">VOTO {!! $arrow('voto') !!}</a></th>
      <th style="width:36px"></th>
    </tr>
  </thead>
  <tbody>
    @forelse($viviendas as $v)
    @php
      $cuotas = $cuotasPorVivienda->get($v->id) ?? collect();
      $entregas = $entregasPorVivienda->get($v->id) ?? collect();
    @endphp
    <tr class="vv-row" data-toggle="vv-detail-{{ $v->id }}">
      <td>
        <span class="vv-toggle">
          <svg class="vv-chevron" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l6 6-6 6"/></svg>
          <a class="vv-nombre" href="{{ route('ficha', [$project->slug, 'viviendas', $v->id]) }}" onclick="event.stopPropagation()">{{ $v->nombre }}</a>
        </span>
      </td>
      <td>{{ $v->ultimo_propietario ?? '—' }}</td>
      <td class="num">{{ number_format($v->deuda_acumulada, 2, ',', '.') }} €</td>
      <td class="num">{{ $v->cuotas_ptes }}</td>
      <td class="num">{{ $v->cuotas_demandadas }}</td>
      <td>
        @if($v->a_demandar)
          <span class="vv-badge vv-badge-si">Sí</span>
        @else
          <span class="vv-badge vv-badge-no">No</span>
        @endif
      </td>
      <td class="num">{{ $v->voto ?? '—' }}</td>
      <td style="text-align:right" onclick="event.stopPropagation()">
        <button type="button" class="vv-btn-entrega" title="Registrar entrega a cuenta"
                onclick="abrirModalEntrega({{ $v->id }}, {{ Illuminate\Support\Js::from($v->nombre) }})">
          <i class="fa-solid fa-wallet"></i>
        </button>
      </td>
    </tr>
    <tr class="vv-detail" id="vv-detail-{{ $v->id }}">
      <td colspan="8">
        <div class="vv-detail-inner">
          @if($cuotas->isEmpty() && $entregas->isEmpty())
            <span style="color:#9ca3af;font-size:12px">Sin cuotas pendientes ni demandadas, ni entregas a cuenta con saldo.</span>
          @else
          <table class="vv-cuotas-table">
            <thead><tr><th>Ejercicio / Tipo</th><th>Fecha emisión</th><th>Estado</th><th class="num">Importe</th><th class="num">Pendiente</th><th style="width:28px"></th></tr></thead>
            <tbody>
              @foreach($cuotas as $c)
              <tr>
                <td>{{ $c->ejercicio }} {{ $c->tipo_cuota }}</td>
                <td>{{ $c->fecha_emision }}</td>
                <td class="{{ $c->estado === 'Demandada' ? 'vv-estado-demandada' : 'vv-estado-pendiente' }}">{{ $c->estado }}</td>
                <td class="num">{{ number_format($c->importe, 2, ',', '.') }} €</td>
                <td class="num">{{ number_format($c->pendiente, 2, ',', '.') }} €</td>
                <td></td>
              </tr>
              @endforeach
              @foreach($entregas as $e)
              <tr>
                <td>{{ $e->concepto ?: 'Entrega a cuenta' }}</td>
                <td>{{ $e->fecha }}</td>
                <td class="vv-estado-entrega">Entrega a cuenta</td>
                <td class="num">{{ number_format($e->importe, 2, ',', '.') }} €</td>
                <td class="num vv-saldo-entrega">+{{ number_format($e->importe - $e->importe_aplicado, 2, ',', '.') }} €</td>
                <td style="text-align:right">
                  <button type="button" class="vv-btn-entrega" title="Compensar saldo con cuotas pendientes"
                          onclick="abrirCompensacionExistente({{ $e->id }})">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @endif
        </div>
      </td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:24px">No hay viviendas que coincidan con la búsqueda.</td></tr>
    @endforelse
  </tbody>
</table>
</div>

<p style="font-size:11.5px;color:#9ca3af;margin-top:10px">{{ $viviendas->count() }} viviendas.</p>

<script>
document.querySelectorAll('.vv-row[data-toggle]').forEach(row => {
  row.addEventListener('click', () => {
    const detail = document.getElementById(row.dataset.toggle);
    const chevron = row.querySelector('.vv-chevron');
    detail.classList.toggle('open');
    chevron.classList.toggle('open');
  });
});
</script>

{{-- Modal 1: registrar entrega a cuenta --}}
<div id="ec-modal-1" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="cerrarModalEntrega()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="ec-modal-card" style="max-width:420px">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-base font-semibold text-gray-800">Registrar entrega a cuenta</h3>
        <p class="text-xs text-gray-400 mt-0.5" id="ec-vivienda-nombre"></p>
      </div>
      <form id="ec-form" class="px-5 py-4 space-y-3">
        <div>
          <label class="ec-label">Fecha</label>
          <input type="date" id="ec-fecha" required class="ec-input">
        </div>
        <div>
          <label class="ec-label">Importe</label>
          <input type="number" step="0.01" min="0.01" id="ec-importe" required class="ec-input">
        </div>
        <div>
          <label class="ec-label">Concepto</label>
          <input type="text" id="ec-concepto" maxlength="255" class="ec-input">
        </div>
        <div>
          <label class="ec-label">Forma de pago</label>
          <select id="ec-forma-pago" class="ec-input">
            <option value="">—</option>
            <option value="Por banco">Por banco</option>
            <option value="Despacho">Despacho</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-1">
          <button type="button" onclick="cerrarModalEntrega()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancelar</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-orange-500 hover:bg-orange-600">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal 2: compensar movimientos pendientes/demandados con el saldo de la entrega --}}
<div id="ec-modal-2" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="cerrarModalCompensacion()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="ec-modal-card" style="max-width:680px;max-height:85vh;display:flex;flex-direction:column">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-base font-semibold text-gray-800">Compensar movimientos pendientes</h3>
        <p class="text-xs text-gray-400 mt-0.5">Saldo disponible: <span id="ec-saldo-restante" class="font-semibold text-gray-700"></span></p>
      </div>
      <div class="px-5 py-4 overflow-y-auto" style="flex:1">
        <table style="width:100%;font-size:12.5px;border-collapse:collapse">
          <thead>
            <tr style="text-align:left;color:#7e93a1;font-size:10.5px;text-transform:uppercase">
              <th style="padding:6px 8px"></th>
              <th style="padding:6px 8px">Ejercicio / Tipo</th>
              <th style="padding:6px 8px">Fecha</th>
              <th style="padding:6px 8px">Estado</th>
              <th style="padding:6px 8px;text-align:right">Pendiente</th>
              <th style="padding:6px 8px;text-align:right">Importe a pagar</th>
            </tr>
          </thead>
          <tbody id="ec-movimientos-body"></tbody>
        </table>
      </div>
      <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
        <button type="button" onclick="cerrarModalCompensacion()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cerrar sin compensar</button>
        <button type="button" onclick="confirmarCompensacion()" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-orange-500 hover:bg-orange-600">Aplicar compensación</button>
      </div>
    </div>
  </div>
</div>

<script>
const ROUTE_ENTREGA_STORE_TPL      = @json(route('mb.entregas-cuenta.store', [$project->slug, '__VIVIENDA__']));
const ROUTE_ENTREGA_APLICAR_TPL    = @json(route('mb.entregas-cuenta.aplicar', [$project->slug, '__ENTREGA__']));
const ROUTE_ENTREGA_PENDIENTES_TPL = @json(route('mb.entregas-cuenta.pendientes', [$project->slug, '__ENTREGA__']));
const CSRF_EC = @json(csrf_token());

let ecViviendaId    = null;
let ecEntregaId     = null;
let ecMovimientos   = [];
let ecSaldoOriginal = 0;

function abrirModalEntrega(idVivienda, nombre) {
  ecViviendaId = idVivienda;
  document.getElementById('ec-vivienda-nombre').textContent = nombre;
  document.getElementById('ec-form').reset();
  document.getElementById('ec-fecha').value = new Date().toISOString().slice(0, 10);
  document.getElementById('ec-modal-1').classList.remove('hidden');
}
function cerrarModalEntrega() {
  document.getElementById('ec-modal-1').classList.add('hidden');
}
function cerrarModalCompensacion() {
  document.getElementById('ec-modal-2').classList.add('hidden');
  window.location.reload();
}

document.getElementById('ec-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;

  const body = {
    fecha: document.getElementById('ec-fecha').value,
    importe: parseFloat(document.getElementById('ec-importe').value),
    concepto: document.getElementById('ec-concepto').value,
    forma_pago: document.getElementById('ec-forma-pago').value,
  };

  let res;
  try {
    res = await fetch(ROUTE_ENTREGA_STORE_TPL.replace('__VIVIENDA__', ecViviendaId), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_EC, 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
  } finally {
    btn.disabled = false;
  }

  if (!res.ok) { alert('Error al registrar la entrega.'); return; }
  const data = await res.json();

  cerrarModalEntrega();
  mostrarCompensacion(data);
});

function mostrarCompensacion(data) {
  if (!data.requiere_compensacion) {
    alert(data.mensaje);
    window.location.reload();
    return;
  }

  ecEntregaId     = data.id_entrega;
  ecSaldoOriginal = data.saldo;
  ecMovimientos   = data.movimientos.map(m => ({ ...m, activo: false, importePagado: 0 }));
  renderCompensacion();
  document.getElementById('ec-modal-2').classList.remove('hidden');
}

async function abrirCompensacionExistente(idEntrega) {
  const res = await fetch(ROUTE_ENTREGA_PENDIENTES_TPL.replace('__ENTREGA__', idEntrega), {
    headers: { 'Accept': 'application/json' },
  });
  if (!res.ok) { alert('Error al consultar los movimientos pendientes.'); return; }
  const data = await res.json();
  mostrarCompensacion(data);
}

function fmtEuro(n) {
  return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function renderCompensacion() {
  const usado = ecMovimientos.reduce((acc, m) => acc + (m.activo ? m.importePagado : 0), 0);
  document.getElementById('ec-saldo-restante').textContent = fmtEuro(ecSaldoOriginal - usado);

  const tbody = document.getElementById('ec-movimientos-body');
  tbody.innerHTML = '';
  ecMovimientos.forEach((m, i) => {
    const maximoFila = Math.min(m.pendiente, disponibleParaFila(i));
    const tr = document.createElement('tr');
    tr.style.background = m.a_demandar ? '#fef2f2' : '';
    tr.innerHTML = `
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6"><input type="checkbox" ${m.activo ? 'checked' : ''} onchange="toggleMovimiento(${i}, this.checked)"></td>
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6">${m.ejercicio ?? ''} ${m.tipo_cuota ?? ''}${m.a_demandar ? ' <span style="color:#b91c1c;font-weight:700;font-size:10px">&#9888; A demandar</span>' : ''}</td>
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6">${m.fecha_emision}</td>
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6">${m.estado}</td>
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right">${fmtEuro(m.pendiente)}</td>
      <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;text-align:right">
        <input type="number" step="0.01" min="0" max="${maximoFila.toFixed(2)}" id="ec-importe-${i}"
               style="width:90px;text-align:right;border:1px solid #dce6ee;border-radius:6px;padding:3px 6px"
               value="${m.importePagado.toFixed(2)}" ${m.activo ? '' : 'disabled'}
               oninput="limitarImporteEnVivo(${i}, this)"
               onchange="cambiarImporte(${i}, this)">
        <div style="font-size:10px;color:#9ca3af;margin-top:2px">máx. ${fmtEuro(maximoFila)}</div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function disponibleParaFila(i) {
  const usadoPorOtros = ecMovimientos.reduce((acc, mm, j) => j === i ? acc : acc + (mm.activo ? mm.importePagado : 0), 0);
  return Math.max(0, ecSaldoOriginal - usadoPorOtros);
}

function toggleMovimiento(i, activo) {
  const m = ecMovimientos[i];
  m.activo = activo;
  m.importePagado = activo ? Math.min(m.pendiente, disponibleParaFila(i)) : 0;
  renderCompensacion();
}

// Recorta en tiempo real mientras se escribe, sin reconstruir toda la tabla (para no perder el foco).
function limitarImporteEnVivo(i, input) {
  const m = ecMovimientos[i];
  const maximo = Math.min(m.pendiente, disponibleParaFila(i));
  let val = parseFloat(input.value);
  if (isNaN(val)) val = 0;
  if (val > maximo) { val = maximo; input.value = val.toFixed(2); }
  if (val < 0) { val = 0; input.value = '0.00'; }
  m.importePagado = Math.round(val * 100) / 100;

  const usado = ecMovimientos.reduce((acc, mm) => acc + (mm.activo ? mm.importePagado : 0), 0);
  document.getElementById('ec-saldo-restante').textContent = fmtEuro(ecSaldoOriginal - usado);
}

// Al salir del campo, recalcula toda la tabla (por si el cambio afecta al máximo disponible de otras filas).
function cambiarImporte(i, input) {
  const m = ecMovimientos[i];
  const maximo = Math.min(m.pendiente, disponibleParaFila(i));
  let val = parseFloat(input.value) || 0;
  if (val > maximo) val = maximo;
  if (val < 0) val = 0;
  m.importePagado = Math.round(val * 100) / 100;
  renderCompensacion();
}

async function confirmarCompensacion() {
  const aplicaciones = ecMovimientos.filter(m => m.activo && m.importePagado > 0).map(m => ({ id_cuota: m.id, importe: m.importePagado }));
  if (aplicaciones.length === 0) { cerrarModalCompensacion(); return; }

  const res = await fetch(ROUTE_ENTREGA_APLICAR_TPL.replace('__ENTREGA__', ecEntregaId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_EC, 'Accept': 'application/json' },
    body: JSON.stringify({ aplicaciones }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: 'Error al aplicar la compensación.' }));
    alert(err.message || 'Error al aplicar la compensación.');
    return;
  }

  alert('Compensación aplicada correctamente.');
  window.location.reload();
}
</script>

</x-app-layout>
