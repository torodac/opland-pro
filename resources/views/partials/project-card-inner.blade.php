{{-- Interior de tarjeta de proyecto. Variables: $project, $firstTable --}}

{{-- Botón favorito --}}
<button @click="toggleFavorite({{ $project->id }}, $event)"
        class="absolute top-2 left-2 p-1 text-gray-300 hover:text-amber-400 transition-colors"
        :class="isFavorite({{ $project->id }}) ? 'text-amber-400' : ''">
    <i class="fas fa-star text-sm"></i>
</button>

{{-- Menú ⋮ --}}
<div class="absolute top-2 right-2" x-data="{ open: false }">
    <button @click.stop="open = !open" @click.outside="open = false"
            class="p-1 text-gray-300 hover:text-gray-500 transition-colors">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
        </svg>
    </button>
    <div x-show="open"
         class="absolute right-0 mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm text-left">
        <a href="{{ route('config.projects.tables.index', $project) }}"
           class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
            <i class="fas fa-table text-xs w-4"></i> Tablas
        </a>
        <a href="{{ route('config.projects.edit', $project) }}"
           class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
            <i class="fas fa-edit text-xs w-4"></i> Editar
        </a>
    </div>
</div>

{{-- Logo o inicial --}}
@if($project->logo)
    <img src="{{ asset($project->logo) }}" class="h-14 w-auto mt-3 object-contain pointer-events-none" alt="{{ $project->name }}">
@else
    <div class="w-14 h-14 mt-3 rounded-xl bg-orange-50 text-orange-400 flex items-center justify-center text-2xl font-bold pointer-events-none">
        {{ strtoupper(substr($project->name, 0, 1)) }}
    </div>
@endif

{{-- Nombre --}}
<p class="text-sm font-semibold text-gray-700 leading-tight">{{ $project->name }}</p>
