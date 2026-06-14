<x-app-layout :project="$project" :breadcrumb="[['label' => $projectTable->label, 'url' => route('listado', [$project->slug, $projectTable->name])], ['label' => 'Importar Excel', 'url' => '']]">

<div class="max-w-lg mx-auto py-10">
    <h1 class="text-xl font-semibold text-gray-800 mb-2">Importar Excel → {{ $projectTable->label }}</h1>
    <p class="text-sm text-gray-500 mb-6">La primera fila del archivo debe contener los nombres de las columnas.</p>

    <form action="{{ route('excel.import-preview', [$project->slug, $projectTable->name]) }}"
          method="POST" enctype="multipart/form-data"
          class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel</label>
            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                   class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 file:font-medium hover:file:bg-orange-100">
            <p class="mt-1 text-xs text-gray-400">Formatos: xlsx, xls, csv. Máx 20 MB.</p>
            @error('archivo') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-between pt-2">
            <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}"
               class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            <button type="submit"
                    class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-600">
                Continuar →
            </button>
        </div>
    </form>
</div>

</x-app-layout>
