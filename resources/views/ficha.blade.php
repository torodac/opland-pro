<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

    <x-slot name="actions">
        @if($registro)
            {{-- Navegación prev/next --}}
            <div class="flex items-center gap-1">
                @if($projectTable->name === 'fichaje')
                <a href="{{ route('vm.fichaje_form', [$project->slug, $registro->id]) }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors mr-1"
                   title="Ver formulario de fichaje">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </a>
                @elseif($projectTable->name === 'usuarios' && $project->slug === 'vm')
                <a href="{{ route('vm.usuario_form', [$project->slug, $registro->id]) }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors mr-1"
                   title="Ver formulario de usuario">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </a>
                @elseif(in_array($projectTable->name, ['tareas_limpieza', 'tareas_mantenimiento', 'tareas_piscinas']))
                @php
                    $tipoMap = ['tareas_limpieza' => 'limpieza', 'tareas_mantenimiento' => 'mantenimiento', 'tareas_piscinas' => 'piscina'];
                    $tareaTipo = $tipoMap[$projectTable->name];
                @endphp
                <a href="{{ url('/vm/tareas_' . $tareaTipo . '_form/' . $registro->id) }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors mr-1"
                   title="Ver formulario de tarea">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </a>
                @endif
                <a href="{{ $prevId ? route('ficha', [$project->slug, $projectTable->name, $prevId]) : '#' }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-400 transition-colors {{ $prevId ? 'hover:bg-gray-50 hover:text-gray-600' : 'opacity-30 cursor-not-allowed pointer-events-none' }}"
                   title="Registro anterior">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <a href="{{ $nextId ? route('ficha', [$project->slug, $projectTable->name, $nextId]) : '#' }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-400 transition-colors {{ $nextId ? 'hover:bg-gray-50 hover:text-gray-600' : 'opacity-30 cursor-not-allowed pointer-events-none' }}"
                   title="Registro siguiente">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            {{-- Modo lectura --}}
            <div id="grupo-ver" class="flex gap-2">
                {{-- Reset password: solo en tabla usuarios, solo admins --}}
                @if($projectTable->name === 'usuarios' && auth()->user()?->isProjectAdmin($project))
                <form id="form-reset-password" method="POST" action="{{ route('ficha.reset-password', [$project->slug, $projectTable->name, $registro->id]) }}">
                    @csrf
                    <button type="button"
                            onclick="confirmarResetPassword()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-key text-xs"></i>
                        <span class="hidden sm:inline">Reset password</span>
                    </button>
                </form>
                @endif
                {{-- Bloquear / Desbloquear: solo admins, siempre visible --}}
                @if(auth()->user()?->isProjectAdmin($project))
                <form method="POST" action="{{ route('ficha.block', [$project->slug, $projectTable->name, $registro->id]) }}">
                    @csrf @method('PATCH')
                    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors
                        {{ ($registro->blocked ?? false) ? 'border-green-300 text-green-600 hover:bg-green-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                        <i class="fas {{ ($registro->blocked ?? false) ? 'fa-lock-open' : 'fa-lock' }} text-xs"></i>
                        <span class="hidden sm:inline">{{ ($registro->blocked ?? false) ? 'Desbloquear' : 'Bloquear' }}</span>
                    </button>
                </form>
                @endif
                {{-- Clonar --}}
                @if($canEdit ?? true)
                <a href="{{ route('ficha.create', [$project->slug, $projectTable->name]) }}?{{ http_build_query(collect($projectTable->fields)->filter(fn($f) => $f->in_form && $f->type !== 'file')->mapWithKeys(fn($f) => [$f->name => $registro->{$f->name} ?? ''])->toArray()) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span class="hidden sm:inline">Clonar</span>
                </a>
                @endif
                {{-- Nueva --}}
                <a href="{{ route('ficha.create', [$project->slug, $projectTable->name]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden sm:inline">Nueva</span>
                </a>
                {{-- Editar --}}
                @if($canEdit ?? true)
                <button onclick="toggleEdit()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                    <span class="hidden sm:inline">Editar</span>
                </button>
                @elseif(!($registro->blocked ?? false))
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed"
                      title="No tienes permisos para editar esta tabla">
                    <i class="fa-solid fa-ban text-sm"></i>
                    <span class="hidden sm:inline">Sin permisos</span>
                </span>
                @endif
            </div>

            {{-- Modo edición --}}
            <div id="grupo-editar" style="display:none" class="flex gap-2">
                {{-- Archivar (ocultar/mostrar): solo si la tabla tiene campo hidden --}}
                @if($tieneHidden)
                    @if($projectTable->name === 'usuarios' && !$registro->hidden)
                        <button type="button" onclick="confirmarOcultar()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors border-amber-300 text-amber-600 hover:bg-amber-50">
                            <i class="fas fa-eye-slash text-xs"></i>
                            <span class="hidden sm:inline">Archivar</span>
                        </button>
                    @else
                        <form method="POST" action="{{ route('ficha.archive', [$project->slug, $projectTable->name, $registro->id]) }}">
                            @csrf @method('PATCH')
                            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors
                                {{ $registro->hidden ? 'border-green-300 text-green-600 hover:bg-green-50' : 'border-amber-300 text-amber-600 hover:bg-amber-50' }}">
                                <i class="fas {{ $registro->hidden ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                                <span class="hidden sm:inline">{{ $registro->hidden ? 'Mostrar' : 'Archivar' }}</span>
                            </button>
                        </form>
                    @endif
                @endif

                {{-- Borrar (soft delete): solo si la tabla tiene campo deleted --}}
                @if($tieneDeleted)
                    <button type="button" onclick="{{ $projectTable->name === 'usuarios' && !$registro->deleted ? 'confirmarArchivar()' : 'confirmarBorrar()' }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors
                                {{ $registro->deleted ? 'border-green-300 text-green-600 hover:bg-green-50' : 'border-red-300 text-red-500 hover:bg-red-50' }}">
                        <i class="fas {{ $registro->deleted ? 'fa-trash-restore' : 'fa-trash' }} text-xs"></i>
                        <span class="hidden sm:inline">{{ $registro->deleted ? 'Restaurar' : 'Borrar' }}</span>
                    </button>
                @endif

                {{-- Eliminar (hard delete): solo si la tabla lo permite --}}
                @if($projectTable->permite_eliminar)
                    <button type="button" onclick="confirmarEliminar()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-400 text-red-600 hover:bg-red-50 transition-colors">
                        <i class="fas fa-times-circle text-xs"></i>
                        <span class="hidden sm:inline">Eliminar</span>
                    </button>
                @endif

                {{-- Cancelar --}}
                <button onclick="toggleEdit()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-xmark text-xs sm:hidden"></i>
                    <span class="hidden sm:inline">Cancelar</span>
                </button>

                {{-- Guardar --}}
                <button onclick="document.getElementById('ficha-form').requestSubmit()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-check text-xs sm:hidden"></i>
                    <span class="hidden sm:inline">Guardar</span>
                </button>
            </div>
        @endif

    </x-slot>

    @if($projectTable->name === 'fichaje' && $project->slug === 'vm')
        @include('partials.role-badge', ['project' => $project, 'texto' => 'Solo Dirección general, Director RRHH o admin pueden ver/ajustar las horas extra y editar la fecha de este fichaje sin el límite de 2 días.'])
    @endif

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="{ tab: 'detalles' }">

        {{-- Pestañas --}}
        @if($registro && !empty($tabs))
            <div class="flex gap-1 mb-4 border-b border-gray-200">
                <button @click="tab = 'detalles'"
                        :class="tab === 'detalles' ? 'border-b-2 border-orange-500 text-orange-600 font-medium' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2 text-sm transition-colors -mb-px">
                    Detalles
                </button>
                @foreach($tabs as $tabData)
                    <button @click="tab = '{{ $tabData['table']->name }}'"
                            :class="tab === '{{ $tabData['table']->name }}' ? 'border-b-2 border-orange-500 text-orange-600 font-medium' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm transition-colors -mb-px">
                        {{ $tabData['table']->label }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Panel Detalles --}}
        <div @if($registro && !empty($tabs)) x-show="tab === 'detalles'" x-cloak @endif>

            @php
                $docPath    = $registro?->documento ?? null;
                $docExt     = $docPath ? strtolower(pathinfo($docPath, PATHINFO_EXTENSION)) : null;
                $mostrarPdf = $docPath && $docExt === 'pdf';
                $docUrl     = $mostrarPdf ? asset('storage/' . $docPath) : null;
            @endphp

            <div class="{{ $mostrarPdf ? 'grid grid-cols-2 gap-6 items-start' : '' }}">

            {{-- Columna izquierda: preview PDF --}}
            @if($mostrarPdf)
            <div class="sticky top-4">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" style="height: calc(100vh - 7rem)">
                    <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 bg-gray-50">
                        <span class="text-xs text-gray-400 truncate">{{ basename($docPath) }}</span>
                        <a href="{{ $docUrl }}" target="_blank"
                           class="text-xs text-orange-500 hover:text-orange-600 shrink-0 ml-2">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <iframe src="{{ $docUrl }}" class="w-full h-full border-0" style="height: calc(100% - 37px)"></iframe>
                </div>
            </div>
            @endif

            {{-- Columna derecha (o única): campos --}}
            <div>

            <form method="POST"
                  action="{{ $registro
                    ? route('ficha.update', [$project->slug, $projectTable->name, $registro->id])
                    : route('ficha.store',  [$project->slug, $projectTable->name]) }}"
                  id="ficha-form"
                  enctype="multipart/form-data">
                @csrf
                @if($registro) @method('PUT') @endif

                @if($errors->any())
                <div class="mx-5 mt-4 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <div>
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="bg-white rounded-xl">

                    @php
                        $camposList  = $campos->filter(fn($c) => $c->in_form)->values();
                        $i = 0;
                        $total = $camposList->count();
                    @endphp

                    @while($i < $total)
                        @php $campo = $camposList[$i]; @endphp

                        @if($campo->type === 'text')
                            {{-- Campo texto: fila completa --}}
                            <div class="px-5 py-4 border-b border-transparent">
                                <label for="campo_{{ $campo->name }}"
                                       class="block text-xs font-bold text-gray-600 mb-1.5">
                                    {{ $campo->label }}
                                    @if($campo->required)<span class="text-red-400">*</span>@endif
                                </label>
                                @php $valor = $registro ? ($registro->{$campo->name} ?? null) : (old($campo->name) ?? ($prefill[$campo->name] ?? null)); @endphp
                                @include('partials.field', ['campo' => $campo, 'valor' => $valor])
                            </div>
                            @php $i++; @endphp

                        @else
                            {{-- Un campo por fila cuando hay PDF, dos por fila en el resto --}}
                            @php $campo2 = (!$mostrarPdf && $i + 1 < $total && $camposList[$i + 1]->type !== 'text') ? $camposList[$i + 1] : null; @endphp
                            <div class="{{ $mostrarPdf ? 'grid grid-cols-1' : 'grid grid-cols-1 sm:grid-cols-2' }}">
                                <div class="px-5 py-4">
                                    @php $valor = $registro ? ($registro->{$campo->name} ?? null) : (old($campo->name) ?? ($prefill[$campo->name] ?? null)); @endphp
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <label for="campo_{{ $campo->name }}" class="block text-xs font-bold text-gray-600">
                                            {{ $campo->label }}
                                            @if($campo->required)<span class="text-red-400">*</span>@endif
                                        </label>
                                        @include('partials.ref-link', ['campo' => $campo, 'valor' => $valor, 'project' => $project])
                                    </div>
                                    @php
                                        $fieldExtra = [];
                                        if ($campo->name === 'fecha_fichaje' && $projectTable->name === 'fichaje' && $project->slug === 'vm') {
                                            $fieldExtra['max'] = now()->toDateString();
                                            if (!($puedeSinLimiteFecha ?? false)) $fieldExtra['min'] = now()->subDays(2)->toDateString();
                                        }
                                    @endphp
                                    @include('partials.field', array_merge(['campo' => $campo, 'valor' => $valor], $fieldExtra))
                                </div>
                                @if($campo2)
                                    <div class="px-5 py-4">
                                        @php $valor = $registro ? ($registro->{$campo2->name} ?? null) : (old($campo2->name) ?? ($prefill[$campo2->name] ?? null)); @endphp
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            <label for="campo_{{ $campo2->name }}" class="block text-xs font-bold text-gray-600">
                                                {{ $campo2->label }}
                                                @if($campo2->required)<span class="text-red-400">*</span>@endif
                                            </label>
                                            @include('partials.ref-link', ['campo' => $campo2, 'valor' => $valor, 'project' => $project])
                                        </div>
                                        @php
                                            $fieldExtra = [];
                                            if ($campo2->name === 'fecha_fichaje' && $projectTable->name === 'fichaje' && $project->slug === 'vm') {
                                                $fieldExtra['max'] = now()->toDateString();
                                                if (!($puedeSinLimiteFecha ?? false)) $fieldExtra['min'] = now()->subDays(2)->toDateString();
                                            }
                                        @endphp
                                        @include('partials.field', array_merge(['campo' => $campo2, 'valor' => $valor], $fieldExtra))
                                    </div>
                                @endif
                            </div>
                            @php $i += $campo2 ? 2 : 1; @endphp
                        @endif
                    @endwhile

                </div>

                {{-- Botones guardar/cancelar (solo en alta nueva) --}}
                @if(!$registro)
                <div class="flex gap-2 mt-4">
                    <button type="submit"
                            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                        Guardar
                    </button>
                    <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}"
                       class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                        Cancelar
                    </a>
                </div>
                @endif
            </form>

            @if($registro)
                {{-- Form oculto para borrar/restaurar (soft delete) --}}
                @if($tieneDeleted)
                <form id="form-borrar" method="POST" action="{{ route('ficha.borrar', [$project->slug, $projectTable->name, $registro->id]) }}" class="hidden">
                    @csrf @method('PATCH')
                </form>
                @endif

                {{-- Form oculto para eliminar definitivamente (hard delete) --}}
                @if($projectTable->permite_eliminar)
                <form id="form-eliminar" method="POST" action="{{ route('ficha.eliminar', [$project->slug, $projectTable->name, $registro->id]) }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-300">
                    @if($registro->createdat ?? null)
                        <span>
                            Creado {{ \Carbon\Carbon::parse($registro->createdat)->format('d/m/Y H:i') }}
                            @if($createUser) <span class="text-gray-400">por {{ $createUser }}</span>@endif
                        </span>
                    @endif
                    @if($registro->updatedat ?? null)
                        <span>
                            Modificado {{ \Carbon\Carbon::parse($registro->updatedat)->format('d/m/Y H:i') }}
                            @if($updateUser) <span class="text-gray-400">por {{ $updateUser }}</span>@endif
                        </span>
                    @endif
                    @if(!empty($registro->hidden))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                            <i class="fas fa-eye-slash text-[10px]"></i> Archivado
                        </span>
                    @endif
                    @if(!empty($registro->deleted))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">
                            <i class="fas fa-trash text-[10px]"></i> Borrado
                        </span>
                    @endif
                    @if(!empty($registro->blocked))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            <i class="fas fa-lock text-[10px]"></i> Bloqueado
                        </span>
                    @endif
                    @if(auth()->user()?->isProjectAdmin($project))
                        <a href="{{ route('config.projects.tables.fields.index', [$project, $projectTable]) }}"
                           id="btn-config-tabla"
                           title="Configurar campos de {{ $projectTable->label }}"
                           class="ml-auto hover:text-orange-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m2.25-2.25h.375a1.125 1.125 0 011.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125H12m2.625 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125h.375"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @endif
            </div> {{-- fin columna derecha --}}
            </div> {{-- fin flex contenedor pdf+campos --}}
        </div>

        {{-- Paneles de tablas relacionadas --}}
        @foreach($tabs as $tabData)
            <div x-show="tab === '{{ $tabData['table']->name }}'" x-cloak>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-transparent">
                        <span class="text-sm font-medium text-gray-700">
                            {{ $tabData['table']->label }}
                            <span class="ml-1.5 text-xs text-gray-400 font-normal">({{ $tabData['rows']->count() }})</span>
                        </span>
                        <a href="{{ route('ficha.create', [$project->slug, $tabData['table']->name]) }}?{{ $tabData['fkField']->name }}={{ $registro->id }}"
                           class="inline-flex items-center gap-1 text-xs text-orange-600 hover:text-orange-700">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Nuevo
                        </a>
                    </div>

                    @if($tabData['rows']->isEmpty())
                        <div class="px-4 py-8 text-center text-sm text-gray-400">Sin registros</div>
                    @else
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-transparent">
                                    @foreach($tabData['campos'] as $campo)
                                        @if($campo->name !== $tabData['fkField']->name)
                                            <th class="text-left px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                                {{ $campo->label }}
                                            </th>
                                        @endif
                                    @endforeach
                                    <th class="w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($tabData['rows'] as $row)
                                    <tr class="hover:bg-gray-50">
                                        @foreach($tabData['campos'] as $campo)
                                            @if($campo->name !== $tabData['fkField']->name)
                                                <td class="px-4 py-2.5">
                                                    @php $valor = $row->{$campo->name} ?? null; @endphp
                                                    @include('partials.cell', [
                                                        'campo'      => $campo,
                                                        'valor'      => $valor,
                                                        'fkOptions'  => $tabData['fkOptions'],
                                                        'usuariosMap' => $usuariosMap,
                                                    ])
                                                </td>
                                            @endif
                                        @endforeach
                                        <td class="px-4 py-2.5 text-right">
                                            <a href="{{ route('ficha', [$project->slug, $tabData['table']->name, $row->id]) }}"
                                               class="text-xs text-gray-400 hover:text-orange-600">Ver</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach

    </div>

