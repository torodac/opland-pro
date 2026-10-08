@php
  $esCobrado   = $cobro->estado_cobros === 'Cobrado';
  $isPendiente = $cobro->estado_cobros === 'Pendiente';
  $mesCarbon   = \Carbon\Carbon::parse($cobro->fecha_cobro);
  $mesLabel    = $mesCarbon->translatedFormat('F Y');
@endphp
<x-app-layout :project="$project" :breadcrumb="[['label'=>'Cobros','url'=>route('clase.cobros',$project->slug)],['label'=>$cobro->alumno_nombre_real,'url'=>'']]">

<div class="flex items-center justify-between mb-6">
  <div class="flex items-center gap-3">
    <div>
      <h1 class="text-base font-bold text-gray-900">{{ $cobro->alumno_nombre_real }}</h1>
      <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ $mesLabel }}</p>
    </div>
    @php $bs = match($cobro->estado_cobros) { 'Cobrado'=>'cobr','Anulado'=>'anul', default=>'pend' }; @endphp
    @if($bs==='cobr')
      <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700">Cobrado</span>
    @elseif($bs==='pend')
      <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700">Pendiente</span>
    @else
      <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700">Anulado</span>
    @endif
  </div>
  <div class="flex items-center gap-2">
    <button type="button" id="btn-editar" onclick="toggleEditar()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
      </svg>
      Editar
    </button>
    <a href="{{ route('clase.cobros', $project->slug) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
      &larr; Cobros
    </a>
  </div>
</div>

