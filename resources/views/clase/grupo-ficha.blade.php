@php
  $esNuevo = !$grupo;
  $titulo  = $esNuevo ? 'Nuevo grupo' : $grupo->nombre;
  $breadcrumb = $esNuevo
    ? [['label'=>'Grupos','url'=>route('clase.grupos',$project->slug)],['label'=>'Nuevo','url'=>'']]
    : [['label'=>'Grupos','url'=>route('clase.grupos',$project->slug)],['label'=>$grupo->nombre,'url'=>'']];
@endphp
<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-base font-bold text-gray-900">{{ $titulo }}</h1>
    @if(!$esNuevo)
      <p class="text-xs text-gray-400 mt-0.5">{{ $grupo->descripcion ?: 'Sin descripción' }}</p>
    @endif
  </div>
  <a href="{{ route('clase.grupos', $project->slug) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
    &larr; Grupos
  </a>
</div>

<div class="grid gap-5 max-w-2xl">

  {{-- Formulario --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
      <h2 class="text-sm font-semibold text-gray-800">Datos del grupo</h2>
    </div>
    <div class="p-5 grid gap-4">

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre</label>
          <input type="text" id="f-nombre" value="{{ $grupo->nombre ?? '' }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400"
                 placeholder="Ej: Inglés Avanzado">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Descripción</label>
          <input type="text" id="f-descripcion" value="{{ $grupo->descripcion ?? '' }}"
                 class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400"
                 placeholder="Opcional">
        </div>
      </div>

      {{-- Días de la semana --}}
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-2">Días de clase</label>
        <div class="flex flex-wrap gap-2">
          @foreach([
            ['lunes',    'Lunes'],
            ['martes',   'Martes'],
            ['miercoles','Miércoles'],
            ['jueves',   'Jueves'],
            ['viernes',  'Viernes'],
            ['sabado',   'Sábado'],
            ['domingo',  'Domingo'],
          ] as [$col, $label])
          <label class="day-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer select-none transition-colors
            {{ (!$esNuevo && $grupo->$col) ? 'border-orange-300 bg-orange-50 text-orange-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}"
            data-day="{{ $col }}">
            <input type="checkbox" class="sr-only day-cb" name="{{ $col }}"
                   {{ (!$esNuevo && $grupo->$col) ? 'checked' : '' }}>
            <span class="text-xs font-medium">{{ $label }}</span>
          </label>
          @endforeach
        </div>
      </div>

    </div>
    <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-between">
      @if(!$esNuevo)
      <button type="button" onclick="borrarGrupo()" id="btn-borrar"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>
        </svg>
        Borrar grupo
      </button>
      @else
      <div></div>
      @endif
      <button type="button" onclick="guardarGrupo()" id="btn-guardar"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
        {{ $esNuevo ? 'Crear grupo' : 'Guardar cambios' }}
      </button>
    </div>
  </div>

  {{-- Alumnos activos (solo en modo edición) --}}
  @if(!$esNuevo)
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
      <h2 class="text-sm font-semibold text-gray-800">Alumnos activos</h2>
    </div>
    @if($alumnos->isEmpty())
      <p class="px-5 py-4 text-sm text-gray-400">Sin alumnos activos en este grupo.</p>
    @else
    <table class="w-full text-xs">
      <thead>
        <tr class="border-b border-gray-100">
          <th class="text-left px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Alumno</th>
          <th class="text-left px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Inicio</th>
          <th class="text-right px-5 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Importe</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
      @foreach($alumnos as $al)
        <tr class="hover:bg-gray-50">
          <td class="px-5 py-3">
            <a href="{{ route('clase.alumnos_form', [$project->slug, $al->id]) }}"
               class="font-medium text-orange-500 hover:text-orange-600">
              {{ $al->alumno_nombre }}
            </a>
          </td>
          <td class="px-5 py-3 text-gray-500">{{ \Carbon\Carbon::parse($al->fecha_inicio)->format('d/m/Y') }}</td>
          <td class="px-5 py-3 text-right text-gray-700">{{ number_format($al->importe, 2, ',', '.') }} €</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
  @endif

</div>

<script>
const TOKEN    = "{{ csrf_token() }}";
const ES_NUEVO = {{ $esNuevo ? 'true' : 'false' }};
const URL_STORE = "{{ route('clase.grupos.store', $project->slug) }}";
@if(!$esNuevo)
const URL_UPD  = "{{ route('clase.grupos.update', [$project->slug, $grupo->id]) }}";
const URL_DEL  = "{{ route('clase.grupos.borrar', [$project->slug, $grupo->id]) }}";
const URL_LIST = "{{ route('clase.grupos', $project->slug) }}";
@endif

// Toggle visual de días
document.querySelectorAll('.day-toggle').forEach(label => {
    label.addEventListener('click', () => {
        const cb = label.querySelector('.day-cb');
        cb.checked = !cb.checked;
        label.classList.toggle('border-orange-300', cb.checked);
        label.classList.toggle('bg-orange-50',      cb.checked);
        label.classList.toggle('text-orange-700',   cb.checked);
        label.classList.toggle('border-gray-200',   !cb.checked);
        label.classList.toggle('text-gray-500',     !cb.checked);
    });
});

function getDias() {
    const dias = {};
    document.querySelectorAll('.day-cb').forEach(cb => {
        dias[cb.name] = cb.checked ? 1 : 0;
    });
    return dias;
}

async function guardarGrupo() {
    const btn = document.getElementById('btn-guardar');
    const nombre = document.getElementById('f-nombre').value.trim();
    if (!nombre) { alert('El nombre es obligatorio'); return; }
    btn.disabled = true;
    btn.textContent = ES_NUEVO ? 'Creando…' : 'Guardando…';

    const payload = {
        nombre,
        descripcion: document.getElementById('f-descripcion').value.trim(),
        ...getDias()
    };

    const url = ES_NUEVO ? URL_STORE : URL_UPD;
    const r = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.ok) {
        if (ES_NUEVO && data.url) {
            window.location = data.url;
        } else {
            btn.textContent = '✓ Guardado';
            setTimeout(() => { btn.disabled = false; btn.textContent = 'Guardar cambios'; }, 2000);
        }
    } else {
        btn.disabled = false;
        btn.textContent = ES_NUEVO ? 'Crear grupo' : 'Guardar cambios';
        alert(data.error || 'Error al guardar');
    }
}

@if(!$esNuevo)
async function borrarGrupo() {
    if (!confirm('¿Borrar este grupo? Los contratos existentes perderán la referencia al grupo.')) return;
    const r = await fetch(URL_DEL, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN},
        body: JSON.stringify({})
    });
    const data = await r.json();
    if (data.ok) window.location = URL_LIST;
    else alert(data.error || 'Error al borrar');
}
@endif
</script>

</x-app-layout>
