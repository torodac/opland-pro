<x-app-layout :project="$project" :breadcrumb="[['label' => 'Configuración', 'url' => route('config.projects.tables.index', $project)], ['label' => 'Crear desde Excel', 'url' => '']]">

<div class="max-w-5xl mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-1">Errores de validación</h1>
    <p class="text-sm text-gray-500 mb-6">
        Se encontraron <span class="font-semibold text-red-600">{{ count($errors) }} problema{{ count($errors) !== 1 ? 's' : '' }}</span>
        en {{ count($errorsByCol) }} columna{{ count($errorsByCol) !== 1 ? 's' : '' }}
        (de {{ $totalRows }} filas totales).
    </p>

    {{-- Resumen por columna --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-sm"></i>
            <h2 class="text-sm font-semibold text-gray-700">Resumen por columna</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($errorsByCol as $col => $colErrors)
            <div class="px-4 py-3 flex items-center justify-between">
                <div>
                    <span class="font-mono text-sm text-gray-700">{{ $col }}</span>
                    <span class="ml-2 text-xs text-gray-400">{{ $colErrors[0]['error'] }}</span>
                </div>
                <span class="text-xs font-medium text-red-500 bg-red-50 px-2 py-0.5 rounded-full">
                    {{ count($colErrors) }} {{ count($colErrors) === 1 ? 'fila' : 'filas' }}
                </span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Detalle de errores --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-8">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Detalle de errores</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 w-16">Fila</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Columna</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Valor</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($errors as $e)
                    <tr class="hover:bg-red-50/40">
                        <td class="px-4 py-2 text-gray-400 font-mono">{{ $e['row'] }}</td>
                        <td class="px-4 py-2 font-mono text-gray-700">{{ $e['col'] }}</td>
                        <td class="px-4 py-2 text-gray-600 max-w-[200px] truncate">{{ $e['value'] }}</td>
                        <td class="px-4 py-2 text-red-600">{{ $e['error'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Botones de acción --}}
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('config.projects.import-excel.form', $project) }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Volver a subir el archivo</a>

        <div class="flex gap-3">
            {{-- Continuar omitiendo filas con error --}}
            <form action="{{ route('config.projects.import-excel.confirm', $project) }}" method="POST">
                @csrf
                <input type="hidden" name="table_name"  value="{{ $tableName }}">
                <input type="hidden" name="table_label" value="{{ $tableLabel }}">
                <input type="hidden" name="dup_mode"    value="{{ $dupMode }}">
                <input type="hidden" name="skip_errors" value="1">
                @foreach($keyFields as $kf)
                    <input type="hidden" name="key_fields[]" value="{{ $kf }}">
                @endforeach
                @foreach($fields as $i => $field)
                    <input type="hidden" name="fields[{{ $i }}][name]"      value="{{ $field['name'] }}">
                    <input type="hidden" name="fields[{{ $i }}][label]"     value="{{ $field['label'] }}">
                    <input type="hidden" name="fields[{{ $i }}][type]"      value="{{ $field['type'] }}">
                    <input type="hidden" name="fields[{{ $i }}][ref_table]" value="{{ $field['ref_table'] ?? '' }}">
                @endforeach
                <button type="submit"
                        onclick="return confirm('Se omitirán {{ count($errors) }} fila{{ count($errors) !== 1 ? 's' : '' }} con errores. ¿Continuar?')"
                        class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Continuar omitiendo errores
                </button>
            </form>
        </div>
    </div>
</div>

</x-app-layout>
