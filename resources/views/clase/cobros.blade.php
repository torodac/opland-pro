<x-app-layout :project="$project" :breadcrumb="[['label'=>'Cobros','url'=>'']]">

<style>
#cl-cesta-fab  { position:fixed; bottom:24px; right:24px; }
#cl-cesta-badge { position:absolute; top:-4px; right:-4px; }
.cl-forma-btn.sel { background:#f97316; color:#fff; border-color:#f97316; }
</style>

@php
function fmtC(float $v): string {
    return number_format($v, 2, ',', '.') . ' €';
}
@endphp

{{-- Navegador mes/año --}}
@php
  $mesCarbon = \Carbon\Carbon::parse($mes.'-01');
  $mesPrev   = $mesCarbon->copy()->subMonth()->format('Y-m');
  $mesNext   = $mesCarbon->copy()->addMonth()->format('Y-m');
  $esFuturo  = $mesCarbon->copy()->addMonth()->isAfter(now()->startOfMonth());
@endphp
<div class="flex items-center gap-2 mb-5 select-none">
  <a href="{{ request()->fullUrlWithQuery(['mes'=>$mesPrev,'page'=>null]) }}"
     class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors no-underline">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
  </a>
  <div class="flex items-center gap-2 px-5 h-9 bg-white border border-gray-200 rounded-xl min-w-[210px] justify-center">
    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
    </svg>
    <span class="text-sm font-semibold text-gray-900 capitalize">{{ $mesCarbon->translatedFormat('F Y') }}</span>
    @if(!$meses->contains($mes))
    <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold">nuevo</span>
    @endif
  </div>
  <a href="{{ !$esFuturo ? request()->fullUrlWithQuery(['mes'=>$mesNext,'page'=>null]) : '#' }}"
     class="flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 bg-white transition-colors no-underline
            {{ $esFuturo ? 'text-gray-200 pointer-events-none' : 'text-gray-400 hover:bg-gray-50 hover:text-gray-600' }}"
     title="{{ $esFuturo ? 'No hay datos futuros' : 'Mes siguiente' }}">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </a>
</div>

{{-- Stats --}}
<div class="flex flex-wrap gap-3 mb-4">
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 min-w-[100px]">
    <div class="text-lg font-bold text-gray-900">{{ $stats['total'] }}</div>
    <div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mt-0.5">Total cobros</div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 min-w-[100px]">
    <div class="text-lg font-bold text-amber-500">{{ $stats['pendiente'] }}</div>
    <div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mt-0.5">Pendientes</div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 min-w-[100px]">
    <div class="text-lg font-bold text-emerald-500">{{ $stats['cobrado'] }}</div>
    <div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mt-0.5">Cobrados</div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 min-w-[120px]">
    <div class="text-lg font-bold text-gray-900">{{ fmtC($stats['importe']) }}</div>
    <div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mt-0.5">Importe total</div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 min-w-[120px]">
    <div class="text-lg font-bold text-emerald-500">{{ fmtC($stats['cobrado_imp']) }}</div>
    <div class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mt-0.5">Cobrado</div>
  </div>
</div>

{{-- Toolbar --}}
<div class="flex flex-wrap gap-2 items-center mb-4">
  <form method="GET" action="" class="flex-1 min-w-[160px] max-w-xs" id="cl-search-form">
    <input type="hidden" name="mes" value="{{ $mes }}">
    <input type="text" name="q" value="{{ $busca }}" placeholder="Buscar alumno o pagador…"
           autocomplete="off" id="cl-q"
           class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-orange-400">
  </form>

  <a href="{{ request()->fullUrlWithQuery(['solo'=>$solo==='pendientes'?null:'pendientes','page'=>null]) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 border text-sm font-medium rounded-lg transition-colors {{ $solo==='pendientes' ? 'bg-orange-500 hover:bg-orange-600 text-white border-orange-500' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
    Solo pendientes
  </a>

  <a href="{{ route('clase.recibos', $project->slug) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
    </svg>
    Recibos
  </a>

  <button id="cl-generar-btn" onclick="generarCobros()"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
    </svg>
    Generar cobros {{ \Carbon\Carbon::parse($mes.'-01')->translatedFormat('M Y') }}
  </button>
</div>

{{-- Tabla --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-xs">
    <thead>
      <tr class="border-b border-gray-200 bg-gray-50">
        <th class="px-4 py-3 w-8">
          <input type="checkbox" class="w-3.5 h-3.5 cursor-pointer accent-orange-500" id="cl-sel-all" title="Seleccionar todos">
        </th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Alumno</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Pagador</th>
        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Importe</th>
        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Total ctto.</th>
        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">%</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Estado</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100" id="cl-tbody">
    @forelse($cobros as $c)
      @php $esCobrado = $c->estado === 'Cobrado'; @endphp
      <tr class="hover:bg-gray-50 cursor-pointer {{ $esCobrado ? 'opacity-60' : '' }}"
          data-id="{{ $c->id }}" data-pagador="{{ e($c->nombre_pagador) }}" data-imp="{{ $c->cantidad_pagador }}"
          onclick="rowClick(event, '{{ route('clase.cobros.ficha', [$project->slug, $c->id]) }}')">
        <td class="px-4 py-3">
          @if(!$esCobrado)
          <input type="checkbox" class="w-3.5 h-3.5 cursor-pointer accent-orange-500 cl-row-check"
                 value="{{ $c->id }}" data-pagador="{{ e($c->nombre_pagador) }}" data-imp="{{ $c->cantidad_pagador }}">
          @endif
        </td>
        <td class="px-4 py-3 text-gray-700">
          {{ $c->alumno_nombre }}
          @if($c->contrato_modificado)
          <span class="inline-block px-1 py-0.5 rounded text-[9px] font-bold bg-red-100 text-red-700 ml-1"
                title="El contrato ha cambiado desde que se generó este cobro">!</span>
          @endif
        </td>
        <td class="px-4 py-3 text-gray-700">{{ $c->pagador_nombre }}</td>
        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ fmtC((float)$c->cantidad_pagador) }}</td>
        <td class="px-4 py-3 text-right text-gray-400">{{ fmtC((float)$c->cantidad_total) }}</td>
        <td class="px-4 py-3 text-right text-gray-400">{{ number_format((float)$c->porcentaje,0) }}%</td>
        <td class="px-4 py-3">
          @php $bs = match($c->estado) { 'Cobrado'=>'cobr','Anulado'=>'anul', default=>'pend' }; @endphp
          @if($bs === 'cobr')
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700">{{ $c->estado }}</span>
          @elseif($bs === 'pend')
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700">{{ $c->estado }}</span>
          @else
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700">{{ $c->estado }}</span>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin cobros para este período</td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>

{{-- Cesta flotante --}}
<button id="cl-cesta-fab" onclick="abrirCesta()" style="display:none"
        class="z-[200] bg-orange-500 hover:bg-orange-600 text-white rounded-full w-14 h-14 text-2xl shadow-lg shadow-orange-200 flex items-center justify-center transition-all hover:scale-105"
        title="Cesta de cobros">
  🛒
  <span id="cl-cesta-badge"
        class="bg-red-500 text-white rounded-full w-5 h-5 text-[10px] font-bold flex items-center justify-center">0</span>
</button>

{{-- Modal cesta --}}
<div id="cl-overlay" class="hidden fixed inset-0 bg-black/40 z-[300] items-center justify-center p-4"
     onclick="if(event.target===this)cerrarCesta()">
  <div class="bg-white rounded-xl w-full max-w-md shadow-xl">
    <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
      <h2 class="text-sm font-bold text-gray-900">Confirmar cobros</h2>
      <button class="text-gray-400 hover:text-gray-600 text-sm border border-gray-200 rounded-lg px-3 py-1 transition-colors" onclick="cerrarCesta()">✕</button>
    </div>
    <div class="px-5 py-4">
      <p class="text-xs text-gray-500 mb-3">Pagador: <strong id="cl-cesta-pagador" class="text-gray-800">—</strong></p>
      <div id="cl-cesta-lista" class="text-xs mb-4"></div>
      <div class="flex justify-between font-bold text-sm py-2 border-t border-gray-200">
        <span>Total</span><span id="cl-cesta-total">0,00 €</span>
      </div>
      <div class="mt-4">
        <div class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-2">Forma de cobro</div>
        <div class="flex flex-wrap gap-2" id="cl-formas-wrap">
          @foreach($formasPago as $fp)
          <button class="cl-forma-btn px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-semibold cursor-pointer transition-colors hover:bg-gray-50"
                  data-valor="{{ $fp->nombre }}" onclick="selForma(this)">{{ $fp->nombre }}</button>
          @endforeach
        </div>
        <div id="cl-formas-importes" class="mt-3"></div>
      </div>
    </div>
    <div class="px-5 py-3 border-t border-gray-200 flex justify-end gap-2">
      <button onclick="cerrarCesta()"
              class="inline-flex items-center px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        Cancelar
      </button>
      <button id="cl-confirmar-btn" onclick="confirmarCesta()" disabled
              class="inline-flex items-center px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
        Confirmar cobro
      </button>
    </div>
  </div>
</div>

<script>
function rowClick(event, url) {
    if (event.target.closest('input[type=checkbox],button,a')) return;
    window.location = url;
}

const GENERAR_URL = "{{ route('clase.cobros.generar', $project->slug) }}";
const COBRAR_URL   = "{{ route('clase.cobros.lote',    $project->slug) }}";
const RECIBOS_URL  = "{{ route('clase.recibos',        $project->slug) }}";
const TOKEN       = "{{ csrf_token() }}";

// ── Búsqueda con debounce ──────────────────────────────────────────────────
let searchTimer;
document.getElementById('cl-q').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.getElementById('cl-search-form').submit(), 400);
});

