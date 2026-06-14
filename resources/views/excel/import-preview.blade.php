<x-app-layout :project="$project" :breadcrumb="[['label' => $projectTable->label, 'url' => route('listado', [$project->slug, $projectTable->name])], ['label' => 'Importar Excel', 'url' => '']]">

<div class="max-w-3xl mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-1">Importar Excel → {{ $projectTable->label }}</h1>
    <p class="text-sm text-gray-500 mb-6">Revisa las opciones antes de confirmar la importación.</p>

    <form action="{{ route('excel.import', [$project->slug, $projectTable->name]) }}"
          method="POST" class="space-y-6" x-data="{ dupMode: 'insert' }">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Campos clave <span class="text-xs text-gray-400 font-normal">(marca uno o varios)</span></label>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach($headings as $h)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="key_fields[]" value="{{ $h }}" class="accent-orange-500 w-4 h-4">
                        <span class="text-sm text-gray-700 font-mono">{{ $h }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preview de datos --}}
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
                                <td class="px-3 py-2 text-gray-600 truncate max-w-[180px]">{{ $row[$h] ?? '—' }}</td>
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
                    class="px-5 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-600">
                Confirmar importación
            </button>
        </div>
    </form>
</div>

</x-app-layout>