</x-app-layout>

{{-- Modal confirmación borrar (soft delete) --}}
@if($registro && $tieneDeleted)
<div id="modal-borrar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalBorrar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-trash text-red-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $registro->deleted ? 'Restaurar registro' : 'Borrar registro' }}
                </h3>
            </div>
            <p class="text-sm text-gray-500 mb-6">
                @if($registro->deleted)
                    ¿Quieres restaurar <strong>{{ $registro->nombre ?? "este registro" }}</strong>?
                @else
                    ¿Seguro que quieres borrar <strong>{{ $registro->nombre ?? "este registro" }}</strong>? Podrás recuperarlo desde la vista de borrados.
                @endif
            </p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalBorrar()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="document.getElementById('form-borrar').submit()"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors
                            {{ $registro->deleted ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600' }}">
                    {{ $registro->deleted ? 'Restaurar' : 'Borrar' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal confirmación eliminar (hard delete) --}}
@if($registro && $projectTable->permite_eliminar)
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
            <p class="text-sm text-gray-500 mb-6">
                ¿Seguro que quieres eliminar <strong>{{ $registro->nombre ?? "este registro" }}</strong> de forma permanente? <span class="text-red-600 font-medium">Esta acción no se puede deshacer.</span>
            </p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalEliminar()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="document.getElementById('form-eliminar').submit()"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Eliminar definitivamente
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@if($registro && $projectTable->name === 'usuarios')
{{-- Modal confirmación ocultar usuario --}}
<div id="modal-ocultar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalOcultar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-eye-slash text-amber-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">Ocultar usuario</h3>
            </div>
            <p class="text-sm text-gray-500 mb-6">
                Al ocultar a <strong>{{ $registro->nombre ?? 'este usuario' }}</strong> se le bloqueará el acceso a la aplicación. ¿Continuar?
            </p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalOcultar()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancelar</button>
                <button onclick="document.getElementById('form-archive-usuario').submit()"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors">Ocultar</button>
            </div>
        </div>
    </div>
</div>
<form id="form-archive-usuario" method="POST" action="{{ route('ficha.archive', [$project->slug, $projectTable->name, $registro->id]) }}" class="hidden">
    @csrf @method('PATCH')
</form>

{{-- Modal confirmación archivar usuario --}}
<div id="modal-archivar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalArchivar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-trash text-red-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">Eliminar usuario</h3>
            </div>
            <p class="text-sm text-gray-500 mb-6">
                Al eliminar a <strong>{{ $registro->nombre ?? 'este usuario' }}</strong> se le bloqueará el acceso a la aplicación. ¿Continuar?
            </p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalArchivar()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancelar</button>
                <button onclick="document.getElementById('form-borrar').submit()"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors">Eliminar</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal reset password --}}
@if($projectTable->name === 'usuarios' && auth()->user()?->isProjectAdmin($project))
<div id="modal-reset-password" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalResetPassword()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-key text-amber-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">Restablecer contraseña</h3>
            </div>
            <p class="text-sm text-gray-500 mb-2">
                La contraseña de <strong>{{ $registro->nombre ?? 'este usuario' }}</strong> se establecerá a:
            </p>
            <div class="bg-gray-100 rounded-lg px-4 py-2 mb-4 text-center">
                <span class="font-mono font-semibold text-gray-800 tracking-widest">bienvenido</span>
            </div>
            <p class="text-xs text-gray-400 mb-6">El usuario deberá cambiarla en su próximo acceso.</p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalResetPassword()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="document.getElementById('form-reset-password').submit()"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors">
                    Restablecer
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
const BG_READONLY = '#f3f4f6'; // gray-100

function setFieldsReadonly(readonly) {
    document.querySelectorAll('#ficha-form input, #ficha-form select, #ficha-form textarea')
        .forEach(f => {
            if (f.dataset.readonly) return;
            f.style.pointerEvents   = readonly ? 'none' : '';
            f.style.userSelect      = readonly ? 'none' : '';
            f.tabIndex              = readonly ? -1 : 0;
            f.style.backgroundColor = readonly ? BG_READONLY : '';
        });

    // Bloquear botones × y el input de los campos multitabla
    document.querySelectorAll('#ficha-form [data-multitabla]')
        .forEach(el => {
            el.style.pointerEvents = readonly ? 'none' : '';
            el.style.backgroundColor = readonly ? BG_READONLY : '';
        });
}

function toggleEdit() {
    const grupoVer    = document.getElementById('grupo-ver');
    const grupoEditar = document.getElementById('grupo-editar');
    const btnConfig   = document.getElementById('btn-config-tabla');
    const isEditing   = grupoEditar.style.display === 'none';

    setFieldsReadonly(!isEditing);
    grupoVer.style.display    = isEditing ? 'none' : '';
    grupoEditar.style.display = isEditing ? '' : 'none';
    if (btnConfig) btnConfig.style.display = isEditing ? 'none' : '';
}


function confirmarResetPassword() {
    document.getElementById('modal-reset-password').classList.remove('hidden');
}
function cerrarModalResetPassword() {
    document.getElementById('modal-reset-password').classList.add('hidden');
}
function confirmarBorrar() {
    document.getElementById('modal-borrar').classList.remove('hidden');
}
function cerrarModalBorrar() {
    document.getElementById('modal-borrar').classList.add('hidden');
}
function confirmarEliminar() {
    document.getElementById('modal-eliminar').classList.remove('hidden');
}
function cerrarModalEliminar() {
    document.getElementById('modal-eliminar').classList.add('hidden');
}
function confirmarOcultar() {
    document.getElementById('modal-ocultar').classList.remove('hidden');
}
function cerrarModalOcultar() {
    document.getElementById('modal-ocultar').classList.add('hidden');
}
function confirmarArchivar() {
    document.getElementById('modal-archivar').classList.remove('hidden');
}
function cerrarModalArchivar() {
    document.getElementById('modal-archivar').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    @if($registro)
        @if($errors->any())
            toggleEdit();
        @else
            setFieldsReadonly(true);
        @endif
    @endif

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            cerrarModalBorrar();
            cerrarModalEliminar();
            cerrarModalOcultar();
            cerrarModalArchivar();
        }
    });
});

    // Alias autocomplete from nombre
    const nombreInput = document.getElementById('campo_nombre');
    const aliasInput  = document.getElementById('campo_alias');
    if (nombreInput && aliasInput) {
        nombreInput.addEventListener('input', () => {
            if (aliasInput.dataset.edited) return;
            aliasInput.value = (nombreInput.value.trim().split(' ')[0] || '');
        });
        aliasInput.addEventListener('input', () => {
            aliasInput.dataset.edited = aliasInput.value ? '1' : '';
        });
    }
</script>
