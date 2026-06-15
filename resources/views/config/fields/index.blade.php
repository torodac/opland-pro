<x-app-layout :project="$project" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => $project->name, 'url' => route('config.projects.tables.index', $project)],
    ['label' => $table->label, 'url' => ''],
]">

    <x-slot name="actions">
        <button @click="window.dispatchEvent(new CustomEvent('abrir-nuevo-campo'))"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo campo
        </button>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif


    {{-- Barra ajustes de tabla --}}
    @php $patchTableUrl = route('config.projects.tables.patch', [$project, $table]); @endphp
    <div class="mb-4 flex items-center gap-4 px-5 py-3.5 bg-white rounded-xl border border-gray-200 text-sm text-gray-600 flex-wrap"
         x-data="{
             label: {{ json_encode($table->label) }},
             icon: '{{ $table->icon ?? '' }}',
             saving: false,
             save(fields) {
                 this.saving = true;
                 fetch('{{ $patchTableUrl }}', {
                     method: 'PATCH',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                     body: JSON.stringify(fields)
                 }).finally(() => { setTimeout(() => this.saving = false, 600) });
             }
         }">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide shrink-0">Nombre</span>
        <input type="text" x-model="label" @change="save({ label })" @blur="save({ label })"
               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1 w-48 focus:outline-none focus:ring-2 focus:ring-orange-300">
        <span class="text-gray-200">|</span>
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide shrink-0">Icono sidebar</span>
        <div class="flex items-center gap-2">
            <i :class="icon || 'fa-regular fa-circle'" class="fa-fw text-gray-500 text-sm w-5 text-center"></i>
            <input type="text" x-model="icon" @change="save({ icon })" @blur="save({ icon })"
                   placeholder="fa-solid fa-dumbbell"
                   class="text-sm border border-gray-200 rounded-lg px-2.5 py-1 w-56 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono">
        </div>
        <a href="https://fontawesome.com/v6/search?o=r&m=free" target="_blank"
           class="text-xs text-orange-500 hover:underline shrink-0">Ver iconos</a>
        <span x-show="saving" class="text-xs text-gray-400" x-cloak>Guardando…</span>
    </div>

    {{-- Configuración nombre --}}
    <div class="mb-4 px-5 py-3.5 bg-white rounded-xl border border-gray-200 text-sm text-gray-600"
         x-data="{
             formula: {{ json_encode($table->nombre_formula ?? '') }},
             ocultarFicha: {{ $table->nombre_ocultar_ficha ?? true ? 'true' : 'false' }},
             saving: false,
             save() {
                 this.saving = true;
                 fetch('{{ $patchTableUrl }}', {
                     method: 'PATCH',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                     body: JSON.stringify({ nombre_formula: this.formula, nombre_ocultar_ficha: this.ocultarFicha })
                 }).finally(() => { setTimeout(() => this.saving = false, 600) });
             }
         }">
        <div class="flex items-center gap-4 flex-wrap">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide shrink-0">Fórmula nombre</span>
            <input type="text" x-model="formula" @change="save()" @blur="save()"
                   placeholder='campo1+"_"+campo2'
                   class="text-sm border border-gray-200 rounded-lg px-2.5 py-1 w-80 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono">
            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-500">
                <input type="checkbox" x-model="ocultarFicha" @change="save()" class="rounded">
                Ocultar en ficha
            </label>
            <span x-show="saving" class="text-xs text-gray-400" x-cloak>Guardando…</span>
        </div>
        <p class="text-xs text-gray-400 mt-1.5">Ej: <span class="font-mono">fecha_inicio+"_"+responsable</span> — los desplegables se resuelven a su nombre.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="w-8"></th>
                        <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Etiqueta</th>
                        <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Columna BD</th>
                        <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                        <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Extras</th>
                        <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Listado</th>
                        <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Form.</th>
                        <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Req.</th>
                        <th class="w-16"></th>
                    </tr>
                </thead>
                <tbody id="sortable-fields"
                       class="divide-y divide-gray-50"
                       data-reorder-url="{{ route('config.projects.tables.fields.reorder', [$project, $table]) }}"
                       data-csrf="{{ csrf_token() }}">
                    @forelse($fields as $field)
                        @php $patchUrl = route('config.projects.tables.fields.patch', [$project, $table, $field]); @endphp
                        <tr class="hover:bg-gray-50 group" data-id="{{ $field->id }}">

                            {{-- Handle arrastre --}}
                            <td class="pl-2 pr-0 py-2 w-8 text-center cursor-grab active:cursor-grabbing">
                                <svg class="w-4 h-4 text-gray-200 group-hover:text-gray-400 mx-auto transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                                </svg>
                            </td>

                            {{-- Etiqueta editable --}}
                            <td class="px-1 py-1"
                                x-data="inlineText('{{ $patchUrl }}', '{{ csrf_token() }}', 'label', {{ json_encode($field->label) }})">
                                <div @click="editing = true">
                                    <span x-show="!editing"
                                          class="block px-2 py-1 min-w-24 min-h-7 rounded cursor-text hover:bg-gray-100 font-medium text-gray-800"
                                          x-text="value"></span>
                                    <input x-show="editing" x-model="value" type="text"
                                           @blur="save()" @keydown.enter="$el.blur()" @keydown.escape="cancel()"
                                           x-init="$watch('editing', v => v && $nextTick(() => $el.focus()))"
                                           class="w-full text-sm border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none font-medium">
                                </div>
                            </td>

                            {{-- Nombre BD (solo lectura) --}}
                            <td class="px-3 py-2 font-mono text-xs text-gray-400 whitespace-nowrap">{{ $field->name }}</td>

                            {{-- Tipo (solo lectura) --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded font-mono">{{ $field->type }}</span>
                            </td>

                            {{-- Extras editable --}}
                            <td class="px-1 py-1"
                                x-data="inlineText('{{ $patchUrl }}', '{{ csrf_token() }}', 'extras', {{ json_encode($field->extras ?? '') }})">
                                <div @click="editing = true">
                                    <span x-show="!editing"
                                          class="block px-2 py-1 min-w-16 min-h-7 rounded cursor-text hover:bg-gray-100 font-mono text-xs text-gray-400"
                                          x-text="value || '—'"></span>
                                    <input x-show="editing" x-model="value" type="text"
                                           @blur="save()" @keydown.enter="$el.blur()" @keydown.escape="cancel()"
                                           x-init="$watch('editing', v => v && $nextTick(() => $el.focus()))"
                                           class="w-full text-sm border border-orange-300 ring-2 ring-orange-200 rounded px-2 py-1 bg-white outline-none font-mono text-xs">
                                </div>
                            </td>

                            {{-- Toggle in_list --}}
                            <td class="px-3 py-2 text-center"
                                x-data="inlineToggle('{{ $patchUrl }}', '{{ csrf_token() }}', 'in_list', {{ $field->in_list ? 'true' : 'false' }})">
                                <button @click="toggle()" class="w-5 h-5 mx-auto flex items-center justify-center rounded transition-colors"
                                        :class="value ? 'text-green-500 hover:text-green-600' : 'text-gray-200 hover:text-gray-400'">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
                                        <path x-show="value" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        <path x-show="!value" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </td>

                            {{-- Toggle in_form --}}
                            <td class="px-3 py-2 text-center"
                                x-data="inlineToggle('{{ $patchUrl }}', '{{ csrf_token() }}', 'in_form', {{ $field->in_form ? 'true' : 'false' }})">
                                <button @click="toggle()" class="w-5 h-5 mx-auto flex items-center justify-center rounded transition-colors"
                                        :class="value ? 'text-green-500 hover:text-green-600' : 'text-gray-200 hover:text-gray-400'">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
                                        <path x-show="value" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        <path x-show="!value" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </td>

                            {{-- Toggle required --}}
                            <td class="px-3 py-2 text-center"
                                x-data="inlineToggle('{{ $patchUrl }}', '{{ csrf_token() }}', 'required', {{ $field->required ? 'true' : 'false' }})">
                                <button @click="toggle()" class="w-5 h-5 mx-auto flex items-center justify-center rounded transition-colors"
                                        :class="value ? 'text-orange-500 hover:text-orange-600' : 'text-gray-200 hover:text-gray-400'">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
                                        <path x-show="value" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        <path x-show="!value" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </td>

                            {{-- Acciones --}}
                            <td class="px-2 py-2 text-right" x-data="{ open: false }">
                                <button @click="open = !open" @click.outside="open = false"
                                        class="p-1 rounded text-gray-300 hover:text-gray-600 hover:bg-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                    </svg>
                                </button>
                                <div x-show="open"
                                     class="absolute right-6 mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm">
                                    <a href="{{ route('config.projects.tables.fields.edit', [$project, $table, $field]) }}"
                                       class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:bg-gray-50">
                                        Editar avanzado
                                    </a>
                                    <form method="POST" action="{{ route('config.projects.tables.fields.destroy', [$project, $table, $field]) }}"
                                          onsubmit="return confirm('¿Eliminar campo «{{ addslashes($field->label) }}»?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-red-500 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-gray-400">No hay campos. Añade el primero.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pestañas en ficha --}}
    @if($relatedTables->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Pestañas en la ficha</h2>
                <p class="text-xs text-gray-400 mt-0.5">Tablas relacionadas que aparecerán como pestañas en la ficha de cada registro</p>
            </div>
            <form method="POST" action="{{ route('config.projects.tables.tabs', [$project, $table]) }}" class="p-4">
                @csrf @method('PATCH')
                <div class="space-y-2">
                    @foreach($relatedTables as $related)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="tab_tables[]" value="{{ $related->name }}"
                                   {{ in_array($related->name, $table->getTabTables()) ? 'checked' : '' }}
                                   class="w-4 h-4 accent-orange-500">
                            <span class="text-sm text-gray-700">{{ $related->label }}</span>
                            <span class="text-xs text-gray-400 font-mono">{{ $related->name }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4">
                    <button type="submit"
                            class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                        Guardar pestañas
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Clonar estructura (solo admin global) --}}
    @if(auth()->user()?->isAdmin() && $allProjects->isNotEmpty())
    <div class="mt-6 bg-white rounded-xl border border-gray-200">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 rounded-t-xl">
            <h2 class="text-sm font-semibold text-gray-700">Clonar estructura</h2>
            <p class="text-xs text-gray-400 mt-0.5">Crea una tabla nueva con los mismos campos en el proyecto que elijas. No copia datos.</p>
        </div>
        @if($errors->has('new_name'))
            <p class="text-xs text-red-500 px-4 pt-3">{{ $errors->first('new_name') }}</p>
        @endif
        <form method="POST" action="{{ route('config.projects.tables.clone', [$project, $table]) }}" class="p-4 space-y-3">
            @csrf
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Proyecto destino</label>
                <select name="target_project_id" required
                        class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 w-64">
                    @foreach($allProjects as $proj)
                        <option value="{{ $proj->id }}" {{ $proj->id === $project->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Nombre interno <span class="text-red-400">*</span></label>
                <input type="text" name="new_name" required
                       value="{{ old('new_name') }}"
                       placeholder="ej: fotos_extra"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono w-64">
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Etiqueta <span class="text-red-400">*</span></label>
                <input type="text" name="new_label" required
                       value="{{ old('new_label') }}"
                       placeholder="ej: Fotos extra"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 w-64">
            </div>
            <div>
                <button type="submit"
                        class="px-5 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fa-regular fa-copy mr-1.5"></i> Crear tabla
                </button>
            </div>
        </form>
    </div>
    @endif

</x-app-layout>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function tableSettingRadio(url, csrf, field, initial) {
    return {
        val: initial,
        saving: false,
        async save() {
            this.saving = true;
            await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ [field]: this.val })
            });
            this.saving = false;
        }
    };
}

