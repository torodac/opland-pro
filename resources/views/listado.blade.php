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
        {{-- PyG: ir al importador --}}
        @if($projectTable->name === 'pyg_valores')
        <a href="{{ url('/vm/pyg_form') }}"
           title="Importar PyG"
           class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
            <i class="fa-solid fa-file-import"></i>
        </a>
        @endif
        {{-- Ejecutar reglas de clasificación (solo mb_movs_mapeo) --}}
        @if($projectTable->name === 'movs_mapeo')
        <button type="button" onclick="mmEjecutarPrevisualizar()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
            <i class="fa-solid fa-play"></i>
            Ejecutar
        </button>
        @endif
        {{-- Planificador (solo tareas_limpieza) --}}
        @if($projectTable->name === 'tareas_limpieza')
        <a href="{{ route('planificador-limpieza', $project->slug) }}"
           title="Planificador del día"
           class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M12 12v4m0 0l-2-2m2 2l2-2"/>
            </svg>
        </a>
        @endif
        {{-- Planificador (horarios) --}}
        @if($projectTable->name === 'horarios')
        <a href="{{ route('horario', $project->slug) }}"
           title="Planificador semanal"
           class="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                <line x1="8" y1="14" x2="8" y2="14" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="12" y1="14" x2="12" y2="14" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="16" y1="14" x2="16" y2="14" stroke-width="2.5" stroke-linecap="round"/>
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

        @if($canEdit || ($projectTable->name === 'fichaje' && $project->slug === 'vm'))
        <a href="{{ ($projectTable->name === 'fichaje' && $project->slug === 'vm') ? route('vm.fichaje_form.nuevo', $project->slug) : (($projectTable->name === 'facturas' && $project->slug === 'opland') ? route('opland.factura_form.nueva', $project->slug) : route('ficha.create', [$project->slug, $projectTable->name])) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo
        </a>
        @endif

        {{-- Generar cuotas (solo mb_cuotas): modal con concepto/ejercicio/tipo/módulo --}}
        @if($projectTable->name === 'cuotas' && $project->slug === 'mb')
        <button type="button" onclick="document.getElementById('gc-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-file-invoice-dollar text-orange-400"></i>
            Generar cuotas
        </button>
        <a href="{{ route('mb.cuotas.informe', $project->slug) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-file-pdf text-orange-400"></i>
            Informe PDF
        </a>
        @endif

        {{-- Generar pagos del mes (solo nf_pagos): modal pidiendo el mes --}}
        @if($projectTable->name === 'pagos' && $project->slug === 'nf')
        <button type="button" onclick="document.getElementById('gp-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-gears text-orange-400"></i>
            Generar pagos
        </button>
        @endif
        @endif

        {{-- Acciones (dropdown): exportar, importar, actualización masiva, copiar IDs --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                Acciones
                <i class="fas fa-chevron-down text-[10px] text-gray-400 ml-0.5"></i>
            </button>
            <div x-show="open" x-cloak @click="open = false"
                 class="absolute right-0 mt-1 w-60 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
                @php $qs = http_build_query(request()->except('page')); @endphp
                <a href="{{ route('excel.export', [$project->slug, $projectTable->name]) }}?tipo=listado&{{ $qs }}"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-filter text-orange-400 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Exportar listado</p>
                        <p class="text-xs text-gray-400">Columnas visibles y filtros aplicados</p>
                    </div>
                </a>
                <a href="{{ route('excel.export', [$project->slug, $projectTable->name]) }}?tipo=tabla"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-table text-blue-400 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Exportar tabla completa</p>
                        <p class="text-xs text-gray-400">Todas las columnas y registros</p>
                    </div>
                </a>
                <button type="button" @click="copiarIds()"
                        class="w-full flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50 text-left">
                    <i class="fas fa-copy text-gray-400 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Copiar IDs</p>
                        <p class="text-xs text-gray-400">Todos los que cumplen el filtro actual</p>
                    </div>
                </button>
                @if(auth()->user()?->isProjectAdmin($project))
                <a href="{{ route('excel.import-form', [$project->slug, $projectTable->name]) }}"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-file-upload text-blue-500 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Importar</p>
                    </div>
                </a>
                <a href="{{ route('ficha.bulk-edit-form', [$project->slug, $projectTable->name]) }}"
                   class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                    <i class="fas fa-layer-group text-purple-500 mt-0.5"></i>
                    <div>
                        <p class="font-medium text-gray-700">Actualización masiva</p>
                        <p class="text-xs text-gray-400">Aplica un valor a varios registros a la vez</p>
                    </div>
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $camposFiltrables = $campos->filter(fn($c) => in_array($c->type, ['select','tinyint','smallint','fecha','id','desplegable']));
        $filtrosActivos   = collect(request()->except(['q','ocultos','borrados','page','modo','stat']))->filter()->isNotEmpty();
    @endphp

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats vm_propiedades --}}
    @if($tablStats)
    @php $statActiva = request('stat'); @endphp

    @if($ejercicioCuotasSel ?? null)
    @php
        [$ejY1, $ejY2] = explode('-', $ejercicioCuotasSel);
        $ejPrev = ((int) $ejY1 - 1) . '-' . ((int) $ejY2 - 1);
        $ejNext = ((int) $ejY1 + 1) . '-' . ((int) $ejY2 + 1);
    @endphp
    <div class="flex items-center gap-2 mb-3">
        <span style="font-size:0.8rem;color:#7e93a1">Ejercicio</span>
        <a href="{{ request()->fullUrlWithQuery(['ejercicio' => $ejPrev, 'page' => null]) }}"
           style="width:26px;height:26px;border-radius:7px;border:1px solid #dce6ee;background:#fff;color:#52697a;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">‹</a>
        <span style="font-size:0.85rem;font-weight:700;color:#16232b;min-width:70px;text-align:center">{{ $ejercicioCuotasSel }}</span>
        <a href="{{ request()->fullUrlWithQuery(['ejercicio' => $ejNext, 'page' => null]) }}"
           style="width:26px;height:26px;border-radius:7px;border:1px solid #dce6ee;background:#fff;color:#52697a;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">›</a>
    </div>
    @endif

    @php
        // mb_movs_bancarios: numero de categorias variable (una por cada valor real de
        // mb_gastos_cuentas usado), asi que no encaja en el reparto fijo row 1/2 de las demas
        // tablas -- se resuelve aparte con una rejilla de columnas calculadas (ver mas abajo).
        $statsDinamicos = null;
        if (isset($tablStats['sin_clasificar'])) {
            $statsDinamicos = [
                'sin_clasificar' => ['label' => 'Sin clasificar', 'count' => $tablStats['sin_clasificar'], 'n' => $tablStats['sin_clasificar_n'], 'tooltip' => 'Movimientos del ejercicio seleccionado sin categoría asignada por ninguna regla.'],
            ];
            foreach ($tablStats['categorias'] as $cat) {
                $statsDinamicos['cat:' . $cat['id']] = ['label' => $cat['nombre'], 'count' => $cat['count'], 'n' => $cat['n'], 'tooltip' => null];
            }
        }
        $stats = $statsDinamicos ? [] : (isset($tablStats['emitido_anio'])
            ? [
                'emitido_anio'       => ['label' => 'Emitido ' . ($ejercicioCuotasSel ?? 'este año'),      'count' => $tablStats['emitido_anio'],       'row' => 1, 'tooltip' => 'Importe total emitido (suma de importe) de las cuotas del ejercicio seleccionado, sin contar Anuladas.'],
                'pendiente_anio'     => ['label' => 'Pendiente ' . ($ejercicioCuotasSel ?? 'este año'),    'count' => $tablStats['pendiente_anio'],     'row' => 1, 'tooltip' => 'Importe pendiente de las cuotas del ejercicio seleccionado (sin contar Anuladas). % = importe pendiente / importe total emitido del ejercicio.'],
                'cobrado_ejercicio'  => ['label' => 'Cobrado ' . ($ejercicioCuotasSel ?? 'este año'),        'count' => $tablStats['cobrado_ejercicio'],  'row' => 1, 'tooltip' => 'Cuotas del ejercicio seleccionado, cobradas (fecha_pago) dentro del propio ejercicio. % = importe cobrado / importe total emitido del ejercicio.'],
                'cobrado_anteriores' => ['label' => 'Cobrado anteriores',                                   'count' => $tablStats['cobrado_anteriores'], 'row' => 1, 'tooltip' => 'Cuotas de ejercicios anteriores al seleccionado, cobradas (fecha_pago) dentro del ejercicio seleccionado.'],
                'pendiente_total'    => ['label' => 'Pendiente total',       'count' => $tablStats['pendiente_total'], 'row' => 2, 'tooltip' => 'Importe pendiente de todas las cuotas, todos los ejercicios (sin contar Anuladas).'],
                'demandado_total'    => ['label' => 'Demandado total',       'count' => $tablStats['demandado_total'], 'row' => 2, 'tooltip' => 'Importe pendiente de las cuotas en estado Demandada, todos los ejercicios.'],
                'a_demandar'         => ['label' => 'A demandar',            'count' => $tablStats['a_demandar'],      'row' => 2, 'tooltip' => $aDemandarTooltip],
            ]
            : (isset($tablStats['en_curso'])
            ? [
                'en_curso' => ['label' => 'reservas en curso',                                               'count' => $tablStats['en_curso'],  'row' => 1, 'tooltip' => 'Reservas activas hoy (check-in pasado, check-out futuro)'],
                'manana'   => ['label' => 'checkins mañana ' . now()->addDay()->format('d/m'),              'count' => $tablStats['manana'],    'row' => 1, 'tooltip' => 'Reservas con check-in mañana'],
                'pasado'   => ['label' => 'checkins pasado mañana ' . now()->addDays(2)->format('d/m'),    'count' => $tablStats['pasado'],    'row' => 1, 'tooltip' => 'Reservas con check-in pasado mañana'],
            ]
            : [
                'pte_info'        => ['label' => 'Pte. información',     'count' => $tablStats['pte_info'],        'row' => 1, 'tooltip' => 'Propiedades activas y visibles a las que les falta la fecha de inicio o el tipo de renta.'],
                'posibles_bajas'  => ['label' => 'Posibles bajas',       'count' => $tablStats['posibles_bajas'],   'row' => 1, 'tooltip' => 'Propiedades activas sin sincronización con Icnea en las últimas 24 h ¿siguen estando en cartera o hay que borrarlas?'],
                'revisar_borrado' => ['label' => 'Revisar borrado',      'count' => $tablStats['revisar_borrado'],  'row' => 1, 'tooltip' => 'Propiedades marcadas como eliminadas que Icnea ha actualizado hoy ¿es correcto mantenerlas borradas?'],
                'ocultas'         => ['label' => 'Propiedades ocultas',  'count' => $tablStats['ocultas'],          'row' => 1, 'tooltip' => 'Propiedades archivadas — no se muestran en desplegables ni en otros módulos'],
                'sin_breezeway'   => ['label' => 'Sin ID Breezeway',      'count' => $tablStats['sin_breezeway'],    'row' => 1, 'tooltip' => 'Propiedades activas y visibles sin breezeway_home_id — no se sincronizarán tareas de limpieza/mantenimiento para ellas.'],
                'codigo_compartido' => ['label' => 'Código compartido',   'count' => $tablStats['codigo_compartido'], 'row' => 1, 'tooltip' => 'Propiedades activas cuyo código histórico (A3 o Icnea) coincide con el de otra propiedad — posible duplicado a revisar.'],
            ]));
    @endphp
    @if($statsDinamicos)
        {{-- mb_movs_bancarios: rejilla con tantas columnas como haga falta para no pasar de 2
             filas (perRow = mitad de los stats, redondeando hacia arriba). --}}
        @php $perRow = max(1, (int) ceil(count($statsDinamicos) / 2)); @endphp
        <div class="mb-4" style="display:grid;grid-template-columns:repeat({{ $perRow }}, minmax(110px, 1fr));gap:0.5rem;">
            @foreach($statsDinamicos as $key => $stat)
            @php $activa = $statActiva === $key; @endphp
            <a href="{{ request()->fullUrlWithQuery(['stat' => $activa ? null : $key, 'page' => null]) }}"
               style="background:{{ $activa ? '#fff7ed' : '#fff' }};border:1px solid {{ $activa ? '#fdba74' : '#dce6ee' }};border-radius:0.6rem;padding:0.5rem 0.65rem;text-decoration:none;transition:opacity .15s;box-sizing:border-box;min-width:0"
               class="hover:opacity-80">
                <div style="display:flex;align-items:baseline;gap:5px">
                    <span style="font-size:0.9rem;font-weight:700;color:#16232b;font-variant-numeric:tabular-nums">{{ number_format($stat['count'], 2, ',', '.') }} €</span>
                    <span style="font-size:0.6rem;color:#a8b7c1;font-variant-numeric:tabular-nums">({{ $stat['n'] }})</span>
                </div>
                <div style="font-size:0.65rem;font-weight:500;color:{{ $activa ? '#c2410c' : '#7e93a1' }};margin-top:0.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $stat['label'] }}">
                    {{ $stat['label'] }}
                </div>
            </a>
            @endforeach
        </div>
    @else
    @foreach([1, 2] as $rowNum)
        @php $statsRow = collect($stats)->filter(fn($s) => $s['row'] === $rowNum); @endphp
        @if($statsRow->isNotEmpty())
        <div class="flex gap-3 {{ $rowNum === 2 ? 'mb-4' : 'mb-3' }} flex-wrap">
            @foreach($statsRow as $key => $stat)
            @php $activa = $statActiva === $key; @endphp
            <a href="{{ request()->fullUrlWithQuery(['stat' => $activa ? null : $key, 'page' => null]) }}"
               style="background:{{ $activa ? '#fff7ed' : '#fff' }};border:1px solid {{ $activa ? '#fdba74' : '#dce6ee' }};border-radius:0.75rem;padding:0.875rem 1rem;text-decoration:none;transition:opacity .15s;width:200px;box-sizing:border-box"
               class="hover:opacity-80">
                <div style="font-size:1.15rem;font-weight:700;color:#16232b;font-variant-numeric:tabular-nums">{{ $stat['count'] }}</div>
                <div style="font-size:0.75rem;font-weight:500;color:{{ $activa ? '#c2410c' : '#7e93a1' }};margin-top:0.125rem;display:flex;align-items:center;gap:4px">
                    <span>{{ $stat['label'] }}</span>
                    @if(!empty($stat['tooltip']))
                    <span class="app-tooltip" onclick="event.preventDefault()">
                        <span style="font-size:0.7rem;flex-shrink:0">&#9432;</span>
                        {{-- a_demandar: la caja es ancha y esta fila de stats vive pegada arriba de la
                             pagina, asi que abrirla hacia arriba (comportamiento por defecto de
                             .app-tooltip-box) la deja tapada por la cabecera fija -- se abre hacia
                             abajo solo para esta. --}}
                        <span class="app-tooltip-box" style="{{ $key === 'a_demandar' ? 'width:26rem;text-align:left;white-space:pre-line;top:100%;bottom:auto;margin-top:6px;margin-bottom:0' : '' }}">{{ $stat['tooltip'] }}</span>
                    </span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    @endforeach
    @endif
    @endif

    @if($icneaSync ?? null)
    <div class="flex items-center gap-3 mb-3">
        <span class="text-xs text-gray-400">
            Última sincronización con Icnea: {{ $icneaSync['fecha'] }}
            @if($icneaSync['errores'] > 0)
                · <span class="text-red-400">{{ $icneaSync['errores'] }} errores</span>
            @endif
        </span>
        <form method="POST" action="{{ route('propiedades.sync-icnea', $project->slug) }}">
            @csrf
            <button type="submit" class="text-xs text-orange-500 hover:text-orange-700 hover:underline transition-colors">
                Sincronizar ahora
            </button>
        </form>
    </div>
    @endif

    @if(($breezewayPendientesHeader ?? null) && $breezewayPendientesHeader->isNotEmpty())
    <div class="text-xs text-gray-400 mb-3">
        Usuarios de Breezeway sin cuenta en Opland ({{ $breezewayPendientesHeader->count() }}):
        {{ $breezewayPendientesHeader->map(fn($p) => $p->nombre . ($p->email ? " ({$p->email})" : ' (sin email)'))->implode(', ') }}
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
                                        class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                                    <option value="">Todos</option>
                                    @foreach($campo->getOptions() as $opt)
                                        <option value="{{ $opt }}" {{ request($param) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                        @elseif(in_array($campo->type, ['tinyint', 'smallint']))
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <select name="{{ $param }}"
                                        class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
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
                                       class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 cursor-pointer">
                                <input type="hidden" name="{{ $param }}_desde" id="{{ $param }}_desde" value="{{ request($param . '_desde') }}">
                                <input type="hidden" name="{{ $param }}_hasta" id="{{ $param }}_hasta" value="{{ request($param . '_hasta') }}">
                            </div>

                        @elseif(in_array($campo->type, ['id', 'desplegable']))
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ $campo->label }}</label>
                                <select name="{{ $param }}"
                                        class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
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
                                // vm_fotos.id_tareas_mantenimiento: ir a la ficha personalizada de la
                                // tarea (tareas_mantenimiento_form) en vez de la ficha genérica.
                                $tareaUrl   = $fkCampo->name === 'id_tareas_mantenimiento' && $project->slug === 'vm'
                                    ? route('vm.tarea', [$project->slug, 'mantenimiento', $val])
                                    : route('ficha', [$project->slug, $fkRefTablas[$fkCampo->name], $val]);
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
    @php $esCuotasProvisional = $projectTable->name === 'cuotas' && $project->slug === 'mb'; @endphp
    @php $esNfPagos = $projectTable->name === 'pagos' && $project->slug === 'nf'; @endphp
    @php $esNfDocumentos = $projectTable->name === 'documentos' && $project->slug === 'nf'; @endphp
    {{-- Tabla de datos --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto" @if($modoTabla) x-data="newRowForm()" @endif>
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        @if($modoTabla)
                            <th class="w-8"></th>
                        @endif
                        @if($esCuotasProvisional)
                            <th class="w-8"></th>
                        @endif
                        @foreach($campos as $campo)
                            @php
                                $isActive = $sortField === $campo->name;
                                $nextDir  = ($isActive && $sortDir === 'asc') ? 'desc' : 'asc';
                                $sortUrl  = request()->fullUrlWithQuery(['sort' => $campo->name, 'dir' => $nextDir, 'page' => null]);
                                $colAlign = match(true) {
                                    in_array($campo->type, ['fecha', 'time'])      => 'text-center',
                                    in_array($campo->type, ['decimal', 'float'])   => 'text-right',
                                    default                                         => 'text-left',
                                };
                            @endphp
                            <th class="{{ $colAlign }} px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap">
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
                                        $colAlign  = match(true) {
                                            in_array($campo->type, ['fecha', 'time'])    => 'text-center',
                                            in_array($campo->type, ['decimal', 'float']) => 'text-right',
                                            default                                       => 'text-left',
                                        };
                                    @endphp
                                    <td class="px-1 py-1 {{ $colAlign }}"
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
                                                    class="w-full text-xs border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value=""></option>
                                                @foreach($campo->getOptions() as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($campo->type === 'tinyint')
                                            <select x-model="value" @change="save()"
                                                    class="w-full text-xs border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value="0">No</option>
                                                <option value="1">Sí</option>
                                            </select>
                                        @elseif($campo->type === 'smallint')
                                            <div class="px-2 py-1">
                                                <input type="checkbox" :checked="value === '1'"
                                                       @change="value = $event.target.checked ? '1' : '0'; save()"
                                                       class="w-4 h-4 accent-orange-500 cursor-pointer">
                                            </div>
                                        @elseif($campo->type === 'fecha')
                                            <input type="date" x-model="value"
                                                   @change="save()"
                                                   class="w-full text-xs border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors">
                                        @elseif(in_array($campo->type, ['id', 'desplegable']))
                                            <select x-model="value" @change="save()"
                                                    class="w-full text-xs border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors cursor-pointer">
                                                <option value=""></option>
                                                @foreach($fkOptions[$campo->name] ?? [] as $fkId => $fkNombre)
                                                    <option value="{{ $fkId }}" {{ (string)$valor === (string)$fkId ? 'selected' : '' }}>{{ $fkNombre }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($campo->type === 'multiusuario')
                                            <select multiple
                                                    x-init="(() => { try { const sel = JSON.parse(value || '[]').map(String); $el.querySelectorAll('option').forEach(o => o.selected = sel.includes(o.value)); } catch(e){} })()"
                                                    @change="value = JSON.stringify(Array.from($el.selectedOptions).map(o => o.value)); save()"
                                                    class="w-full text-xs border border-transparent hover:border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-transparent focus:bg-white outline-none transition-colors">
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
                                                          class="w-full text-xs border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none resize-none"></textarea>
                                            </div>
                                        @else
                                            <div @click="editing = true">
                                                <span x-show="!editing" class="block px-2 py-1 min-w-16 min-h-7 rounded cursor-text hover:bg-gray-50" x-text="value || '—'"></span>
                                                <input x-show="editing" x-model="value" type="{{ $campo->type === 'email' ? 'email' : ($campo->type === 'time' ? 'time' : 'text') }}"
                                                       @blur="save()" @keydown.enter="$el.blur()" @keydown.escape="value = original; editing = false"
                                                       x-init="$watch('editing', v => v && $nextTick(() => $el.focus()))"
                                                       class="w-full text-xs border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none">
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
                                        <a href="{{ ($projectTable->name === 'facturas' && $project->slug === 'opland') ? route('opland.factura_form.show', [$project->slug, $registro->id]) : (in_array($projectTable->name, ['tareas_limpieza','tareas_mantenimiento','tareas_piscinas']) ? url('/vm/tareas_' . ['tareas_limpieza'=>'limpieza','tareas_mantenimiento'=>'mantenimiento','tareas_piscinas'=>'piscina'][$projectTable->name] . '_form/' . $registro->id) : (($projectTable->name === 'clientes' && $project->slug === 'nf') ? route('nf.clientes_form', [$project->slug, $registro->id]) : route('ficha', [$project->slug, $projectTable->name, $registro->id]))) }}"
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
                                onclick="window.location='{{ $projectTable->name === 'fichaje' ? route('vm.fichaje_form', [$project->slug, $registro->id]) : ($projectTable->name === 'usuarios' && $project->slug === 'vm' ? route('vm.usuario_form', [$project->slug, $registro->id]) : (($projectTable->name === 'facturas' && $project->slug === 'opland') ? route('opland.factura_form.show', [$project->slug, $registro->id]) : (in_array($projectTable->name, ['tareas_limpieza','tareas_mantenimiento','tareas_piscinas']) ? url('/vm/tareas_' . ['tareas_limpieza'=>'limpieza','tareas_mantenimiento'=>'mantenimiento','tareas_piscinas'=>'piscina'][$projectTable->name] . '_form/' . $registro->id) : (($projectTable->name === 'clientes' && $project->slug === 'nf') ? route('nf.clientes_form', [$project->slug, $registro->id]) : route('ficha', [$project->slug, $projectTable->name, $registro->id]))))) }}'">
                                @if($esCuotasProvisional)
                                    <td class="pl-3 pr-0 py-3 w-8" onclick="event.stopPropagation()">
                                        <button type="button" onclick="toggleCuotaHistorico({{ $registro->id }}, this)"
                                                class="p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100">
                                            <svg id="hist-icon-{{ $registro->id }}" class="w-4 h-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </td>
                                @endif
                                @foreach($campos as $campo)
                                    @php
                                        $colAlign = match(true) {
                                            in_array($campo->type, ['fecha', 'time'])    => 'text-center',
                                            in_array($campo->type, ['decimal', 'float']) => 'text-right',
                                            default                                        => 'text-left',
                                        };
                                    @endphp
                                    <td class="px-4 py-3 text-gray-700 {{ $colAlign }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;{{ in_array($campo->type, ['fecha','time']) ? 'min-width:90px' : '' }}">
                                        @if($projectTable->name === 'movs_bancarios' && $campo->name === 'id_gastos_cuentas' && empty($registro->id_gastos_cuentas))
                                            {{-- Categoria vacia: atajo para clasificar en 1 clic sin abrir la ficha -- las dos
                                                 categorias mas frecuentes entre "Sin clasificar" (Gastos = PTE REVISIÓN id 116,
                                                 Cuotas = Ingresos cuotas id 52). Si ya tiene categoria, se ve normal (nombre). --}}
                                            <div class="flex gap-1" id="mb-cat-cell-{{ $registro->id }}" onclick="event.stopPropagation()">
                                                <button type="button" onclick="mbSetCategoria({{ $registro->id }}, 116, 'PTE REVISIÓN', this)"
                                                        style="background:#fed7aa;color:#9a3412;border:none;border-radius:0.375rem;padding:0.2rem 0.55rem;font-size:0.7rem;font-weight:600;cursor:pointer;white-space:nowrap">Gastos</button>
                                                <button type="button" onclick="mbSetCategoria({{ $registro->id }}, 52, 'Ingresos cuotas', this)"
                                                        style="background:#bfdbfe;color:#1e40af;border:none;border-radius:0.375rem;padding:0.2rem 0.55rem;font-size:0.7rem;font-weight:600;cursor:pointer;white-space:nowrap">Cuotas</button>
                                            </div>
                                        @else
                                            @include('partials.cell', ['campo' => $campo, 'valor' => $registro->{$campo->name} ?? null, 'fkOptions' => $fkOptions, 'usuariosMap' => $usuariosMap ?? []])
                                        @endif
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
                                        <a href="{{ ($projectTable->name === 'facturas' && $project->slug === 'opland') ? route('opland.factura_form.show', [$project->slug, $registro->id]) : (in_array($projectTable->name, ['tareas_limpieza','tareas_mantenimiento','tareas_piscinas']) ? url('/vm/tareas_' . ['tareas_limpieza'=>'limpieza','tareas_mantenimiento'=>'mantenimiento','tareas_piscinas'=>'piscina'][$projectTable->name] . '_form/' . $registro->id) : (($projectTable->name === 'clientes' && $project->slug === 'nf') ? route('nf.clientes_form', [$project->slug, $registro->id]) : route('ficha', [$project->slug, $projectTable->name, $registro->id]))) }}"
                                           class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                            Ver ficha
                                        </a>
                                        @if($esNfPagos && $registro->id_estado_pagos == 1)
                                        <form method="POST" action="{{ route('nf.pagos.pagar', [$project->slug, $registro->id, 2]) }}">
                                            @csrf
                                            <button class="w-full flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                                Confirmar Banco
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('nf.pagos.pagar', [$project->slug, $registro->id, 1]) }}">
                                            @csrf
                                            <button class="w-full flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                                Confirmar Efectivo
                                            </button>
                                        </form>
                                        @if(!empty($nfClientesTelefono[$registro->id_clientes] ?? null))
                                        <a href="https://wa.me/34{{ $nfClientesTelefono[$registro->id_clientes] }}?text={{ rawurlencode('Hola, te escribo para recordarte que la cuota de este mes está pendiente. Abona tu cuota del 25 al 30 de cada mes. Quedo a la espera de que te pongas en contacto conmigo. Saludos') }}"
                                           target="_blank"
                                           class="w-full flex items-center gap-2 px-3 py-2 text-green-600 hover:bg-green-50">
                                            Recordatorio WhatsApp
                                        </a>
                                        @endif
                                        @endif
                                        @if($esNfDocumentos && $registro->id_estado_documento == 1)
                                        <form method="POST" action="{{ route('nf.documentos.enviar', [$project->slug, $registro->id]) }}">
                                            @csrf
                                            <button class="w-full flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                                Enviar por email
                                            </button>
                                        </form>
                                        @endif
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
                            @if($esCuotasProvisional)
                                <tr id="hist-row-{{ $registro->id }}" style="display:none">
                                    <td colspan="{{ $campos->count() + 2 }}" class="bg-gray-50 px-6 py-3">
                                        <div id="hist-body-{{ $registro->id }}" class="text-xs text-gray-400">Cargando histórico…</div>
                                    </td>
                                </tr>
                            @endif
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
                                            class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value=""></option>
                                        @foreach($campo->getOptions() as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'tinyint')
                                    <select x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </select>
                                @elseif($campo->type === 'smallint')
                                    <div class="px-2 py-1">
                                        <input type="checkbox" x-model="fields['{{ $campo->name }}']"
                                               class="w-4 h-4 accent-orange-500 cursor-pointer">
                                    </div>
                                @elseif($campo->type === 'fecha')
                                    <input type="date" x-model="fields['{{ $campo->name }}']"
                                           @keydown.enter="save()"
                                           class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                @elseif(in_array($campo->type, ['id', 'desplegable']))
                                    <select x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        <option value=""></option>
                                        @foreach($fkOptions[$campo->name] ?? [] as $fkId => $fkNombre)
                                            <option value="{{ $fkId }}">{{ $fkNombre }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'multiusuario')
                                    <select multiple x-model="fields['{{ $campo->name }}']"
                                            class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
                                        @foreach($projectUsuarios as $pu)
                                            <option value="{{ $pu['id'] }}">{{ $pu['label'] }}</option>
                                        @endforeach
                                    </select>
                                @elseif($campo->type === 'text')
                                    <textarea x-model="fields['{{ $campo->name }}']" rows="2"
                                              @keydown.escape="cancel()"
                                              class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none resize-none"></textarea>
                                @else
                                    <input type="{{ $campo->type === 'email' ? 'email' : ($campo->type === 'time' ? 'time' : 'text') }}"
                                           x-model="fields['{{ $campo->name }}']"
                                           @keydown.enter="save()"
                                           @keydown.escape="cancel()"
                                           class="w-full text-xs border border-gray-200 focus:border-orange-300 focus:ring-2 focus:ring-orange-200 rounded px-2 py-1 bg-white outline-none">
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

@if($projectTable->name === 'cuotas' && $project->slug === 'mb')
{{-- Modal "Generar cuotas": concepto/ejercicio/tipo/módulo, con cálculo en vivo del importe total --}}
<div id="gc-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('gc-modal').classList.add('hidden')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/2 min-w-80">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Generar nuevas cuotas</h3>
                <p class="text-xs text-gray-400 mt-0.5">Se emitirá una cuota por cada una de las {{ $countViviendasActivas ?? 0 }} viviendas activas.</p>
            </div>
            <form method="POST" action="{{ route('mb.cuotas.generar', $project->slug) }}" class="px-5 py-4 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Concepto</label>
                    <input type="text" name="concepto" required maxlength="255"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ejercicio</label>
                        <input type="text" name="ejercicio" value="{{ $ejercicioActualDefault ?? '' }}" required maxlength="9"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tipo de cuota</label>
                        <select name="tipo_cuota" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                            <option value="Ordinaria">Ordinaria</option>
                            <option value="Extraordinaria">Extraordinaria</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Módulo (€ por m² de cuota)</label>
                    <input type="number" step="0.000001" min="0.000001" name="modulo" required
                           oninput="document.getElementById('gc-total').textContent = ((parseFloat(this.value)||0) * {{ (float) ($sumSuperficieViviendas ?? 0) }}).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €'"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div class="bg-gray-50 rounded-lg px-3 py-2.5 text-sm flex items-center justify-between">
                    <span class="text-gray-500">Total a emitir</span>
                    <span id="gc-total" class="font-semibold text-gray-800">0,00 €</span>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="document.getElementById('gc-modal').classList.add('hidden')"
                            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-orange-500 hover:bg-orange-600">
                        Generar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal "Generar pagos" (nf_pagos): crea los pagos pendientes del mes indicado para los contratos activos --}}
