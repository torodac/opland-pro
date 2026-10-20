<x-app-layout
    :breadcrumb="[
        ['label' => 'Dashboard', 'url' => route('vm.dashboard', [$project->slug])],
        ['label' => $tarea->nombre ?: ('Tarea #' . $tarea->id), 'url' => ''],
    ]"
    :project="$project">

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
    <span class="text-sm px-3 py-1 rounded-full bg-gray-100 text-gray-700">{{ $tarea->estado ?: '—' }}</span>
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

  {{-- Accesos directos a las unidades de gestión relacionadas --}}
  @if(count($enlaces))
    <div class="space-y-2">
      <h2 class="text-sm font-semibold text-gray-700">Relacionado con</h2>
      @foreach($enlaces as $enlace)
        <a href="{{ $enlace['url'] }}"
           class="flex items-center gap-2 bg-white rounded-xl border border-gray-200 px-4 py-3 text-sm text-blue-700 hover:bg-blue-50">
          <i class="ti {{ $enlace['icono'] }}"></i>{{ $enlace['etiqueta'] }}
        </a>
      @endforeach
    </div>
  @endif

  {{-- Comentario: lo único que se escribe desde esta ficha --}}
  <form method="POST" action="{{ route('vm.sscc_form.comentario', [$project->slug, $tarea->id]) }}"
        class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
    @csrf
    <label for="comentario" class="block text-sm font-semibold text-gray-700">Comentario</label>
    <textarea id="comentario" name="comentario" rows="4"
              class="w-full rounded-lg border border-gray-300 text-sm p-2 disabled:bg-gray-50"
              @disabled(!$puedeComentar)>{{ old('comentario', $tarea->comentario) }}</textarea>
    @if($puedeComentar)
      <div class="flex justify-end">
        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-900 text-white hover:bg-gray-700">
          Guardar comentario
        </button>
      </div>
    @endif
  </form>

</div>
</x-app-layout>
