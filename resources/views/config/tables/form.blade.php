<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => route('config.projects.tables.index', $project)],
    ['label' => $table->exists ? 'Editar tabla' : 'Nueva tabla', 'url' => ''],
]">

    <div class="max-w-lg mx-auto">
        <form method="POST"
              action="{{ $table->exists ? route('config.projects.tables.update', [$project, $table]) : route('config.projects.tables.store', $project) }}">
            @csrf
            @if($table->exists) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

                @if(!$table->exists)
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Nombre (slug) <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="ej: socios" required>
                        <p class="text-xs text-gray-400 mt-1">Será el nombre de la tabla en BD: <span class="font-mono">{{ $project->slug }}_socios</span></p>
                    </div>
                </div>
                @endif

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Etiqueta <span class="text-red-400">*</span></label>
                    <input type="text" name="label" value="{{ old('label', $table->label) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                           placeholder="ej: Socios" required>
                </div>

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Icono</label>
                    <div class="flex-1">
                        <input type="text" name="icon" value="{{ old('icon', $table->icon) }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="fas fa-users">
                        <p class="text-xs text-gray-400 mt-1">
                            Clase de Font Awesome, ej: <span class="font-mono">fas fa-calendar</span> —
                            <a href="https://fontawesome.com/v6/search?o=r&m=free" target="_blank"
                               class="text-orange-500 hover:underline">Ver iconos disponibles</a>
                        </p>
                    </div>
                </div>

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Orden</label>
                    <input type="number" name="order" value="{{ old('order', $table->order ?? 0) }}"
                           class="w-24 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>


                <div class="px-5 py-4">
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="admin_only" value="1" {{ old('admin_only', $table->admin_only) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        <span><strong>Solo administradores</strong> — aparece en el submenú Configuración, oculto para usuarios normales</span>
                    </label>
                </div>

                <div class="px-5 py-4 space-y-3">
                    <p class="text-sm text-gray-400 mb-2">Vistas adicionales</p>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="has_kanban" value="1" {{ old('has_kanban', $table->has_kanban) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Kanban
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="has_calendar" value="1" {{ old('has_calendar', $table->has_calendar) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Calendario
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="has_matrix" value="1" {{ old('has_matrix', $table->has_matrix) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Matriz
                    </label>
                </div>

            </div>

            @if($errors->any())
                <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-2 mt-4">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ $table->exists ? 'Guardar cambios' : 'Crear tabla' }}
                </button>
                <a href="{{ route('config.projects.tables.index', $project) }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

</x-app-layout>
