{{--
    Renderiza el input adecuado para cada tipo de campo en la ficha.
    Variables: $campo (TableField), $valor (mixed)
--}}
@php
    $hasError = $errors->has($campo->name);
    $base = 'w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-default '
          . ($hasError
              ? 'border-red-400 focus:ring-red-200 bg-red-50'
              : 'border-gray-200 focus:ring-orange-300');
    $req  = $campo->required ? 'required' : '';
@endphp

@switch($campo->type)

    @case('text')
        <textarea id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
                  rows="4" {{ $req }}
                  class="{{ $base }} resize-none">{{ $valor }}</textarea>
        @break

    @case('fecha')
        <input type="date" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor ? \Carbon\Carbon::parse($valor)->format('Y-m-d') : '' }}"
               {{ $req }} class="{{ $base }}">
        @break

    @case('time')
        <input type="time" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}"
               {{ $req }} class="{{ $base }}">
        @break

    @case('int')
        <input type="number" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}"
               {{ $req }} class="{{ $base }}">
        @break

    @case('decimal')
        <input type="number" step="0.01" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}"
               {{ $req }} class="{{ $base }}">
        @break

    @case('email')
        <input type="email" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}"
               {{ $req }} class="{{ $base }}">
        @break

    @case('telefono')
        <input type="tel" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}" maxlength="20"
               {{ $req }} class="{{ $base }}">
        @break

    @case('password')
        <input type="password" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               placeholder="Dejar vacío para no cambiar"
               class="{{ $base }}">
        @break

    @case('tinyint')
        <select id="campo_{{ $campo->name }}" name="{{ $campo->name }}" class="{{ $base }}">
            <option value="0" {{ !$valor ? 'selected' : '' }}>No</option>
            <option value="1" {{ $valor  ? 'selected' : '' }}>Sí</option>
        </select>
        @break

    @case('smallint')
        <div class="pt-1">
            <input type="checkbox" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
                   value="1" {{ $valor ? 'checked' : '' }}
                   class="w-4 h-4 accent-orange-500">
        </div>
        @break

    @case('id')
        @php $opciones = $fkOptions[$campo->name] ?? []; @endphp
        <select id="campo_{{ $campo->name }}" name="{{ $campo->name }}" {{ $req }} class="{{ $base }}">
            <option value="">— Selecciona —</option>
            @foreach($opciones as $id => $nombre)
                <option value="{{ $id }}" {{ (string)$valor === (string)$id ? 'selected' : '' }}>{{ $nombre }}</option>
            @endforeach
        </select>
        @break

    @case('select')
        <select id="campo_{{ $campo->name }}" name="{{ $campo->name }}" {{ $req }} class="{{ $base }}">
            <option value="">— Selecciona —</option>
            @foreach($campo->getOptions() as $opcion)
                <option value="{{ $opcion }}" {{ $valor === $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
            @endforeach
        </select>
        @break

    @case('multitabla')
        @php
            $selected = is_array($valor) ? $valor : (is_string($valor) ? json_decode($valor, true) ?? [] : []);
            $tablas   = ($projectTables ?? collect())->map(fn($t) => ['name' => $t->name, 'label' => $t->label])->values()->toArray();
        @endphp
        <div x-data="{
                all:      {{ json_encode($tablas) }},
                selected: {{ json_encode($selected) }},
                q: '',
                open: false,
                get filtered() {
                    const q = this.q.toLowerCase();
                    return this.all.filter(t =>
                        !this.selected.includes(t.name) &&
                        (t.label.toLowerCase().includes(q) || t.name.toLowerCase().includes(q))
                    );
                },
                add(name) { this.selected.push(name); this.q = ''; this.open = false; },
                remove(name) { this.selected = this.selected.filter(n => n !== name); },
                labelOf(name) { return (this.all.find(t => t.name === name) || {}).label || name; }
             }"
             @click.outside="open = false"
             data-multitabla
             class="relative">

            {{-- Tags seleccionadas + input --}}
            <div @click="$refs.input.focus(); open = filtered.length > 0"
                 class="min-h-[38px] flex flex-wrap gap-1.5 items-center border rounded-lg px-2 py-1.5 cursor-text transition-colors
                        {{ $hasError ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-white focus-within:border-orange-300 focus-within:ring-2 focus-within:ring-orange-200' }}">

                <template x-for="name in selected" :key="name">
                    <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 bg-orange-100 text-orange-800 text-xs rounded-md">
                        <span x-text="labelOf(name)"></span>
                        <button type="button" @click.stop="remove(name)"
                                class="ml-0.5 text-orange-400 hover:text-orange-700 leading-none disabled:pointer-events-none">
                            <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 2l8 8M10 2l-8 8"/>
                            </svg>
                        </button>
                    </span>
                </template>

                <input x-ref="input"
                       x-model="q"
                       @input="open = filtered.length > 0"
                       @focus="open = filtered.length > 0"
                       @keydown.escape="open = false; q = ''"
                       @keydown.enter.prevent="if (filtered.length) add(filtered[0].name)"
                       type="text"
                       placeholder="{{ $selected ? '' : 'Buscar tabla…' }}"
                       class="flex-1 min-w-[120px] text-sm outline-none bg-transparent py-0.5 disabled:pointer-events-none">
            </div>

            {{-- Dropdown de sugerencias --}}
            <ul x-show="open" x-cloak
                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
                <template x-for="t in filtered" :key="t.name">
                    <li @mousedown.prevent="add(t.name)"
                        class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-orange-50">
                        <span x-text="t.label" class="font-medium text-gray-700"></span>
                        <span x-text="t.name" class="text-gray-400 text-xs font-mono"></span>
                    </li>
                </template>
            </ul>

            {{-- Inputs ocultos para el submit del form --}}
            <template x-for="name in selected" :key="name">
                <input type="hidden" name="{{ $campo->name }}[]" :value="name">
            </template>

            <p class="mt-1 text-xs text-gray-400" x-show="selected.length === 0">
                Vacío = sin restricciones (acceso a todas las tablas)
            </p>
        </div>
        @break

    @case('multiusuario')
        @php
            $selected  = is_array($valor) ? $valor : (is_string($valor) ? json_decode($valor, true) ?? [] : []);
            $selected  = array_map('intval', $selected);
            $usuarios  = $projectUsuarios ?? [];
        @endphp
        <div x-data="{
                all:      {{ json_encode($usuarios) }},
                selected: {{ json_encode($selected) }},
                q: '',
                open: false,
                get filtered() {
                    const q = this.q.toLowerCase();
                    return this.all.filter(u =>
                        !this.selected.includes(u.id) &&
                        u.label.toLowerCase().includes(q)
                    );
                },
                add(id) { this.selected.push(id); this.q = ''; this.open = false; },
                remove(id) { this.selected = this.selected.filter(n => n !== id); },
                labelOf(id) { return (this.all.find(u => u.id === id) || {}).label || '#' + id; }
             }"
             @click.outside="open = false"
             data-multitabla
             class="relative">

            <div @click="$refs.input.focus(); open = filtered.length > 0"
                 class="min-h-[38px] flex flex-wrap gap-1.5 items-center border rounded-lg px-2 py-1.5 cursor-text transition-colors
                        {{ $hasError ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-white focus-within:border-orange-300 focus-within:ring-2 focus-within:ring-orange-200' }}">

                <template x-for="id in selected" :key="id">
                    <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 bg-orange-100 text-orange-800 text-xs rounded-md">
                        <span x-text="labelOf(id)"></span>
                        <button type="button" @click.stop="remove(id)"
                                class="ml-0.5 text-orange-400 hover:text-orange-700 leading-none disabled:pointer-events-none">
                            <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 2l8 8M10 2l-8 8"/>
                            </svg>
                        </button>
                    </span>
                </template>

                <input x-ref="input"
                       x-model="q"
                       @input="open = filtered.length > 0"
                       @focus="open = filtered.length > 0"
                       @keydown.escape="open = false; q = ''"
                       @keydown.enter.prevent="if (filtered.length) add(filtered[0].id)"
                       type="text"
                       placeholder="{{ $selected ? '' : 'Buscar usuario…' }}"
                       class="flex-1 min-w-[120px] text-sm outline-none bg-transparent py-0.5 disabled:pointer-events-none">
            </div>

            <ul x-show="open" x-cloak
                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
                <template x-for="u in filtered" :key="u.id">
                    <li @mousedown.prevent="add(u.id)"
                        class="px-3 py-2 cursor-pointer hover:bg-orange-50 text-gray-700">
                        <span x-text="u.label"></span>
                    </li>
                </template>
            </ul>

            <template x-for="id in selected" :key="id">
                <input type="hidden" name="{{ $campo->name }}[]" :value="id">
            </template>
        </div>
        @break

    @case('file')
        <div class="space-y-2">
            @if($valor)
                <a href="{{ asset($valor) }}" target="_blank"
                   class="text-xs text-blue-500 hover:underline block">Ver archivo actual</a>
            @endif
            <input type="file" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
                   class="text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
        </div>
        @break

    @default
        {{-- string y cualquier otro tipo --}}
        <input type="text" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor }}"
               {{ $campo->isAutocalc() ? 'data-readonly="1" readonly' : $req }}
               class="{{ $base }} {{ $campo->isAutocalc() ? 'text-gray-400 italic' : '' }}">
@endswitch
