@php
$initials = collect(explode(' ', $usuario->nombre ?? ''))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode('');

function fmtTime(?string $t): string {
    if (!$t) return '—';
    return substr($t, 0, 5);
}
function fmtMin2(?int $min): string {
    if ($min === null) return '—';
    $neg = $min < 0;
    $abs = abs($min);
    return ($neg ? '−' : '') . intdiv($abs, 60) . 'h ' . str_pad($abs % 60, 2, '0', STR_PAD_LEFT) . 'm';
}

$tipoColores = [
    'limpieza'      => ['bg'=>'#E6F1FB','tx'=>'#0C447C','bd'=>'#B5D4F4'],
    'mantenimiento' => ['bg'=>'#FAEEDA','tx'=>'#633806','bd'=>'#F5C97C'],
    'piscina'       => ['bg'=>'#E1F5EE','tx'=>'#085041','bd'=>'#7DCDB5'],
];
$tipoLabel = ['limpieza'=>'Limpieza','mantenimiento'=>'Mantenimiento','piscina'=>'Piscinas'];
$tipoIcon  = ['limpieza'=>'ti-sparkles','mantenimiento'=>'ti-tool','piscina'=>'ti-droplet'];

$tieneGps = $fichaje->entrada_lat || $fichaje->salida_lat
         || $fichaje->pausa_ini_lat || $fichaje->pausa_fin_lat;

// tarjetas: colores según HE
$heColor  = $heMin === null ? '#999' : ($heMin > 0 ? '#27500A' : ($heMin < 0 ? '#A32D2D' : '#185FA5'));
$heBg     = $heMin === null ? 'var(--surface-1)' : ($heMin > 0 ? '#EAF3DE' : ($heMin < 0 ? '#FCEBEB' : '#E6F1FB'));
$heBd     = $heMin === null ? 'rgba(0,0,0,.1)' : ($heMin > 0 ? '#7BBF50' : ($heMin < 0 ? '#F7C1C1' : '#B5D4F4'));
$heIcon   = $heMin === null ? 'ti-minus' : ($heMin > 0 ? 'ti-trending-up' : ($heMin < 0 ? 'ti-trending-down' : 'ti-equal'));
@endphp

<x-app-layout
    :breadcrumb="[
        ['label' => 'Fichajes', 'url' => route('listado', [$project->slug, 'fichaje'])],
        ['label' => ($usuario->nombre ?? 'Fichaje') . ' · ' . \Carbon\Carbon::parse($fichaje->fecha_fichaje)->translatedFormat('j M Y'), 'url' => ''],
    ]"
    :project="$project">

<x-slot name="actions">
  <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
    <a href="{{ route('ficha', [$project->slug, 'fichaje', $fichaje->id]) }}"
      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors"
      title="Ver ficha estándar">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </a>
    <button onclick="enterEdit()"
      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
      <i class="ti ti-pencil text-sm"></i>Editar
    </button>
  </div>
  <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
    <button onclick="openModal('modal-delete')"
      class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
      <i class="ti ti-trash text-sm"></i>Borrar
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
</x-slot>

@include('partials.role-badge', ['project' => $project, 'texto' => 'Solo Dirección general y Director RRHH (o admin) pueden ajustar las horas extra y editar fecha/horario sin el límite de 2 días.'])

