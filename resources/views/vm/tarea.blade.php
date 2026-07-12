@php
function minToHm(int $min): string {
    if ($min <= 0) return '0h 00m';
    return intdiv($min, 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'm';
}
$colores = [
    'limpieza'      => ['bg'=>'#E6F1FB','bd'=>'#378ADD','tx'=>'#0C447C','bar'=>'#85B7EB'],
    'mantenimiento' => ['bg'=>'#FAEEDA','bd'=>'#EF9F27','tx'=>'#633806','bar'=>'#FAC775'],
    'piscina'       => ['bg'=>'#E1F5EE','bd'=>'#1D9E75','tx'=>'#085041','bar'=>'#5DCAA5'],
];
$c = $colores[$tipo];
$tipoLabel = ['limpieza'=>'Limpieza','mantenimiento'=>'Mantenimiento','piscina'=>'Piscinas'][$tipo];
$tipoIcon  = ['limpieza'=>'ti-sparkles','mantenimiento'=>'ti-tool','piscina'=>'ti-droplet'][$tipo];
$tipoCurrent = $tarea->Tipo ?? null;
$listadoTable = $tablaLabel[$tipo];
$urlUpdate    = route('vm.tarea.update',          [$project->slug, $tipo, $tarea->id]);
$urlAsignados = route('vm.tarea.asignados',        [$project->slug, $tipo, $tarea->id]);
$urlImpStore  = route('vm.tarea.imputacion.store', [$project->slug, $tipo, $tarea->id]);
$urlImpDel    = url("/vm/tareas_{$tipo}_form/{$tarea->id}/imputaciones");
$csrf         = csrf_token();
@endphp
<x-app-layout
    :breadcrumb="[
        ['label' => $tipoLabel, 'url' => route('vm.tarea.list', [$project->slug, $tipo])],
        ['label' => $tarea->nombre ?? 'Tarea #' . $tarea->id, 'url' => ''],
    ]"
    :project="$project">

<x-slot name="actions">
  <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
    <a href="{{ route('ficha', [$project->slug, $listadoTable, $tarea->id]) }}"
      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors"
      title="Ver ficha estándar">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </a>
    @if ($canEdit)
    <button onclick="enterEdit()"
      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
      <i class="ti ti-pencil text-sm"></i>Editar
    </button>
    @endif
  </div>
  @if ($canEdit)
  <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
    <button onclick="toggleBorrar()"
      class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg transition-colors {{ $tarea->deleted ? 'text-red-600 border-red-300 bg-red-50 hover:bg-red-100' : 'text-red-500 border-red-200 hover:bg-red-50' }}">
      <i class="ti ti-trash text-sm"></i>{{ $tarea->deleted ? 'Restaurar' : 'Borrar' }}
    </button>
    <button onclick="toggleOcultar()"
      class="flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg transition-colors {{ $tarea->hidden ? 'text-amber-600 border-amber-300 bg-amber-50 hover:bg-amber-100' : 'text-amber-500 border-amber-200 hover:bg-amber-50' }}">
      <i class="ti ti-eye-off text-sm"></i>{{ $tarea->hidden ? 'Mostrar' : 'Ocultar' }}
    </button>
    <button onclick="cancelEdit()"
      class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
      Cancelar
    </button>
    <button id="btn-save" onclick="guardar()"
      class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-orange-500 border border-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
      Guardar
    </button>
  </div>
  @endif
</x-slot>

