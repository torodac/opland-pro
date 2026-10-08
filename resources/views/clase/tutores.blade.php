<x-app-layout :project="$project" :breadcrumb="[['label'=>'Tutores','url'=>'']]">

<div class="flex items-center justify-between mb-4">
  <h1 class="text-sm font-bold text-gray-900">Tutores</h1>
</div>

{{-- Búsqueda --}}
<form method="GET" action="" class="flex gap-2 mb-4">
  <div class="relative flex-1 max-w-xs">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="text" name="q" value="{{ $busca }}" placeholder="Buscar por nombre, email o teléfono…"
           autocomplete="off"
           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-orange-400">
  </div>
  @if($busca)
  <a href="{{ request()->url() }}"
     class="inline-flex items-center px-3 py-1.5 border border-gray-200 text-gray-500 text-sm rounded-lg hover:bg-gray-50 transition-colors">
    Limpiar
  </a>
  @endif
</form>

{{-- Tabla --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-xs">
    <thead>
      <tr class="border-b border-gray-200 bg-gray-50">
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Nombre</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Tipo</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Teléfono</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Email</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Alumnos</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    @forelse($tutores as $t)
      <tr class="hover:bg-gray-50 cursor-pointer"
          onclick="window.location='{{ route('clase.tutores.ficha', [$project->slug, $t->id]) }}'">
        <td class="px-4 py-3 font-medium text-gray-900">{{ $t->nombre }}</td>
        <td class="px-4 py-3 text-gray-500">{{ $t->tipo_tutor ?: '—' }}</td>
        <td class="px-4 py-3 text-gray-500">{{ $t->telefono ?: '—' }}</td>
        <td class="px-4 py-3 text-gray-500">{{ $t->email ?: '—' }}</td>
        <td class="px-4 py-3 text-gray-500">
          @if($t->n_alumnos > 0)
            <span class="text-gray-700">{{ $t->alumnos_nombres }}</span>
          @else
            <span class="text-gray-300">Sin alumnos</span>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
          {{ $busca ? 'Sin resultados para "' . $busca . '"' : 'Sin tutores registrados' }}
        </td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="mt-4">{{ $tutores->links() }}</div>

</x-app-layout>