@if($projectTable->name === 'pagos' && $project->slug === 'nf')
<div id="gp-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('gp-modal').classList.add('hidden')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Generar pagos del mes</h3>
                <p class="text-xs text-gray-400 mt-0.5">Crea un pago pendiente para cada contrato activo ese mes que todavía no tenga uno.</p>
            </div>
            <form method="POST" action="{{ route('nf.pagos.generar', $project->slug) }}" class="px-5 py-4 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Mes</label>
                    <input type="month" name="mes" value="{{ now()->format('Y-m') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="document.getElementById('gp-modal').classList.add('hidden')"
                            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-orange-500 hover:bg-orange-600">
                        Generar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

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
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors bg-red-500 hover:bg-red-600">
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
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6 max-h-[90vh] overflow-y-auto">
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
                        class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors">
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

<script>
async function copiarIds() {
    const qs = new URLSearchParams(window.location.search);
    qs.delete('page');
    const url = '{{ route('listado.ids', [$project->slug, $projectTable->name]) }}?' + qs.toString();

    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || !data.ids) { alert('No se pudieron obtener los IDs.'); return; }
        if (data.count === 0) { alert('No hay ningún registro que coincida con el filtro actual.'); return; }

        await navigator.clipboard.writeText(data.ids.join(','));
        alert(`${data.count} ID(s) copiados al portapapeles.`);
    } catch (e) {
        alert('Error al copiar los IDs.');
    }
}

