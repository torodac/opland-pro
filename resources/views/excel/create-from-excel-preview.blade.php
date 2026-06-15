<x-app-layout :project="$project" :breadcrumb="[['label' => 'Configuración', 'url' => route('config.projects.tables.index', $project)], ['label' => 'Crear desde Excel', 'url' => '']]">

<div class="max-w-4xl mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-1">Crear tabla desde Excel</h1>
    <p class="text-sm text-gray-500 mb-6">Revisa y ajusta los campos inferidos antes de crear la tabla.</p>

    <form action="{{ route('config.projects.import-excel.validate', $project) }}" method="POST" class="space-y-6">
        @csrf
        <input type="hidden" name="table_name" value="{{ $tableName }}">
        <input type="hidden" name="table_label" value="{{ $tableLabel }}">

        {{-- Nombre de la tabla (solo lectura) --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Información de la tabla</h2>
            <div class="flex gap-6 text-sm">
                <div><span class="text-gray-400">Etiqueta:</span> <span class="font-medium text-gray-700">{{ $tableLabel }}</span></div>
                <div><span class="text-gray-400">Nombre BD:</span> <span class="font-mono text-gray-700">{{ $tableName }}</span></div>
            </div>
        </div>

        {{-- Campos inferidos --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Campos detectados</h2>
                <span class="text-xs text-gray-400">{{ count($headings) }} columnas</span>
            </div>

            @php
            $typeOptions = array_diff_key(\App\Models\TableField::$typeMap, array_flip(['id', 'multitabla', 'multiusuario']));
            @endphp

            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Columna Excel</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Nombre interno</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Etiqueta</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Tipo</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Tabla ref.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($headings as $i => $heading)
                    @php $inferredType = $inferTypes[$heading] ?? 'string'; @endphp
                    <tr x-data="{ tipo: '{{ $inferredType }}' }">
                        <td class="px-4 py-2 font-mono text-gray-600">{{ $heading }}</td>
                        <td class="px-2 py-2">
                            <input type="text" name="fields[{{ $i }}][name]"
                                   value="{{ \Illuminate\Support\Str::snake(\Illuminate\Support\Str::slug($heading, '_')) }}"
                                   required
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
                        </td>
                        <td class="px-2 py-2">
                            <input type="text" name="fields[{{ $i }}][label]"
                                   value="{{ ucfirst($heading) }}" required
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
                        </td>
                        <td class="px-2 py-2">
                            <select name="fields[{{ $i }}][type]" x-model="tipo"
                                    class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
                                @foreach($typeOptions as $key => $col)
                                    <option value="{{ $key }}" {{ $key === $inferredType ? 'selected' : '' }}>{{ $key }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-2">
                            <select name="fields[{{ $i }}][ref_table]"
                                    x-show="tipo === 'desplegable'" x-cloak
                                    class="w-full border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none">
                                <option value="">— tabla —</option>
                                @foreach($projectTables as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Preview de datos --}}
        @if($preview->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Muestra de datos (primeras 5 filas)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach($headings as $h)
                                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($preview as $row)
                        <tr>
                            @foreach($headings as $h)
                                <td class="px-3 py-2 text-gray-600 truncate max-w-[150px]">{{ $row[$h] ?? '—' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Deduplicación --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4" x-data="{ dupMode: 'insert' }">
            <h2 class="text-sm font-semibold text-gray-700">Manejo de duplicados</h2>
            <div class="space-y-2">
                @foreach(['insert' => 'Insertar todos', 'update' => 'Actualizar si ya existe', 'skip' => 'Saltar si ya existe'] as $val => $label)
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="dup_mode" value="{{ $val }}" {{ $val === 'insert' ? 'checked' : '' }}
                           x-model="dupMode" class="accent-orange-500">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                </label>
                @endforeach
            </div>
            <div x-show="dupMode !== 'insert'" x-cloak class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Campos clave <span class="text-xs text-gray-400 font-normal">(marca uno o varios)</span></label>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach($headings as $h)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="key_fields[]"
                               value="{{ \Illuminate\Support\Str::snake(\Illuminate\Support\Str::slug($h, '_')) }}"
                               class="accent-orange-500 w-4 h-4">
                        <span class="text-sm text-gray-700 font-mono">{{ $h }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('config.projects.import-excel.form', $project) }}"
               class="text-sm text-gray-500 hover:text-gray-700">← Volver</a>
            <button type="submit"
                    class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                Crear tabla e importar datos
            </button>
        </div>
    </form>
</div>

</x-app-layout>
