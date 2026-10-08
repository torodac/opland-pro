<x-app-layout :project="$project" :breadcrumb="[['label'=>'Tutores','url'=>route('clase.tutores',$project->slug)],['label'=>$tutor->nombre,'url'=>'']]">

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-base font-bold text-gray-900">{{ $tutor->nombre }}</h1>
    <p class="text-xs text-gray-400 mt-0.5">{{ $tutor->tipo_tutor ?: 'Tutor' }}</p>
  </div>
  <a href="{{ route('clase.tutores', $project->slug) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
    &larr; Tutores
  </a>
</div>

<div class="grid gap-5 max-w-2xl">

  {{-- Datos personales --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
      <h2 class="text-sm font-semibold text-gray-800">Datos personales</h2>
    </div>
    <div class="p-5 grid gap-4">

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre completo</label>
          <input type="text" id="f-nombre" value="{{ $tutor->nombre }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo de tutor</label>
          <select id="f-tipo"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
            <option value="">— Seleccionar —</option>
            @foreach($tiposTutor as $tipo)
            <option value="{{ $tipo }}" {{ $tutor->tipo_tutor === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Teléfono</label>
          <input type="tel" id="f-telefono" value="{{ $tutor->telefono }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Email</label>
          <input type="email" id="f-email" value="{{ $tutor->email }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>
      </div>

    </div>
    <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-between">
      <button type="button" onclick="borrarTutor()"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>
        </svg>
        Borrar tutor
      </button>
      <button type="button" onclick="guardarDatos()" id="btn-guardar"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
        Guardar cambios
      </button>
    </div>
  </div>

  {{-- Alumnos vinculados --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
      <h2 class="text-sm font-semibold text-gray-800">Alumnos vinculados</h2>
    </div>
    @if($relaciones->isEmpty())
      <p class="px-5 py-4 text-sm text-gray-400">Sin alumnos vinculados.</p>
    @else
    <table class="w-full text-xs">
      <thead>
        <tr class="border-b border-gray-100">
          <th class="text-left px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Alumno</th>
          <th class="text-left px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Roles</th>
          <th class="text-right px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">% pago</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
      @foreach($relaciones as $rel)
        <tr class="hover:bg-gray-50">
          <td class="px-5 py-3">
            <a href="{{ route('clase.alumnos_form', [$project->slug, $rel->id_alumno]) }}"
               class="font-medium text-orange-500 hover:text-orange-600">
              {{ $rel->alumno_nombre }}
            </a>
          </td>
          <td class="px-5 py-3">
            <div class="flex flex-wrap gap-1">
              @if($rel->es_pagador)
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-50 text-orange-600">Pagador</span>
              @endif
              @if($rel->puede_recoger)
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-50 text-sky-600">Recoge</span>
              @endif
              @if($rel->recibe_comunicaciones)
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-violet-50 text-violet-600">Comunic.</span>
              @endif
            </div>
          </td>
          <td class="px-5 py-3 text-right text-gray-500">
            {{ $rel->es_pagador ? $rel->porcentaje_pago.'%' : '—' }}
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>

</div>

<script>
const TOKEN    = "{{ csrf_token() }}";
const URL_UPD  = "{{ route('clase.tutores.update_datos', [$project->slug, $tutor->id]) }}";
const URL_DEL  = "{{ route('clase.tutores.borrar', [$project->slug, $tutor->id]) }}";
const URL_LIST = "{{ route('clase.tutores', $project->slug) }}";

async function guardarDatos() {
    const btn = document.getElementById('btn-guardar');
    btn.disabled = true; btn.textContent = 'Guardando…';
    const payload = {
        nombre:     document.getElementById('f-nombre').value.trim(),
        tipo_tutor: document.getElementById('f-tipo').value,
        telefono:   document.getElementById('f-telefono').value.trim(),
        email:      document.getElementById('f-email').value.trim(),
    };
    const r = await fetch(URL_UPD, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.ok) {
        btn.textContent = '✓ Guardado';
        setTimeout(() => { btn.disabled = false; btn.textContent = 'Guardar cambios'; }, 2000);
    } else {
        btn.disabled = false; btn.textContent = 'Guardar cambios';
        alert(data.error || 'Error al guardar');
    }
}

async function borrarTutor() {
    if (!confirm('¿Borrar este tutor y todas sus vinculaciones con alumnos? Esta acción no se puede deshacer.')) return;
    const r = await fetch(URL_DEL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify({})
    });
    const data = await r.json();
    if (data.ok) window.location = URL_LIST;
    else alert(data.error || 'Error al borrar');
}
</script>

</x-app-layout>