<div class="grid gap-5 max-w-2xl">

  {{-- Datos del cobro --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-gray-800">Datos del cobro</h2>
      {{-- Botón guardar (solo visible en modo edición) --}}
      <button type="button" id="btn-guardar-datos" onclick="guardarDatos()"
              class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition-colors disabled:opacity-40">
        Guardar cambios
      </button>
    </div>

    {{-- Modo lectura --}}
    <dl id="modo-lectura" class="divide-y divide-gray-100">
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Alumno</dt>
        <dd class="col-span-2 text-sm text-gray-800">
          @if($cobro->alumno_id)
            <a href="{{ route('clase.alumnos_form', [$project->slug, $cobro->alumno_id]) }}"
               class="text-orange-500 hover:text-orange-600 font-medium">{{ $cobro->alumno_nombre_real }}</a>
          @else
            {{ $cobro->alumno_nombre_real }}
          @endif
        </dd>
      </div>
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Pagador</dt>
        <dd class="col-span-2 text-sm text-gray-800">
          @if($cobro->tutor_id)
            <a href="{{ route('clase.tutores.ficha', [$project->slug, $cobro->tutor_id]) }}"
               class="text-orange-500 hover:text-orange-600 font-medium">{{ $cobro->pagador_nombre_real }}</a>
          @else
            {{ $cobro->pagador_nombre_real }}
          @endif
        </dd>
      </div>
      @if($cobro->grupo_nombre)
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Grupo</dt>
        <dd class="col-span-2 text-sm text-gray-700">{{ $cobro->grupo_nombre }}</dd>
      </div>
      @endif
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Mes</dt>
        <dd class="col-span-2 text-sm text-gray-700 capitalize">{{ $mesLabel }}</dd>
      </div>
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Importe</dt>
        <dd class="col-span-2 text-sm font-bold text-gray-900">
          {{ number_format($cobro->cantidad_pagador, 2, ',', '.') }} €
          <span class="text-xs font-normal text-gray-400 ml-2">
            {{ number_format($cobro->porcentaje, 0) }}% de {{ number_format($cobro->cantidad_total, 2, ',', '.') }} €
          </span>
        </dd>
      </div>
      @if($esCobrado)
      <div class="px-5 py-3 grid grid-cols-3 gap-2">
        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Cobrado el</dt>
        <dd class="col-span-2 text-sm text-gray-700">
          {{ $cobro->fecha_cobrado ? \Carbon\Carbon::parse($cobro->fecha_cobrado)->format('d/m/Y') : '—' }}
          @if($cobro->forma_pago)
          <span class="ml-2 inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-600">{{ $cobro->forma_pago }}</span>
          @endif
        </dd>
      </div>
      @endif
      @if($cobro->contrato_modificado)
      <div class="px-5 py-3">
        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">
          El contrato ha cambiado desde que se generó este cobro. Los importes pueden no ser actuales.
        </p>
      </div>
      @endif
    </dl>

    {{-- Modo edición --}}
    <div id="modo-edicion" class="hidden p-5 grid gap-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre alumno</label>
          <input type="text" id="e-nombre-alumno" value="{{ $cobro->nombre_alumno ?? $cobro->alumno_nombre_real }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre pagador</label>
          <input type="text" id="e-nombre-pagador" value="{{ $cobro->nombre_pagador ?? $cobro->pagador_nombre_real }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Total contrato (€)</label>
          <input type="number" id="e-cantidad-total" value="{{ $cobro->cantidad_total }}" step="0.01" min="0"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400"
                 oninput="recalcularImporte()">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Porcentaje (%)</label>
          <input type="number" id="e-porcentaje" value="{{ $cobro->porcentaje }}" step="1" min="0" max="100"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400"
                 oninput="recalcularImporte()">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Importe pagador (€)</label>
          <input type="number" id="e-cantidad-pagador" value="{{ $cobro->cantidad_pagador }}" step="0.01" min="0"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400 bg-gray-50"
                 readonly>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Fecha cobro (mes)</label>
        <input type="month" id="e-fecha-cobro" value="{{ substr($cobro->fecha_cobro, 0, 7) }}"
               class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
      </div>
      @if(isset($cobro->notas))
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Notas</label>
        <textarea id="e-notas" rows="2"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400 resize-none">{{ $cobro->notas }}</textarea>
      </div>
      @endif
    </div>

  </div>

  {{-- Cambiar estado --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
      <h2 class="text-sm font-semibold text-gray-800">Estado</h2>
    </div>
    <div class="p-5">

      {{-- Forma de pago (visible al cobrar) --}}
      <div id="bloque-forma" class="{{ $isPendiente ? '' : 'hidden' }} mb-4">
        <label class="block text-xs font-semibold text-gray-500 mb-2">Forma de cobro</label>
        <div class="flex flex-wrap gap-2">
          @foreach($formasPago as $fp)
          <button type="button"
                  class="cl-forma-btn px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-semibold cursor-pointer transition-colors hover:bg-gray-50"
                  data-valor="{{ $fp }}" onclick="selForma(this)">{{ $fp }}</button>
          @endforeach
        </div>
      </div>

      {{-- Acciones --}}
      <div class="flex flex-wrap gap-2">
        @if($isPendiente)
        <button type="button" onclick="cambiarEstado('Cobrado')" id="btn-cobrar"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          Cobrar
        </button>
        @endif
        @if(!$esCobrado && $cobro->estado_cobros !== 'Anulado')
        <button type="button" onclick="cambiarEstado('Anulado')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
          Anular
        </button>
        @endif
        @if(!$isPendiente)
        <button type="button" onclick="cambiarEstado('Pendiente')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
          Marcar pendiente
        </button>
        @endif
      </div>

    </div>
  </div>

</div>

<script>
const TOKEN      = "{{ csrf_token() }}";
const URL_ESTADO = "{{ route('clase.cobros.estado', [$project->slug, $cobro->id]) }}";
const URL_UPDATE = "{{ route('clase.cobros.update', [$project->slug, $cobro->id]) }}";
const URL_LIST   = "{{ route('clase.cobros', [$project->slug, 'mes' => substr($cobro->fecha_cobro, 0, 7)]) }}";
let formaSeleccionada = null;
let modoEdicion = false;

function toggleEditar() {
    modoEdicion = !modoEdicion;
    document.getElementById('modo-lectura').classList.toggle('hidden', modoEdicion);
    document.getElementById('modo-edicion').classList.toggle('hidden', !modoEdicion);
    document.getElementById('btn-guardar-datos').classList.toggle('hidden', !modoEdicion);
    const btnEditar = document.getElementById('btn-editar');
    if (modoEdicion) {
        btnEditar.classList.add('bg-orange-50','text-orange-600','border-orange-200');
        btnEditar.classList.remove('border-gray-200','text-gray-600');
        btnEditar.querySelector('svg').style.stroke = '#f97316';
    } else {
        btnEditar.classList.remove('bg-orange-50','text-orange-600','border-orange-200');
        btnEditar.classList.add('border-gray-200','text-gray-600');
    }
}

function recalcularImporte() {
    const total = parseFloat(document.getElementById('e-cantidad-total').value) || 0;
    const pct   = parseFloat(document.getElementById('e-porcentaje').value)     || 0;
    document.getElementById('e-cantidad-pagador').value = (total * pct / 100).toFixed(2);
}

async function guardarDatos() {
    const btn = document.getElementById('btn-guardar-datos');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fechaInput = document.getElementById('e-fecha-cobro').value; // YYYY-MM
    const payload = {
        nombre_alumno:    document.getElementById('e-nombre-alumno').value.trim(),
        nombre_pagador:   document.getElementById('e-nombre-pagador').value.trim(),
        cantidad_total:   parseFloat(document.getElementById('e-cantidad-total').value)   || 0,
        porcentaje:       parseFloat(document.getElementById('e-porcentaje').value)        || 0,
        cantidad_pagador: parseFloat(document.getElementById('e-cantidad-pagador').value)  || 0,
        fecha_cobro:      fechaInput ? fechaInput + '-01' : null,
    };
    const notasEl = document.getElementById('e-notas');
    if (notasEl) payload.notas = notasEl.value.trim();

    const r = await fetch(URL_UPDATE, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.ok) {
        window.location.reload();
    } else {
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
        alert(data.error || 'Error al guardar');
    }
}

function selForma(btn) {
    document.querySelectorAll('.cl-forma-btn').forEach(b => {
        b.classList.remove('bg-orange-500','text-white','border-orange-500');
        b.classList.add('border-gray-200','bg-white','text-gray-700');
    });
    btn.classList.remove('border-gray-200','bg-white','text-gray-700');
    btn.classList.add('bg-orange-500','text-white','border-orange-500');
    formaSeleccionada = btn.dataset.valor;
    document.getElementById('btn-cobrar')?.removeAttribute('disabled');
}

async function cambiarEstado(estado) {
    if (estado === 'Cobrado' && !formaSeleccionada) {
        alert('Selecciona la forma de cobro');
        return;
    }
    if (estado === 'Anulado' && !confirm('¿Anular este cobro?')) return;
    const r = await fetch(URL_ESTADO, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify({ estado_cobros: estado, forma_pago: formaSeleccionada })
    });
    const data = await r.json();
    if (data.ok) window.location.reload();
    else alert(data.error || 'Error al actualizar');
}
</script>

</x-app-layout>
