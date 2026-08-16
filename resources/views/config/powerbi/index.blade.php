<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => route('config.projects.tables.index', $project)],
    ['label' => 'Power BI', 'url' => ''],
]">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-3xl">

        <div class="mb-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Informes de Power BI</h2>
            <p class="text-xs text-gray-400">Cada informe aparece como un enlace propio en el menú lateral del proyecto. La visibilidad por rol se gestiona desde Roles de este proyecto, usando el nombre de tabla <code class="bg-gray-100 px-1 rounded">powerbi_&lt;slug&gt;</code> que se muestra debajo de cada informe.</p>
        </div>

        {{-- Listado --}}
        <div class="flex flex-col gap-3 mb-8">
            @forelse($reports as $report)
                <div class="bg-white border border-gray-200 rounded-xl p-4" x-data="{ editing: false }">
                    <div class="flex items-start justify-between gap-3" x-show="!editing">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $report->label }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 font-mono truncate">{{ $report->reportid }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                Permiso: <code class="bg-gray-100 px-1 rounded">powerbi_{{ $report->slug }}</code>
                                · URL: <code class="bg-gray-100 px-1 rounded">/{{ $project->slug }}/powerbi_report/{{ $report->slug }}</code>
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" @click="editing = true"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors" title="Editar">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                </svg>
                            </button>
                            <form method="POST" action="{{ route('config.projects.powerbi.destroy', [$project, $report->id]) }}"
                                  onsubmit="return confirm('¿Eliminar el informe «{{ $report->label }}»? Se quitará también del menú lateral.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors" title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Edición inline --}}
                    <form method="POST" action="{{ route('config.projects.powerbi.update', [$project, $report->id]) }}" x-show="editing" x-cloak class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                                <input type="text" name="label" value="{{ $report->label }}" required
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Report ID (Power BI)</label>
                                <input type="text" name="reportid" value="{{ $report->reportid }}" required
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono">
                            </div>
                        </div>
        <div>
                            <label class="block text-xs text-gray-500 mb-1">Página inicial (opcional)</label>
                            <input type="text" name="reportpage" value="{{ $report->reportpage }}" placeholder="Nombre técnico de la página de Power BI"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Filtros fijos (opcional, JSON)</label>
                            <textarea name="filtros" rows="2" placeholder='[{"tabla":"clientes","columna":"id","valor":"5"}]'
                                      class="w-full text-xs border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono">{{ $report->filtros }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">Se aplican siempre y el usuario no puede quitarlos. Array de objetos con <code class="bg-gray-100 px-1 rounded">tabla</code>/<code class="bg-gray-100 px-1 rounded">columna</code>/<code class="bg-gray-100 px-1 rounded">valor</code>. Vacío = sin restricción.</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="page_navigation" value="1" {{ $report->page_navigation ? 'checked' : '' }}>
                                Navegación entre páginas
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="filters_visible" value="1" {{ $report->filters_visible ? 'checked' : '' }}>
                                Panel de filtros visible
                            </label>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editing = false" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
                            <button type="submit" class="px-4 py-1.5 text-sm font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg">Guardar</button>
                        </div>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-400">Este proyecto todavía no tiene ningún informe de Power BI.</p>
            @endforelse
        </div>

        {{-- Nuevo informe --}}
        <div class="bg-white border border-dashed border-gray-300 rounded-xl p-4">
            <p class="text-sm font-semibold text-gray-600 mb-3">Añadir informe</p>
            <form method="POST" action="{{ route('config.projects.powerbi.store', $project) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                        <input type="text" name="label" required placeholder="p.ej. Operaciones"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Report ID (Power BI)</label>
                        <input type="text" name="reportid" required placeholder="bc5d603a-0d5c-4831-9e73-919e7637b226"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Página inicial (opcional)</label>
                    <input type="text" name="reportpage" placeholder="Nombre técnico de la página de Power BI"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Filtros fijos (opcional, JSON)</label>
                    <textarea name="filtros" rows="2" placeholder='[{"tabla":"clientes","columna":"id","valor":"5"}]'
                              class="w-full text-xs border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"></textarea>
                    <p class="text-xs text-gray-400 mt-1">Se aplican siempre y el usuario no puede quitarlos. Array de objetos con <code class="bg-gray-100 px-1 rounded">tabla</code>/<code class="bg-gray-100 px-1 rounded">columna</code>/<code class="bg-gray-100 px-1 rounded">valor</code>. Vacío = sin restricción.</p>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" name="page_navigation" value="1" checked>
                        Navegación entre páginas
                    </label>
                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" name="filters_visible" value="1">
                        Panel de filtros visible
                    </label>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-1.5 text-sm font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg">
                        Crear informe
                    </button>
                </div>
            </form>
        </div>

    </div>

</x-app-layout>
