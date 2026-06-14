<x-app-layout :project="$project" :breadcrumb="[['label' => 'Configuración', 'url' => route('config.projects.tables.index', $project)], ['label' => 'Crear desde Excel', 'url' => '']]">

<div class="max-w-lg mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-2">Crear tabla desde Excel</h1>
    <p class="text-sm text-gray-500 mb-6">La primera fila del archivo debe contener los nombres de las columnas.</p>

    <form action="{{ route('config.projects.import-excel.preview', $project) }}"
          method="POST" enctype="multipart/form-data"
          class="bg-white border border-gray-200 rounded-xl p-6 space-y-4"
          x-data="{ archivo: false }">
        @csrf
        <input type="hidden" name="table_label" value="{{ request('table_label') }}">
        <input type="hidden" name="table_name" value="{{ request('table_name') }}">

        {{-- Info de la tabla (solo lectura) --}}
        <div class="flex gap-6 py-2 text-sm border-b border-gray-100 mb-1">
            <div><span class="text-gray-400">Etiqueta:</span> <span class="font-medium text-gray-700">{{ request('table_label') }}</span></div>
            <div><span class="text-gray-400">Nombre BD:</span> <span class="font-mono text-gray-700">{{ request('table_name') }}</span></div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Archivo Excel</label>
            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                   @change="archivo = $event.target.files.length > 0"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 file:font-medium hover:file:bg-orange-100">
            <p class="mt-1 text-xs text-gray-400">Formatos: xlsx, xls, csv. Máx 20 MB.</p>
            @error('archivo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-between pt-1">
            <a href="{{ route('config.projects.tables.index', $project) }}"
               class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            <button type="submit"
                    :disabled="!archivo"
                    :class="archivo ? 'bg-orange-500 hover:bg-orange-600 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                    class="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors">
                Analizar archivo →
            </button>
        </div>
    </form>
</div>

</x-app-layout>
