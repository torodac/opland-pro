<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => ''],
]">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @php
        $reorderUrl     = route('config.projects.tables.reorder', $project);
        $reorderModulo  = route('config.projects.tables.modulo-order', $project);
        $csrfToken      = csrf_token();
    @endphp

    {{-- Macro para renderizar una tarjeta de tabla --}}
    @php
        $renderCard = function($table, $project) {
            $href    = route('config.projects.tables.fields.index', [$project, $table]);
            $patch   = route('config.projects.tables.patch', [$project, $table]);
            $blue    = $table->is_virtual ?? false;
            $textCls = $blue ? 'text-blue-500 group-hover:text-blue-700' : 'text-gray-700 group-hover:text-orange-700';
            return compact('href', 'patch', 'textCls', 'table');
        };
    @endphp

    {{-- Modal nuevo/editar módulo --}}
    <div id="modal-modulo" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30">
        <div class="bg-white rounded-xl shadow-xl p-6 w-80">
            <h3 id="modal-modulo-title" class="text-sm font-semibold text-gray-700 mb-4">Nuevo módulo</h3>
            <input id="modal-modulo-input" type="text" placeholder="Nombre del módulo"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 mb-4">
            <div class="flex justify-end gap-2">
                <button id="modal-modulo-cancel"
                        class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    Cancelar
                </button>
                <button id="modal-modulo-confirm"
                        class="px-4 py-1.5 text-sm font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors">
                    Crear
                </button>
            </div>
        </div>
    </div>

    {{-- Panel --}}
    <div style="margin-bottom: 2.3rem;">
        <div class="flex items-center gap-3 mb-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Panel</h2>
            <button class="btn-add-modulo text-gray-400 hover:text-orange-500 transition-colors" data-block="panel" title="Añadir módulo">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </button>
        </div>

        @if($panelTables->isEmpty())
            <p class="text-sm text-gray-400">Aún no hay tablas. Crea la primera abajo.</p>
        @else
            <div id="panel-groups"
                 data-block="panel"
                 data-reorder-url="{{ $reorderUrl }}"
                 data-reorder-modulo-url="{{ $reorderModulo }}"
                 data-csrf="{{ $csrfToken }}"
                 class="flex flex-col gap-4">
                @foreach($panelGroups as $modulo => $moduloTables)
                    <div class="modulo-group" data-modulo="{{ $modulo }}">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="group-handle flex items-center gap-1.5 cursor-grab select-none">
                                <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5h16.5M3.75 12h16.5M3.75 19h16.5"/>
                                </svg>
                                <span class="text-xs font-semibold text-orange-500 uppercase tracking-wider">
                                    {{ $modulo ?: 'Sin módulo' }}
                                </span>
                            </div>
                            @if($modulo)
                                <button class="btn-delete-modulo cursor-pointer p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-100 transition-colors leading-none"
                                        data-modulo="{{ $modulo }}"
                                        data-url="{{ route('config.projects.tables.delete-modulo', [$project, $modulo]) }}"
                                        title="Eliminar módulo">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                                <button class="btn-edit-modulo cursor-pointer p-1 rounded text-gray-400 hover:text-orange-500 hover:bg-orange-100 transition-colors leading-none"
                                        data-modulo="{{ $modulo }}"
                                        data-url="{{ route('config.projects.tables.rename-modulo', [$project, $modulo]) }}"
                                        title="Renombrar módulo">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                        <div class="tables-grid border border-dashed border-gray-200 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 min-h-[52px]"
                             data-reorder-url="{{ $reorderUrl }}"
                             data-block="panel">
                            @foreach($moduloTables as $table)
                                <div class="table-card cursor-grab active:cursor-grabbing select-none flex items-center gap-2.5 px-3 py-2.5 bg-white border border-gray-200 rounded-lg hover:border-orange-300 hover:bg-orange-50 transition-colors group"
                                     data-id="{{ $table->id }}"
                                     data-patch-url="{{ route('config.projects.tables.patch', [$project, $table]) }}"
                                     data-modulo-url="{{ route('config.projects.tables.set-modulo', [$project, $table]) }}">
