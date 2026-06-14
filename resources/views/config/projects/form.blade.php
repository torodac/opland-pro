<x-app-layout :project="$project->exists ? $project : null" title="{{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }}">

    <div class="max-w-lg mx-auto">
        <form method="POST"
              enctype="multipart/form-data"
              action="{{ $project->exists ? route('config.projects.update', $project) : route('config.projects.store') }}">
            @csrf
            @if($project->exists) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Nombre <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $project->name) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                           required autofocus>
                </div>

                @if(!$project->exists)
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Slug <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <input type="text" name="slug" value="{{ old('slug', $project->slug) }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="ej: gym" required>
                        <p class="text-xs text-gray-400 mt-1">Solo letras, números y guiones. No se puede cambiar después.</p>
                    </div>
                </div>
                @endif

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Descripción</label>
                    <input type="text" name="description" value="{{ old('description', $project->description) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>

                {{-- Logo --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Logo</label>
                    <div class="flex-1 flex items-center gap-3 flex-wrap">
                        @if($project->logo)
                            <img src="{{ asset($project->logo) }}?{{ time() }}"
                                 class="h-10 w-auto object-contain rounded border border-gray-100">
                        @endif
                        <input type="file" name="logo" accept="image/*"
                               class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                    </div>
                </div>

                {{-- Favicon --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Favicon</label>
                    <div class="flex-1 flex items-center gap-3 flex-wrap">
                        @if($project->favicon)
                            <img src="{{ asset($project->favicon) }}?{{ time() }}"
                                 class="h-6 w-auto object-contain rounded border border-gray-100">
                        @endif
                        <div class="flex-1">
                            <input type="file" name="favicon" accept=".ico,.png,.svg"
                                   class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                            <p class="text-xs text-gray-400 mt-1">.ico, .png o .svg</p>
                        </div>
                    </div>
                </div>

                @if($project->exists)
                <div class="px-5 py-4 flex items-center gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400">Activo</label>
                    <input type="checkbox" name="active" value="1" {{ $project->active ? 'checked' : '' }}
                           class="w-4 h-4 accent-orange-500">
                </div>
                @endif

            </div>

            @if($errors->any())
                <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-2 mt-4">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ $project->exists ? 'Guardar cambios' : 'Crear proyecto' }}
                </button>
                <a href="{{ route('config.projects.index') }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

</x-app-layout>