// ── Selección y cesta ──────────────────────────────────────────────────────
const cesta = new Map(); // id → {pagador, imp}
let pagadorCesta = null;

function fmt(v) {
    return new Intl.NumberFormat('es-ES',{minimumFractionDigits:2}).format(v) + ' €';
}

function actualizarFab() {
    const fab   = document.getElementById('cl-cesta-fab');
    const badge = document.getElementById('cl-cesta-badge');
    if (cesta.size > 0) {
        fab.style.display = 'flex';
        badge.textContent = cesta.size;
    } else {
        fab.style.display = 'none';
        pagadorCesta = null;
    }
}

document.getElementById('cl-sel-all').addEventListener('change', function() {
    document.querySelectorAll('.cl-row-check').forEach(cb => {
        const p = cb.dataset.pagador;
        if (!pagadorCesta || pagadorCesta === p) {
            cb.checked = this.checked;
            if (this.checked) {
                cesta.set(parseInt(cb.value), {pagador: p, imp: parseFloat(cb.dataset.imp)});
                pagadorCesta = p;
            } else {
                cesta.delete(parseInt(cb.value));
            }
        }
    });
    if (!this.checked) pagadorCesta = cesta.size > 0 ? [...cesta.values()][0].pagador : null;
    actualizarFab();
});