<button type="button"
                                        title="{{ $table->active ? 'Ocultar del menú' : 'Mostrar en menú' }}"
                                        onclick="event.stopPropagation(); toggleActive(this)"
                                        data-patch-url="{{ route('config.projects.tables.patch', [$project, $table]) }}"
                                        data-active="{{ $table->active ? '1' : '0' }}"
                                        class="shrink-0 transition-colors {{ $table->active ? 'text-gray-400 hover:text-gray-600' : 'text-gray-200 hover:text-gray-400' }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </button>
                                    <a href="{{ route('config.projects.tables.fields.index', [$project, $table]) }}"
                                       class="flex-1 min-w-0 text-sm font-medium truncate {{ ($table->is_virtual ?? false) ? 'text-blue-500 group-hover:text-blue-700' : 'text-gray-700 group-hover:text-orange-700' }}"
                                       onclick="event.stopPropagation()">
                                        {{ $table->label }}
                                    </a>
                                    <button type="button"
                                        title="Eliminar tabla"
                                        onclick="event.stopPropagation(); deleteTable(this)"
                                        data-destroy-url="{{ route('config.projects.tables.destroy', [$project, $table]) }}"
                                        data-name="{{ $table->label }}"
                                        class="shrink-0 text-gray-200 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Configuración (solo admin) --}}
    <div style="margin-bottom: 2.3rem;">
        <div class="flex items-center gap-3 mb-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Configuración</h2>
            <button class="btn-add-modulo text-gray-400 hover:text-orange-500 transition-colors" data-block="config" title="Añadir módulo">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </button>
        </div>

        <div id="config-groups"
             data-block="config"
             data-reorder-url="{{ $reorderUrl }}"
             data-reorder-modulo-url="{{ $reorderModulo }}"
             data-csrf="{{ $csrfToken }}"
             class="flex flex-col gap-4">
            @foreach($configGroups as $modulo => $moduloTables)
                <div class="modulo-group" data-modulo="{{ $modulo }}">
                    <div class="group-handle flex items-center gap-1.5 mb-2 cursor-grab select-none w-fit">
                        <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5h16.5M3.75 12h16.5M3.75 19h16.5"/>
                        </svg>
                        <span class="text-xs font-semibold text-orange-500 uppercase tracking-wider">
                            {{ $modulo ?: 'Sin módulo' }}
                        </span>
                    </div>
                    <div class="tables-grid border border-dashed border-gray-200 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 min-h-[52px]"
                         data-reorder-url="{{ $reorderUrl }}"
                         data-block="config">
                        @foreach($moduloTables as $table)
                            <div class="table-card cursor-grab active:cursor-grabbing select-none flex items-center gap-2.5 px-3 py-2.5 bg-white border border-gray-200 rounded-lg hover:border-orange-300 hover:bg-orange-50 transition-colors group"
                                 data-id="{{ $table->id }}"
                                 data-patch-url="{{ route('config.projects.tables.patch', [$project, $table]) }}"
                                 data-modulo-url="{{ route('config.projects.tables.set-modulo', [$project, $table]) }}">
                                <button type="button"
                                    title="{{ $table->active ? 'Ocultar del menú' : 'Mostrar en menú' }}"
                                    onclick="event.stopPropagation(); toggleActive(this)"
                                    data-patch-url="{{ route('config.projects.tables.patch', [$project, $table]) }}"
                                    data-active="{{ $table->active ? '1' : '0' }}"
                                    class="shrink-0 transition-colors {{ $table->active ? 'text-gray-400 hover:text-gray-600' : 'text-gray-200 hover:text-gray-400' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.963-7.178z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                                <a href="{{ route('config.projects.tables.fields.index', [$project, $table]) }}"
                                   class="flex-1 min-w-0 text-sm font-medium truncate {{ ($table->is_virtual ?? false) ? 'text-blue-500 group-hover:text-blue-700' : 'text-gray-700 group-hover:text-orange-700' }}"
                                   onclick="event.stopPropagation()">
                                    {{ $table->label }}
                                </a>
                                    <button type="button"
                                        title="Eliminar tabla"
                                        onclick="event.stopPropagation(); deleteTable(this)"
                                        data-destroy-url="{{ route('config.projects.tables.destroy', [$project, $table]) }}"
                                        data-name="{{ $table->label }}"
                                        class="shrink-0 text-gray-200 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if($configGroups->isEmpty())
                <div class="border border-dashed border-gray-200 rounded-xl p-3 min-h-[52px] tables-grid"
                     data-reorder-url="{{ $reorderUrl }}"
                     data-block="config"></div>
            @endif
        </div>
    </div>

    {{-- Crear tabla --}}
    <div style="margin-bottom: 2.3rem;" x-data="{
        label: '{{ old('label') }}',
        name: '{{ old('name') }}',
        nameTouched: {{ old('name') ? 'true' : 'false' }},
        syncName() {
            if (!this.nameTouched) {
                this.name = this.label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            }
        }
    }">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Nueva tabla</h2>

        {{-- Inputs compartidos --}}
        <div class="flex gap-2 items-start flex-wrap mb-2">
            <div>
                <input type="text" placeholder="Nombre visible (ej: Clientes)"
                       x-model="label" @input="syncName()"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 w-56">
                @error('label')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="text" placeholder="Nombre BD (ej: clientes)"
                       x-model="name" @input="nameTouched = true"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono w-44">
                @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Crear tabla --}}
            <form method="POST" action="{{ route('config.projects.tables.store', $project) }}">
                @csrf
                <input type="hidden" name="label" :value="label">
                <input type="hidden" name="name" :value="name">
                <button type="submit"
                        :disabled="!label.trim() || !name.trim()"
                        :class="label.trim() && name.trim() ? 'bg-orange-500 hover:bg-orange-600 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                        class="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    Crear tabla
                </button>
            </form>

            {{-- Crear desde Excel --}}
            <form method="GET" action="{{ route('config.projects.import-excel.form', $project) }}">
                <input type="hidden" name="table_label" :value="label">
                <input type="hidden" name="table_name" :value="name">
                <button type="submit"
                        :disabled="!label.trim() || !name.trim()"
                        :class="label.trim() && name.trim() ? 'border-gray-200 text-gray-600 hover:bg-gray-50 cursor-pointer' : 'border-gray-100 text-gray-300 cursor-not-allowed'"
                        class="inline-flex items-center gap-1.5 px-4 py-2 border text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    <i class="fas fa-file-excel" :class="label.trim() && name.trim() ? 'text-green-600' : 'text-gray-300'"></i>
                    Crear desde Excel
                </button>
            </form>
        </div>

        </form>
    </div>

    {{-- Ajustes del proyecto --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Ajustes del proyecto</h2>
        <form method="POST"
              enctype="multipart/form-data"
              action="{{ route('config.projects.update', $project) }}"
              class="max-w-lg" style="display:flex; flex-direction:column; gap:1.4rem;">
            @csrf @method('PUT')
            <input type="hidden" name="_redirect" value="{{ route('config.projects.tables.index', $project) }}">

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Nombre del proyecto</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}"
                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Descripción</label>
                <input type="text" name="description" value="{{ old('description', $project->description) }}"
                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Logo</label>
                <div class="flex items-center gap-3">
                    @if($project->logo)
                        <img src="{{ asset($project->logo) }}?{{ time() }}"
                             class="h-10 w-10 object-contain rounded border border-gray-100 shrink-0" title="Logo actual">
                    @endif
                    <input type="file" name="logo" accept="image/*"
                           class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="w-36 shrink-0 text-sm text-gray-500">Favicon</label>
                <div class="flex items-center gap-3">
                    @if($project->favicon)
                        <img src="{{ asset($project->favicon) }}?{{ time() }}"
                             class="h-10 w-10 object-contain rounded border border-gray-100 shrink-0" title="Favicon actual">
                    @endif
                    <div>
                        <input type="file" name="favicon" accept=".ico,.png,.svg"
                               class="text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                        <p class="text-xs text-gray-400 mt-1">.ico, .png o .svg</p>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="pt-2">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>

</x-app-layout>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-genera el slug de BD al escribir el nombre visible
    const labelInput = document.querySelector('input[name="label"]');
    const nameInput  = document.querySelector('input[name="name"]');
    if (labelInput && nameInput) {
        let userEditedName = false;
        nameInput.addEventListener('input', () => { userEditedName = true; });
        labelInput.addEventListener('input', () => {
            if (userEditedName) return;
            nameInput.value = labelInput.value
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
        });
    }

    const csrf         = '{{ csrf_token() }}';
    const reorderUrl   = '{{ route('config.projects.tables.reorder', $project) }}';
    const moduloUrl    = '{{ route('config.projects.tables.modulo-order', $project) }}';

    // Envía el orden global de todas las tarjetas (panel + config)
    function sendGlobalOrder() {
        const allIds = [...document.querySelectorAll('.tables-grid .table-card')]
            .map(c => c.dataset.id);
        fetch(reorderUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ ids: allIds }),
        }).catch(e => console.error('reorder fetch failed', e));
    }

    // Envía el orden de módulos de un bloque (panel o config)
    function sendModuloOrder(blockEl) {
        const order = [...blockEl.querySelectorAll('.modulo-group')]
            .map(g => g.dataset.modulo);
        fetch(moduloUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ order }),
        }).catch(e => console.error('modulo reorder failed', e));
    }

    // ── Modal nuevo módulo ──────────────────────────────────────────────────────
    const modal       = document.getElementById('modal-modulo');
    const modalInput  = document.getElementById('modal-modulo-input');
    const modalTitle  = document.getElementById('modal-modulo-title');
    let   activeBlock = null;
    let   editState   = null; // { btn, groupEl, oldNombre } cuando estamos en modo edición

    document.querySelectorAll('.btn-add-modulo').forEach(btn => {
        btn.addEventListener('click', () => {
            activeBlock = btn.dataset.block;
            editState   = null;
            modalTitle.textContent = 'Nuevo módulo';
            modalInput.value = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modalInput.focus();
        });
    });

    function bindEditModulo(btn) {
        if (!btn) return;
        btn.addEventListener('click', () => {
            const groupEl   = btn.closest('.modulo-group');
            const oldNombre = btn.dataset.modulo;
            editState   = { btn, groupEl, oldNombre };
            activeBlock = null;
            modalTitle.textContent = 'Renombrar módulo';
            modalInput.value = oldNombre;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modalInput.select();
        });
    }
    document.querySelectorAll('.btn-edit-modulo').forEach(bindEditModulo);

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        activeBlock = null;
        editState   = null;
    }

    document.getElementById('modal-modulo-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    modalInput.addEventListener('keydown', e => { if (e.key === 'Enter') confirmModal(); if (e.key === 'Escape') closeModal(); });
    document.getElementById('modal-modulo-confirm').addEventListener('click', confirmModal);

    function confirmModal() {
        const nombre = modalInput.value.trim().toLowerCase().replace(/\s+/g, '_');
        if (!nombre) return;

        if (editState) {
            // Modo edición: renombrar módulo existente
            fetch(editState.btn.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ nombre }),
            }).then(r => r.json()).then(data => {
                const nuevo = data.nuevo;
                // Actualizar DOM del grupo
                const groupEl = editState.groupEl;
                groupEl.dataset.modulo = nuevo;
                groupEl.querySelector('.group-handle span').textContent = nuevo.toUpperCase();
                // Actualizar data-modulo y data-url de los botones del grupo
                groupEl.querySelectorAll('.btn-edit-modulo, .btn-delete-modulo').forEach(b => {
                    b.dataset.modulo = nuevo;
                    const baseUrl = b.dataset.url.replace(/\/modulo\/[^/]+\//, `/modulo/${nuevo}/`);
                    b.dataset.url = baseUrl.replace(/\/modulo\/[^/]+$/, `/modulo/${nuevo}`);
                });
                closeModal();
            }).catch(e => console.error('rename modulo failed', e));
        } else {
            // Modo creación
            fetch(moduloUrl, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ order: [...getModuloOrder(), nombre] }),
            }).then(() => {
                addModuloGroup(activeBlock, nombre);
                closeModal();
            }).catch(e => console.error('create modulo failed', e));
        }
    }

    function getModuloOrder() {
        return [...document.querySelectorAll('.modulo-group')]
            .map(g => g.dataset.modulo)
            .filter(Boolean);
    }

    function addModuloGroup(block, nombre) {
        const container = document.getElementById(block + '-groups');
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'modulo-group';
        div.dataset.modulo = nombre;
        const renameBase = "{{ route('config.projects.tables.rename-modulo', [$project, '__MODULO__']) }}";
        const deleteBase = "{{ route('config.projects.tables.delete-modulo', [$project, '__MODULO__']) }}";
        div.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <div class="group-handle flex items-center gap-1.5 cursor-grab select-none">
                    <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5h16.5M3.75 12h16.5M3.75 19h16.5"/>
                    </svg>
                    <span class="text-xs font-semibold text-orange-500 uppercase tracking-wider">${nombre}</span>
                </div>
                <button class="btn-delete-modulo cursor-pointer p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-100 transition-colors leading-none"
                        data-modulo="${nombre}"
                        data-url="${deleteBase.replace('__MODULO__', encodeURIComponent(nombre))}"
                        title="Eliminar módulo">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
                <button class="btn-edit-modulo cursor-pointer p-1 rounded text-gray-400 hover:text-orange-500 hover:bg-orange-100 transition-colors leading-none"
                        data-modulo="${nombre}"
                        data-url="${renameBase.replace('__MODULO__', encodeURIComponent(nombre))}"
                        title="Renombrar módulo">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                    </svg>
                </button>
            </div>
            <div class="tables-grid border border-dashed border-gray-200 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 min-h-[52px]"
                 data-reorder-url="${reorderUrl}"
                 data-block="${block}"></div>`;
        container.appendChild(div);
        // Activar sortable en el nuevo grid
        Sortable.create(div.querySelector('.tables-grid'), {
            group: 'tables', animation: 150, ghostClass: 'opacity-40',
            onEnd: cardDragEnd,
        });
        // Activar botones
        bindEditModulo(div.querySelector('.btn-edit-modulo'));
        bindDeleteModulo(div.querySelector('.btn-delete-modulo'));
    }

    // ── Eliminar módulo ─────────────────────────────────────────────────────────
    function bindDeleteModulo(btn) {
        if (!btn) return;
        btn.addEventListener('click', () => {
            const groupEl = btn.closest('.modulo-group');
            const grid    = groupEl?.querySelector('.tables-grid');
            const isEmpty = !grid || grid.querySelectorAll('.table-card').length === 0;
            if (!isEmpty && !confirm('El módulo tiene tablas asignadas. ¿Seguro que quieres eliminarlo?')) return;
            fetch(btn.dataset.url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            }).then(() => groupEl?.remove())
              .catch(e => console.error('delete modulo failed', e));
        });
    }
    document.querySelectorAll('.btn-delete-modulo').forEach(bindDeleteModulo);

    // ── Drag de módulos (cabeceras) ─────────────────────────────────────────────
    document.querySelectorAll('#panel-groups, #config-groups').forEach(blockEl => {
        Sortable.create(blockEl, {
            handle: '.group-handle',
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd() { sendModuloOrder(blockEl); },
        });
    });

    // Drag de tarjetas dentro de grupos (cross-group y cross-block permitido)
    function cardDragEnd(evt) {
        if (evt.from !== evt.to) {
            const adminOnly = evt.to.closest('[data-block]')?.dataset.block === 'config';
            fetch(evt.item.dataset.patchUrl, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ admin_only: adminOnly }),
            }).catch(e => console.error('patch admin_only failed', e));

            const fromModulo = evt.from.closest('.modulo-group')?.dataset.modulo ?? '';
            const toModulo   = evt.to.closest('.modulo-group')?.dataset.modulo ?? '';
            if (fromModulo !== toModulo) {
                fetch(evt.item.dataset.moduloUrl, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ modulo: toModulo || null }),
                }).catch(e => console.error('patch modulo failed', e));
            }
        }
        sendGlobalOrder();
    }

    document.querySelectorAll('.tables-grid').forEach(grid => {
        Sortable.create(grid, { group: 'tables', animation: 150, ghostClass: 'opacity-40', onEnd: cardDragEnd });
    });
});

    function deleteTable(btn) {
        const card = btn.closest('.table-card');
        const name = btn.dataset.name;
        if (!confirm('¿Eliminar la tabla "' + name + '"? Se borrarán también todos sus campos.')) return;
        fetch(btn.dataset.destroyUrl, {
            method: 'DELETE',
            redirect: 'manual',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(r => {
            if (r.ok || r.type === 'opaqueredirect') {
                card.remove();
            } else {
                alert('Error al eliminar la tabla');
            }
        }).catch(() => alert('Error al eliminar la tabla'));
    }

    function toggleActive(btn) {
        const currentActive = btn.dataset.active === '1';
        const newActive = currentActive ? 0 : 1;
        fetch(btn.dataset.patchUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ active: newActive })
        }).then(r => {
            if (!r.ok) throw new Error();
            btn.dataset.active = newActive ? '1' : '0';
            if (newActive) {
                btn.classList.remove('text-gray-200', 'hover:text-gray-400');
                btn.classList.add('text-gray-400', 'hover:text-gray-600');
                btn.title = 'Ocultar del menú';
            } else {
                btn.classList.remove('text-gray-400', 'hover:text-gray-600');
                btn.classList.add('text-gray-200', 'hover:text-gray-400');
                btn.title = 'Mostrar en menú';
            }
        }).catch(() => alert('Error al actualizar'));
    }
</script>
