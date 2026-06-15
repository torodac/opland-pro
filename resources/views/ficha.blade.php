<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

    <x-slot name="actions">
        @if($registro)
            {{-- Modo lectura --}}
            <div id="grupo-ver" class="flex gap-2">
                @if($canEdit ?? true)
                <button onclick="toggleEdit()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 0L19 7l-6.586 6.586A2 2 0 0116 14H13v-3z"/>
                    </svg>
                    <span class="hidden sm:inline">Editar</span>
                </button>
                @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed"
                      title="No tienes permisos para editar esta tabla">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <span class="hidden sm:inline">Sin permisos</span>
                </span>
                @endif
            </div>

            {{-- Modo edición --}}
            <div id="grupo-editar" style="display:none" class="flex gap-2">
                {{-- Ocultar --}}
                <form method="POST" action="{{ route('ficha.archive', [$project->slug, $projectTable->name, $registro->id]) }}">
                    @csrf @method('PATCH')
                    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors
                        {{ $registro->hidden ? 'border-green-300 text-green-600 hover:bg-green-50' : 'border-amber-300 text-amber-600 hover:bg-amber-50' }}">
                        <i class="fas {{ $registro->hidden ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                        <span class="hidden sm:inline">{{ $registro->hidden ? 'Mostrar' : 'Ocultar' }}</span>
                    </button>
                </form>

                {{-- Eliminar --}}
                <button type="button" onclick="confirmarEliminar()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors
                            {{ $registro->deleted ? 'border-green-300 text-green-600 hover:bg-green-50' : 'border-red-300 text-red-500 hover:bg-red-50' }}">
                    <i class="fas {{ $registro->deleted ? 'fa-trash-restore' : 'fa-trash' }} text-xs"></i>
                    <span class="hidden sm:inline">{{ $registro->deleted ? 'Restaurar' : 'Eliminar' }}</span>
                </button>

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
        <div @if($registro && !empty($tabs)) x-show="tab === 'detalles'" @endif>
            <form method="POST"
                  action="{{ $registro
                    ? route('ficha.update', [$project->slug, $projectTable->name, $registro->id])
                    : route('ficha.store',  [$project->slug, $projectTable->name]) }}"
                  id="ficha-form">
                @csrf
                @if($registro) @method('PUT') @endif

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
                            {{-- Dos campos por fila (o uno solo si es el último impar) --}}
                            @php $campo2 = ($i + 1 < $total && $camposList[$i + 1]->type !== 'text') ? $camposList[$i + 1] : null; @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-2">
                                <div class="px-5 py-4">
                                    <label for="campo_{{ $campo->name }}"
                                           class="block text-xs font-bold text-gray-600 mb-1.5">
                                        {{ $campo->label }}
                                        @if($campo->required)<span class="text-red-400">*</span>@endif
                                    </label>
                                    @php $valor = $registro ? ($registro->{$campo->name} ?? null) : (old($campo->name) ?? ($prefill[$campo->name] ?? null)); @endphp
                                    @include('partials.field', ['campo' => $campo, 'valor' => $valor])
                                </div>
                                @if($campo2)
                                    <div class="px-5 py-4">
                                        <label for="campo_{{ $campo2->name }}"
                                               class="block text-xs font-bold text-gray-600 mb-1.5">
                                            {{ $campo2->label }}
                                            @if($campo2->required)<span class="text-red-400">*</span>@endif
                                        </label>
                                        @php $valor = $registro ? ($registro->{$campo2->name} ?? null) : (old($campo2->name) ?? ($prefill[$campo2->name] ?? null)); @endphp
                                        @include('partials.field', ['campo' => $campo2, 'valor' => $valor])
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
                {{-- Form oculto para borrar/restaurar (disparado por el popup) --}}
                <form id="form-destroy" method="POST" action="{{ route('ficha.destroy', [$project->slug, $projectTable->name, $registro->id]) }}" class="hidden">
                    @csrf @method('DELETE')
                </form>

                <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-300">
                    @if($registro->createdat)
                        <span>
                            Creado {{ \Carbon\Carbon::parse($registro->createdat)->format('d/m/Y H:i') }}
                            @if($createUser) <span class="text-gray-400">por {{ $createUser }}</span>@endif
                        </span>
                    @endif
                    @if($registro->updatedat)
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
        </div>

        {{-- Paneles de tablas relacionadas --}}
        @foreach($tabs as $tabData)
            <div x-show="tab === '{{ $tabData['table']->name }}'">
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
                        <table class="w-full text-sm">
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

{{-- Modal confirmación eliminar --}}
@if($registro)
<div id="modal-eliminar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="cerrarModalEliminar()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-1/3 min-w-80 p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-trash text-red-500"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $registro->deleted ? 'Restaurar registro' : 'Eliminar registro' }}
                </h3>
            </div>
            <p class="text-sm text-gray-500 mb-6">
                @if($registro->deleted)
                    ¿Quieres restaurar <strong>{{ $registro->nombre ?? "este registro" }}</strong>?
                @else
                    ¿Seguro que quieres eliminar <strong>{{ $registro->nombre ?? "este registro" }}</strong>? Esta acción se puede deshacer desde la vista de borrados.
                @endif
            </p>
            <div class="flex justify-end gap-2">
                <button onclick="cerrarModalEliminar()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button onclick="document.getElementById('form-destroy').submit()"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors
                            {{ $registro->deleted ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600' }}">
                    {{ $registro->deleted ? 'Restaurar' : 'Eliminar' }}
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
            f.disabled = readonly;
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

function confirmarEliminar() {
    document.getElementById('modal-eliminar').classList.remove('hidden');
}

function cerrarModalEliminar() {
    document.getElementById('modal-eliminar').classList.add('hidden');
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
        if (e.key === 'Escape') cerrarModalEliminar();
    });
});
</script>
