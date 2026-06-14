<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => ''],
]">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Panel --}}
    <div style="margin-bottom: 2.3rem;">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Panel</h2>

        @if($panelTables->isEmpty())
            <p class="text-sm text-gray-400">Aún no hay tablas. Crea la primera abajo.</p>
        @else
            <div id="sortable-panel"
                 class="border border-dashed border-gray-300 rounded-xl p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3"
                 data-reorder-url="{{ route('config.projects.tables.reorder', $project) }}"
                 data-csrf="{{ csrf_token() }}">
                @foreach($panelTables as $table)
                    <div class="table-card cursor-grab active:cursor-grabbing select-none flex items-center gap-2.5 px-3 py-2.5 bg-white border border-gray-200 rounded-lg hover:border-orange-300 hover:bg-orange-50 transition-colors group"
                         data-id="{{ $table->id }}">
                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m2.25-2.25h.375a1.125 1.125 0 011.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125H12m2.625 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125h.375"/>
                        </svg>
                        <a href="{{ route('config.projects.tables.fields.index', [$project, $table]) }}"
                           class="flex-1 min-w-0 text-sm font-medium text-gray-700 group-hover:text-orange-700 truncate"
                           onclick="event.stopPropagation()">
                            {{ $table->label }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Configuración (solo admin) --}}
    @if($configTables->count())
    <div style="margin-bottom: 2.3rem;">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Configuración</h2>

        <div id="sortable-config"
             class="border border-dashed border-gray-300 rounded-xl p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3"
             data-reorder-url="{{ route('config.projects.tables.reorder', $project) }}"
             data-csrf="{{ csrf_token() }}">
            @foreach($configTables as $table)
                <div class="table-card cursor-grab active:cursor-grabbing select-none flex items-center gap-2.5 px-3 py-2.5 bg-white border border-gray-200 rounded-lg hover:border-orange-300 hover:bg-orange-50 transition-colors group"
                     data-id="{{ $table->id }}">
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                    <a href="{{ route('config.projects.tables.fields.index', [$project, $table]) }}"
                       class="flex-1 min-w-0 text-sm font-medium text-gray-700 group-hover:text-orange-700 truncate"
                       onclick="event.stopPropagation()">
                        {{ $table->label }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Crear tabla --}}
    <div style="margin-bottom: 2.3rem;" x-data="{
        label: '{{ old('label') }}',
        name: '{{ old('name') }}',
        nameTouched: {{ old('name') ? 'true' : 'false' }},
        syncName() {
            if (!this.nameTouched) {
                this.name = this.label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            }
        }
    }">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Nueva tabla</h2>

        {{-- Inputs compartidos --}}
        <div class="flex gap-2 items-start flex-wrap mb-2">
            <div>
                <input type="text" placeholder="Nombre visible (ej: Clientes)"
                       x-model="label" @input="syncName()"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 w-56">
                @error('label')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="text" placeholder="Nombre BD (ej: clientes)"
                       x-model="name" @input="nameTouched = true"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono w-44">
                @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Crear tabla --}}
            <form method="POST" action="{{ route('config.projects.tables.store', $project) }}">
                @csrf
                <input type="hidden" name="label" :value="label">
                <input type="hidden" name="name" :value="name">
                <button type="submit"
                        :disabled="!label.trim() || !name.trim()"
                        :class="label.trim() && name.trim() ? 'bg-orange-500 hover:bg-orange-600 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                        class="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    Crear tabla
                </button>
            </form>

            {{-- Crear desde Excel --}}
            <form method="GET" action="{{ route('config.projects.import-excel.form', $project) }}">
                <input type="hidden" name="table_label" :value="label">
                <input type="hidden" name="table_name" :value="name">
                <button type="submit"
                        :disabled="!label.trim() || !name.trim()"
                        :class="label.trim() && name.trim() ? 'border-gray-200 text-gray-600 hover:bg-gray-50 cursor-pointer' : 'border-gray-100 text-gray-300 cursor-not-allowed'"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    <i class="fas fa-file-excel" :class="label.trim() && name.trim() ? 'text-green-600' : 'text-gray-300'"></i>
                    Crear desde Excel
                </button>
            </form>
        </div>

        </form>
    </div>

    {{-- Ajustes del proyecto --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Ajustes del proyecto</h2>
        <form method="POST"
              enctype="multipart/form-data"
              action="{{ route('config.projects.update', $project) }}"
              class="max-w-lg" style="display:flex; flex-direction:column; gap:1.4rem;">
            @csrf @method('PUT')
            <input type="hidden" name="_redirect" value="{{ route('config.projects.tables.index', $project) }}">

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Nombre del proyecto</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}"
                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Descripción</label>
                <input type="text" name="description" value="{{ old('description', $project->description) }}"
                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Logo</label>
                <div class="flex items-center gap-3">
                    @if($project->logo)
                        <img src="{{ asset($project->logo) }}?{{ time() }}"
                             class="h-10 w-10 object-contain rounded border border-gray-100 shrink-0" title="Logo actual">
                    @endif
                    <input type="file" name="logo" accept="image/*"
                           class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Favicon</label>
                <div class="flex items-center gap-3">
                    @if($project->favicon)
                        <img src="{{ asset($project->favicon) }}?{{ time() }}"
                             class="h-10 w-10 object-contain rounded border border-gray-100 shrink-0" title="Favicon actual">
                    @endif
                    <div>
                        <input type="file" name="favicon" accept=".ico,.png,.svg"
                               class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                        <p class="text-xs text-gray-400 mt-1">.ico, .png o .svg</p>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="pt-2">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>

</x-app-layout>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-genera el slug de BD al escribir el nombre visible
    const labelInput = document.querySelector('input[name="label"]');
    const nameInput  = document.querySelector('input[name="name"]');
    if (labelInput && nameInput) {
        let userEditedName = false;
        nameInput.addEventListener('input', () => { userEditedName = true; });
        labelInput.addEventListener('input', () => {
            if (userEditedName) return;
            nameInput.value = labelInput.value
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
        });
    }

    // Drag-to-reorder: al mover en cualquier bloque, enviamos el orden global (panel + config)
    function sendGlobalOrder() {
        const allIds = [...document.querySelectorAll('[id^="sortable-"] .table-card')]
            .map(c => c.dataset.id);
        const firstContainer = document.querySelector('[id^="sortable-"]');
        if (!firstContainer) return;
        fetch(firstContainer.dataset.reorderUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': firstContainer.dataset.csrf },
            body: JSON.stringify({ ids: allIds }),
        }).then(r => { if (!r.ok) r.text().then(t => console.error('reorder error', r.status, t)); })
          .catch(e => console.error('reorder fetch failed', e));
    }

    document.querySelectorAll('[id^="sortable-"]').forEach(container => {
        Sortable.create(container, {
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd: sendGlobalOrder,
        });
    });
});
</script>