document.getElementById('cl-tbody').addEventListener('change', function(e) {
    const cb = e.target.closest('.cl-row-check');
    if (!cb) return;
    const id  = parseInt(cb.value);
    const p   = cb.dataset.pagador;
    const imp = parseFloat(cb.dataset.imp);

    if (cb.checked) {
        if (pagadorCesta && pagadorCesta !== p) {
            cb.checked = false;
            alert('Solo puedes añadir cobros del mismo pagador a la cesta.\nPagador actual: ' + pagadorCesta);
            return;
        }
        cesta.set(id, {pagador: p, imp});
        pagadorCesta = p;
    } else {
        cesta.delete(id);
        if (cesta.size === 0) pagadorCesta = null;
    }
    actualizarFab();
});

// ── Modal cesta ────────────────────────────────────────────────────────────
function abrirCesta() {
    if (cesta.size === 0) return;
    document.getElementById('cl-cesta-pagador').textContent = pagadorCesta || '—';
    let html = '<table style="width:100%;border-collapse:collapse">';
    let total = 0;
    cesta.forEach(({pagador, imp}, id) => {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const alumno = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'Alumno #' + id;
        html += `<tr><td style="padding:4px 0;font-size:12px">${alumno}</td><td style="text-align:right;font-weight:600;font-size:12px">${fmt(imp)}</td></tr>`;
        total += imp;
    });
    html += '</table>';
    document.getElementById('cl-cesta-lista').innerHTML = html;
    document.getElementById('cl-cesta-total').textContent = fmt(total);
    totalCesta = total;
    formasSeleccionadas = new Map();
    document.querySelectorAll('.cl-forma-btn').forEach(b => b.classList.remove('sel'));
    document.getElementById('cl-formas-importes').innerHTML = '';
    document.getElementById('cl-confirmar-btn').disabled = true;
    document.getElementById('cl-overlay').classList.remove('hidden');
    document.getElementById('cl-overlay').classList.add('flex');
}

function cerrarCesta() {
    document.getElementById('cl-overlay').classList.remove('flex');
    document.getElementById('cl-overlay').classList.add('hidden');
}

let formasSeleccionadas = new Map();
let totalCesta = 0;

function selForma(btn) {
    const valor = btn.dataset.valor;
    if (formasSeleccionadas.has(valor)) {
        formasSeleccionadas.delete(valor);
        btn.classList.remove('sel');
    } else {
        formasSeleccionadas.set(valor, 0);
        btn.classList.add('sel');
    }
    actualizarImportesForma();
}