function tableSettingToggle(url, csrf, field, initial) {
    return {
        val: initial,
        async toggle() {
            this.val = !this.val;
            await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ [field]: this.val })
            });
        }
    };
}

function inlineText(url, csrf, field, initial) {
    return {
        editing: false,
        value: initial,
        original: initial,
        async save() {
            if (this.value === this.original) { this.editing = false; return; }
            await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ [field]: this.value })
            });
            this.original = this.value;
            this.editing = false;
        },
        cancel() { this.value = this.original; this.editing = false; }
    };
}

function inlineToggle(url, csrf, field, initial) {
    return {
        value: initial,
        async toggle() {
            this.value = !this.value;
            await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ [field]: this.value })
            });
        }
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('sortable-fields');
    if (!container) return;

    Sortable.create(container, {
        animation: 150,
        handle: 'td:first-child',
        ghostClass: 'opacity-40',
        onEnd() {
            const ids  = [...container.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
            const url  = container.dataset.reorderUrl;
            const csrf = container.dataset.csrf;
            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ids })
            });
        }
    });
});


</script>

{{-- Modal Nuevo campo --}}
@php $maxOrder = $fields->max('order') ?? 0; @endphp
<div x-data="{
    open: {{ $errors->any() ? 'true' : 'false' }},
    type: '{{ old('type') }}',
    submitting: false,
    errors: {},
    async submit(e) {
        this.submitting = true;
        this.errors = {};
        const form = e.target;
        const data = new FormData(form);
        const res  = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        if (res.ok) {
            window.location.reload();
        } else if (res.status === 422) {
            const json = await res.json();
            this.errors = json.errors ?? {};
        }
        this.submitting = false;
    }
}"
     x-show="open" x-cloak
     @abrir-nuevo-campo.window="open = true"
     class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-1/3 min-w-80">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Nuevo campo</h3>
            <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form @submit.prevent="submit($event)" action="{{ route('config.projects.tables.fields.store', [$project, $table]) }}" method="POST">
            @csrf
            <input type="hidden" name="order" value="{{ $maxOrder + 1 }}">
            <div class="divide-y divide-gray-50">
                <div class="px-6 py-4 flex items-start gap-4">
                    <label class="w-28 shrink-0 text-sm text-gray-400 pt-2">Columna <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <input type="text" name="name" value="{{ old('name') }}" required
                               :class="errors.name ? 'border-red-300 ring-1 ring-red-300' : 'border-gray-200'"
                               class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               placeholder="ej: fecha_alta">
                        <p x-show="errors.name" x-text="errors.name?.[0]" class="text-xs text-red-500 mt-1"></p>
                        <p x-show="!errors.name" class="text-xs text-gray-400 mt-1">Nombre en la base de datos.</p>
                    </div>
                </div>
                <div class="px-6 py-4 flex items-start gap-4">
                    <label class="w-28 shrink-0 text-sm text-gray-400 pt-2">Etiqueta <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <input type="text" name="label" value="{{ old('label') }}" required
                               :class="errors.label ? 'border-red-300 ring-1 ring-red-300' : 'border-gray-200'"
                               class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                               placeholder="ej: Fecha de alta">
                        <p x-show="errors.label" x-text="errors.label?.[0]" class="text-xs text-red-500 mt-1"></p>
                    </div>
                </div>
                <div class="px-6 py-4 flex items-start gap-4">
                    <label class="w-28 shrink-0 text-sm text-gray-400 pt-2">Tipo <span class="text-red-400">*</span></label>
                    <div class="flex-1">
                        <select name="type" required x-model="type"
                                :class="errors.type ? 'border-red-300 ring-1 ring-red-300' : 'border-gray-200'"
                                class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                            <option value="">— Selecciona —</option>
                            @foreach(array_keys(\App\Models\TableField::$typeMap) as $key)
                                @if(in_array($key, ['id', 'multitabla'])) @continue @endif
                                <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $key }}</option>
                            @endforeach
                        </select>
                        <p x-show="errors.type" x-text="errors.type?.[0]" class="text-xs text-red-500 mt-1"></p>
                    </div>
                </div>
                <div x-show="type === 'select' || type === 'desplegable'" class="px-6 py-4 flex items-start gap-4">
                    <label class="w-28 shrink-0 text-sm text-gray-400 pt-2">Extras</label>
                    <div class="flex-1">
                        <input type="text" name="extras" value="{{ old('extras') }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 font-mono"
                               :placeholder="type === 'select' ? 'opt:Opción1,Opción2' : 'ref:tabla'">
                        <p class="text-xs text-gray-400 mt-1">
                            <span x-show="type === 'select'">Formato: <span class="font-mono">opt:Val1,Val2</span></span>
                            <span x-show="type === 'desplegable'">Formato: <span class="font-mono">ref:nombre_tabla</span></span>
                        </p>
                    </div>
                </div>
                <div class="px-6 py-4 flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="required" value="1" {{ old('required') ? 'checked' : '' }} class="w-4 h-4 accent-orange-500"> Obligatorio
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="in_list" value="1" {{ old('in_list', true) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500"> En listado
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="in_form" value="1" {{ old('in_form', true) ? 'checked' : '' }} class="w-4 h-4 accent-orange-500"> En formulario
                    </label>
                </div>
            </div>
            <div class="px-6 py-5 flex items-center justify-between">
                <button type="button" @click="open = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                    Cancelar
                </button>
                <button type="submit" :disabled="submitting"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50">
                    <span x-show="!submitting">Crear campo</span>
                    <span x-show="submitting">Creando…</span>
                </button>
            </div>
        </form>
    </div>
</div>
