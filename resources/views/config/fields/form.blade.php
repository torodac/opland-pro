<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => route('config.projects.tables.index', $project)],
    ['label' => $table->label, 'url' => route('config.projects.tables.fields.index', [$project, $table])],
    ['label' => $field->exists ? 'Editar campo' : 'Nuevo campo', 'url' => ''],
]">

    <div class="max-w-lg mx-auto">
        <form method="POST"
              action="{{ $field->exists ? route('config.projects.tables.fields.update', [$project, $table, $field]) : route('config.projects.tables.fields.store', [$project, $table]) }}">
            @csrf
            @if($field->exists) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

                @if(!$field->exists)
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-36 shrink-0 text-sm text-gray-400 pt-2">Nombre (columna) <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="ej: fecha_alta" required>
                        <p class="text-xs text-gray-400 mt-1">Nombre de la columna en la base de datos.</p>
                    </div>
                </div>
                @endif

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-36 shrink-0 text-sm text-gray-400 pt-2">Etiqueta <span class="text-red-400">*</span></label>
                    <input type="text" name="label" value="{{ old('label', $field->label) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                           placeholder="ej: Fecha de alta" required>
                </div>

                @if(!$field->exists)
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-36 shrink-0 text-sm text-gray-400 pt-2">Tipo <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <select name="type" id="field-type"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                                required onchange="toggleExtras(this.value)">
                            <option value="">— Selecciona —</option>
                            @foreach($types as $key => $sql)
                                <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>
                                    {{ $key }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Determina qué input se muestra en el formulario.</p>
                    </div>
                </div>
                @endif

                {{-- Extras: solo visible para tipo "select" --}}
                <div class="px-5 py-4 flex items-start gap-4" id="extras-row" style="{{ old('type', $field->type) === 'select' ? '' : 'display:none' }}">
                    <label class="w-36 shrink-0 text-sm text-gray-400 pt-2">Opciones</label>
                    <div class="flex-1">
                        <input type="text" name="extras" value="{{ old('extras', $field->extras) }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="opt:Opción1,Opción2,Opción3">
                        <p class="text-xs text-gray-400 mt-1">Formato: <span class="font-mono">opt:Val1,Val2,Val3</span></p>
                    </div>
                </div>

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-36 shrink-0 text-sm text-gray-400 pt-2">Orden</label>
                    <input type="number" name="order" value="{{ old('order', $field->order ?? 0) }}"
                           class="w-24 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>

                <div class="px-5 py-4 space-y-3">
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="required" value="1" {{ old('required', $field->required) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Obligatorio
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="in_list" value="1" {{ old('in_list', $field->in_list ?? true) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Mostrar en listado
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-600">
                        <input type="checkbox" name="in_form" value="1" {{ old('in_form', $field->in_form ?? true) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500">
                        Mostrar en formulario
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
                    {{ $field->exists ? 'Guardar cambios' : 'Crear campo' }}
                </button>
                <a href="{{ route('config.projects.tables.fields.index', [$project, $table]) }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

<script>
function toggleExtras(type) {
    document.getElementById('extras-row').style.display = type === 'select' ? '' : 'none';
}
</script>

</x-app-layout>