@if($esCuotasProvisional)
const HIST_URL_TPL = @json(route('mb.cuotas_provisional.historico', [$project->slug, '__ID__']));

async function toggleCuotaHistorico(id, btn) {
    const row = document.getElementById(`hist-row-${id}`);
    const icon = document.getElementById(`hist-icon-${id}`);
    const abrir = row.style.display === 'none';
    row.style.display = abrir ? '' : 'none';
    icon.style.transform = abrir ? 'rotate(90deg)' : '';
    if (!abrir || row.dataset.loaded) return;

    const body = document.getElementById(`hist-body-${id}`);
    try {
        const res = await fetch(HIST_URL_TPL.replace('__ID__', id), { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const eventos = data.historico || [];
        if (!eventos.length) {
            body.innerHTML = '<span class="text-gray-400">Sin cambios de estado registrados para esta cuota.</span>';
        } else {
            const filas = eventos.map(h => `
                <tr class="border-t border-gray-100">
                    <td class="py-1 pr-4">${h.fecha_exportacion}</td>
                    <td class="py-1 pr-4">${h.estado_anterior ?? '—'} → <span class="font-medium">${h.estado_nuevo ?? '—'}</span></td>
                    <td class="py-1 pr-4 text-right">${h.pendiente_anterior} → ${h.pendiente_nuevo}</td>
                    <td class="py-1">${h.fichero_origen ?? ''}</td>
                </tr>`).join('');
            body.innerHTML = `
                <table class="w-full text-xs">
                    <thead><tr class="text-gray-400">
                        <th class="text-left py-1 pr-4 font-medium">Fecha exportación</th>
                        <th class="text-left py-1 pr-4 font-medium">Estado</th>
                        <th class="text-right py-1 pr-4 font-medium">Pendiente</th>
                        <th class="text-left py-1 font-medium">Fichero</th>
                    </tr></thead>
                    <tbody>${filas}</tbody>
                </table>`;
        }
        row.dataset.loaded = '1';
    } catch (e) {
        body.innerHTML = '<span class="text-red-500">Error al cargar el histórico.</span>';
    }
}
@endif
</script>

@if($projectTable->name === 'movs_mapeo')
<div class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30" id="mm-modal">
    <div class="bg-white rounded-xl shadow-xl p-6 w-96">
        <h3 class="text-sm font-semibold text-gray-700 mb-3" id="mm-modal-title">Ejecutar reglas de clasificación</h3>
        <div id="mm-modal-body" class="text-sm text-gray-600 space-y-2 mb-5">
            <div class="flex items-center gap-2 text-gray-400"><i class="fa-solid fa-spinner fa-spin"></i> Calculando…</div>
        </div>
        <div class="flex justify-end gap-2" id="mm-modal-footer">
            <button class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors" onclick="document.getElementById('mm-modal').classList.add('hidden')">Cancelar</button>
        </div>
    </div>
</div>

<script>
const MM_PREVIEW_URL = '{{ route('mb.movs_mapeo.ejecutar.previsualizar', $project->slug) }}';
const MM_APLICAR_URL = '{{ route('mb.movs_mapeo.ejecutar.aplicar', $project->slug) }}';
const MM_CSRF = '{{ csrf_token() }}';

async function mmEjecutarPrevisualizar() {
    const modal = document.getElementById('mm-modal');
    const body = document.getElementById('mm-modal-body');
    const footer = document.getElementById('mm-modal-footer');
    document.getElementById('mm-modal-title').textContent = 'Ejecutar reglas de clasificación';
    body.innerHTML = '<div class="flex items-center gap-2 text-gray-400"><i class="fa-solid fa-spinner fa-spin"></i> Calculando…</div>';
    footer.innerHTML = '<button class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors" onclick="document.getElementById(\'mm-modal\').classList.add(\'hidden\')">Cancelar</button>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const res = await fetch(MM_PREVIEW_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': MM_CSRF, 'Accept': 'application/json' } });
        const data = await res.json();
        if (!data.ok) { body.innerHTML = `<span class="text-red-500">${data.error || 'Error al calcular la previsualización.'}</span>`; return; }

        body.innerHTML = `
            <p>Sobre <strong>${data.total}</strong> movimientos importados:</p>
            <ul class="list-disc pl-5">
                <li><strong class="text-green-700">${data.a_clasificar}</strong> se clasificarían (no tenían categoría)</li>
                <li><strong class="text-orange-600">${data.a_reclasificar}</strong> se reclasificarían (ya tenían una categoría distinta)</li>
                <li class="text-gray-400">${data.sin_cambios} sin cambios</li>
            </ul>
            ${(data.a_clasificar + data.a_reclasificar) === 0 ? '<p class="text-gray-400 mt-2">No hay nada que aplicar.</p>' : ''}
        `;
        if (data.a_clasificar + data.a_reclasificar > 0) {
            footer.innerHTML = `
                <button class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors" onclick="document.getElementById('mm-modal').classList.add('hidden')">Cancelar</button>
                <button class="px-4 py-1.5 text-sm font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors" onclick="mmEjecutarAplicar()">Continuar</button>
            `;
        }
    } catch (e) {
        body.innerHTML = '<span class="text-red-500">Error de red al calcular la previsualización.</span>';
    }
}

