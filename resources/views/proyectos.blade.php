<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyectos — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col"
      x-data="proyectos()"
      x-init="init()">

    {{-- Navbar --}}
    <nav class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0">
        <span class="font-semibold text-gray-700 text-sm">{{ config('app.name') }}</span>
        @auth
            <span class="text-xs text-gray-400">{{ auth()->user()->name }}</span>
        @endauth
    </nav>

    <main class="flex-1 px-6 py-10 max-w-5xl mx-auto w-full">

        {{-- Bloque favoritos --}}
        <template x-if="favorites.length > 0">
            <div class="mb-10">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
                    <i class="fas fa-star text-amber-400 mr-1"></i> Favoritos
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach($projects as $project)
                        @php $firstTable = $project->tables->first(); @endphp
                        <template x-if="favorites.includes({{ $project->id }})">
                            <div @click="@if($firstTable) window.location='{{ route('listado', [$project->slug, $firstTable->name]) }}' @endif"
                                 class="bg-white rounded-xl border border-amber-200 shadow-sm flex flex-col items-center text-center p-5 gap-3 relative cursor-pointer hover:shadow-md hover:border-amber-300 transition-all">
                                @include('partials.project-card-inner', ['project' => $project, 'firstTable' => $firstTable])
                            </div>
                        </template>
                    @endforeach
                </div>
            </div>
        </template>

        {{-- Todos los proyectos --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Proyectos</h2>
                <a href="{{ route('config.projects.create') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo proyecto
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @forelse($projects as $project)
                    @php $firstTable = $project->tables->first(); @endphp
                    <div @click="@if($firstTable) window.location='{{ route('listado', [$project->slug, $firstTable->name]) }}' @endif"
                         class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col items-center text-center p-5 gap-3 relative cursor-pointer hover:shadow-md hover:border-orange-300 transition-all">
                        @include('partials.project-card-inner', ['project' => $project, 'firstTable' => $firstTable])
                    </div>
                @empty
                    <div class="col-span-full text-center text-gray-400 text-sm py-12">
                        No hay proyectos. <a href="{{ route('config.projects.create') }}" class="text-orange-500 hover:underline">Crear uno</a>.
                    </div>
                @endforelse
            </div>
        </div>

    </main>

    <footer class="shrink-0 px-6 py-4">
        <img src="{{ asset('images/logo-opland.png') }}" class="h-7 opacity-20" alt="Opland"
             onerror="this.style.display='none'">
    </footer>

<script>
function proyectos() {
    return {
        favorites: [],
        init() {
            this.favorites = JSON.parse(localStorage.getItem('opland_favorites') || '[]');
        },
        toggleFavorite(id, event) {
            event.stopPropagation();
            if (this.favorites.includes(id)) {
                this.favorites = this.favorites.filter(f => f !== id);
            } else {
                this.favorites.push(id);
            }
            localStorage.setItem('opland_favorites', JSON.stringify(this.favorites));
        },
        isFavorite(id) {
            return this.favorites.includes(id);
        }
    }
}
</script>

</body>
</html>
