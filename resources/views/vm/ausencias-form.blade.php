@php
$hayFiltros = array_filter($filtros);
@endphp

<x-app-layout :breadcrumb="$breadcrumb" :project="$project">

<x-slot name="actions">
    @if($canEdit)
    <button type="button" onclick="nuevaAusencia()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo
    </button>
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
            @php $qs = http_build_query($filtros); @endphp
            <a href="{{ route('excel.export', [$project->slug, 'ausencias']) }}?tipo=listado&{{ $qs }}"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-filter text-orange-400 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Exportar listado</p>
                    <p class="text-xs text-gray-400">Columnas visibles y filtros aplicados</p>
                </div>
            </a>
            <a href="{{ route('excel.export', [$project->slug, 'ausencias']) }}?tipo=tabla"
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
            @if($isAdmin)
            <a href="{{ route('excel.import-form', [$project->slug, 'ausencias']) }}"
               class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50">
                <i class="fas fa-file-upload text-blue-500 mt-0.5"></i>
                <div>
                    <p class="font-medium text-gray-700">Importar</p>
                </div>
            </a>
            <a href="{{ route('ficha.bulk-edit-form', [$project->slug, 'ausencias']) }}"
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

<style>
.badge{font-size:11px;padding:2px 8px;border-radius:6px;color:#fff;white-space:nowrap;}
.badge.Vacaciones{background:#e8b800;}
.badge.Baja{background:#7b3f8c;}
.badge.Comp__festivo,.badge.Comp__horas,.badge.Compensaci_n{background:#e83e8c;}
.badge.Asuntos_propios{background:#34c163;}
.badge.Absentismo{background:#dc3545;}
.badge.otro{background:#9ca3af;}
.icon-btn{background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;border-radius:6px;}
.icon-btn:hover{background:rgba(0,0,0,.06);color:#222;}
.icon-btn.danger:hover{background:#FCEBEB;color:#A32D2D;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border:0.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.5rem;width:400px;max-width:94vw;max-height:90vh;overflow-y:auto;}
.dark .modal{background:#1a1a1a;border-color:rgba(255,255,255,.1);}
.modal-title{font-weight:500;font-size:15px;margin:0 0 1rem;}
.form-row{margin-bottom:12px;}
.form-label{font-size:12px;color:#888;margin:0 0 4px;display:block;}
.form-row input,.form-row select{width:100%;box-sizing:border-box;border:0.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 10px;font-size:13px;background:#fff;}
.dark .form-row input,.dark .form-row select{background:#111;color:#eee;border-color:rgba(255,255,255,.15);}
.form-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:0.5px solid rgba(0,0,0,.07);}
.btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff;}
.dark .btn{background:#222;border-color:rgba(255,255,255,.15);color:#eee;}
.btn-primary{background:#E6F1FB;color:#0C447C;border-color:#B5D4F4;}
.btn-danger-link{color:#A32D2D;border-color:#F7C1C1;background:none;margin-right:auto;}
</style>

{{-- Barra de filtros --}}
<div class="flex gap-2 mb-4 flex-wrap items-center">
    <select id="f-empleado" onchange="aplicarFiltros()"
            class="text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
      <option value="">Todos los empleados</option>
      @foreach($usuarios as $u)<option value="{{ $u->id }}" @selected(($filtros['f_id_usuarios'] ?? null) == $u->id)>{{ $u->nombre }}</option>@endforeach
    </select>
    <select id="f-tipo" onchange="aplicarFiltros()"
            class="text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
      <option value="">Todos los tipos</option>
      @foreach($tiposAusencia as $t)<option value="{{ $t }}" @selected(($filtros['f_tipo'] ?? null) === $t)>{{ $t }}</option>@endforeach
    </select>
    <select id="f-anyo" onchange="aplicarFiltros()"
            class="text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
      <option value="">Todos los años</option>
      @foreach(range(now()->year + 1, now()->year - 5) as $y)<option value="{{ $y }}" @selected(($filtros['f_anyo_devengo'] ?? null) == $y)>{{ $y }}</option>@endforeach
    </select>
    @if($hayFiltros)
    <a href="{{ route('vm.ausencias_form', $project->slug) }}" title="Limpiar filtros"
       class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </a>
    @endif
</div>

{{-- Tabla de datos --}}
<div class="bg-white rounded-xl border border-gray-200">
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead>
        <tr class="border-b border-gray-200 bg-gray-50">
          <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Empleado</th>
          <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Tipo</th>
          <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Desde</th>
          <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Hasta</th>
          <th class="text-center px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Año devengo</th>
          <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Comentario</th>
          <th class="w-10"></th>
          <th class="w-10"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($ausencias as $a)
        @php $badgeClass = str_replace([' ', '.', '/'], '_', $a->tipo); @endphp
        <tr class="hover:bg-gray-50 cursor-pointer" onclick="editarAusencia({{ $a->id }})">
          <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $a->empleado }}</td>
          <td class="px-4 py-3"><span class="badge {{ $badgeClass }}">{{ $a->tipo }}</span></td>
          <td class="px-4 py-3 text-center text-gray-700 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($a->fecha_inicio)->format('d/m/Y') }}</td>
          <td class="px-4 py-3 text-center text-gray-700 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($a->fecha_fin)->format('d/m/Y') }}</td>
          <td class="px-4 py-3 text-center text-gray-700">{{ $a->anyo_devengo ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-500">{{ $a->comentario ?: '—' }}</td>
          <td class="px-2 py-3 text-center" onclick="event.stopPropagation()">
            @if($a->file_fichero)
            <a href="{{ Illuminate\Support\Facades\Storage::url($a->file_fichero) }}" target="_blank" class="icon-btn" title="Ver justificante"><i class="ti ti-paperclip"></i></a>
            @endif
          </td>
          <td class="px-2 py-3 text-right" onclick="event.stopPropagation()">
            @if($canEdit)
            <button class="icon-btn danger" onclick="borrarAusencia({{ $a->id }})" title="Eliminar"><i class="ti ti-trash"></i></button>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">No hay ausencias registradas{{ $hayFiltros ? ' con estos filtros' : '' }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación y contador --}}
  @if($ausencias->hasPages() || $ausencias->total() > 0)
  <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-xs text-gray-400">
      <span>{{ $ausencias->total() }} registros</span>
      {{ $ausencias->links('partials.pagination') }}
  </div>
  @endif
</div>

<div class="modal-overlay" id="modal-ausencia">
  <div class="modal">
    <input type="hidden" id="a-id">
    <p class="modal-title" id="a-title">Nueva ausencia</p>
    <div id="a-error" style="display:none;background:#FCEBEB;color:#A32D2D;border:1px solid #F7C1C1;border-radius:6px;padding:8px 12px;font-size:13px;margin-bottom:12px;"></div>
    <div class="form-row"><label class="form-label">Empleado</label>
      <select id="a-empleado">
        @foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->nombre }}</option>@endforeach
      </select>
    </div>
    <div class="form-row"><label class="form-label">Tipo</label>
      <select id="a-tipo" onchange="onTipoAusenciaChange(this.value)">
        @foreach($tiposAusencia as $t)<option>{{ $t }}</option>@endforeach
      </select>
    </div>
    <div id="a-baja-aviso" style="display:none;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:6px;padding:8px 12px;font-size:12px;margin-top:4px;">
      Si aún no dispones del justificante médico, adjúntalo a la mayor brevedad posible.
    </div>
    <div class="form-grid2" style="margin-top:12px;">
      <div class="form-row"><label class="form-label">Fecha inicio</label><input type="date" id="a-inicio"></div>
      <div class="form-row"><label class="form-label">Fecha fin</label><input type="date" id="a-fin"></div>
    </div>
    <div class="form-row" id="a-anyo-row" style="display:none;">
      <label class="form-label">Año de devengo</label>
      <input type="number" id="a-anyo" value="{{ now()->year }}" min="2020" max="{{ now()->year + 1 }}" style="width:100px;">
    </div>
    <div class="form-row"><label class="form-label">Comentario <span style="font-size:10px;">(opcional)</span></label>
      <input type="text" id="a-comentario" placeholder="Ej. IT-2026-1234">
    </div>
    <div class="form-row"><label class="form-label">Justificante <span style="font-size:10px;">(opcional)</span></label>
      <div id="a-fichero-zone" onclick="document.getElementById('a-fichero').click()"
           style="border:2px dashed #d8d6de;border-radius:8px;padding:14px 12px;text-align:center;cursor:pointer;background:#fafafa;transition:border-color .2s;"
           onmouseenter="this.style.borderColor='#7367f0'" onmouseleave="this.style.borderColor='#d8d6de'">
        <div style="font-size:20px;line-height:1;">📎</div>
        <div id="a-fichero-label" style="font-size:12px;color:#b9b9c3;margin-top:4px;">PDF, JPG o PNG · <span style="color:#7367f0;font-weight:600;text-decoration:underline;">selecciona</span></div>
      </div>
      <input type="file" id="a-fichero" accept=".pdf,.jpg,.jpeg,.png" style="display:none;"
             onchange="document.getElementById('a-fichero-label').innerHTML=this.files[0]?'<strong style=\'color:#5F5E5A\'>'+this.files[0].name+'</strong>':'PDF, JPG o PNG · <span style=\'color:#7367f0;font-weight:600;text-decoration:underline\'>selecciona</span>'">
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger-link" id="a-delete-btn" style="display:none;margin-right:auto;" onclick="borrarAusenciaModal()">Eliminar</button>
      <button class="btn" onclick="closeModal('modal-ausencia')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarAusencia()">Guardar</button>
    </div>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/ausencias_form") }}';
const AUSENCIAS = {!! $ausencias->map(fn($a) => [
    'id'          => $a->id,
    'tipo'        => $a->tipo,
    'id_usuarios' => $a->id_usuarios,
    'desde'       => \Illuminate\Support\Carbon::parse($a->fecha_inicio)->format('Y-m-d'),
    'hasta'       => \Illuminate\Support\Carbon::parse($a->fecha_fin)->format('Y-m-d'),
    'anyo_devengo'=> $a->anyo_devengo,
    'comentario'  => $a->comentario,
    'fichero'     => $a->file_fichero ? true : false,
])->values()->toJson() !!};

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function onTipoAusenciaChange(val) {
    const esBaja = val.toLowerCase().includes('baja');
    document.getElementById('a-baja-aviso').style.display = esBaja ? 'block' : 'none';
    document.getElementById('a-anyo-row').style.display = val === 'Vacaciones' ? 'block' : 'none';
}

function resetModalAusencia() {
    document.getElementById('a-error').style.display = 'none';
    document.getElementById('a-inicio').value = '';
    document.getElementById('a-fin').value = '';
    document.getElementById('a-comentario').value = '';
    document.getElementById('a-fichero').value = '';
    document.getElementById('a-fichero-label').innerHTML = 'PDF, JPG o PNG · <span style="color:#7367f0;font-weight:600;text-decoration:underline;">selecciona</span>';
}

function nuevaAusencia() {
    document.getElementById('a-id').value = '';
    resetModalAusencia();
    document.getElementById('a-title').textContent = 'Nueva ausencia';
    document.getElementById('a-delete-btn').style.display = 'none';
    onTipoAusenciaChange(document.getElementById('a-tipo').value);
    openModal('modal-ausencia');
}

function editarAusencia(id) {
    const aus = AUSENCIAS.find(a => a.id == id);
    if (!aus) return;

    document.getElementById('a-id').value = id;
    resetModalAusencia();
    document.getElementById('a-title').textContent = 'Editar ausencia';
    document.getElementById('a-delete-btn').style.display = '';

    document.getElementById('a-empleado').value = aus.id_usuarios;

    const sel = document.getElementById('a-tipo');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === aus.tipo) { sel.selectedIndex = i; break; }
    }
    onTipoAusenciaChange(aus.tipo);

    document.getElementById('a-inicio').value = aus.desde;
    document.getElementById('a-fin').value = aus.hasta;
    document.getElementById('a-anyo').value = aus.anyo_devengo || aus.desde.substring(0,4);
    document.getElementById('a-comentario').value = aus.comentario || '';

    const fLabel = aus.fichero
        ? '<span style="color:#166534;font-weight:600;">📎 Ya tiene justificante adjunto</span><br><span style="font-size:11px;color:#b9b9c3;">Selecciona otro para reemplazarlo</span>'
        : 'PDF, JPG o PNG · <span style="color:#7367f0;font-weight:600;text-decoration:underline;">selecciona</span>';
    document.getElementById('a-fichero-label').innerHTML = fLabel;

    openModal('modal-ausencia');
}

function guardarAusencia() {
    const errorEl = document.getElementById('a-error');
    errorEl.style.display = 'none';

    const empleado = document.getElementById('a-empleado').value;
    const inicio   = document.getElementById('a-inicio').value;
    const fin      = document.getElementById('a-fin').value;
    const ausId    = document.getElementById('a-id').value;

    if (!empleado) {
        errorEl.textContent = 'El empleado es obligatorio.';
        errorEl.style.display = 'block';
        return;
    }
    if (!inicio || !fin) {
        errorEl.textContent = 'Las fechas de inicio y fin son obligatorias.';
        errorEl.style.display = 'block';
        return;
    }
    if (inicio > fin) {
        errorEl.textContent = 'La fecha de inicio debe ser anterior o igual a la fecha de fin.';
        errorEl.style.display = 'block';
        return;
    }

    const fichero = document.getElementById('a-fichero').files[0];
    const form    = new FormData();
    form.append('id_usuarios',  empleado);
    form.append('tipo',         document.getElementById('a-tipo').value);
    form.append('fecha_inicio', inicio);
    form.append('fecha_fin',    fin);
    form.append('anyo_devengo', document.getElementById('a-anyo').value);
    form.append('comentario',   document.getElementById('a-comentario').value);
    if (fichero) form.append('fichero', fichero);
    if (ausId) form.append('_method', 'PATCH');

    fetch(ausId ? BASE + '/' + ausId : BASE, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: form,
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            errorEl.textContent = data.error;
            errorEl.style.display = 'block';
        } else {
            if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
            location.reload();
        }
    });
}

function borrarAusencia(id) {
    if (!confirm('¿Eliminar esta ausencia?')) return;
    fetch(BASE + '/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); } else {
            if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
            location.reload();
        }
    });
}

function borrarAusenciaModal() {
    const id = document.getElementById('a-id').value;
    if (id) borrarAusencia(id);
}

async function copiarIds() {
    const qs = new URLSearchParams(window.location.search);
    const url = '{{ route('listado.ids', [$project->slug, 'ausencias']) }}?' + qs.toString();

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

function aplicarFiltros() {
    const params = new URLSearchParams();
    const emp  = document.getElementById('f-empleado').value;
    const tipo = document.getElementById('f-tipo').value;
    const anyo = document.getElementById('f-anyo').value;
    if (emp)  params.set('f_id_usuarios', emp);
    if (tipo) params.set('f_tipo', tipo);
    if (anyo) params.set('f_anyo_devengo', anyo);
    window.location.href = '{{ route('vm.ausencias_form', $project->slug) }}' + (params.toString() ? '?' + params.toString() : '');
}
</script>

</x-app-layout>
