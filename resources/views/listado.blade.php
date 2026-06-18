<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

    {{-- Acciones del header --}}
    <x-slot name="actions">
        @if($canEdit)
        {{-- Toggle vista galería (solo si hay campo file) --}}
        @if($campoFile)
        <a href="{{ request()->fullUrlWithQuery(['modo' => $modoGaleria ? 'lista' : 'galeria', 'page' => null]) }}"
           title="{{ $modoGaleria ? 'Vista lista' : 'Vista galería' }}"
           class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors {{ $modoGaleria ? 'bg-orange-50 border-orange-300 text-orange-500' : '' }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="3" width="5" height="5" rx="0.5"/><rect x="10" y="3" width="5" height="5" rx="0.5"/>
                <rect x="17" y="3" width="4" height="5" rx="0.5"/><rect x="3" y="10" width="5" height="5" rx="0.5"/>
                <rect x="10" y="10" width="5" height="5" rx="0.5"/><rect x="17" y="10" width="4" height="5" rx="0.5"/>
                <rect x="3" y="17" width="5" height="4" rx="0.5"/><rect x="10" y="17" width="5" height="4" rx="0.5"/>
                <rect x="17" y="17" width="4" height="4" rx="0.5"/>
            </svg>
        </a>
        @endif
        {{-- Toggle vista tabla editable --}}
        <a href="{{ request()->fullUrlWithQuery(['modo' => $modoTabla ? 'lista' : 'tabla']) }}"
           title="{{ $modoTabla ? 'Vista lista' : 'Vista tabla editable' }}"
           class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors {{ $modoTabla ? 'bg-orange-50 border-orange-300 text-orange-500' : '' }}">
            @if($modoTabla)
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
            @else
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
            @endif
        </a>

        @if(auth()->user()?->isProjectAdmin($project))
        <a href="{{ route('ficha.create', [$project->slug, $projectTable->name]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo
        </a>
        @endif
        @endif

        {{-- Excel exportar (dropdown) --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-file-excel text-green-600"></i>
                Exportar
                <i class="fas fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
            </button>
            <div x-show="open" x-cloak
                 class="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
                @php $qs = http_build_query(request()->except('page')); @endphp
                <a href="{{ route('excel.export', [$project->slug, $projectTable->name]) }}?tipo=listado&{{ $qs }}"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-filter text-orange-400 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Listado</p>
                        <p class="text-xs text-gray-400">Columnas visibles y filtros aplicados</p>
                    </div>
                </a>
                <a href="{{ route('excel.export', [$project->slug, $projectTable->name]) }}?tipo=tabla"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-table text-blue-400 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Tabla completa</p>
                        <p class="text-xs text-gray-400">Todas las columnas y registros</p>
                    </div>
                </a>
            </div>
        </div>

        {{-- Excel importar: solo admin del proyecto --}}
        @if(auth()->user()?->isProjectAdmin($project))
        <a href="{{ route('excel.import-form', [$project->slug, $projectTable->name]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-file-upload text-blue-500"></i>
            Importar
        </a>
        @endif
    </x-slot>

    @php
        $camposFiltrables = $campos->filter(fn($c) => in_array($c->type, ['select','tinyint','fecha','id','desplegable']));
        $filtrosActivos   = collect(request()->except(['q','ocultos','borrados','page','modo','stat']))->filter()->isNotEmpty();
    @endphp

    {{-- Stats vm_propiedades --}}
    @if($tablStats)
    @php $statActiva = request('stat'); @endphp
    <div class="flex gap-3 mb-4 flex-wrap">
        @php
        $stats = isset($tablStats['en_curso'])
            ? [
                'en_curso' => ['label' => 'En curso',          'color' => 'green',  'count' => $tablStats['en_curso']],
                'manana'   => ['label' => 'Check-in mañana',   'color' => 'blue',   'count' => $tablStats['manana']],
                'pasado'   => ['label' => 'Check-in pasado',   'color' => 'yellow', 'count' => $tablStats['pasado']],
            ]
            : [
                'pte_info'        => ['label' => 'Pte. información', 'color' => 'yellow', 'count' => $tablStats['pte_info']],
                'posibles_bajas'  => ['label' => 'Posibles bajas',   'color' => 'red',    'count' => $tablStats['posibles_bajas']],
                'revisar_borrado' => ['label' => 'Revisar borrado',  'color' => 'blue',   'count' => $tablStats['revisar_borrado']],
            ];
        $colorMap = [
            'yellow' => ['bg' => '#fefce8', 'border' => '#fde047', 'text' => '#854d0e', 'num' => '#a16207', 'active_bg' => '#fef08a'],
            'red'    => ['bg' => '#fef2f2', 'border' => '#fca5a5', 'text' => '#991b1b', 'num' => '#b91c1c', 'active_bg' => '#fecaca'],
            'blue'   => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1e40af', 'num' => '#1d4ed8', 'active_bg' => '#bfdbfe'],
            'green'  => ['bg' => '#f0fdf4', 'border' => '#86efac', 'text' => '#166534', 'num' => '#15803d', 'active_bg' => '#bbf7d0'],
        ];
        @endphp
        @foreach($stats as $key => $stat)
        @php $c = $colorMap[$stat['color']]; $activa = $statActiva === $key; @endphp
        <a href="{{ request()->fullUrlWithQuery(['stat' => $activa ? null : $key, 'page' => null]) }}"
           style="background:{{ $activa ? $c['active_bg'] : $c['bg'] }};border:1px solid {{ $c['border'] }};border-radius:0.75rem;padding:0.625rem 1rem;display:flex;align-items:center;gap:0.625rem;text-decoration:none;transition:opacity .15s"
           class="hover:opacity-80">
            <span style="font-size:1.25rem;font-weight:700;color:{{ $c['num'] }}">{{ $stat['count'] }}</span>
            <span style="font-size:0.75rem;font-weight:500;color:{{ $c['text'] }}">{{ $stat['label'] }}</span>
        </a>
        @endforeach
    </div>
    @endif

    {{-- Barra de búsqueda --}}
    <form method="GET" id="form-listado" class="flex gap-2 mb-4" x-data="{ modalFiltros: false }">

        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="Buscar..."
               class="flex-1 max-w-xs text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">

        <button type="submit"
                class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">
            Buscar
        </button>

        {{-- Botón filtros (solo si hay campos filtrables) --}}
        @if($camposFiltrables->isNotEmpty())
            <button type="button" @click="modalFiltros = true"
                    title="Filtros"
                    class="p-1.5 rounded-lg border transition-colors
                        {{ $filtrosActivos ? 'border-orange-400 text-orange-500 bg-orange-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M11 12h2M11 16h2"/>
                </svg>
            </button>
        @endif

        @if(request('q') || $filtrosActivos || request('ocultos') || request('borrados'))
            <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}"
               title="Limpiar filtros"
               class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        @endif

        {{-- Toggle ocultos (solo si la tabla tiene campo hidden) --}}
        @if($tieneHidden)
        <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}?{{ http_build_query(array_merge(request()->except('ocultos','borrados'), request('ocultos') ? [] : ['ocultos' => 1])) }}"
           title="Ocultos"
           class="ml-auto p-1.5 rounded-lg border transition-colors {{ request('ocultos') ? 'border-amber-400 text-amber-500 bg-amber-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
            <i class="fas fa-eye-slash text-base leading-none"></i>
        </a>
        @endif

        {{-- Toggle borrados (solo si la tabla tiene campo deleted) --}}
        @if($tieneDeleted)
        <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}?{{ http_build_query(array_merge(request()->except('borrados','ocultos'), request('borrados') ? [] : ['borrados' => 1])) }}"
           title="Borrados"
           class="{{ $tieneHidden ? '' : 'ml-auto ' }}p-1.5 rounded-lg border transition-colors {{ request('borrados') ? 'border-red-400 text-red-500 bg-red-50' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
            <i class="fas fa-trash text-base leading-none"></i>
        </a>
        @endif

        {{-- Campos ocultos para preservar filtros activos al buscar con 'q' --}}
        @foreach($camposFiltrables as $campo)
            @php $param = 'f_' . $campo->name; @endphp
            @if($campo->type === 'fecha')
                @if(request($param . '_desde'))
                    <input type="hidden" name="{{ $param }}_desde" value="{{ request($param . '_desde') }}">
                @endif
                @if(request($param . '_hasta'))
                    <input type="hidden" name="{{ $param }}_hasta" value="{{ request($param . '_hasta') }}">
                @endif
            @else
                @if(request($param) !== null && request($param) !== '')
                    <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                @endif
            @endif
        @endforeach

        {{-- Modal de filtros --}}
        @if($camposFiltrables->isNotEmpty())
        <div x-show="modalFiltros"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center"
             @click.self="modalFiltros = false"
             style="display:none">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40"></div>

            {{-- Panel --}}
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4"
                 @click.stop>

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Filtros</h3>
                    <button type="button" @click="modalFiltros = false"
                            class="text-gray-300 hover:text-gray-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 grid grid-cols-2 gap-4">
                    @foreach($camposFiltrables as $campo)
                        @php $param = 'f_' . $campo->name; @endphp

                        @if($campo->type === 'select')
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <select name="{{ $param }}"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <option value="">Todos</option>
                                    @foreach($campo->getOptions() as $opt)
                                        <option value="{{ $opt }}" {{ request($param) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                        @elseif($campo->type === 'tinyint')
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <select name="{{ $param }}"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <option value="">Todos</option>
                                    <option value="1" {{ request($param) === '1' ? 'selected' : '' }}>Sí</option>
                                    <option value="0" {{ request($param) === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>

                        @elseif($campo->type === 'fecha')
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <input type="text"
                                       id="rango-{{ $campo->name }}"
                                       placeholder="Selecciona un rango de fechas..."
                                       autocomplete="off"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 cursor-pointer">
                                <input type="hidden" name="{{ $param }}_desde" id="{{ $param }}_desde" value="{{ request($param . '_desde') }}">
                                <input type="hidden" name="{{ $param }}_hasta" id="{{ $param }}_hasta" value="{{ request($param . '_hasta') }}">
                            </div>

                        @elseif(in_array($campo->type, ['id', 'desplegable']))
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <select name="{{ $param }}"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <option value="">Todos</option>
                                    @foreach($fkOptions[$campo->name] ?? [] as $id => $nombre)
                                        <option value="{{ $id }}" {{ request($param) == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="modalFiltros = false"
                            class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-lg transition-colors">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
        @endif

    </form>

    @if($tablaNoDisponible)
    <div class="mb-4 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl">
        <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <div>
            El modo tabla editable no está disponible porque
            {{ $requiredHidden->count() === 1 ? 'el campo obligatorio' : 'los campos obligatorios' }}
            <strong>{{ $requiredHidden->pluck('label')->join(', ') }}</strong>
            no {{ $requiredHidden->count() === 1 ? 'está visible' : 'están visibles' }} en el listado.
            Actívalo{{ $requiredHidden->count() === 1 ? '' : 's' }} en la configuración de campos de la tabla.
        </div>
    </div>
    @endif

    {{-- Vista galería --}}
    @if($modoGaleria)
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($registros->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-gray-400">Sin registros</div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1px; background:#e5e7eb;">
                @foreach($registros as $registro)
                    @php
                        $fotoPath = $registro->{$campoFile->name} ?? null;
                        $tareaLabel = null;
                        $tareaUrl   = null;
                        foreach($camposFiltrablesGaleria as $fkCampo) {
                            $val = $registro->{$fkCampo->name} ?? null;
                            if ($val && isset($fkOptions[$fkCampo->name][$val])) {
                                $tareaLabel = $fkOptions[$fkCampo->name][$val];
                                $tareaUrl   = route('ficha', [$project->slug, $fkRefTablas[$fkCampo->name], $val]);
                                break;
                            }
                        }
                    @endphp
                    <div class="bg-white flex flex-col">
                        @if($fotoPath)
                            <a href="{{ Storage::url($fotoPath) }}" target="_blank" class="block overflow-hidden" style="height:150px;">
                                <img src="{{ Storage::url($fotoPath) }}" alt="foto"
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-200">
                            </a>
                        @else
                            <div class="flex items-center justify-center bg-gray-50 text-gray-300" style="height:150px;">
                                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="px-2 py-1.5 text-center" style="min-height:36px;">
                            @if($tareaLabel && $tareaUrl)
                                <a href="{{ $tareaUrl }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 hover:underline line-clamp-2 leading-tight">
                                    {{ $tareaLabel }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Paginación --}}
            @if($registros->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $registros->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
    @else
    {{-- Tabla de datos --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto" @if($modoTabla) x-data="newRowForm()" @endif>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        @if($modoTabla)
                            <th class="w-8"></th>
                        @endif
                        @foreach($campos as $campo)
                            @php
                                $isActive = $sortField === $campo->name;
                                $nextDir  = ($isActive && $sortDir === 'asc') ? 'desc' : 'asc';
                                $sortUrl  = request()->fullUrlWithQuery(['sort' => $campo->name, 'dir' => $nextDir, 'page' => null]);
                            @endphp
                            <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap">
                                <a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 {{ $isActive ? 'text-orange-500' : 'text-gray-400 hover:text-gray-600' }}">
                                    {{ $campo->label }}
                                    @if($isActive)
                                        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            @if($sortDir === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            @endif
                                        </svg>
                                    @else
                                        <svg class="w-3 h-3 shrink-0 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/>
                                        </svg>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($registros as $registro)
                        @if($modoTabla)
                            {{-- ── FILA EDITABLE ── --}}
                            <tr class="hover:bg-gray-50 group">
                                {{-- Indicador de estado de guardado --}}
                                <td class="pl-2 pr-0 py-2 w-8 text-center">
                                    <span id="state-{{ $registro->id }}"
                                          class="inline-block w-1.5 h-1.5 rounded-full bg-transparent transition-colors"></span>
                                </td>

                                @foreach($campos as $campo)
                                    @php
                                        $valor     = $registro->{$campo->name} ?? '';
                                        $endpoint  = route('ficha.update-field', [$project->slug, $projectTable->name, $registro->id]);
                                        $readonly  = in_array($campo->type, ['file']);
                                    @endphp
                                    <td class="px-1 py-1"
                                        x-data="{
                                            editing: false,
                                            original: {{ json_encode((string) $valor) }},
                                            value: {{ json_encode((string) $valor) }},
                                            saving: false,
                                            async save() {
                                                if (this.value === this.original) { this.editing = false; return; }
                                                this.saving = true;
                                                const dot = document.getElementById('state-{{ $registro->id }}');
                                                dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-amber-400 transition-colors';
                                                try {
                                                    const r = await fetch('{{ $endpoint }}', {
                                                        method: 'PATCH',
                                                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                                        body: JSON.stringify({field:'{{ $campo->name }}', value: this.value})
                                                    });
                                                    if (r.ok) {
                                                        this.original = this.value;
                                                        dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-green-400 transition-colors';
                                                        setTimeout(() => dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-transparent transition-colors', 1500);
                                                    } else {
                                                        this.value = this.original;
                                                        dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-red-400 transition-colors';
                                                        setTimeout(() => dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-transparent transition-colors', 2000);
                                                    }
                                                } catch(e) {
                                                    this.value = this.original;
                                                }
                                                this.saving = false;
                                                this.editing = false;
                                            }
                                        }">

                                        @if($readonly)
                                            <span class="block px-2 py-1 text-gray-400 text-xs">—</span>
                                        @elseif($campo->type === 'select')
                                            <select x-model="value" @change="save()"
                                                    class="w-full text-sm border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value=""></option>
                                                @foreach($campo->getOptions() as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($campo->type === 'tinyint')
                                            <select x-model="value" @change="save()"
                                                    class="w-full text-sm border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value="0">No</option>
                                                <option value="1">Sí</option>
                                            </select>
                                        @elseif($campo->type === 'fecha')
                                            <input type="date" x-model="value"
                                                   @change="save()"
                                                   class="w-full text-sm border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors">
                                        @elseif(in_array($campo->type, ['id', 'desplegable']))
                                            <select x-model="value" @change="save()"
                                                    class="w-full text-sm border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value=""></option>
                                                @foreach($fkOptions[$campo->name] ?? [] as $fkId => $fkNombre)
                                                    <option value="{{ $fkId }}" {{ (string)$valor === (string)$fkId ? 'selected' : '' }}>{{ $fkNombre }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($campo->type === 'multiusuario')
                                            <select multiple
                                                    x-init="(() => { try { const sel = JSON.parse(value || '[]').map(String); $el.querySelectorAll('option').forEach(o => o.selected = sel.includes(o.value)); } catch(e){} })()"
                                                    @change="value = JSON.stringify(Array.from($el.selectedOptions).map(o => o.value)); save()"
                                                    class="w-full text-sm border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors">
                                                @foreach($projectUsuarios as $pu)
                                                    <option value="{{ $pu['id'] }}">{{ $pu['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($campo->type === 'text')
                                            <div @click="editing = true">
                                                <span x-show="!editing" class="block px-2 py-1 min-w-16 min-h-7 rounded cursor-text hover:bg-gray-50 truncate max-w-xs" x-text="value || '—'"></span>
                                                <textarea x-show="editing" x-model="value"
                                                          @blur="save()" @keydown.escape="value = original; editing = false"
                                                          x-init="$watch('editing', v => v && $nextTick(() => $el.focus()))"
                                                          rows="2"
                                                          class="w-full text-sm border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none resize-none"></textarea>
                                            </div>
                                        @else
                                            <div @click="editing = true">
                                                <span x-show="!editing" class="block px-2 py-1 min-w-16 min-h-7 rounded cursor-text hover:bg-gray-50" x-text="value || '—'"></span>
                                                <input x-show="editing" x-model="value" type="{{ $campo->type === 'email' ? 'email' : ($campo->type === 'time' ? 'time' : 'text') }}"
                                                       @blur="save()" @keydown.enter="$el.blur()" @keydown.escape="value = original; editing = false"
                                                       x-init="$watch('editing', v => v && $nextTick(() => $el.focus()))"
                                                       class="w-full text-sm border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                            </div>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-2 py-2 text-right" x-data="{ open: false }">
                                    <button @click="open = !open" @click.outside="open = false"
                                            class="p-1 rounded text-gray-300 hover:text-gray-600 hover:bg-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                        </svg>
                                    </button>
                                    <div x-show="open"
                                         class="absolute right-6 mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm">
                                        <a href="{{ route('ficha', [$project->slug, $projectTable->name, $registro->id]) }}"
                                           class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                            Ver ficha
                                        </a>
                                        @if($canEdit)
                                        @if($tieneHidden)
                                        <form method="POST" action="{{ route('ficha.archive', [$project->slug, $projectTable->name, $registro->id]) }}">
                                            @csrf @method('PATCH')
                                            <button class="w-full flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                                {{ $registro->hidden ? 'Mostrar' : 'Archivar' }}
                                            </button>
                                        </form>
                                        @endif
                                        @if($tieneDeleted)
                                        <button type="button"
                                                onclick="confirmarBorrar('{{ route('ficha.borrar', [$project->slug, $projectTable->name, $registro->id]) }}', '{{ addslashes($registro->nombre ?? '') }}', {{ $registro->deleted ? 'true' : 'false' }})"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-red-500 hover:bg-red-50">
                                            {{ $registro->deleted ? 'Restaurar' : 'Borrar' }}
                                        </button>
                                        @endif
                                        @if($projectTable->permite_eliminar)
                                        <button type="button"
                                                onclick="confirmarEliminar('{{ route('ficha.eliminar', [$project->slug, $projectTable->name, $registro->id]) }}', '{{ addslashes($registro->nombre ?? '') }}')"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-red-700 hover:bg-red-50 font-medium">
                                            Eliminar
                                        </button>
                                        @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @else
                            {{-- ── FILA NORMAL (solo lectura) ── --}}
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('ficha', [$project->slug, $projectTable->name, $registro->id]) }}'">
                                @foreach($campos as $campo)
                                    <td class="px-4 py-3 text-gray-700" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $registro->{$campo->name} ?? '' }}">
                                        @include('partials.cell', ['campo' => $campo, 'valor' => $registro->{$campo->name} ?? null, 'fkOptions' => $fkOptions, 'usuariosMap' => $usuariosMap ?? []])
                                    </td>
                                @endforeach

                                <td class="px-2 py-3 text-right" onclick="event.stopPropagation()" x-data="{ open: false }">
                                    <button @click="open = !open" @click.outside="open = false"
                                            class="p-1 rounded text-gray-300 hover:text-gray-600 hover:bg-gray-100">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                        </svg>
                                    </button>
                                    <div x-show="open"
                                         class="absolute right-6 mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm">
                                        <a href="{{ route('ficha', [$project->slug, $projectTable->name, $registro->id]) }}"
                                           class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                            Ver ficha
                                        </a>
                                        @if($canEdit)
                                        @if($tieneHidden)
                                        <form method="POST" action="{{ route('ficha.archive', [$project->slug, $projectTable->name, $registro->id]) }}">
                                            @csrf @method('PATCH')
                                            <button class="w-full flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                                {{ $registro->hidden ? 'Mostrar' : 'Archivar' }}
                                            </button>
                                        </form>
                                        @endif
                                        @if($tieneDeleted)
                                        <button type="button"
                                                onclick="confirmarBorrar('{{ route('ficha.borrar', [$project->slug, $projectTable->name, $registro->id]) }}', '{{ addslashes($registro->nombre ?? '') }}', {{ $registro->deleted ? 'true' : 'false' }})"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-red-500 hover:bg-red-50">
                                            {{ $registro->deleted ? 'Restaurar' : 'Borrar' }}
                                        </button>
                                        @endif
                                        @if($projectTable->permite_eliminar)
                                        <button type="button"
                                                onclick="confirmarEliminar('{{ route('ficha.eliminar', [$project->slug, $projectTable->name, $registro->id]) }}', '{{ addslashes($registro->nombre ?? '') }}')"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-red-700 hover:bg-red-50 font-medium">
                                            Eliminar
                                        </button>
                                        @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $campos->count() + 2 }}" class="px-4 py-12 text-center text-gray-400">
                                No hay registros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                    {{-- ── FILA NUEVA (solo modo tabla) ── --}}
                    @if($modoTabla)
                    <tbody>
                        {{-- Fila con botón + --}}
                        <tr x-show="!newRow" x-cloak>
                            <td colspan="{{ $campos->count() + 2 }}" class="px-4 py-2">
                                <button @click="newRow = true; $nextTick(() => $el.closest('table').querySelector('tbody:last-child input,tbody:last-child select,tbody:last-child textarea')?.focus())"
                                        class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-orange-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                    Nuevo registro
                                </button>
                            </td>
                        </tr>

                        {{-- Fila editable --}}
                        <tr x-show="newRow" x-cloak class="bg-orange-50/50">
                            <td class="w-8 pl-2 pr-0 py-1"></td>

                            @foreach($campos as $campo)
                            <td class="px-1 py-1">
                                @if($campo->type === 'select')
                                    <select x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value=""></option>
                                        @foreach($campo->getOptions() as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'tinyint')
                                    <select x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </select>
                                @elseif($campo->type === 'fecha')
                                    <input type="date" x-model="fields['{{ $campo->name }}']"
                                           @keydown.enter="save()"
                                           class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                @elseif(in_array($campo->type, ['id', 'desplegable']))
                                    <select x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value=""></option>
                                        @foreach($fkOptions[$campo->name] ?? [] as $fkId => $fkNombre)
                                            <option value="{{ $fkId }}">{{ $fkNombre }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'multiusuario')
                                    <select multiple x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        @foreach($projectUsuarios as $pu)
                                            <option value="{{ $pu['id'] }}">{{ $pu['label'] }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'text')
                                    <textarea x-model="fields['{{ $campo->name }}']" rows="2"
                                              @keydown.escape="cancel()"
                                              class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none resize-none"></textarea>
                                @else
                                    <input type="{{ $campo->type === 'email' ? 'email' : ($campo->type === 'time' ? 'time' : 'text') }}"
                                           x-model="fields['{{ $campo->name }}']"
                                           @keydown.enter="save()"
                                           @keydown.escape="cancel()"
                                           class="w-full text-sm border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                @endif
                            </td>
                            @endforeach

                            <td class="px-2 py-1 text-right whitespace-nowrap">
                                <button @click="save()" :disabled="saving"
                                        class="inline-flex items-center justify-center w-7 h-7 bg-orange-500 hover:bg-orange-600 text-white rounded transition-colors disabled:opacity-50">
                                    <svg x-show="!saving" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                    </svg>
                                </button>
                                <button @click="cancel()"
                                        class="inline-flex items-center justify-center w-7 h-7 bg-gray-100 hover:bg-gray-200 text-gray-500 rounded transition-colors ml-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    @endif
            </table>
        </div>

        {{-- Paginación y contador --}}
        @if($registros->hasPages() || $registros->total() > 0)
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-xs text-gray-400">
                <span>{{ $registros->total() }} registros</span>
                {{ $registros->links('partials.pagination') }}
            </div>
        @endif
    </div>
    @endif {{-- fin @else modoGaleria --}}

</x-app-layout>

{{-- Modal confirmación borrar (soft delete) --}}
<div id="modal-borrar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalBorrar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-trash text-red-500"></i>
                </div>
                <h3 id="modal-borrar-titulo" class="text-base font-semibold text-gray-800"></h3>
            </div>
            <p id="modal-borrar-texto" class="text-sm text-gray-500 mb-6"></p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalBorrar()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button id="modal-borrar-btn" onclick="ejecutarBorrar()"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal confirmación eliminar (hard delete) --}}
<div id="modal-eliminar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalEliminar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-200 flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-600"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">Eliminar registro definitivamente</h3>
            </div>
            <p id="modal-eliminar-texto" class="text-sm text-gray-500 mb-6"></p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalEliminar()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="ejecutarEliminar()"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Eliminar definitivamente
                </button>
            </div>
        </div>
    </div>
</div>

<form id="form-borrar-listado" method="POST" class="hidden">
    @csrf @method('PATCH')
</form>

<form id="form-eliminar-listado" method="POST" class="hidden">
    @csrf @method('DELETE')
</form>

<script>
@if($modoTabla)
function newRowForm() {
    return {
        newRow: false,
        saving: false,
        fields: {!! json_encode(collect($campos)->mapWithKeys(fn($c) => [$c->name => $c->type === 'multiusuario' ? [] : ''])) !!},
        async save() {
            this.saving = true;
            try {
                const r = await fetch('{{ route('ficha.store', [$project->slug, $projectTable->name]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.fields)
                });
                if (r.ok) window.location = '{{ request()->fullUrl() }}';
            } catch(e) {}
            this.saving = false;
        },
        cancel() {
            this.newRow = false;
            this.fields = {!! json_encode(collect($campos)->mapWithKeys(fn($c) => [$c->name => ''])) !!};
        }
    };
}
@endif

function confirmarBorrar(url, nombre, isDeleted) {
    const titulo = document.getElementById('modal-borrar-titulo');
    const texto  = document.getElementById('modal-borrar-texto');
    const btn    = document.getElementById('modal-borrar-btn');

    if (isDeleted) {
        titulo.textContent = 'Restaurar registro';
        texto.innerHTML    = nombre ? `¿Quieres restaurar <strong>${nombre}</strong>?` : '¿Quieres restaurar este registro?';
        btn.className      = 'px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-green-500 hover:bg-green-600';
        btn.textContent    = 'Restaurar';
    } else {
        titulo.textContent = 'Borrar registro';
        texto.innerHTML    = nombre
            ? `¿Seguro que quieres borrar <strong>${nombre}</strong>? Podrás recuperarlo desde la vista de borrados.`
            : '¿Seguro que quieres borrar este registro?';
        btn.className      = 'px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-red-500 hover:bg-red-600';
        btn.textContent    = 'Borrar';
    }

    document.getElementById('form-borrar-listado').action = url;
    document.getElementById('modal-borrar').classList.remove('hidden');
}

function ejecutarBorrar() {
    document.getElementById('form-borrar-listado').submit();
}

function cerrarModalBorrar() {
    document.getElementById('modal-borrar').classList.add('hidden');
}

function confirmarEliminar(url, nombre) {
    const texto = document.getElementById('modal-eliminar-texto');
    texto.innerHTML = nombre
        ? `¿Seguro que quieres eliminar <strong>${nombre}</strong> de forma permanente? <span style="color:#dc2626;font-weight:500">Esta acción no se puede deshacer.</span>`
        : '¿Seguro que quieres eliminar este registro de forma permanente? Esta acción no se puede deshacer.';

    document.getElementById('form-eliminar-listado').action = url;
    document.getElementById('modal-eliminar').classList.remove('hidden');
}

function ejecutarEliminar() {
    document.getElementById('form-eliminar-listado').submit();
}

function cerrarModalEliminar() {
    document.getElementById('modal-eliminar').classList.add('hidden');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { cerrarModalBorrar(); cerrarModalEliminar(); }
});
</script>

{{-- Flatpickr para rangos de fecha --}}
@php $camposFecha = $campos->filter(fn($c) => $c->type === 'fecha'); @endphp
@if($camposFecha->isNotEmpty())
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @foreach($camposFecha as $campo)
    @php
        $param  = 'f_' . $campo->name;
        $desde  = request($param . '_desde');
        $hasta  = request($param . '_hasta');
        $defVal = $desde ? ($hasta ? $desde . ' to ' . $hasta : $desde) : '';
    @endphp
    flatpickr('#rango-{{ $campo->name }}', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        locale: 'es',
        defaultDate: {{ $defVal ? json_encode(explode(' to ', $defVal)) : 'null' }},
        onChange: function(dates) {
            document.getElementById('f_{{ $campo->name }}_desde').value = dates[0] ? flatpickr.formatDate(dates[0], 'Y-m-d') : '';
            document.getElementById('f_{{ $campo->name }}_hasta').value = dates[1] ? flatpickr.formatDate(dates[1], 'Y-m-d') : '';
        }
    });
    @endforeach
});
</script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('table');
    if (!table) return;

    // Medir anchos naturales sin w-full
    table.style.tableLayout = 'auto';
    table.style.width = 'auto';
    table.offsetHeight; // forzar reflow

    const ths = table.querySelectorAll('thead th');
    const widths = Array.from(ths).map(th => Math.min(th.offsetWidth, 500));

    // Aplicar layout fijo con los anchos medidos
    table.style.tableLayout = 'fixed';
    table.style.width = '100%';
    ths.forEach((th, i) => { th.style.width = widths[i] + 'px'; });
});
</script>