async function mmEjecutarAplicar() {
    const body = document.getElementById('mm-modal-body');
    const footer = document.getElementById('mm-modal-footer');
    body.innerHTML = '<div class="flex items-center gap-2 text-gray-400"><i class="fa-solid fa-spinner fa-spin"></i> Aplicando…</div>';
    footer.innerHTML = '';

    try {
        const res = await fetch(MM_APLICAR_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': MM_CSRF, 'Accept': 'application/json' } });
        const data = await res.json();
        if (!data.ok) { body.innerHTML = `<span class="text-red-500">${data.error || 'Error al aplicar la clasificación.'}</span>`; return; }

        document.getElementById('mm-modal-title').textContent = '✓ Clasificación aplicada';
        body.innerHTML = `<p><strong>${data.a_clasificar}</strong> movimientos clasificados y <strong>${data.a_reclasificar}</strong> reclasificados.</p>`;
        footer.innerHTML = '<button class="px-4 py-1.5 text-sm font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors" onclick="location.reload()">Cerrar</button>';
    } catch (e) {
        body.innerHTML = '<span class="text-red-500">Error de red al aplicar la clasificación.</span>';
    }
}
</script>
@endif

@if($projectTable->name === 'movs_bancarios')
<script>
// Sin recargar la pagina: al clasificar, la celda pasa a mostrar el nombre de la categoria al
// instante (igual que quedaria tras recargar), para poder seguir clasificando filas seguidas sin
// perder el sitio en el listado. Los stats de arriba (sumas por categoria) no se recalculan en
// vivo -- se actualizaran solos la proxima vez que se cargue la pagina.
async function mbSetCategoria(id, idGastosCuentas, nombreCategoria, btn) {
    const celda = document.getElementById('mb-cat-cell-' + id);
    celda.querySelectorAll('button').forEach(b => b.disabled = true);
    btn.style.opacity = '0.5';

    try {
        const res = await fetch('{{ route('ficha.update-field', [$project->slug, 'movs_bancarios', '__ID__']) }}'.replace('__ID__', id), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ field: 'id_gastos_cuentas', value: idGastosCuentas }),
        });
        if (res.ok) {
            celda.outerHTML = nombreCategoria;
        } else {
            alert('No se ha podido guardar la categoría.');
            celda.querySelectorAll('button').forEach(b => b.disabled = false);
            btn.style.opacity = '1';
        }
    } catch (e) {
        alert('Error de red al guardar la categoría.');
        celda.querySelectorAll('button').forEach(b => b.disabled = false);
        btn.style.opacity = '1';
    }
}
</script>
@endif
