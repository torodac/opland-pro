{{--
    Renderiza el input adecuado para cada tipo de campo en la ficha.
    Variables: $campo (TableField), $valor (mixed)
--}}
@php
    $hasError = $errors->has($campo->name);
    $base = 'w-full text-xs border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-default '
          . ($hasError
              ? 'border-red-400 focus:ring-red-200 bg-red-50'
              : 'border-gray-200 focus:ring-orange-300');
    $req  = $campo->required ? 'required' : '';
    $esBonusMeses = ($projectTable->name ?? null) === 'bonus' && $campo->name === 'meses';
@endphp

@if($esBonusMeses)
    {{-- vm_bonus.meses: se teclean nombres de mes (buscador tipo tag-picker, igual que "Puede
         ver" de vm/roles), pero la columna guarda numeros separados por coma ("3,6,9,12"), no
         JSON -- por eso no se reutiliza el tipo "multitabla" tal cual (ese hace
         json_encode($request->input(...)) en FichaController::filterData(), formato distinto). --}}
    @php
        $mesesNombres = ['Enero'=>1,'Febrero'=>2,'Marzo'=>3,'Abril'=>4,'Mayo'=>5,'Junio'=>6,'Julio'=>7,'Agosto'=>8,'Septiembre'=>9,'Octubre'=>10,'Noviembre'=>11,'Diciembre'=>12];
        $todosMeses   = collect($mesesNombres)->map(fn($num, $nombre) => ['num' => $num, 'label' => $nombre])->values()->toArray();
        $seleccionados = collect(explode(',', (string) $valor))
            ->map(fn($n) => trim($n))
            ->filter(fn($n) => $n !== '' && ctype_digit($n))
            ->map(fn($n) => (int) $n)
            ->values()->toArray();
    @endphp
    <div x-data="{
            all:      {{ json_encode($todosMeses) }},
            selected: {{ json_encode($seleccionados) }},
            q: '',
            open: false,
            get filtered() {
                const q = this.q.toLowerCase();
                return this.all.filter(m =>
                    !this.selected.includes(m.num) &&
                    m.label.toLowerCase().includes(q)
                );
            },
            add(num) { this.selected.push(num); this.selected.sort((a,b) => a - b); this.q = ''; this.open = false; },
            remove(num) { this.selected = this.selected.filter(n => n !== num); },
            labelOf(num) { return (this.all.find(m => m.num === num) || {}).label || num; }
         }"
         @click.outside="open = false"
         data-multitabla
         class="relative">
        <div @click="$refs.input.focus(); open = filtered.length > 0"
             class="min-h-[38px] flex flex-wrap gap-1.5 items-center border rounded-lg px-2 py-1.5 cursor-text transition-colors
                    {{ $hasError ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-white focus-within:border-orange-300 focus-within:ring-2 focus-within:ring-orange-200' }}">
            <template x-for="num in selected" :key="num">
                <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 bg-orange-100 text-orange-800 text-xs rounded-md">
                    <span x-text="labelOf(num)"></span>
                    <button type="button" @click.stop="remove(num)"
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
                   @keydown.enter.prevent="if (filtered.length) add(filtered[0].num)"
                   type="text"
                   placeholder="{{ $seleccionados ? '' : 'Buscar mes…' }}"
                   class="flex-1 min-w-[120px] text-sm outline-none bg-transparent py-0.5 disabled:pointer-events-none">
        </div>
        <ul x-show="open" x-cloak
            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
            <template x-for="m in filtered" :key="m.num">
                <li @mousedown.prevent="add(m.num)"
                    class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-orange-50">
                    <span x-text="m.label" class="font-medium text-gray-700"></span>
                </li>
            </template>
        </ul>
        <input type="hidden" id="campo_{{ $campo->name }}" name="{{ $campo->name }}" {{ $req }} :value="selected.join(',')">
    </div>
@else
@switch($campo->type)

    @case('text')
        @php $valorTexto = is_array($valor) || is_object($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $valor; @endphp
        <textarea id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
                  rows="4" {{ $req }}
                  class="{{ $base }} resize-none">{{ $valorTexto }}</textarea>
        @break

    @case('fecha')
        <input type="date" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor ? \Carbon\Carbon::parse($valor)->format('Y-m-d') : '' }}"
               {{ $req }} class="{{ $base }}"
               @if(isset($min)) min="{{ $min }}" @endif
               @if(isset($max)) max="{{ $max }}" @endif>
        @break

    @case('time')
        <input type="time" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
               value="{{ $valor ? substr($valor, 0, 5) : '' }}"
               step="60" {{ $req }} class="{{ $base }}">
        @break

    @case('int')
        @php $rawInt = $valor !== null && $valor !== '' ? (int) $valor : ''; @endphp
        <input type="hidden" name="{{ $campo->name }}" id="campo_{{ $campo->name }}_raw" value="{{ $rawInt }}">
        <input type="text" inputmode="numeric" id="campo_{{ $campo->name }}"
               value="{{ $rawInt !== '' ? number_format($rawInt, 0, ',', '.') : '' }}"
               {{ $req }} class="{{ $base }}"
               oninput="this.previousElementSibling.value=this.value.replace(/\./g,'')"
               onblur="var n=parseInt(this.value.replace(/\./g,'')); if(!isNaN(n)){this.value=n.toLocaleString('es-ES');this.previousElementSibling.value=n;} else if(this.value===''){this.previousElementSibling.value='';}">
        @break

    @case('decimal')
        @php $rawDec = $valor !== null && $valor !== '' ? (float) $valor : ''; @endphp
        <input type="hidden" name="{{ $campo->name }}" id="campo_{{ $campo->name }}_raw" value="{{ $rawDec }}">
        <input type="text" inputmode="decimal" id="campo_{{ $campo->name }}"
               value="{{ $rawDec !== '' ? number_format($rawDec, 2, ',', '.') : '' }}"
               {{ $req }} class="{{ $base }}"
               oninput="this.previousElementSibling.value=this.value.replace(/\./g,'').replace(',','.')"
               onblur="var n=parseFloat(this.value.replace(/\./g,'').replace(',','.')); if(!isNaN(n)){this.value=n.toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2});this.previousElementSibling.value=n;} else if(this.value===''){this.previousElementSibling.value='';}">
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
    @case('desplegable')
        @php
            $opciones = $fkOptions[$campo->name] ?? [];
            // vm_bonus.id_referencia: el "alcance" (usuario/cargo/departamento) decide que tipo de
            // control corresponde -- desplegable de usuarios (este mismo, campo real ref:usuarios)
            // o uno de los dos <select> de valores fijos que se añaden justo debajo. Los tres
            // comparten el mismo input hidden (el de este fk-combo) como unico portador del valor
            // que se envia al servidor.
            //
            // OJO: para alcance=usuario, vm_bonus.id_referencia NO guarda el id numerico del
            // usuario -- guarda su NOMBRE (texto), porque VmUsuarioController::ficha() cruza los
            // bonus de un usuario con `alcance='usuario' AND id_referencia = usuario.nombre`
            // (comprobado en el codigo, no una suposicion). Por eso aqui se reindexan las opciones
            // de nombre=>nombre en vez de id=>nombre, para que el fk-combo (que envia el "id" del
            // <li> clicado) acabe enviando el nombre, no el id real de vm_usuarios.
            $esBonusReferencia = ($projectTable->name ?? null) === 'bonus' && $campo->name === 'id_referencia';
            if ($esBonusReferencia) {
                $opciones = collect($opciones)->mapWithKeys(fn($nombre) => [$nombre => $nombre])->all();
            }
            $selLabel = ($valor !== null && $valor !== '' && isset($opciones[$valor])) ? $opciones[$valor] : null;
            $alcanceActual = $esBonusReferencia ? old('alcance', optional($registro ?? null)->alcance ?: request('alcance')) : null;
        @endphp
        @if($esBonusReferencia)
        <div data-bonus-ref-wrap>
        @endif
        <div class="fk-combo" data-required="{{ $campo->required ? '1' : '0' }}" @if($esBonusReferencia) data-alcance-tipo="usuario" @endif
             style="position:relative;{{ ($esBonusReferencia && $alcanceActual && $alcanceActual !== 'usuario') ? 'display:none;' : '' }}{{ $hasError ? 'outline:1px solid #f87171;border-radius:0.5rem;' : '' }}">
            <button type="button" class="fk-combo-toggle"
                    style="width:100%;text-align:left;padding:0.5rem 0.75rem;border:1px solid {{ $hasError ? '#f87171' : '#e5e7eb' }};border-radius:0.5rem;background:#fff;font-size:0.75rem;display:flex;justify-content:space-between;align-items:center;gap:6px;cursor:pointer;color:{{ $selLabel ? '#111827' : '#9ca3af' }};">
                <span class="fk-combo-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $selLabel ?? '— Selecciona —' }}</span>
                <span style="flex-shrink:0;color:#9ca3af;">▾</span>
            </button>
            <div class="fk-combo-panel" style="display:none;position:absolute;z-index:30;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;box-shadow:0 6px 16px rgba(0,0,0,.12);">
                <input type="text" class="fk-combo-search" placeholder="Buscar…"
                       style="width:100%;box-sizing:border-box;padding:0.5rem 0.75rem;border:none;border-bottom:1px solid #f3f4f6;font-size:0.75rem;outline:none;border-radius:0.5rem 0.5rem 0 0;">
                <ul class="fk-combo-list" style="list-style:none;margin:0;padding:2px 0;max-height:12rem;overflow-y:auto;">
                    <li data-id="" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#9ca3af;">— Selecciona —</li>
                    @foreach($opciones as $id => $nombre)
                        <li data-id="{{ $id }}" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#374151;">{{ $nombre }}</li>
                    @endforeach
                </ul>
            </div>
            <input type="hidden" id="campo_{{ $campo->name }}" name="{{ $campo->name }}"
                   class="fk-combo-value" value="{{ $valor }}">
        </div>
        @if($esBonusReferencia)
            @php
                $opcionesCargo = ['Responsable propietarios','Jefe Mantenimiento','Recepcionista','Jefe finanzas','Revenue manager','RRHH','Contable','Oficial mantenimiento','Ayte. mantenimiento','Limpiadora','Gobernanta','Captador clientes','Ayte diseño','Rpble propietarios'];
                $opcionesDepto = ['Adm/Finanzas','Dirección','Expansión','Laboral','Limpieza','Mantenimiento','Operaciones','Proveedor ext','Proyectos','RRHH','Recepción','Reservas'];
            @endphp
            <select data-alcance-tipo="cargo" class="{{ $base }}"
                    style="{{ $alcanceActual !== 'cargo' ? 'display:none;' : '' }}"
                    onchange="document.getElementById('campo_id_referencia').value = this.value">
                <option value="">— Selecciona —</option>
                @foreach($opcionesCargo as $o)
                    <option value="{{ $o }}" {{ $valor === $o ? 'selected' : '' }}>{{ $o }}</option>
                @endforeach
            </select>
            <select data-alcance-tipo="departamento" class="{{ $base }}"
                    style="{{ $alcanceActual !== 'departamento' ? 'display:none;' : '' }}"
                    onchange="document.getElementById('campo_id_referencia').value = this.value">
                <option value="">— Selecciona —</option>
                @foreach($opcionesDepto as $o)
                    <option value="{{ $o }}" {{ $valor === $o ? 'selected' : '' }}>{{ $o }}</option>
                @endforeach
            </select>
        </div>
        @once
            <script>
            (function() {
                function actualizarBonusReferencia(selectAlcance) {
                    var wrap = document.querySelector('[data-bonus-ref-wrap]');
                    if (!wrap) return;
                    var val = selectAlcance.value;
                    wrap.querySelectorAll('[data-alcance-tipo]').forEach(function(el) {
                        el.style.display = (el.dataset.alcanceTipo === val) ? '' : 'none';
                    });
                    // Al cambiar de modo el valor anterior ya no es valido para el nuevo tipo -- se limpia.
                    var hidden = document.getElementById('campo_id_referencia');
                    if (hidden) hidden.value = '';
                    var comboLabel = wrap.querySelector('.fk-combo-label');
                    if (comboLabel) { comboLabel.textContent = '— Selecciona —'; comboLabel.style.color = '#9ca3af'; }
                    wrap.querySelectorAll('select[data-alcance-tipo]').forEach(function(s) { s.value = ''; });
                }
                document.addEventListener('DOMContentLoaded', function() {
                    var selectAlcance = document.getElementById('campo_alcance');
                    if (!selectAlcance || !document.querySelector('[data-bonus-ref-wrap]')) return;
                    selectAlcance.addEventListener('change', function() { actualizarBonusReferencia(this); });
                });
            })();
            </script>
        @endonce
        @endif
        @once
            <script>
            (function() {
                function initFkCombo(wrap) {
                    if (wrap.dataset.fkInit) return;
                    wrap.dataset.fkInit = '1';

                    const toggle = wrap.querySelector('.fk-combo-toggle');
                    const label  = wrap.querySelector('.fk-combo-label');
                    const panel  = wrap.querySelector('.fk-combo-panel');
                    const search = wrap.querySelector('.fk-combo-search');
                    const list   = wrap.querySelector('.fk-combo-list');
                    const hidden = wrap.querySelector('.fk-combo-value');
                    const items  = Array.from(list.querySelectorAll('li'));

                    function filtrar(q) {
                        const ql = q.trim().toLowerCase();
                        items.forEach(li => {
                            li.style.display = (li.dataset.id === '' || li.textContent.toLowerCase().includes(ql)) ? '' : 'none';
                        });
                    }

                    toggle.addEventListener('click', () => {
                        const willOpen = panel.style.display === 'none';
                        document.querySelectorAll('.fk-combo-panel').forEach(p => p.style.display = 'none');
                        panel.style.display = willOpen ? 'block' : 'none';
                        if (willOpen) { search.value = ''; filtrar(''); search.focus(); }
                    });

                    search.addEventListener('input', () => filtrar(search.value));

                    list.addEventListener('mousedown', (e) => {
                        const li = e.target.closest('li[data-id]');
                        if (!li) return;
                        e.preventDefault();
                        hidden.value = li.dataset.id;
                        label.textContent = li.dataset.id !== '' ? li.textContent : '— Selecciona —';
                        label.style.color = li.dataset.id !== '' ? '#111827' : '#9ca3af';
                        toggle.style.borderColor = '#e5e7eb';
                        panel.style.display = 'none';
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    document.addEventListener('click', (e) => {
                        if (!wrap.contains(e.target)) panel.style.display = 'none';
                    });
                }

                function initAll() {
                    document.querySelectorAll('.fk-combo').forEach(initFkCombo);
                }

                document.addEventListener('DOMContentLoaded', initAll);
                initAll();

                new MutationObserver(() => initAll())
                    .observe(document.body, { childList: true, subtree: true });

                // Validacion basica de "obligatorio" en el submit del formulario (los inputs hidden no la soportan de forma nativa)
                document.addEventListener('submit', (e) => {
                    const invalidos = Array.from(e.target.querySelectorAll?.('.fk-combo[data-required="1"]') || [])
                        .filter(wrap => !wrap.querySelector('.fk-combo-value').value);
                    invalidos.forEach(wrap => wrap.querySelector('.fk-combo-toggle').style.borderColor = '#f87171');
                    if (invalidos.length) {
                        e.preventDefault();
                        invalidos[0].querySelector('.fk-combo-toggle').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, true);
            })();
            </script>
        @endonce
        @break

    @case('select')
        @php
            $enables = $campo->getExtraDirective('enables');
            // Un select siempre deberia poder dejarse en blanco (null), no solo elegir uno de los
            // valores de opt: -- salvo que la columna real sea NOT NULL sin default (ahi si hace
            // falta el required nativo, o Postgres devolveria un 500 al guardar en blanco). Ver
            // FichaController::validateRequired(), mismo criterio en el servidor.
            $reqSelect = ($campo->required && !$campo->columnIsNullable($projectTable->getFullTableName())) ? 'required' : '';
        @endphp
        <select id="campo_{{ $campo->name }}" name="{{ $campo->name }}" {{ $reqSelect }} class="{{ $base }}"
                @if($enables) data-enables="{{ $enables }}" onchange="window.__fieldToggleEnables(this)" @endif>
            <option value="">— Selecciona —</option>
            @foreach($campo->getOptions() as $opcion)
                <option value="{{ $opcion }}" {{ $valor === $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
            @endforeach
        </select>
        @if($enables)
            @once
                <script>
                function __fieldToggleEnables(select) {
                    var spec = select.dataset.enables;
                    if (!spec) return;
                    var parts = spec.split(':');
                    var targetField = parts[0], activeValue = parts[1];
                    var target = document.querySelector('[data-field="' + targetField + '"]');
                    if (!target) return;
                    var active = select.value === activeValue;
                    target.classList.toggle('opacity-40', !active);
                    target.classList.toggle('pointer-events-none', !active);
                }
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('[data-enables]').forEach(__fieldToggleEnables);
                });
                </script>
            @endonce
        @endif
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
             data-field="{{ $campo->name }}"
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
             data-field="{{ $campo->name }}"
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
                @php $esImagen = preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $valor); @endphp
                @if($esImagen)
                    <a href="{{ Storage::url($valor) }}" target="_blank">
                        <img src="{{ Storage::url($valor) }}" alt="foto"
                             class="h-32 w-32 object-cover rounded border border-gray-200 hover:opacity-80 transition-opacity">
                    </a>
                @else
                    <a href="{{ Storage::url($valor) }}" target="_blank"
                       class="text-xs text-blue-500 hover:underline block">Ver archivo actual</a>
                @endif
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
@endif