<style>
.section-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .section-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.sec-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0;display:flex;align-items:center;gap:6px}
.field-grid{display:grid;gap:0}
.field-row{display:grid;grid-template-columns:140px 1fr;align-items:start;padding:8px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .field-row{border-bottom-color:rgba(255,255,255,.05)}
.field-row:last-child{border-bottom:none;padding-bottom:0}
.field-row:first-child{padding-top:0}
.field-lbl{font-size:12px;color:#999;padding-top:1px}
.field-val{font-size:13px;color:#111}
.dark .field-val{color:#eee}
.field-val.empty{color:#bbb;font-style:italic}
.f-input{width:100%;box-sizing:border-box;border:0.5px solid rgba(0,0,0,.18);border-radius:6px;padding:6px 9px;font-size:13px;background:#fff;color:#111}
.dark .f-input{background:#111;color:#eee;border-color:rgba(255,255,255,.18)}
.fbadge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:500}
/* asgn */
.asgn-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .asgn-row{border-bottom-color:rgba(255,255,255,.05)}
.asgn-row:last-child{border-bottom:none;padding-bottom:0}
.asgn-row:first-child{padding-top:0}
.t-avatar{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:500;flex-shrink:0}
.asgn-info{flex:1;min-width:0}
.asgn-name{font-size:13px;font-weight:500;color:#111}
.dark .asgn-name{color:#eee}
.bar-wrap{height:4px;border-radius:2px;margin-top:4px;overflow:hidden;background:rgba(0,0,0,.06)}
.bar-fill{height:100%;border-radius:2px}
.asgn-time{font-size:12px;font-weight:500;flex-shrink:0;min-width:44px;text-align:right}
/* imp */
.imp-row{display:grid;grid-template-columns:28px 1fr auto auto;align-items:start;gap:10px;padding:9px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .imp-row{border-bottom-color:rgba(255,255,255,.05)}
.imp-row:last-child{border-bottom:none;padding-bottom:0}
.imp-row:first-child{padding-top:0}
.imp-actions{display:flex;align-items:center;gap:4px;flex-shrink:0}
.imp-avatar{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:500}
.imp-uname{font-size:13px;font-weight:500;color:#111}
.dark .imp-uname{color:#eee}
.imp-obs{font-size:12px;color:#888;margin-top:2px;line-height:1.4}
.imp-obs.empty{color:#bbb;font-style:italic}
.imp-date{font-size:11px;color:#aaa;margin-top:2px}
.imp-dur{font-size:13px;font-weight:600;white-space:nowrap}
.btn-x{background:none;border:0.5px solid rgba(0,0,0,.12);cursor:pointer;padding:3px 7px;border-radius:5px;color:#888;line-height:1;font-size:13px;transition:color .12s,background .12s}
.btn-x:hover{color:#ef4444;border-color:#fca5a5;background:#fee2e2}
.btn-pencil{background:none;border:0.5px solid rgba(0,0,0,.12);cursor:pointer;padding:3px 7px;border-radius:5px;color:#888;line-height:1;font-size:12px;transition:color .12s,background .12s}
.btn-pencil:hover{color:#3b82f6;border-color:#93c5fd;background:#eff6ff}
/* sec header with action */
.sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.btn-sec{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;border:0.5px solid rgba(0,0,0,.15);background:#fff;font-size:12px;color:#555;cursor:pointer;transition:background .12s}
.dark .btn-sec{background:#222;border-color:rgba(255,255,255,.15);color:#ccc}
.btn-sec:hover{background:#f5f5f5}
.dark .btn-sec:hover{background:#2a2a2a}
/* modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border:0.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.5rem;width:400px;max-width:94vw;max-height:90vh;overflow-y:auto}
.dark .modal{background:#1a1a1a;border-color:rgba(255,255,255,.1)}
.modal-title{font-weight:500;font-size:15px;margin:0 0 1rem}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:0.5px solid rgba(0,0,0,.07)}
.dark .modal-footer{border-top-color:rgba(255,255,255,.07)}
.modal .m-lbl{font-size:11px;color:#999;display:block;margin-bottom:4px;margin-top:8px}
.modal .m-lbl:first-child{margin-top:0}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff;color:#444}
.dark .btn{background:#222;border-color:rgba(255,255,255,.15);color:#eee}
.btn:hover{background:#f5f5f5}
.dark .btn:hover{background:#2a2a2a}
.btn-primary{background:#F97316;border-color:#F97316;color:#fff;font-weight:500}
.btn-primary:hover{background:#ea6c0a;border-color:#ea6c0a}
/* user checkbox list */
.user-check-list{display:flex;flex-direction:column;gap:2px;max-height:300px;overflow-y:auto;border:0.5px solid rgba(0,0,0,.1);border-radius:8px;padding:4px}
.dark .user-check-list{border-color:rgba(255,255,255,.1)}
.user-check-item{display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:6px;transition:background .1s}
.user-check-item:not(.disabled){cursor:pointer}
.user-check-item:not(.disabled):hover{background:rgba(0,0,0,.04)}
.dark .user-check-item:not(.disabled):hover{background:rgba(255,255,255,.04)}
.user-check-item.disabled{opacity:.55;cursor:not-allowed}
.user-check-item input[type=checkbox]{width:15px;height:15px;accent-color:#F97316}
.user-check-item input[type=checkbox]:not(:disabled){cursor:pointer}
.user-check-item span{font-size:13px;color:#111}
.dark .user-check-item span{color:#eee}
.user-imp-badge{font-size:10px;color:#888;margin-left:auto;white-space:nowrap}
/* warn modal */
.warn-modal{width:360px}
.warn-icon{font-size:28px;color:#f59e0b;margin-bottom:10px}
.warn-msg{font-size:14px;color:#333;line-height:1.5}
.dark .warn-msg{color:#ddd}
</style>

<div style="max-width:860px;margin:0 auto;padding:1rem 0 3rem">

{{-- ── CABECERA TAREA ── --}}
<div class="section-card" style="margin-bottom:12px">
  <div style="display:flex;align-items:flex-start;gap:12px">
    <div style="flex:1;min-width:0">
      <div id="v-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
        <div style="flex:1;min-width:0">
          <div style="font-size:16px;font-weight:500;color:var(--text-primary,#111)">{{ $tarea->nombre ?? 'Sin nombre' }}</div>
          @if ($propiedad)
            <div style="font-size:12px;color:#888;margin-top:4px"><i class="ti ti-building" style="font-size:11px;margin-right:3px"></i>{{ $propiedad->nombre }}</div>
          @endif
        </div>
        <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;justify-content:flex-end;flex-shrink:0">
          <span class="fbadge" style="background:{{ $c['bg'] }};border:0.5px solid {{ $c['bd'] }};color:{{ $c['tx'] }}">
            <i class="ti {{ $tipoIcon }}" style="font-size:10px"></i>{{ $tipoCurrent ?? $tipoLabel }}
          </span>
          @if ($badgeImp === 'sin_imputar')
            <span class="fbadge" style="background:#f5f5f5;border:0.5px solid #ddd;color:#999"><i class="ti ti-clock-pause" style="font-size:10px"></i>Sin imputar</span>
          @elseif ($badgeImp === 'parcial')
            <span class="fbadge" style="background:#FAEEDA;border:0.5px solid #EF9F27;color:#633806"><i class="ti ti-clock-half-2" style="font-size:10px"></i>Parcial</span>
          @else
            <span class="fbadge" style="background:#EAF3DE;border:0.5px solid #7BBF50;color:#27500A"><i class="ti ti-circle-check" style="font-size:10px"></i>Imputado</span>
          @endif
          @if ($fotos->count())
            <span class="fbadge" style="background:#F3E8FF;border:0.5px solid #A855F7;color:#6B21A8"><i class="ti ti-camera" style="font-size:10px"></i>{{ $fotos->count() }} foto{{ $fotos->count() !== 1 ? 's' : '' }}</span>
          @endif
          @if ($tarea->deleted)
            <span class="fbadge" style="background:#fee2e2;border:0.5px solid #fca5a5;color:#991b1b"><i class="ti ti-trash" style="font-size:10px"></i>Borrado</span>
          @endif
          @if ($tarea->hidden)
            <span class="fbadge" style="background:#fef9c3;border:0.5px solid #fde047;color:#854d0e"><i class="ti ti-eye-off" style="font-size:10px"></i>Oculto</span>
          @endif
        </div>
      </div>
      <div id="e-header" style="display:none">
        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end">
          <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Nombre</div>
            <input type="text" id="e-nombre" class="f-input" value="{{ $tarea->nombre }}">
          </div>
          <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Tipo</div>
            <select id="e-tipo" class="f-input" style="width:170px">
              <option value="">— Sin tipo —</option>
              @foreach ($tipoOptions as $opt)
                <option value="{{ $opt }}" {{ $tipoCurrent === $opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ── DATOS GENERALES ── --}}
<div class="section-card">
  <div class="sec-header">
    <div class="sec-title"><i class="ti ti-info-circle"></i>Datos generales</div>
  </div>
  <div id="v-datos" class="field-grid">
    <div class="field-row">
      <div class="field-lbl">Fecha planificada</div>
      <div class="field-val {{ !$tarea->fecha_planificada ? 'empty' : '' }}">
        {{ $tarea->fecha_planificada ? \Carbon\Carbon::parse($tarea->fecha_planificada)->translatedFormat('j \d\e F Y') : 'Sin fecha' }}
      </div>
    </div>
    <div class="field-row">
      <div class="field-lbl">Descripción</div>
      <div class="field-val {{ !$tarea->descripcion ? 'empty' : '' }}" style="white-space:pre-line">{{ $tarea->descripcion ?: 'Sin descripción.' }}</div>
    </div>
    @if ($tipo === 'limpieza' && $propiedad?->tiempo_limpieza)
      @php $prevMin = (int)$propiedad->tiempo_limpieza * 60; $isOver = $totalImputado > $prevMin; @endphp
      <div class="field-row">
        <div class="field-lbl">Tiempo previsto</div>
        <div class="field-val">{{ minToHm($prevMin) }}</div>
      </div>
      <div class="field-row">
        <div class="field-lbl">Tiempo imputado</div>
        <div class="field-val" style="{{ $isOver ? 'color:#A32D2D' : '' }}">
          {{ $totalImputado ? minToHm($totalImputado) : '—' }}
          @if ($isOver)<span style="font-size:11px;color:#A32D2D"> · Excede lo previsto</span>@endif
        </div>
      </div>
    @endif
  </div>
  <div id="e-datos" style="display:none">
    <div style="margin-bottom:10px">
      <div style="font-size:11px;color:#999;margin-bottom:4px">Fecha planificada</div>
      <input type="date" id="e-fecha" class="f-input" style="max-width:200px" value="{{ $tarea->fecha_planificada }}">
    </div>
    <div>
      <div style="font-size:11px;color:#999;margin-bottom:4px">Descripción</div>
      <textarea id="e-descripcion" class="f-input" rows="4" style="resize:vertical">{{ $tarea->descripcion }}</textarea>
    </div>
  </div>
</div>

{{-- ── PERSONAS ASIGNADAS ── --}}
<div class="section-card">
  <div class="sec-header">
    <div class="sec-title"><i class="ti ti-users"></i>Personas asignadas</div>
    @if ($canEdit)
    <button class="btn-sec" onclick="openModal('modal-personas')">
      <i class="ti ti-users-plus" style="font-size:12px"></i>Editar
    </button>
    @endif
  </div>
  <div id="asgn-list">
    @forelse ($usuarios as $u)
      @php
        $uMin = $tiempoPorUsuario[(int)$u->id] ?? 0;
        $pct  = $maxPorUsuario > 0 ? round($uMin / $maxPorUsuario * 100) : 0;
        $inis = collect(explode(' ', $u->nombre))->map(fn($p) => strtoupper($p[0] ?? ''))->take(2)->join('');
      @endphp
      <div class="asgn-row">
        <div class="t-avatar" style="background:{{ $uMin ? $c['bg'] : '#f3f4f6' }};color:{{ $uMin ? $c['tx'] : '#9ca3af' }}">{{ $inis }}</div>
        <div class="asgn-info">
          <div class="asgn-name">{{ $u->nombre }}</div>
          <div class="bar-wrap"><div class="bar-fill" style="width:{{ $pct }}%;background:{{ $c['bar'] }}"></div></div>
        </div>
        @if ($uMin)
          <span class="asgn-time" style="color:{{ $c['tx'] }}">{{ minToHm($uMin) }}</span>
        @else
          <span style="font-size:11px;color:#bbb">Sin imputar</span>
        @endif
      </div>
    @empty
      <div style="font-size:13px;color:#bbb;font-style:italic">Sin personas asignadas.</div>
    @endforelse
  </div>
</div>

{{-- ── IMPUTACIONES ── --}}
<div class="section-card">
  <div class="sec-header">
    <div class="sec-title">
      <i class="ti ti-clock-record"></i>Imputaciones
    </div>
    @if ($canEdit)
    <button class="btn-sec" onclick="openModal('modal-imp')">
      <i class="ti ti-plus" style="font-size:12px"></i>Añadir
    </button>
    @endif
  </div>
  <div id="imp-list">
    @forelse ($imputaciones as $imp)
      @php $inis = collect(explode(' ', $imp->usuario_nombre))->map(fn($p) => strtoupper($p[0] ?? ''))->take(2)->join(''); @endphp
      <div class="imp-row" id="imp-{{ $imp->id }}" data-dur="{{ $imp->duracion }}">
        <div class="imp-avatar" style="background:{{ $c['bg'] }};color:{{ $c['tx'] }};grid-row:span 2">{{ $inis }}</div>
        <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;min-width:0">
          <span class="imp-uname">{{ $imp->usuario_nombre }}</span>
          <span class="imp-date">{{ \Carbon\Carbon::parse($imp->fecha_imputacion)->translatedFormat('j M Y') }}</span>
        </div>
        <span class="imp-dur" style="color:{{ $c['tx'] }}">{{ minToHm($imp->duracion) }}</span>
        @if ($canEdit)
        <div class="imp-actions">
          <button class="btn-pencil" title="Editar" onclick="abrirEditImp({{ $imp->id }}, '{{ $imp->fecha_imputacion }}', {{ $imp->duracion }}, {{ json_encode($imp->observacion) }})">✎</button>
          <button class="btn-x" title="Eliminar" onclick="pedirConfirmEliminarImp({{ $imp->id }}, this)">✕</button>
        </div>
        @else
        <span></span>
        @endif
        <span class="imp-obs {{ $imp->observacion ? '' : 'empty' }}" style="grid-column:2/-1">{{ $imp->observacion ?? 'Sin observación.' }}</span>
      </div>
    @empty
      <div id="imp-empty" style="font-size:13px;color:#bbb;font-style:italic">Sin imputaciones todavía.</div>
    @endforelse
    <div id="imp-total-row" style="display:{{ $totalImputado ? 'grid' : 'none' }};grid-template-columns:28px 1fr auto auto;gap:10px;padding:10px 0 0;margin-top:6px;border-top:0.5px solid rgba(0,0,0,.08)">
      <span></span>
      <span style="font-size:12px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.04em">Total</span>
      <span id="imp-total-badge" style="grid-column:3/span 2;font-size:14px;font-weight:600;color:{{ $c['tx'] }}">{{ $totalImputado ? minToHm($totalImputado) : '' }}</span>
    </div>
  </div>
</div>

@if ($fotos->count() || $canEdit)
{{-- ── GALERÍA DE FOTOS ── --}}
<style>
.foto-dropzone{border:2px dashed rgba(0,0,0,.14);border-radius:8px;padding:22px 16px;text-align:center;
  cursor:pointer;transition:all .2s;background:#fafafa;user-select:none;margin-bottom:12px}
.foto-dropzone:hover,.foto-dropzone.drag-over{border-color:{{ $c['bd'] }};background:{{ $c['bg'] }}}
.foto-dropzone.paste-flash{border-color:#28c76f;background:#f0fff4;animation:foto-flash .5s ease}
@keyframes foto-flash{0%{transform:scale(1)}40%{transform:scale(1.01)}100%{transform:scale(1)}}
.foto-prog-wrap{margin-bottom:10px;display:none}
.foto-prog-bar{height:4px;background:#f0f0f0;border-radius:2px;overflow:hidden}
.foto-prog-fill{height:100%;background:{{ $c['bd'] }};border-radius:2px;width:0%;transition:width .15s}
.foto-prog-lbl{font-size:11px;color:#888;margin-top:3px}
.foto-card{position:relative;border-radius:8px;overflow:hidden;background:#f3f4f6;
  border:0.5px solid rgba(0,0,0,.08);cursor:pointer}
.foto-card img{width:100%;aspect-ratio:1;object-fit:cover;display:block;cursor:pointer}
.foto-card-actions{position:absolute;top:4px;right:4px;display:flex;gap:3px;z-index:2}
.foto-btn{background:rgba(0,0,0,.55);border:none;color:#fff;font-size:11px;line-height:1;
  width:22px;height:22px;border-radius:50%;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:background .12s}
.foto-btn:hover{background:rgba(0,0,0,.8)}
.foto-name{font-size:10px;color:#777;text-align:center;padding:3px 4px 4px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}
</style>
<div class="section-card">
  <div class="sec-header">
    <div class="sec-title"><i class="ti ti-camera"></i>Fotos</div>
  </div>

  @if ($canEdit)
  {{-- Drop zone --}}
  <div class="foto-dropzone" id="foto-dropzone"
       onclick="document.getElementById('foto-file-input').click()">
    <div style="font-size:22px;margin-bottom:4px">📷</div>
    <div style="font-size:13px;color:#666">
      Arrastra fotos aquí o <span style="color:{{ $c['tx'] }};font-weight:600;text-decoration:underline">selecciona</span>
    </div>
    <div style="font-size:11px;color:#bbb;margin-top:4px">
      o pega una captura con <kbd style="background:#f0f0f0;border:1px solid #ddd;border-radius:3px;padding:1px 5px;font-size:10px">Ctrl</kbd>+<kbd style="background:#f0f0f0;border:1px solid #ddd;border-radius:3px;padding:1px 5px;font-size:10px">V</kbd>
    </div>
  </div>
  <input id="foto-file-input" type="file" accept="image/*" multiple style="display:none">

  {{-- Barra de progreso --}}
  <div class="foto-prog-wrap" id="foto-prog-wrap">
    <div class="foto-prog-bar"><div class="foto-prog-fill" id="foto-prog-fill"></div></div>
    <div class="foto-prog-lbl" id="foto-prog-lbl">Subiendo…</div>
  </div>
  @endif

  {{-- Grid de fotos --}}
  <div id="foto-grid" style="display:{{ $fotos->count() ? 'grid' : 'none' }};grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px">
    @foreach ($fotos as $foto)
      @php $url = asset('storage/' . $foto->file_foto); @endphp
      <div id="foto-{{ $foto->id }}" class="foto-card">
        <img src="{{ $url }}" alt="Foto" loading="lazy" data-foto-url="{{ $url }}" style="width:100%;aspect-ratio:1;object-fit:cover;display:block;pointer-events:none">
        @if ($canEdit)
        <div class="foto-card-actions">
          <button class="foto-btn" title="Renombrar"
                  data-foto-action="rename" data-foto-id="{{ $foto->id }}" data-foto-nombre="{{ $foto->nombre ?? '' }}">✎</button>
          <button class="foto-btn" title="Eliminar"
                  data-foto-action="delete" data-foto-id="{{ $foto->id }}">&times;</button>
        </div>
        @endif
        @if ($foto->nombre)
        <div class="foto-name" id="foto-name-{{ $foto->id }}" title="{{ $foto->nombre }}">{{ $foto->nombre }}</div>
        @else
        <div class="foto-name" id="foto-name-{{ $foto->id }}" style="display:none"></div>
        @endif
      </div>
    @endforeach
  </div>
  @if (!$fotos->count())
  <div id="foto-empty" style="font-size:13px;color:#bbb;font-style:italic{{ $canEdit ? ';display:none' : '' }}">Sin fotos.</div>
  @else
  <div id="foto-empty" style="font-size:13px;color:#bbb;font-style:italic;display:none">Sin fotos.</div>
  @endif
</div>
@endif

{{-- ── LIGHTBOX ── --}}
<div id="foto-lightbox" onclick="cerrarFoto()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:500;align-items:center;justify-content:center;cursor:zoom-out">
  <img id="foto-lightbox-img" src="" alt=""
       style="max-width:94vw;max-height:92vh;border-radius:8px;object-fit:contain;box-shadow:0 8px 40px rgba(0,0,0,.6)">
  <button onclick="cerrarFoto()" style="position:absolute;top:16px;right:20px;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:22px;line-height:1;padding:6px 10px;border-radius:8px;cursor:pointer">&times;</button>
</div>

{{-- ── MODAL CONFIRMAR ELIMINAR FOTO ── --}}
<div class="modal-overlay" id="modal-confirm-del-foto">
  <div class="modal" style="width:320px">
    <div class="modal-title">Eliminar foto</div>
    <div style="font-size:13px;color:#555;margin-top:4px">¿Eliminar esta foto? Esta acción no se puede deshacer.</div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-confirm-del-foto')">Cancelar</button>
      <button class="btn" style="background:#ef4444;color:#fff" onclick="confirmarEliminarFoto()">Eliminar</button>
    </div>
  </div>
</div>

{{-- ── MODAL RENOMBRAR FOTO ── --}}
<div class="modal-overlay" id="modal-foto-rename">
  <div class="modal" style="width:340px">
    <div class="modal-title">Renombrar foto</div>
    <div style="margin-top:10px">
      <label class="m-lbl">Nombre</label>
      <input type="text" id="foto-rename-input" class="f-input" placeholder="Nombre de la foto…"
             onkeydown="if(event.key==='Enter') fotoRenombrarGuardar()">
      <div id="foto-rename-err" style="color:#ef4444;font-size:11px;margin-top:3px;display:none">El nombre no puede estar vacío</div>
    </div>
    <div class="modal-footer" style="margin-top:14px">
      <button class="btn" onclick="closeModal('modal-foto-rename')">Cancelar</button>
      <button class="btn btn-primary" id="foto-rename-btn" onclick="fotoRenombrarGuardar()">Guardar</button>
    </div>
  </div>
</div>

<script>
function abrirFoto(url) {
  document.getElementById('foto-lightbox-img').src = url;
  document.getElementById('foto-lightbox').style.display = 'flex';
  document.addEventListener('keydown', _lbKey);
}
function cerrarFoto() {
  document.getElementById('foto-lightbox').style.display = 'none';
  document.removeEventListener('keydown', _lbKey);
}
function _lbKey(e) { if (e.key === 'Escape') cerrarFoto(); }

/* ── Drag & drop ── */
(function () {
  var zone = document.getElementById('foto-dropzone');
  if (!zone) return;
  zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', function ()  { zone.classList.remove('drag-over'); });
  zone.addEventListener('drop',      function (e) {
    e.preventDefault(); zone.classList.remove('drag-over');
    subirFotos(e.dataTransfer.files);
  });
  document.getElementById('foto-file-input').addEventListener('change', function () {
    if (this.files.length) subirFotos(this.files);
    this.value = '';
  });

  /* Ctrl+V paste */
  document.addEventListener('paste', function (e) {
    var tag = (document.activeElement || {}).tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || (document.activeElement || {}).isContentEditable) return;
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    var imgs = [];
    for (var i = 0; i < items.length; i++) {
      if (items[i].kind === 'file' && items[i].type.startsWith('image/')) {
        var blob = items[i].getAsFile();
        if (!blob) continue;
        var ext = (items[i].type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        var ts  = new Date().toISOString().replace(/[:.]/g,'-').slice(0,19);
        imgs.push(new File([blob], 'captura-' + ts + '.' + ext, { type: items[i].type }));
      }
    }
    if (imgs.length) {
      e.preventDefault();
      zone.classList.add('paste-flash');
      setTimeout(function () { zone.classList.remove('paste-flash'); }, 600);
      subirFotos(imgs);
    }
  });
})();

/* ── Upload queue (secuencial con XHR para progreso) ── */
function subirFotos(files) {
  var arr = Array.from(files), idx = 0;
  function next() {
    if (idx >= arr.length) {
      document.getElementById('foto-prog-wrap').style.display = 'none';
      return;
    }
    subirUna(arr[idx], function () { idx++; next(); });
  }
  next();
}

function subirUna(file, done) {
  var wrap = document.getElementById('foto-prog-wrap');
  var fill = document.getElementById('foto-prog-fill');
  var lbl  = document.getElementById('foto-prog-lbl');
  wrap.style.display = '';
  fill.style.width   = '0%';
  lbl.style.color    = '#888';
  lbl.textContent    = 'Subiendo ' + file.name + '…';

  var previewUrl = URL.createObjectURL(file);

  var fd = new FormData();
  fd.append('foto',    file);
  fd.append('_token',  CSRF);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', URL_FOTOS, true);
  xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
  xhr.setRequestHeader('Accept', 'application/json');

  xhr.upload.onprogress = function (e) {
    if (e.lengthComputable) {
      var pct = Math.round(e.loaded / e.total * 100);
      fill.style.width = pct + '%';
      lbl.textContent  = 'Subiendo ' + file.name + '… ' + pct + '%';
    }
  };

  xhr.onload = function () {
    if (xhr.status === 200) {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.ok) {
          lbl.textContent = '✓ ' + file.name;
          appendFoto(data, previewUrl);
          setTimeout(function () { URL.revokeObjectURL(previewUrl); }, 60000);
          done(); return;
        }
      } catch (ex) {}
    }
    lbl.style.color = '#ef4444';
    lbl.textContent = '✗ Error al subir ' + file.name;
    URL.revokeObjectURL(previewUrl);
    done();
  };
  xhr.onerror = function () {
    lbl.style.color = '#ef4444';
    lbl.textContent = '✗ Error de red';
    URL.revokeObjectURL(previewUrl);
    done();
  };
  xhr.send(fd);
}

function appendFoto(data, previewUrl) {
  var grid  = document.getElementById('foto-grid');
  var empty = document.getElementById('foto-empty');
  grid.style.display  = 'grid';
  empty.style.display = 'none';

  var div = document.createElement('div');
  div.id        = 'foto-' + data.id;
  div.className = 'foto-card';
  div.innerHTML =
    '<img src="' + (previewUrl || data.url) + '" alt="Foto" loading="lazy" data-foto-url="' + data.url + '" style="width:100%;aspect-ratio:1;object-fit:cover;display:block;pointer-events:none">'
    + '<div class="foto-card-actions">'
    + '<button class="foto-btn" title="Renombrar" data-foto-action="rename" data-foto-id="' + data.id + '" data-foto-nombre="">✎</button>'
    + '<button class="foto-btn" title="Eliminar" data-foto-action="delete" data-foto-id="' + data.id + '">&times;</button>'
    + '</div>'
    + '<div class="foto-name" id="foto-name-' + data.id + '" style="display:none"></div>';
  grid.appendChild(div);
}

var _fotoDeleteId = null;
function pedirConfirmEliminarFoto(fotoId) {
  _fotoDeleteId = fotoId;
  openModal('modal-confirm-del-foto');
}
async function confirmarEliminarFoto() {
  if (!_fotoDeleteId) return;
  var fotoId = _fotoDeleteId;
  _fotoDeleteId = null;
  closeModal('modal-confirm-del-foto');
  try {
    var r = await fetch(URL_FOTOS + '/' + fotoId, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    });
    var d = await r.json();
    if (d.ok) {
      document.getElementById('foto-' + fotoId)?.remove();
      var grid = document.getElementById('foto-grid');
      if (!grid || !grid.children.length) {
        if (grid) grid.style.display = 'none';
        var empty = document.getElementById('foto-empty');
        if (empty) empty.style.display = '';
      }
    }
  } catch(e) { alert('Error al eliminar foto.'); }
}

/* ── Event delegation para foto-cards ── */
document.addEventListener('click', function(e) {
  var actionBtn = e.target.closest('[data-foto-action]');
  if (actionBtn) {
    e.stopPropagation();
    var action = actionBtn.getAttribute('data-foto-action');
    var id     = actionBtn.getAttribute('data-foto-id');
    if (action === 'delete') { pedirConfirmEliminarFoto(id); return; }
    if (action === 'rename') { fotoRenombrarAbrir(id, actionBtn.getAttribute('data-foto-nombre') || ''); return; }
    return;
  }
  var card = e.target.closest('.foto-card');
  if (card) {
    var img = card.querySelector('img[data-foto-url]');
    if (img) window.open(img.getAttribute('data-foto-url'), '_blank');
  }
});

/* ── Renombrar foto ── */
var _fotoRenombrarId = null;
function fotoRenombrarAbrir(fotoId, nombreActual) {
  _fotoRenombrarId = fotoId;
  var inp = document.getElementById('foto-rename-input');
  var err = document.getElementById('foto-rename-err');
  var btn = document.getElementById('foto-rename-btn');
  inp.value = nombreActual || '';
  err.style.display = 'none';
  btn.disabled = false;
  btn.textContent = 'Guardar';
  openModal('modal-foto-rename');
  setTimeout(function () { inp.focus(); inp.select(); }, 100);
}
async function fotoRenombrarGuardar() {
  var nombre = document.getElementById('foto-rename-input').value.trim();
  var err    = document.getElementById('foto-rename-err');
  var btn    = document.getElementById('foto-rename-btn');
  if (!nombre) { err.style.display = ''; return; }
  err.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    var r = await fetch(URL_FOTOS + '/' + _fotoRenombrarId, {
      method: 'PATCH',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ nombre: nombre })
    });
    var data = await r.json();
    if (data.ok) {
      var nameEl = document.getElementById('foto-name-' + _fotoRenombrarId);
      if (nameEl) { nameEl.textContent = nombre; nameEl.title = nombre; nameEl.style.display = ''; }
      closeModal('modal-foto-rename');
    }
  } catch(e) { alert('Error al renombrar.'); }
  btn.disabled = false; btn.textContent = 'Guardar';
}
</script>

</div>{{-- /max-width --}}

@if ($canEdit)
{{-- ── MODAL EDITAR IMPUTACIÓN ── --}}
<div class="modal-overlay" id="modal-edit-imp">
  <div class="modal">
    <div class="modal-title">Editar imputación</div>
    <input type="hidden" id="edit-imp-id">
    <input type="hidden" id="edit-imp-old-dur">
    <div class="form-row">
      <div>
        <label class="m-lbl">Fecha</label>
        <input type="date" id="edit-imp-fecha" class="f-input">
      </div>
      <div>
        <label class="m-lbl">Duración (hh:mm)</label>
        <input type="time" id="edit-imp-dur" class="f-input">
      </div>
    </div>
    <label class="m-lbl" style="margin-top:8px">Observación</label>
    <textarea id="edit-imp-obs" class="f-input" rows="2" placeholder="Observación..."></textarea>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-edit-imp')">Cancelar</button>
      <button class="btn btn-primary" id="btn-edit-imp-save" onclick="guardarEditImp()">Guardar</button>
    </div>
  </div>
</div>
{{-- ── MODAL PERSONAS ── --}}
<div class="modal-overlay" id="modal-personas">
  <div class="modal">
    <div class="modal-title">Personas asignadas</div>
    <div class="user-check-list" id="personas-check-list">
      @foreach ($usuariosDisponibles as $u)
        @php
          $checked     = in_array((int) $u->id, array_map('intval', json_decode($tarea->control_user ?? '[]', true) ?? []));
          $hasImp      = in_array((int) $u->id, $usuariosConImputaciones);
        @endphp
        <label class="user-check-item {{ $hasImp && $checked ? 'disabled' : '' }}"
               data-uid="{{ $u->id }}"
               data-has-imp="{{ $hasImp ? 'true' : 'false' }}"
               data-nombre="{{ $u->nombre }}">
          <input type="checkbox"
                 value="{{ $u->id }}"
                 {{ $checked ? 'checked' : '' }}
                 {{ $hasImp && $checked ? 'disabled' : '' }}
                 data-orig="{{ $checked ? '1' : '0' }}">
          <span>{{ $u->nombre }}</span>
          @if ($hasImp)
            <span class="user-imp-badge"><i class="ti ti-clock-record"></i> con imputaciones</span>
          @endif
        </label>
      @endforeach
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-personas')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarAsignados()">Guardar</button>
    </div>
  </div>
</div>

{{-- ── MODAL CONFIRMAR ELIMINAR IMPUTACIÓN ── --}}
<div class="modal-overlay" id="modal-confirm-del-imp">
  <div class="modal" style="width:360px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div style="width:40px;height:40px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas fa-times-circle" style="color:#dc2626"></i>
      </div>
      <div class="modal-title" style="margin:0">Eliminar imputación</div>
    </div>
    <p style="font-size:13px;color:#6b7280;margin:0 0 20px">
      ¿Seguro que quieres eliminar esta imputación? <span style="color:#dc2626;font-weight:500">Esta acción no se puede deshacer.</span>
    </p>
    <div class="modal-footer">
      <button class="btn" onclick="cancelarEliminarImp()">Cancelar</button>
      <button id="btn-confirm-del-imp" onclick="confirmarEliminarImp()"
        style="background:#dc2626;border-color:#dc2626;color:#fff;font-weight:500"
        class="btn">Eliminar</button>
    </div>
  </div>
</div>
{{-- ── MODAL AVISO: usuario con imputaciones ── --}}
<div class="modal-overlay" id="modal-warn-imp">
  <div class="modal warn-modal" style="text-align:center">
    <div class="warn-icon"><i class="ti ti-alert-triangle"></i></div>
    <div class="warn-msg" id="warn-imp-msg"></div>
    <div class="modal-footer" style="justify-content:center">
      <button class="btn btn-primary" onclick="closeModal('modal-warn-imp')">Entendido</button>
    </div>
  </div>
</div>

{{-- ── MODAL IMPUTACIÓN ── --}}
<div class="modal-overlay" id="modal-imp">
  <div class="modal">
    <div class="modal-title">Añadir imputación</div>
    <label class="m-lbl">Persona</label>
    <select id="imp-usuario" class="f-input">
      <option value="">— Selecciona —</option>
      @foreach ($usuarios as $u)
        <option value="{{ $u->id }}">{{ $u->nombre }}</option>
      @endforeach
    </select>
    <div class="form-row" style="margin-top:8px">
      <div>
        <label class="m-lbl">Fecha</label>
        <input type="date" id="imp-fecha" class="f-input" value="{{ date('Y-m-d') }}">
      </div>
      <div>
        <label class="m-lbl">Duración (hh:mm)</label>
        <input type="time" id="imp-dur" class="f-input">
      </div>
    </div>
    <label class="m-lbl" style="margin-top:8px">Observación (opcional)</label>
    <textarea id="imp-obs" class="f-input" rows="2" placeholder="Observación..."></textarea>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-imp')">Cancelar</button>
      <button class="btn btn-primary" id="btn-imp-save" onclick="guardarImp()">Guardar</button>
    </div>
  </div>
</div>
@endif

<script>
const CSRF          = document.querySelector('meta[name=csrf-token]')?.content ?? '{{ $csrf }}';
const URL_UPDATE    = '{{ $urlUpdate }}';
const URL_ASIGNADOS = '{{ $urlAsignados }}';
const URL_IMP_STORE = '{{ $urlImpStore }}';
const URL_IMP_DEL   = '{{ $urlImpDel }}';
const URL_FOTOS     = '{{ url("/vm/tareas_{$tipo}_form/{$tarea->id}/fotos") }}';
const COLOR_TX      = '{{ $c['tx'] }}';
const COLOR_BG      = '{{ $c['bg'] }}';
const COLOR_BAR     = '{{ $c['bar'] }}';
const COLOR_BD      = '{{ $c['bd'] }}';

// ── Edit mode ──
function enterEdit() {
  document.getElementById('v-header').style.display = 'none';
  document.getElementById('e-header').style.display = '';
  document.getElementById('v-datos').style.display  = 'none';
  document.getElementById('e-datos').style.display  = '';
  document.getElementById('btn-view-mode').style.display = 'none';
  document.getElementById('btn-edit-mode').style.display = 'flex';
}
async function toggleBorrar() {
  const r = await fetch('{{ route("vm.tarea.borrar", [$project->slug, $tipo, $tarea->id]) }}', {
    method: 'PATCH', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
  });
  location.reload();
}
async function toggleOcultar() {
  const r = await fetch('{{ route("vm.tarea.ocultar", [$project->slug, $tipo, $tarea->id]) }}', {
    method: 'PATCH', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
  });
  location.reload();
}
function cancelEdit() {
  document.getElementById('v-header').style.display = '';
  document.getElementById('e-header').style.display = 'none';
  document.getElementById('v-datos').style.display  = '';
  document.getElementById('e-datos').style.display  = 'none';
  document.getElementById('btn-view-mode').style.display = 'flex';
  document.getElementById('btn-edit-mode').style.display = 'none';
}

async function guardar() {
  const btn = document.getElementById('btn-save');
  btn.textContent = 'Guardando…'; btn.disabled = true;
  try {
    const r = await fetch(URL_UPDATE, {
      method: 'PUT',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({
        nombre:            document.getElementById('e-nombre')?.value ?? '',
        Tipo:              document.getElementById('e-tipo')?.value || null,
        fecha_planificada: document.getElementById('e-fecha')?.value || null,
        descripcion:       document.getElementById('e-descripcion')?.value || null,
      })
    });
    if (!r.ok) throw new Error(await r.text());
    location.reload();
  } catch(e) {
    alert('Error al guardar: ' + e.message);
    btn.textContent = 'Guardar'; btn.disabled = false;
  }
}

// ── Modals ──
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); })
);

// Prevenir desmarcar usuarios con imputaciones
document.querySelectorAll('#personas-check-list .user-check-item').forEach(label => {
  const cb = label.querySelector('input[type=checkbox]');
  if (!cb) return;
  cb.addEventListener('change', function() {
    if (!this.checked && label.dataset.hasImp === 'true') {
      this.checked = true;
      document.getElementById('warn-imp-msg').textContent =
        '"' + label.dataset.nombre + '" tiene imputaciones registradas en esta tarea y no puede ser eliminado de la lista de personas asignadas.';
      openModal('modal-warn-imp');
    }
  });
});

// ── Helpers ──
function iniciales(n) { return n.split(' ').slice(0,2).map(p=>(p[0]||'').toUpperCase()).join(''); }
function minToTime(m) { return String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0'); }
function timeToMin(t) { const [h,m]=(t||'').split(':').map(Number); return (h||0)*60+(m||0); }
function minToHm(m) { if(m<=0)return'0h 00m'; return Math.floor(m/60)+'h '+String(m%60).padStart(2,'0')+'m'; }

// ── Personas asignadas ──
async function guardarAsignados() {
  const ids = [...document.querySelectorAll('#personas-check-list input:checked')].map(cb => parseInt(cb.value));
  const r = await fetch(URL_ASIGNADOS, {
    method: 'PATCH',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
    body: JSON.stringify({ids})
  });
  const data = await r.json();
  if (!data.ok) return;
  closeModal('modal-personas');

  const list = document.getElementById('asgn-list');
  list.innerHTML = '';
  const checked = [...document.querySelectorAll('#personas-check-list input:checked')];
  if (!checked.length) {
    list.innerHTML = '<div style="font-size:13px;color:#bbb;font-style:italic">Sin personas asignadas.</div>';
  } else {
    checked.forEach(cb => {
      const nombre = cb.closest('label').querySelector('span').textContent.trim();
      const row = document.createElement('div'); row.className = 'asgn-row';
      row.innerHTML = `
        <div class="t-avatar" style="background:#f3f4f6;color:#9ca3af">${iniciales(nombre)}</div>
        <div class="asgn-info"><div class="asgn-name">${nombre}</div>
        <div class="bar-wrap"><div class="bar-fill" style="width:0%;background:${COLOR_BAR}"></div></div></div>
        <span style="font-size:11px;color:#bbb">Sin imputar</span>`;
      list.appendChild(row);
    });
  }

  // Actualiza select de modal-imp con nuevas personas asignadas
  const sel = document.getElementById('imp-usuario');
  if (sel) {
    sel.innerHTML = '<option value="">— Selecciona —</option>';
    checked.forEach(cb => {
      const opt = document.createElement('option');
      opt.value = cb.value;
      opt.textContent = cb.closest('label').querySelector('span').textContent.trim();
      sel.appendChild(opt);
    });
  }
}

// ── Imputaciones ──
let impTotal = {{ $totalImputado }};

function updateImpBadge() {
  const badge = document.getElementById('imp-total-badge');
  const row   = document.getElementById('imp-total-row');
  if (!badge || !row) return;
  if (impTotal > 0) {
    badge.textContent = minToHm(impTotal);
    row.style.display = 'grid';
  } else {
    badge.textContent = '';
    row.style.display = 'none';
  }
}

let _pendingDelImpId = null;
let _pendingDelBtn    = null;

function pedirConfirmEliminarImp(impId, btn) {
  _pendingDelImpId = impId;
  _pendingDelBtn   = btn;
  openModal('modal-confirm-del-imp');
}
function cancelarEliminarImp() {
  _pendingDelImpId = null;
  _pendingDelBtn   = null;
  document.getElementById('modal-confirm-del-imp').classList.add('hidden');
}
async function confirmarEliminarImp() {
  if (!_pendingDelImpId) return;
  const impId = _pendingDelImpId;
  const btn   = _pendingDelBtn;
  closeModal('modal-confirm-del-imp');
  if (btn) btn.disabled = true;
  try {
    const r = await fetch(URL_IMP_DEL + '/' + impId, {
      method: 'DELETE',
      headers: {'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    const data = await r.json();
    if (data.ok) {
      const row = document.getElementById('imp-' + impId);
      impTotal -= parseInt(row?.dataset.dur || 0);
      row?.remove();
      updateImpBadge();
      if (!document.querySelectorAll('#imp-list .imp-row').length) {
        document.getElementById('imp-list').innerHTML =
          '<div id="imp-empty" style="font-size:13px;color:#bbb;font-style:italic">Sin imputaciones todavía.</div>';
      }
    }
  } catch(e) { alert('Error al eliminar: ' + e.message); }
  if (btn) btn.disabled = false;
  _pendingDelImpId = null;
  _pendingDelBtn   = null;
}

async function guardarImp() {
  const uid   = parseInt(document.getElementById('imp-usuario').value);
  const fecha = document.getElementById('imp-fecha').value;
  const dur   = timeToMin(document.getElementById('imp-dur').value);
  const obs   = document.getElementById('imp-obs').value.trim();
  if (!uid || !fecha || dur < 1) { alert('Completa persona, fecha y duración.'); return; }

  const btn = document.getElementById('btn-imp-save');
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    const r = await fetch(URL_IMP_STORE, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({id_usuario:uid, fecha_imputacion:fecha, duracion:dur, observacion:obs||null})
    });
    const data = await r.json();
    if (data.ok) {
      const imp = data.imp;
      const inis = iniciales(imp.usuario_nombre);
      const fechaFmt = new Date(imp.fecha_imputacion+'T00:00:00').toLocaleDateString('es-ES',{day:'numeric',month:'short'});
      document.getElementById('imp-empty')?.remove();
      const row = document.createElement('div');
      row.className = 'imp-row'; row.id = 'imp-'+imp.id; row.dataset.dur = imp.duracion;
      row.innerHTML = `
        <div class="imp-avatar" style="background:${COLOR_BG};color:${COLOR_TX};grid-row:span 2">${inis}</div>
        <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;min-width:0">
          <span class="imp-uname">${imp.usuario_nombre}</span>
          <span class="imp-date">${fechaFmt}</span>
        </div>
        <span class="imp-dur" style="color:${COLOR_TX}">${minToHm(imp.duracion)}</span>
        <div class="imp-actions">
          <button class="btn-pencil" title="Editar" onclick="abrirEditImp(${imp.id},'${imp.fecha_imputacion}',${imp.duracion},${JSON.stringify(imp.observacion)})">✎</button>
          <button class="btn-x" title="Eliminar" onclick="pedirConfirmEliminarImp(${imp.id},this)">✕</button>
        </div>
        <span class="imp-obs ${imp.observacion?'':'empty'}" style="grid-column:2/-1">${imp.observacion||'Sin observación.'}</span>`;
      const list = document.getElementById('imp-list');
      list.appendChild(row);
      impTotal += imp.duracion;
      updateImpBadge();
      closeModal('modal-imp');
      document.getElementById('imp-usuario').value = '';
      document.getElementById('imp-dur').value = '00:00';
      document.getElementById('imp-obs').value = '';
    }
  } catch(e) { alert('Error al guardar la imputación.'); }
  btn.disabled = false; btn.textContent = 'Guardar';
}

function abrirEditImp(impId, fecha, dur, obs) {
  document.getElementById('edit-imp-id').value      = impId;
  document.getElementById('edit-imp-old-dur').value = dur;
  document.getElementById('edit-imp-fecha').value   = fecha ? fecha.substring(0,10) : '';
  document.getElementById('edit-imp-dur').value     = minToTime(dur);
  document.getElementById('edit-imp-obs').value     = obs || '';
  openModal('modal-edit-imp');
}

async function guardarEditImp() {
  const impId  = parseInt(document.getElementById('edit-imp-id').value);
  const oldDur = parseInt(document.getElementById('edit-imp-old-dur').value);
  const fecha  = document.getElementById('edit-imp-fecha').value;
  const dur    = timeToMin(document.getElementById('edit-imp-dur').value);
  const obs    = document.getElementById('edit-imp-obs').value.trim();
  if (!fecha || dur < 1) { alert('Completa fecha y duración.'); return; }

  const btn = document.getElementById('btn-edit-imp-save');
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    const r = await fetch(URL_IMP_DEL + '/' + impId, {
      method: 'PATCH',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({duracion: dur, fecha_imputacion: fecha, observacion: obs || null})
    });
    const data = await r.json();
    if (data.ok) {
      const imp  = data.imp;
      const row  = document.getElementById('imp-' + impId);
      if (row) {
        row.dataset.dur = imp.duracion;
        const fechaFmt  = new Date(imp.fecha_imputacion+'T00:00:00').toLocaleDateString('es-ES',{day:'numeric',month:'short',year:'numeric'});
        const dateEl = row.querySelector('.imp-date');
        if (dateEl) dateEl.textContent = fechaFmt;
        const durEl = row.querySelector('.imp-dur');
        if (durEl) durEl.textContent = minToHm(imp.duracion);
        const obsEl = row.querySelector('.imp-obs');
        if (obsEl) {
          obsEl.textContent = imp.observacion || 'Sin observación.';
          obsEl.className   = 'imp-obs' + (imp.observacion ? '' : ' empty');
        }
      }
      impTotal = impTotal - oldDur + imp.duracion;
      updateImpBadge();
      closeModal('modal-edit-imp');
    }
  } catch(e) { alert('Error al guardar.'); }
  btn.disabled = false; btn.textContent = 'Guardar';
}
</script>
</x-app-layout>
