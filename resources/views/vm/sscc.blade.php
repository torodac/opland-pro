<x-app-layout
    :breadcrumb="[
        ['label' => 'Dashboard', 'url' => route('vm.dashboard', [$project->slug])],
        ['label' => $tarea->nombre ?: ('Tarea #' . $tarea->id), 'url' => ''],
    ]"
    :project="$project">

{{-- Editar / Guardar en la cabecera, igual que en la ficha de fichaje. Solo el comentario se edita. --}}
<x-slot name="actions">
  @if($puedeComentar)
    <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
      <button type="button" onclick="enterEdit()"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <i class="ti ti-pencil text-sm"></i>Editar
      </button>
    </div>
    <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
      <button type="button" onclick="cancelEdit()"
        class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
        Cancelar
      </button>
      <button type="submit" form="form-sscc" id="btn-save"
        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-orange-500 border border-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
        Guardar
      </button>
    </div>
  @endif
</x-slot>

<div class="max-w-3xl mx-auto space-y-6 p-4">

  @if(session('ok'))
    <div class="rounded-lg bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('ok') }}</div>
  @endif

  {{-- Cabecera: nombre y estado. El estado solo se muestra; se cambia desde el dashboard. --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">{{ $tarea->nombre ?: ('Tarea #' . $tarea->id) }}</h1>
      <p class="text-sm text-gray-500">{{ $tarea->Tipo ?: 'Sin tipo' }}</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm px-3 py-1 rounded-full bg-gray-100 text-gray-700">{{ $tarea->estado ?: '—' }}</span>
      @if($puedeHecho)
        {{-- Mismo cierre que el dashboard: pide confirmación porque dispara lo que depende de la tarea --}}
        {{-- Color en línea: las clases de color de Tailwind no siempre están compiladas en este proyecto --}}
        <button type="button" id="ssc-hecho" onclick="marcarHecho()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg text-white"
                style="background:#16a34a;border:1px solid #16a34a;"
                onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
          <i class="ti ti-check text-sm"></i>Hecho
        </button>
      @endif
    </div>
  </div>

  {{-- Datos de la tarea, todos de solo lectura --}}
  <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm bg-white rounded-xl border border-gray-200 p-4">
    <dt class="text-gray-500">Propiedad</dt><dd class="text-gray-900">{{ $propiedad ?: '—' }}</dd>
    <dt class="text-gray-500">Departamento</dt><dd class="text-gray-900">{{ $departamento ?: '—' }}</dd>
    <dt class="text-gray-500">Rol</dt><dd class="text-gray-900">{{ $rol ?: '—' }}</dd>
    <dt class="text-gray-500">Fecha planificada</dt>
    <dd class="text-gray-900">{{ $tarea->fecha_planificada ? \Carbon\Carbon::parse($tarea->fecha_planificada)->format('d/m/Y') : '—' }}</dd>
    <dt class="text-gray-500">Fecha de finalización</dt>
    <dd class="text-gray-900">{{ $tarea->fecha_finalizacion ? \Carbon\Carbon::parse($tarea->fecha_finalizacion)->format('d/m/Y') : '—' }}</dd>
    @if($tarea->descripcion)
      <dt class="text-gray-500">Descripción</dt><dd class="text-gray-900 whitespace-pre-line">{{ $tarea->descripcion }}</dd>
    @endif
  </dl>

  {{-- Accesos directos a las unidades de gestión relacionadas, como botones --}}
  @if(count($enlaces))
    <div class="flex flex-wrap gap-2">
      @foreach($enlaces as $enlace)
        <a href="{{ $enlace['url'] }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100">
          <i class="ti {{ $enlace['icono'] }}"></i>{{ $enlace['etiqueta'] }}
        </a>
      @endforeach
    </div>
  @endif

  {{-- Comentario: lo único que se escribe desde esta ficha. Solo se habilita en modo edición. --}}
  <form id="form-sscc" method="POST" action="{{ route('vm.sscc_form.comentario', [$project->slug, $tarea->id]) }}"
        class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
    @csrf
    <label for="comentario" class="block text-sm font-semibold text-gray-700">Comentario</label>
    <textarea id="comentario" name="comentario" rows="4" readonly
              data-original="{{ $tarea->comentario }}"
              class="w-full rounded-lg border border-gray-300 text-sm p-2 bg-gray-50">{{ old('comentario', $tarea->comentario) }}</textarea>
  </form>

  <script>
    async function marcarHecho() {
      if (!confirm('¿Marcar esta tarea SSCC como hecha?\n\nSi es la última de su fianza, la fianza pasará a Devuelta y se avisará al huésped.')) return;

      const btn = document.getElementById('ssc-hecho');
      btn.disabled = true;
      const r = await fetch(@json(route('vm.dashboard.sscc-hecho', [$project->slug, $tarea->id])), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
      });
      const data = await r.json().catch(() => ({}));
      if (r.ok && data.ok) {
        location.reload();
      } else {
        btn.disabled = false;
        alert(data.error || 'No se pudo marcar la tarea como hecha.');
      }
    }

    // Mismo mecanismo que la ficha de fichaje: se ocultan los bloques de lectura y se muestran los de edición.
    function enterEdit() {
      const t = document.getElementById('comentario');
      t.readOnly = false;
      t.classList.remove('bg-gray-50');
      document.getElementById('btn-view-mode').style.display = 'none';
      document.getElementById('btn-edit-mode').style.display = 'flex';
      t.focus();
    }

    function cancelEdit() {
      const t = document.getElementById('comentario');
      t.value = t.dataset.original;
      t.readOnly = true;
      t.classList.add('bg-gray-50');
      document.getElementById('btn-view-mode').style.display = 'flex';
      document.getElementById('btn-edit-mode').style.display = 'none';
    }
  </script>

</div>
</x-app-layout>
