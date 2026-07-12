<x-app-layout :project="$project" :breadcrumb="[['label' => $projectTable->label, 'url' => route('listado', [$project->slug, $projectTable->name])], ['label' => 'Importar Excel', 'url' => '']]">

<div class="max-w-3xl mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-1">Importar Excel → {{ $projectTable->label }}</h1>
    <p class="text-sm text-gray-500 mb-6">Revisa las opciones antes de confirmar la importación.</p>

    <form action="{{ route('excel.import', [$project->slug, $projectTable->name]) }}"
          method="POST" class="space-y-6" x-data="{ dupMode: 'insert', keyCount: 0 }">
        @csrf

        {{-- Opciones de deduplicación --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700">Manejo de duplicados</h2>
            <div class="space-y-2">
                @foreach(['insert' => 'Insertar todos (no verificar duplicados)', 'update' => 'Actualizar si ya existe', 'skip' => 'Saltar si ya existe'] as $val => $label)
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="dup_mode" value="{{ $val }}" {{ $val === 'insert' ? 'checked' : '' }}
                           x-model="dupMode" class="accent-orange-500">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div x-show="dupMode !== 'insert'" x-cloak class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Campos clave <span class="text-xs text-red-400 font-normal">* obligatorio</span></label>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach($keyHeadings as $h)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="key_fields[]" value="{{ $h }}"
                               class="accent-orange-500 w-4 h-4"
                               @change="keyCount = $el.closest('.flex').querySelectorAll(':checked').length">
                        <span class="text-sm text-gray-700 font-mono">{{ $h }}</span>
                    </label>
                    @endforeach
                </div>
                @if(empty($keyHeadings))
                    <p class="text-xs text-red-400 mt-1">Ninguna columna del Excel coincide con los campos de la tabla.</p>
                @endif
                <p x-show="keyCount === 0" class="text-xs text-red-400 mt-2">Debes marcar al menos un campo clave para poder comparar.</p>
            </div>
        </div>

        {{-- Preview de datos --}}
        @php
            $fieldTypeMap = $projectTable->fields->pluck('type', 'name')->toArray();
        @endphp
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Primeras filas del archivo</h2>
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
                                @php
                                    $val = $row[$h] ?? null;
                                    $ftype = $fieldTypeMap[$h] ?? null;
                                    if ($val !== null && in_array($ftype, ['fecha','timestamp']) && is_numeric($val)) {
                                        $s = (float)$val;
                                        if ($s > 1 && $s < 109574) {
                                            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($s);
                                            $val = $ftype === 'fecha' ? $dt->format('d/m/Y') : $dt->format('d/m/Y H:i');
                                        }
                                    }
                                @endphp
                                <td class="px-3 py-2 text-gray-600 truncate max-w-[180px]">{{ $val ?? '—' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('excel.import-form', [$project->slug, $projectTable->name]) }}"
               class="text-sm text-gray-500 hover:text-gray-700">← Volver</a>
            <button type="submit"
                    :disabled="dupMode !== 'insert' && keyCount === 0"
                    :class="dupMode !== 'insert' && keyCount === 0
                        ? 'px-5 py-2 bg-gray-200 text-gray-400 text-sm font-medium rounded-lg cursor-not-allowed'
                        : 'px-5 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-600'">
                Confirmar importación
            </button>
        </div>
    </form>
</div>

</x-app-layout>