function actualizarImportesForma() {
    const wrap = document.getElementById('cl-formas-importes');
    const n = formasSeleccionadas.size;
    if (n === 0) { wrap.innerHTML = ''; document.getElementById('cl-confirmar-btn').disabled = true; return; }
    let html = '<div style="display:flex;flex-direction:column;gap:8px">';
    formasSeleccionadas.forEach((imp, forma) => {
        const autoVal = n === 1 ? totalCesta.toFixed(2) : (imp > 0 ? imp.toFixed(2) : '');
        html += `<div style="display:flex;align-items:center;gap:10px">`
              + `<span style="flex:1;font-size:12px;font-weight:600">${forma}</span>`
              + `<input type="number" class="cl-forma-imp" data-forma="${forma}" value="${autoVal}"`
              + ` min="0.01" step="0.01" oninput="validarFormaImportes()"`
              + ` style="width:110px;text-align:right;border:1px solid #e5e7eb;`
              + `border-radius:6px;padding:5px 8px;font-size:13px;">`
              + `<span style="font-size:11px;color:#64748b">€</span></div>`;
    });
    if (n > 1) {
        html += `<div style="display:flex;justify-content:space-between;font-size:11px;`
               + `color:#64748b;border-top:1px solid #e5e7eb;padding-top:6px">`
               + `<span>Total: <strong>${fmt(totalCesta)}</strong></span>`
               + `<span id="cl-forma-diff"></span></div>`;
    }
    html += '</div>';
    wrap.innerHTML = html;
    if (n === 1) {
        formasSeleccionadas.set([...formasSeleccionadas.keys()][0], totalCesta);
        document.getElementById('cl-confirmar-btn').disabled = false;
    } else { validarFormaImportes(); }
}

function validarFormaImportes() {
    let suma = 0, ok = true;
    document.querySelectorAll('.cl-forma-imp').forEach(inp => {
        const v = parseFloat(inp.value) || 0;
        formasSeleccionadas.set(inp.dataset.forma, v);
        suma += v;
        if (v <= 0) ok = false;
    });
    const n = formasSeleccionadas.size;
    if (n > 1) {
        const diff = totalCesta - suma;
        const el = document.getElementById('cl-forma-diff');
        if (el) {
            if (Math.abs(diff) < 0.01) { el.textContent = '✓ Cuadra'; el.style.color = '#10b981'; }
            else { el.textContent = 'Pendiente: ' + fmt(diff); el.style.color = diff > 0 ? '#f59e0b' : '#ef4444'; ok = false; }
        }
        ok = ok && Math.abs(suma - totalCesta) < 0.01;
    }
    document.getElementById('cl-confirmar-btn').disabled = !ok;
}

async function confirmarCesta() {
    if (formasSeleccionadas.size === 0) return;
    document.getElementById('cl-confirmar-btn').disabled = true;
    const ids = [...cesta.keys()];
    const formas_pago = [...formasSeleccionadas.entries()].map(([forma, importe]) => ({forma, importe}));
    const res = await fetch(COBRAR_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify({ids, formas_pago})
    });
    const data = await res.json();
    if (data.ok) {
        cerrarCesta();
        cesta.clear(); pagadorCesta = null; actualizarFab();
        if (data.numero_recibo) {
            const n = document.createElement('div');
            n.innerHTML = `<strong>${data.numero_recibo}</strong> generado — `
                        + `<a href="${RECIBOS_URL}" style="color:#f97316">Ver recibos</a>`;
            n.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);'
                            + 'background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 18px;'
                            + 'font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:999;white-space:nowrap;';
            document.body.appendChild(n);
            setTimeout(() => { n.remove(); location.reload(); }, 2500);
        } else { location.reload(); }
    } else {
        document.getElementById('cl-confirmar-btn').disabled = false;
        alert(data.error || 'Error al procesar');
    }
}

// ── Generar cobros ─────────────────────────────────────────────────────────
async function generarCobros() {
    const btn = document.getElementById('cl-generar-btn');
    btn.disabled = true; btn.textContent = 'Generando…';
    const res  = await fetch(GENERAR_URL, {method:'POST', headers:{'X-CSRF-TOKEN':TOKEN}});
    const data = await res.json();
    btn.disabled = false;
    btn.innerHTML = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Generar cobros';
    if (data.ok) {
        alert(`Generados: ${data.generados}  |  Ya existían: ${data.ignorados}`);
        if (data.generados > 0) location.reload();
    }
}
</script>

</x-app-layout>