<style>
.section-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .section-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.sec-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;display:flex;align-items:center;gap:6px}
/* horario grid */
.hor-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.hor-cell{display:flex;flex-direction:column;gap:0}
.hor-lbl{font-size:11px;color:#aaa;font-weight:500;margin-bottom:5px;display:flex;align-items:center;gap:3px}
.hor-manual{font-size:20px;font-weight:500;color:#111;line-height:1}
.dark .hor-manual{color:#eee}
.hor-manual.empty{font-size:14px;color:#bbb;font-style:italic}
.hor-auto-row{display:flex;align-items:center;gap:4px;margin-top:5px;padding-top:5px;border-top:0.5px solid rgba(0,0,0,.06)}
.dark .hor-auto-row{border-top-color:rgba(255,255,255,.06)}
.hor-auto-lbl{font-size:10px;color:#bbb;white-space:nowrap}
.hor-auto-val{font-size:12px;color:#999;font-weight:500}
/* tarjetas resumen */
.cards-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:12px}
@media(max-width:700px){.cards-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:480px){.cards-grid{grid-template-columns:repeat(2,1fr)}}
.sum-card{border:0.5px solid rgba(0,0,0,.08);border-radius:10px;padding:12px 14px;background:#fff}
.dark .sum-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.sum-lbl{font-size:11px;color:#aaa;margin-bottom:4px;display:flex;align-items:center;gap:4px}
.sum-val{font-size:19px;font-weight:500;line-height:1}
/* field rows */
.field-grid{display:grid;gap:0}
.field-row{display:grid;grid-template-columns:140px 1fr;align-items:start;padding:8px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .field-row{border-bottom-color:rgba(255,255,255,.05)}
.field-row:last-child{border-bottom:none;padding-bottom:0}
.field-row:first-child{padding-top:0}
.field-lbl{font-size:12px;color:#999;padding-top:1px}
.field-val{font-size:13px;color:#111}
.dark .field-val{color:#eee}
.field-val.empty{color:#bbb;font-style:italic}
/* imputaciones */
.imp-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .imp-row{border-bottom-color:rgba(255,255,255,.05)}
.imp-row:last-child{border-bottom:none;padding-bottom:0}
.imp-row:first-child{padding-top:0}
.imp-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;font-size:11px;font-weight:500;white-space:nowrap;flex-shrink:0}
.imp-tarea{font-size:13px;color:#111;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dark .imp-tarea{color:#eee}
.imp-obs{font-size:11px;color:#aaa;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.imp-dur{font-size:13px;font-weight:500;flex-shrink:0;min-width:52px;text-align:right}
/* GPS */
.gps-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.gps-cell{background:rgba(0,0,0,.02);border:0.5px solid rgba(0,0,0,.06);border-radius:8px;padding:10px 12px}
.dark .gps-cell{background:rgba(255,255,255,.02);border-color:rgba(255,255,255,.06)}
.gps-lbl{font-size:11px;color:#aaa;margin-bottom:4px;display:flex;align-items:center;gap:4px}
.gps-val{font-size:12px;color:#666;font-family:monospace}
.dark .gps-val{color:#aaa}
/* badges cabecera */
.fbadge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:500}
/* edit */
.f-input{width:100%;box-sizing:border-box;border:0.5px solid rgba(0,0,0,.18);border-radius:6px;padding:6px 9px;font-size:13px;background:#fff;color:#111}
.dark .f-input{background:#111;color:#eee;border-color:rgba(255,255,255,.18)}
.f-time{width:86px}
.toggle-wrap{display:flex;align-items:center;gap:8px;padding:5px 0;cursor:pointer}
.toggle{position:relative;display:inline-block;width:34px;height:20px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#ddd;border-radius:10px;transition:.2s}
.toggle input:checked+.toggle-slider{background:#0C447C}
.toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle input:checked+.toggle-slider:before{transform:translateX(14px)}
.toggle-lbl{font-size:13px;color:#444}
.dark .toggle-lbl{color:#ccc}
/* modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border:0.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.5rem;width:380px;max-width:94vw}
.dark .modal{background:#1a1a1a;border-color:rgba(255,255,255,.1)}
.modal-title{font-weight:500;font-size:15px;margin:0 0 .75rem}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:1.25rem;padding-top:1rem;border-top:0.5px solid rgba(0,0,0,.07)}
.btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff}
.dark .btn{background:#222;border-color:rgba(255,255,255,.15);color:#eee}
.btn-danger{background:#A32D2D;color:#fff;border-color:#A32D2D}
</style>

<div style="max-width:900px;margin:0 auto;padding:1rem 0 3rem">

{{-- ── TARJETAS RESUMEN ── --}}
<div class="cards-grid">
  {{-- Tiempo fichado --}}
  <div class="sum-card">
    <div class="sum-lbl"><i class="ti ti-clock-check" style="font-size:12px"></i>Fichado</div>
    <div class="sum-val" style="color:#185FA5">{{ fmtMin2($fichadoMin) }}</div>
    @if ($fichadoMin !== null)
      <div style="font-size:11px;color:#aaa;margin-top:3px">neto de pausa</div>
    @endif
  </div>
  {{-- Tiempo contrato --}}
  <div class="sum-card">
    <div class="sum-lbl"><i class="ti ti-file-text" style="font-size:12px"></i>Contrato</div>
    <div class="sum-val" style="color:#555">{{ fmtMin2($esperadoMin) }}</div>
    @if ($esperadoMin !== null)
      <div style="font-size:11px;color:#aaa;margin-top:3px">jornada esperada</div>
    @endif
  </div>
  {{-- Horas extra --}}
  <div class="sum-card" style="background:{{ $heBg }};border-color:{{ $heBd }}">
    <div class="sum-lbl" style="color:{{ $heColor }}"><i class="ti {{ $heIcon }}" style="font-size:12px"></i>Horas extra</div>
    <div class="sum-val" style="color:{{ $heColor }}">{{ fmtMin2($heMin) }}</div>
    @if ($heMin !== null)
      <div style="font-size:11px;color:{{ $heColor }};opacity:.7;margin-top:3px">
        {{ $heMin > 0 ? 'por encima del contrato' : ($heMin < 0 ? 'por debajo del contrato' : 'exacto') }}
      </div>
    @endif
  </div>
  {{-- Horas efectivas --}}
  <div class="sum-card">
    <div class="sum-lbl"><i class="ti ti-run" style="font-size:12px"></i>H. efectivas</div>
    <div class="sum-val" style="color:#185FA5">{{ fmtMin2($efectivasMin) }}</div>
    @if ($efectivasMin !== null)
      <div style="font-size:11px;color:#aaa;margin-top:3px">fichado − pausa bruta</div>
    @endif
  </div>
  {{-- Tiempo imputado --}}
  <div class="sum-card">
    <div class="sum-lbl"><i class="ti ti-clock-record" style="font-size:12px"></i>Imputado</div>
    <div class="sum-val" style="color:{{ $imputaciones->count() ? '#633806' : '#bbb' }}">
      {{ $imputaciones->count() ? fmtMin2($totalImputado) : '—' }}
    </div>
    @if ($imputaciones->count())
      <div style="font-size:11px;color:#aaa;margin-top:3px">{{ $imputaciones->count() }} {{ $imputaciones->count() === 1 ? 'tarea' : 'tareas' }}</div>
    @endif
  </div>
</div>

{{-- ── CABECERA EMPLEADO ── --}}
<div class="section-card" style="display:flex;align-items:center;gap:14px">
  <div style="width:48px;height:48px;border-radius:50%;background:#E6F1FB;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:16px;color:#185FA5;flex-shrink:0">
    {{ $initials }}
  </div>
  <div style="flex:1;min-width:0">
    <div id="v-header">
      <div style="font-size:16px;font-weight:500;color:var(--text-primary)">{{ $usuario->nombre ?? '—' }}</div>
      <div style="font-size:13px;color:#888;margin-top:2px">
        {{ \Carbon\Carbon::parse($fichaje->fecha_fichaje)->translatedFormat('l, j \d\e F \d\e Y') }}
      </div>
    </div>
    <div id="e-header" data-display="grid" style="display:none;grid-template-columns:1fr 1fr;gap:10px">
      <div>
        <div style="font-size:11px;color:#999;margin-bottom:4px">Empleado</div>
        <select id="e-control_user" class="f-input">
          @foreach ($usuarios as $u)
            <option value="{{ $u->id }}" {{ $u->id == $fichaje->control_user ? 'selected' : '' }}>{{ $u->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div style="font-size:11px;color:#999;margin-bottom:4px">Fecha</div>
        <input type="date" id="e-fecha_fichaje" class="f-input" value="{{ $fichaje->fecha_fichaje }}"
               max="{{ now()->toDateString() }}"
               @if(!($puedeSinLimiteFecha ?? false)) min="{{ now()->subDays(2)->toDateString() }}" @endif>
      </div>
      <div style="grid-column:1/-1;display:flex;gap:20px;margin-top:4px">
        <label class="toggle-wrap">
          <span class="toggle"><input type="checkbox" id="e-festivo" {{ $fichaje->festivo ? 'checked' : '' }}><span class="toggle-slider"></span></span>
          <span class="toggle-lbl">Festivo trabajado</span>
        </label>
        <label class="toggle-wrap">
          <span class="toggle"><input type="checkbox" id="e-fuera_de_turno" {{ $fichaje->fuera_de_turno ? 'checked' : '' }}><span class="toggle-slider"></span></span>
          <span class="toggle-lbl">Fuera de turno</span>
        </label>
      </div>
    </div>
  </div>
  <div id="v-badges" style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
    @if ($fichaje->validado)
      <span class="fbadge" style="background:#EAF3DE;border:0.5px solid #7BBF50;color:#27500A">
        <i class="ti ti-circle-check" style="font-size:11px"></i>Validado
      </span>
    @else
      <span class="fbadge" style="background:var(--surface-1);border:0.5px solid rgba(0,0,0,.12);color:#999">
        <i class="ti ti-clock" style="font-size:11px"></i>Pendiente
      </span>
    @endif
    @if ($fichaje->festivo)
      <span class="fbadge" style="background:#FEF3C7;border:0.5px solid #F59E0B;color:#92400E">
        <i class="ti ti-sun" style="font-size:11px"></i>Festivo trab.
      </span>
    @endif
    @if ($fichaje->fuera_de_turno)
      <span class="fbadge" style="background:#F5F3FF;border:0.5px solid #8B5CF6;color:#4C1D95">
        <i class="ti ti-arrows-shuffle" style="font-size:11px"></i>Fuera de turno
      </span>
    @endif
  </div>
</div>

{{-- ── JORNADA HORARIA ── --}}
<div class="section-card">
  <div class="sec-title"><i class="ti ti-clock"></i>Jornada horaria</div>

  {{-- VIEW --}}
  <div id="v-jornada" class="hor-grid">
    @foreach ([
      ['Inicio',      'hora_inicio',  'hora_ini_auto',  'ti-player-play'],
      ['Pausa ini',   'pausa_inicio', 'pausa_ini_auto', 'ti-coffee'],
      ['Pausa fin',   'pausa_fin',    'pausa_fin_auto', 'ti-coffee-off'],
      ['Fin',         'hora_fin',     'hora_fin_auto',  'ti-player-stop'],
    ] as [$lbl, $col, $auto, $icon])
      <div class="hor-cell">
        <div class="hor-lbl"><i class="ti {{ $icon }}" style="font-size:10px"></i>{{ $lbl }}</div>
        @if ($fichaje->$col)
          <div class="hor-manual">{{ fmtTime($fichaje->$col) }}</div>
        @else
          <div class="hor-manual empty">—</div>
        @endif
        <div class="hor-auto-row">
          <span class="hor-auto-lbl">Auto</span>
          <span class="hor-auto-val">{{ $fichaje->$auto ? fmtTime($fichaje->$auto) : '—' }}</span>
        </div>
      </div>
    @endforeach
  </div>

  {{-- EDIT --}}
  <div id="e-jornada" style="display:none">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
      @foreach ([
        ['Inicio manual','hora_inicio'],
        ['Pausa ini manual','pausa_inicio'],
        ['Pausa fin manual','pausa_fin'],
        ['Fin manual','hora_fin'],
      ] as [$lbl,$col])
        <div>
          <div style="font-size:11px;color:#999;margin-bottom:4px">{{ $lbl }}</div>
          <input type="time" id="e-{{ $col }}" class="f-input f-time" value="{{ $fichaje->$col ? substr($fichaje->$col,0,5) : '' }}">
        </div>
      @endforeach
    </div>
    <div style="border-top:0.5px solid rgba(0,0,0,.06);padding-top:12px;margin-bottom:14px">
      <div style="font-size:11px;color:#aaa;margin-bottom:8px;font-weight:500">HORAS AUTOMÁTICAS (app)</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
        @foreach ([
          ['Inicio auto','hora_ini_auto'],
          ['Pausa ini auto','pausa_ini_auto'],
          ['Pausa fin auto','pausa_fin_auto'],
          ['Fin auto','hora_fin_auto'],
        ] as [$lbl,$col])
          <div>
            <div style="font-size:11px;color:#bbb;margin-bottom:4px">{{ $lbl }}</div>
            <input type="time" id="e-{{ $col }}" class="f-input f-time" style="background:rgba(0,0,0,.02);color:#888" value="{{ $fichaje->$col ? substr($fichaje->$col,0,5) : '' }}" disabled>
          </div>
        @endforeach
      </div>
    </div>
    <div style="border-top:0.5px solid rgba(0,0,0,.06);padding-top:12px;display:flex;flex-direction:column;gap:0">
      <label class="toggle-wrap">
        <span class="toggle"><input type="checkbox" id="e-validado" {{ $fichaje->validado ? 'checked' : '' }}><span class="toggle-slider"></span></span>
        <span class="toggle-lbl">Validado</span>
      </label>
    </div>
  </div>
</div>

{{-- ── IMPUTACIONES DEL DÍA ── --}}
<div class="section-card">
  <div class="sec-title" style="margin-bottom:{{ $imputaciones->count() ? '10px' : '0' }}">
    <i class="ti ti-clock-record"></i>Imputaciones del día
    @if ($totalImputado)
      <span style="margin-left:auto;font-size:12px;font-weight:500;color:#633806;background:#FAEEDA;padding:2px 9px;border-radius:5px;border:0.5px solid #F5C97C;text-transform:none;letter-spacing:0">
        {{ fmtMin2($totalImputado) }} total
      </span>
    @endif
  </div>
  @forelse ($imputaciones as $imp)
    @php $c = $tipoColores[$imp->tipo] ?? $tipoColores['limpieza']; @endphp
    <div class="imp-row">
      <span class="imp-badge" style="background:{{ $c['bg'] }};color:{{ $c['tx'] }};border:0.5px solid {{ $c['bd'] }}">
        <i class="ti {{ $tipoIcon[$imp->tipo] ?? 'ti-clock' }}" style="font-size:10px"></i>
        {{ $tipoLabel[$imp->tipo] ?? $imp->tipo }}
      </span>
      <div style="flex:1;min-width:0">
        <div class="imp-tarea">{{ $imp->tarea_nombre }}</div>
        @if ($imp->observacion)
          <div class="imp-obs">{{ $imp->observacion }}</div>
        @endif
      </div>
      <span class="imp-dur" style="color:{{ $c['tx'] }}">{{ fmtMin2($imp->duracion) }}</span>
    </div>
  @empty
    <div style="font-size:13px;color:#bbb;font-style:italic">Sin imputaciones este día.</div>
  @endforelse
</div>

{{-- ── DESPLAZAMIENTO ── --}}
<div class="section-card">
  <div class="sec-title"><i class="ti ti-car"></i>Desplazamiento</div>
  <div id="v-despla" class="field-grid">
    <div class="field-row">
      <div class="field-lbl">Kilómetros</div>
      <div class="field-val {{ !$fichaje->km ? 'empty' : '' }}">
        {{ $fichaje->km ? number_format((float)$fichaje->km, 1, ',', '.') . ' km' : 'Sin registrar' }}
      </div>
    </div>
    <div class="field-row">
      <div class="field-lbl">Trayecto</div>
      <div class="field-val {{ !$fichaje->trayecto ? 'empty' : '' }}">
        {{ $fichaje->trayecto ?: 'Sin registrar' }}
      </div>
    </div>
  </div>
  <div id="e-despla" data-display="grid" style="display:none;grid-template-columns:110px 1fr;gap:10px;align-items:end">
    <div>
      <div style="font-size:11px;color:#999;margin-bottom:4px">Kilómetros</div>
      <input type="number" id="e-km" class="f-input" step="0.1" min="0" value="{{ $fichaje->km ?? '' }}" placeholder="0">
    </div>
    <div>
      <div style="font-size:11px;color:#999;margin-bottom:4px">Trayecto</div>
      <input type="text" id="e-trayecto" class="f-input" value="{{ $fichaje->trayecto ?? '' }}" placeholder="Origen → Destino">
    </div>
  </div>
</div>


{{-- ── OBSERVACIÓN ── --}}
<div class="section-card">
  <div class="sec-title"><i class="ti ti-message"></i>Observación</div>
  <div id="v-obs">
    @if ($fichaje->observacion)
      <div style="font-size:13px;color:#444;line-height:1.55">{{ $fichaje->observacion }}</div>
    @else
      <div style="font-size:13px;color:#bbb;font-style:italic">Sin observación.</div>
    @endif
  </div>
  <div id="e-obs" style="display:none">
    <textarea id="e-observacion" class="f-input" rows="3" style="resize:vertical"
      placeholder="Añade una observación…">{{ $fichaje->observacion ?? '' }}</textarea>
  </div>
</div>

@if($puedeAjustar)
{{-- ── AJUSTE HE ── --}}
<div class="section-card">
  <div class="sec-title">
    <i class="ti ti-adjustments-horizontal"></i>Ajuste horas extra
    <span class="app-tooltip">
          <span style="font-size:11px;color:#aaa;margin-left:4px;cursor:default">&#9432;</span>
          <span class="app-tooltip-box">Corrección manual de horas extra. Valor en minutos (positivo suma, negativo resta). Ejemplo: 180 equivale a 3 horas. Requiere motivo obligatorio.&#10;Visible para: role admin, role vm_admin y roles 3 (Dirección general) u 11 (Director RRHH).</span>
    </span>
  </div>
  <div id="v-ajuste_he" class="field-grid">
    <div class="field-row">
      <div class="field-lbl">Ajuste (min)</div>
      @if($fichaje->ajuste_he)
        @php
          $absMin = abs($fichaje->ajuste_he);
          $heDias = intdiv($absMin, 1440);
          $heHoras = intdiv($absMin % 1440, 60);
          $heMin = $absMin % 60;
          $heParts = [];
          if ($heDias) $heParts[] = $heDias . 'd';
          if ($heHoras || $heDias) $heParts[] = $heHoras . 'h';
          $heParts[] = str_pad($heMin, 2, '0', STR_PAD_LEFT) . 'm';
          $heHuman = ($fichaje->ajuste_he > 0 ? '+' : '−') . implode(' ', $heParts);
        @endphp
        <div style="font-size:13px;color:#444">
          {{ ($fichaje->ajuste_he > 0 ? '+' : '') . $fichaje->ajuste_he . ' min' }}
          <span style="color:#888;margin-left:6px">({{ $heHuman }})</span>
        </div>
      @else
        <div style="font-size:13px;color:#bbb;font-style:italic">Sin ajuste.</div>
      @endif
    </div>
    <div class="field-row">
      <div class="field-lbl">Motivo</div>
      @if($fichaje->ajuste_he_motivo)
        <div style="font-size:13px;color:#444">{{ $fichaje->ajuste_he_motivo }}</div>
      @else
        <div style="font-size:13px;color:#bbb;font-style:italic">Sin motivo.</div>
      @endif
    </div>
  </div>
  <div id="e-ajuste_he" data-display="grid" style="display:none;grid-template-columns:140px 1fr;gap:10px;align-items:end">
    <div>
      <div style="font-size:11px;color:#999;margin-bottom:4px">Ajuste (minutos)</div>
      <input type="number" id="e-ajuste_he_val" class="f-input" step="1"
             value="{{ $fichaje->ajuste_he ?? 0 }}" placeholder="0"
             oninput="document.getElementById('ajuste-he-human').textContent=ajusteHuman(this.value)">
      <div id="ajuste-he-human" style="font-size:11px;color:#888;margin-top:3px"></div>
    </div>
    <div>
      <div style="font-size:11px;color:#999;margin-bottom:4px">Motivo <span style="color:#e53e3e">*</span></div>
      <input type="text" id="e-ajuste_he_motivo" class="f-input" maxlength="500"
             value="{{ $fichaje->ajuste_he_motivo ?? '' }}"
             placeholder="Motivo del ajuste (obligatorio si hay ajuste)…">
    </div>
  </div>
</div>
@endif


{{-- ── GPS ── --}}
@if ($tieneGps)
<div class="section-card">
  <div class="sec-title"><i class="ti ti-map-pin"></i>Coordenadas GPS</div>
  <div class="gps-grid">
    @foreach ([
      ['Entrada',   $fichaje->entrada_lat,   $fichaje->entrada_lng,   'ti-door-enter'],
      ['Pausa ini', $fichaje->pausa_ini_lat, $fichaje->pausa_ini_lng, 'ti-coffee'],
      ['Pausa fin', $fichaje->pausa_fin_lat, $fichaje->pausa_fin_lng, 'ti-coffee-off'],
      ['Salida',    $fichaje->salida_lat,    $fichaje->salida_lng,    'ti-door-exit'],
    ] as [$lbl, $lat, $lng, $icon])
      <div class="gps-cell">
        <div class="gps-lbl">
          <i class="ti {{ $icon }}" style="font-size:11px"></i>{{ $lbl }}
          @if ($lat && $lng)
            <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}" target="_blank"
               style="margin-left:auto;color:#185FA5;font-size:11px;display:flex;align-items:center;gap:3px;text-decoration:none">
              <i class="ti ti-external-link" style="font-size:10px"></i>Mapa
            </a>
          @endif
        </div>
        @if ($lat && $lng)
          <div class="gps-val">{{ $lat }}, {{ $lng }}</div>
        @else
          <div style="font-size:12px;color:#ccc;font-style:italic">Sin datos</div>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endif

</div>{{-- /max-width --}}

{{-- ── MODAL DELETE ── --}}
<div class="modal-overlay" id="modal-delete">
  <div class="modal">
    <div class="modal-title">¿Borrar este fichaje?</div>
    <p style="font-size:13px;color:#666;margin:0">
      Se eliminará el fichaje de <strong>{{ $usuario->nombre ?? '' }}</strong>
      del <strong>{{ \Carbon\Carbon::parse($fichaje->fecha_fichaje)->translatedFormat('j M Y') }}</strong>.
      Esta acción no se puede deshacer.
    </p>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-delete')">Cancelar</button>
      <button class="btn btn-danger" onclick="borrar()">Borrar fichaje</button>
    </div>
  </div>
</div>

<script>
const PATCH_URL = "{{ route('vm.fichaje.update', [$project->slug, $fichaje->id]) }}";
const LIST_URL  = "{{ route('listado', [$project->slug, 'fichaje']) }}";
const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';

const EDIT_SECTIONS = ['header','jornada','despla','obs','ajuste_he'];

function enterEdit() {
  EDIT_SECTIONS.forEach(k => {
    document.getElementById('v-' + k)?.style.setProperty('display','none');
    const el = document.getElementById('e-' + k);
    if (el) el.style.setProperty('display', el.dataset.display || 'block');
  });
  document.getElementById('v-badges').style.display = 'none';
  document.getElementById('btn-view-mode').style.display = 'none';
  document.getElementById('btn-edit-mode').style.display = 'flex';
}

function cancelEdit() {
  EDIT_SECTIONS.forEach(k => {
    document.getElementById('v-' + k)?.style.removeProperty('display');
    document.getElementById('e-' + k)?.style.setProperty('display','none');
  });
  document.getElementById('v-badges').style.display = 'flex';
  document.getElementById('btn-view-mode').style.display = 'flex';
  document.getElementById('btn-edit-mode').style.display = 'none';
}

function val(id) { return document.getElementById(id)?.value ?? ''; }
function ajusteHuman(v) {
  const n = parseInt(v) || 0;
  const abs = Math.abs(n);
  const d = Math.floor(abs / 1440);
  const h = Math.floor((abs % 1440) / 60);
  const m = abs % 60;
  const parts = [];
  if (d) parts.push(d + 'd');
  if (h || d) parts.push(h + 'h');
  parts.push(String(m).padStart(2,'0') + 'm');
  return n === 0 ? '' : (n > 0 ? '+' : '−') + parts.join(' ');
}
document.addEventListener('DOMContentLoaded', function() {
  const el = document.getElementById('ajuste-he-human');
  const inp = document.getElementById('e-ajuste_he_val');
  if (el && inp) el.textContent = ajusteHuman(inp.value);
});
function chk(id) { return document.getElementById(id)?.checked ? 1 : 0; }

const SIN_LIMITE_FECHA = @json($puedeSinLimiteFecha ?? false);

function validarHorarioFichaje(inicio, fin, pausaIni, pausaFin) {
  const toMin = t => { if (!t) return null; const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const i = toMin(inicio), f = toMin(fin), pi = toMin(pausaIni), pf = toMin(pausaFin);
  if (i !== null && f !== null && f < i)   return 'La salida no puede ser anterior a la entrada';
  if (pi !== null && pf !== null && pf < pi) return 'El fin de pausa no puede ser anterior al inicio de pausa';
  if (pi !== null && i !== null && pi < i)   return 'El inicio de pausa no puede ser anterior a la entrada';
  if (pi !== null && f !== null && pi > f)   return 'El inicio de pausa no puede ser posterior a la salida';
  if (pf !== null && i !== null && pf < i)   return 'El fin de pausa no puede ser anterior a la entrada';
  if (pf !== null && f !== null && pf > f)   return 'El fin de pausa no puede ser posterior a la salida';
  return null;
}

async function guardar() {
  const btn = document.getElementById('btn-save');

  const fechaVal = val('e-fecha_fichaje');
  const hoyMenos2 = (() => { const d = new Date(); d.setDate(d.getDate() - 2); return d.toLocaleDateString('en-CA'); })();
  if (!SIN_LIMITE_FECHA && fechaVal && fechaVal < hoyMenos2) {
    alert('Solo se pueden editar fichajes de los últimos 2 días');
    return;
  }
  const horarioError = validarHorarioFichaje(val('e-hora_inicio'), val('e-hora_fin'), val('e-pausa_inicio'), val('e-pausa_fin'));
  if (horarioError) {
    alert(horarioError);
    return;
  }

  btn.textContent = 'Guardando…'; btn.disabled = true;

  const body = {
    _method:         'PATCH',
    control_user:    val('e-control_user'),
    fecha_fichaje:   val('e-fecha_fichaje'),
    hora_inicio:     val('e-hora_inicio')    || null,
    hora_fin:        val('e-hora_fin')       || null,
    pausa_inicio:    val('e-pausa_inicio')   || null,
    pausa_fin:       val('e-pausa_fin')      || null,
    hora_ini_auto:   val('e-hora_ini_auto')  || null,
    hora_fin_auto:   val('e-hora_fin_auto')  || null,
    pausa_ini_auto:  val('e-pausa_ini_auto') || null,
    pausa_fin_auto:  val('e-pausa_fin_auto') || null,
    festivo:         chk('e-festivo'),
    fuera_de_turno:  chk('e-fuera_de_turno'),
    validado:        chk('e-validado'),
    km:              val('e-km')             || null,
    trayecto:        val('e-trayecto')       || null,
    observacion:     val('e-observacion')    || null,
    ajuste_he:        parseInt(val('e-ajuste_he_val') || '0', 10),
    ajuste_he_motivo: val('e-ajuste_he_motivo') || null,
  };

  const ajusteVal = parseInt(val('e-ajuste_he_val') || '0', 10);
  if (ajusteVal !== 0 && !val('e-ajuste_he_motivo')?.trim()) {
    alert('El motivo del ajuste es obligatorio cuando el ajuste es distinto de cero.');
    btn.textContent = 'Guardar'; btn.disabled = false;
    return;
  }

  try {
    const r = await fetch(PATCH_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!r.ok) {
      let msg = await r.text();
      try { msg = JSON.parse(msg).error || msg; } catch {}
      throw new Error(msg);
    }
    location.reload();
  } catch (e) {
    alert('Error al guardar: ' + e.message);
    btn.textContent = 'Guardar'; btn.disabled = false;
  }
}

async function borrar() {
  const r = await fetch(PATCH_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: JSON.stringify({ _method: 'PATCH', deleted: 1 }),
  });
  if (r.ok) window.location.href = LIST_URL;
  else alert('Error al borrar.');
}

function openModal(id)  { document.getElementById(id).classList.add('open') }
function closeModal(id) { document.getElementById(id).classList.remove('open') }
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open') })
);
</script>

</x-app-layout>
