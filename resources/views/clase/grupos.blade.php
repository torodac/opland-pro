<x-app-layout :project="$project" :breadcrumb="[['label'=>'Grupos','url'=>'']]">

<div class="flex items-center justify-between mb-4">
  <h1 class="text-sm font-bold text-gray-900">Grupos</h1>
  <a href="{{ route('clase.grupos.nuevo', $project->slug) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Nuevo grupo
  </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-xs">
    <thead>
      <tr class="border-b border-gray-200 bg-gray-50">
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Nombre</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Días</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Descripción</th>
        <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Alumnos</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    @forelse($grupos as $g)
      <tr class="hover:bg-gray-50 cursor-pointer"
          onclick="window.location='{{ route('clase.grupos.ficha', [$project->slug, $g->id]) }}'">
        <td class="px-4 py-3 font-medium text-gray-900">{{ $g->nombre }}</td>
        <td class="px-4 py-3">
          <div class="flex gap-0.5">
            @foreach([['lunes','L'],['martes','M'],['miercoles','X'],['jueves','J'],['viernes','V'],['sabado','S'],['domingo','D']] as [$col,$lbl])
              <span class="inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold
                {{ $g->$col ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-300' }}">
                {{ $lbl }}
              </span>
            @endforeach
          </div>
        </td>
        <td class="px-4 py-3 text-gray-500">{{ $g->descripcion ?: '—' }}</td>
        <td class="px-4 py-3 text-center">
          @if($g->n_alumnos > 0)
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-600">
              {{ $g->n_alumnos }}
            </span>
          @else
            <span class="text-gray-300">—</span>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin grupos registrados</td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>

</x-app-layout>
