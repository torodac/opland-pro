<x-app-layout :project="$project" :breadcrumb="[['label'=>'Fotos','url'=>'']]">

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
  <div>
    <label class="block text-xs text-gray-400 mb-1">Nombre de tarea</label>
    <input type="text" name="q_nombre" value="{{ $qNombre }}" placeholder="Buscar…"
           class="h-8 text-xs border border-gray-200 rounded-lg px-3" style="width:220px">
  </div>
  <div>
    <label class="block text-xs text-gray-400 mb-1">Fecha planificada desde</label>
    <input type="date" name="fecha_desde" value="{{ $fechaDesde }}" class="h-8 text-xs border border-gray-200 rounded-lg px-3">
  </div>
  <div>
    <label class="block text-xs text-gray-400 mb-1">hasta</label>
    <input type="date" name="fecha_hasta" value="{{ $fechaHasta }}" class="h-8 text-xs border border-gray-200 rounded-lg px-3">
  </div>
  <div>
    <label class="block text-xs text-gray-400 mb-1">Propiedad</label>
    <select name="id_propiedades" class="h-8 text-xs border border-gray-200 rounded-lg px-3" style="width:200px">
      <option value="">Todas</option>
      @foreach($propiedades as $p)
        <option value="{{ $p->id }}" {{ (string) $idPropiedad === (string) $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block text-xs mb-1 invisible">Filtrar</label>
    <button type="submit" class="h-8 text-xs font-medium px-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg">Filtrar</button>
  </div>
  @if($qNombre || $fechaDesde || $fechaHasta || $idPropiedad)
    <div>
      <label class="block text-xs mb-1 invisible">Quitar</label>
      <a href="{{ route('vm.fotos-list', $project->slug) }}" class="h-8 flex items-center text-xs text-gray-400 hover:text-gray-600 underline">Quitar filtros</a>
    </div>
  @endif
</form>

<div class="bg-white rounded-xl border border-gray-200">
    @if($fotos->isEmpty())
        <div class="px-6 py-12 text-center text-sm text-gray-400">Sin fotos que coincidan con el filtro.</div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1px; background:#e5e7eb;">
            @foreach($fotos as $foto)
                @php
                    $tareaUrl = $foto->tarea_tipo && $foto->tarea_id
                        ? route('vm.tarea', [$project->slug, $foto->tarea_tipo, $foto->tarea_id])
                        : null;
                    $tooltipLineas = collect([
                        $foto->tarea_nombre ? '📋 ' . $foto->tarea_nombre : null,
                        $foto->propiedad_nombre ? '📍 ' . $foto->propiedad_nombre : null,
                        $foto->fecha_planificada ? '📅 ' . \Carbon\Carbon::parse($foto->fecha_planificada)->format('d/m/Y') : null,
                    ])->filter()->implode("\n");
                @endphp
                <div class="bg-white flex flex-col">
                    <span class="app-tooltip" style="display:block;">
                        <a href="{{ Storage::url($foto->file_foto) }}" target="_blank" class="block overflow-hidden" style="height:150px;">
                            <img src="{{ Storage::url($foto->file_foto) }}" alt="foto"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-200">
                        </a>
                        @if($tooltipLineas !== '')
                            <span class="app-tooltip-box" style="width:16rem;">{{ $tooltipLineas }}</span>
                        @endif
                    </span>
                    <div class="px-2 py-1.5 text-center" style="min-height:36px;">
                        @if($tareaUrl)
                            <a href="{{ $tareaUrl }}" class="text-xs text-blue-600 hover:text-blue-800 hover:underline line-clamp-2 leading-tight break-words">
                                {{ $foto->tarea_nombre }}
                            </a>
                        @elseif($foto->tarea_nombre)
                            <span class="text-xs text-gray-500 line-clamp-2 leading-tight break-words">{{ $foto->tarea_nombre }}</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $fotos->links() }}
        </div>
    @endif
</div>

</x-app-layout>
