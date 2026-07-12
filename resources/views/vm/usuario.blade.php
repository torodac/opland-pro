@php
// $tiposAusencia viene del controller
$mesesNombres  = ['Ene'=>1,'Feb'=>2,'Mar'=>3,'Abr'=>4,'May'=>5,'Jun'=>6,'Jul'=>7,'Ago'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dic'=>12];

function estadoContrato($c, $contratos): string {
    $hoy = date('Y-m-d');
    if ($c->fecha_alta > $hoy) return 'Próximo';
    if (!$c->fecha_baja || $c->fecha_baja > $hoy) return 'Activo';
    return 'Finalizado';
}

function varPct($actual, $prev): ?float {
    if (!$prev || $prev->salario_base == 0) return null;
    return round(($actual->salario_base - $prev->salario_base) / $prev->salario_base * 100, 1);
}
// contratos vienen DESC; para var necesitamos el anterior cronológico (mayor fecha_alta menor que la actual)
function prevContrato($c, $contratos) {
    return $contratos
        ->filter(fn($x) => $x->fecha_alta < $c->fecha_alta)
        ->sortByDesc('fecha_alta')
        ->first();
}

$alcanceLabels = ['usuario'=>'Personal','cargo'=>'Cargo','departamento'=>'Departamento'];
$alcanceBg     = ['usuario'=>'#EAF3DE','cargo'=>'#E6F1FB','departamento'=>'#F1EFE8'];
$alcanceColor  = ['usuario'=>'#27500A','cargo'=>'#0C447C','departamento'=>'#5F5E5A'];
$mesesMap      = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

$ausenciasJs = $ausencias->map(fn($a) => [
    'id'          => $a->id,
    'tipo'        => $a->tipo,
    'desde'       => \Illuminate\Support\Carbon::parse($a->fecha_inicio)->format('Y-m-d'),
    'hasta'       => \Illuminate\Support\Carbon::parse($a->fecha_fin)->format('Y-m-d'),
    'anyo_devengo'=> $a->anyo_devengo,
    'comentario'  => $a->comentario,
    'fichero'     => $a->file_fichero ? true : false,
    'dias'        => \Carbon\Carbon::parse($a->fecha_inicio)->diffInDays(\Carbon\Carbon::parse($a->fecha_fin)) + 1,
])->values()->toJson();

// Horas diarias según contrato activo (horas_semana / 5 días)
$contratoActivo = $contratos->first(); // ya vienen en orden desc por fecha_alta
$horasDiarias = ($contratoActivo && $contratoActivo->horas_semana)
    ? round($contratoActivo->horas_semana / 5, 2)
    : 8;

$initials = collect(explode(' ', $usuario->nombre))->take(2)->map(fn($w) => strtoupper($w[0]))->implode('');

$hoy = date('Y-m-d');
$sinVigente = $contratos->isNotEmpty()
    && $contratos->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
    && $contratos->every(fn($c) => $c->fecha_alta <= $hoy);
@endphp

<x-app-layout :breadcrumb="[['label'=>'Usuarios','url'=>route('listado',[$project->slug,'usuarios'])],['label'=>$usuario->nombre,'url'=>route('vm.usuario',[$project->slug,$usuario->id])]]" :project="$project">

<x-slot name="actions">
    <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
        <a href="{{ route('vm.usuario', [$project->slug, $usuario->id]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors"
           title="Ver ficha estándar">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </a>
        <a href="{{ route('ficha.create', [$project->slug, 'vm_usuarios']) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo
        </a>
        <button onclick="enterEditMode()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
            <i class="fa-solid fa-pen-to-square text-sm"></i>
            Editar
        </button>
    </div>
    <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
        <button onclick="openModal('modal-reset')"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            Reset password
        </button>
        <button onclick="openModal('modal-delete')"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            Borrar
        </button>
        <button onclick="cancelEditMode()"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
            Cancelar
        </button>
        <button onclick="guardarUsuario()"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-orange-500 border border-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
            Guardar
        </button>
    </div>
</x-slot>

<style>
.section-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.25rem;margin-bottom:12px;}
.dark .section-card{background:#1a1a1a;border-color:rgba(255,255,255,.08);}
.sec-title{font-weight:500;font-size:15px;margin:0 0 12px;display:flex;align-items:center;gap:8px;}
th{text-align:left;padding:6px 8px;font-size:12px;color:#888;font-weight:500;}
td{padding:8px;font-size:13px;}
.trow{border-bottom:0.5px solid rgba(0,0,0,.06);}
.dark .trow{border-bottom-color:rgba(255,255,255,.06);}
.badge{font-size:11px;padding:2px 8px;border-radius:6px;}
.icon-btn{background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;border-radius:6px;}
.icon-btn:hover{background:rgba(0,0,0,.06);color:#222;}
.icon-btn.danger:hover{background:#FCEBEB;color:#A32D2D;}
.stat-pill{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;border:0.5px solid rgba(0,0,0,.1);cursor:pointer;font-size:12px;color:#888;background:#fff;transition:opacity .15s;}
.dark .stat-pill{background:#1a1a1a;border-color:rgba(255,255,255,.1);}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;}
.cd{height:14px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:8px;color:#888;}
.cd.Vacaciones{background:#e8b800;color:#fff;}
.cd.Baja{background:#7b3f8c;color:#fff;}
.cd.Comp__festivo,.cd.Comp__horas,.cd.Compensaci_n{background:#e83e8c;color:#fff;}
.cd.Asuntos_propios{background:#34c163;color:#fff;}
.cd.Absentismo{background:#dc3545;color:#fff;}
.cd.faded{opacity:.2;}
.cd.empty{opacity:0;pointer-events:none;}
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
.mes-pill{display:inline-flex;align-items:center;justify-content:center;width:36px;height:28px;border-radius:6px;border:0.5px solid rgba(0,0,0,.12);cursor:pointer;font-size:11px;font-weight:500;color:#888;user-select:none;}
.mes-pill.sel{background:#E6F1FB;border-color:#B5D4F4;color:#0C447C;}
</style>

<div style="padding:0 0 3rem;">

  {{-- Cabecera --}}
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:52px;height:52px;border-radius:50%;background:{{ $sinVigente ? '#FCEBEB' : '#E6F1FB' }};display:flex;align-items:center;justify-content:center;font-weight:500;font-size:16px;color:{{ $sinVigente ? '#A32D2D' : '#185FA5' }};">{{ $initials }}</div>
      <div>
        <div style="display:flex;align-items:center;gap:8px;">
          <p style="font-weight:500;font-size:18px;margin:0;">{{ $usuario->nombre }}</p>
          @if($sinVigente)
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;background:#FCEBEB;color:#A32D2D;border:0.5px solid #F7C1C1;white-space:nowrap;">Sin contrato vigente</span>
          @endif
          @if($pushInactivo)
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;background:#FCEBEB;color:#A32D2D;border:0.5px solid #F7C1C1;white-space:nowrap;">Notificaciones app no activas</span>
          @endif
        </div>
        <p style="font-size:13px;color:#888;margin:2px 0 0;">{{ $usuario->departamento }} · {{ $usuario->cargo }}</p>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;justify-content:flex-end;">
      <a href="{{ route('informe-imputaciones', $project->slug) }}?year={{ now()->year }}&month={{ now()->month }}&user_id={{ $usuario->id }}" style="font-size:12px;color:#185FA5;display:flex;align-items:center;gap:3px;text-decoration:none;border:0.5px solid #B5D4F4;border-radius:6px;padding:5px 9px;">
        <i class="ti ti-chart-bar" style="font-size:14px;"></i>
        Informe {{ now()->translatedFormat('M Y') }}
        <i class="ti ti-external-link" style="font-size:12px;"></i>
      </a>

    </div>
  </div>

  {{-- Datos básicos --}}
  {{-- MODO VISTA --}}
  <div id="datos-view" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px;">
    @foreach([['Nombre',$usuario->nombre],['DNI',$usuario->dni],['Mail',$usuario->mail ?? null],['Teléfono',$usuario->telefono],['Departamento',$usuario->departamento],['Cargo',$usuario->cargo],['Rol', collect($roles)->firstWhere('id', $usuario->id_rol ?? null)?->nombre ?? null],['Acceso',$usuario->acceso]] as [$label,$val])
    <div style="background:rgba(0,0,0,.03);border-radius:8px;padding:.8rem;">
      <p style="font-size:11px;color:#888;margin:0 0 3px;">{{ $label }}</p>
      <p style="font-size:13px;font-weight:500;margin:0;">{{ $val ?: '—' }}</p>
    </div>
    @endforeach
  </div>
  {{-- MODO EDICIÓN --}}
  <div id="datos-edit" style="display:none;background:#fff;border:0.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1rem 1.25rem;margin-bottom:12px;">
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
      <div class="form-row"><label class="form-label">Nombre completo</label><input type="text" id="u-nombre" value="{{ $usuario->nombre }}"></div>
      <div class="form-row"><label class="form-label">DNI</label><input type="text" id="u-dni" value="{{ $usuario->dni }}"></div>
      <div class="form-row"><label class="form-label">Email</label><input type="email" id="u-mail" value="{{ $usuario->mail ?? '' }}"></div>
      <div class="form-row"><label class="form-label">Teléfono</label><input type="text" id="u-tel" value="{{ $usuario->telefono }}"></div>
      <div class="form-row"><label class="form-label">Departamento</label>
        <select id="u-dept">
          @foreach($departamentos as $d)<option {{ $usuario->departamento===$d?'selected':'' }}>{{ $d }}</option>@endforeach
        </select>
      </div>
      <div class="form-row"><label class="form-label">Cargo</label>
        <select id="u-cargo">
          @foreach($cargos as $c)<option {{ $usuario->cargo===$c?'selected':'' }}>{{ $c }}</option>@endforeach
        </select>
      </div>
      <div class="form-row"><label class="form-label">Rol</label>
        <select id="u-rol">
          <option value="">— Sin rol —</option>
          @foreach($roles as $r)<option value="{{ $r->id }}" {{ ($usuario->id_rol ?? null)==$r->id?'selected':'' }}>{{ $r->nombre }}</option>@endforeach
        </select>
      </div>
      <div class="form-row"><label class="form-label">Acceso</label>
        <select id="u-acceso">
          @foreach(['APP y web','Solo APP','Solo web','Sin acceso'] as $op)
          <option {{ $usuario->acceso===$op?'selected':'' }}>{{ $op }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- Contratos --}}
  <div class="section-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="sec-title" style="margin:0;"><i class="ti ti-file-text" style="font-size:16px;"></i>Contratos</p>
      <button class="btn" onclick="openContratoModal(null)"><i class="ti ti-plus" style="font-size:13px;vertical-align:-2px;"></i> Nuevo</button>
    </div>
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
      <thead><tr>
        <th style="width:14%;text-align:center;">Inicio</th>
        <th style="width:14%;text-align:center;">Fin</th>
        <th style="width:18%;text-align:center;">Salario</th>
        <th style="width:10%;text-align:center;">Horas</th>
        <th style="width:10%;text-align:center;">Var.</th>
        <th style="width:14%;text-align:center;">Estado</th>
        <th style="width:8%;text-align:center;"></th>
      </tr></thead>
      <tbody>
        @php $contratosArr = $contratos->values(); @endphp
        @foreach($contratosArr as $i => $c)
        @php
          $estado  = estadoContrato($c, $contratos);
          $prev    = prevContrato($c, $contratos);
          $pct     = $prev ? varPct($c, $prev) : null;
        @endphp
        <tr class="trow">
          <td style="text-align:center;">{{ $c->fecha_alta ? \Carbon\Carbon::parse($c->fecha_alta)->format('d/m/Y') : '—' }}</td>
          <td style="text-align:center;color:{{ $c->fecha_baja ? 'inherit' : '#aaa' }}">{{ $c->fecha_baja ? \Carbon\Carbon::parse($c->fecha_baja)->format('d/m/Y') : '—' }}</td>
          <td style="text-align:center;font-weight:500;">{{ number_format($c->salario_base,2,',','.') }} €</td>
          <td style="text-align:center;">{{ $c->horas_semana ? (intval($c->horas_semana) == $c->horas_semana ? intval($c->horas_semana) : $c->horas_semana).'h' : '—' }}</td>
          <td style="text-align:center;font-weight:500;color:{{ $pct !== null ? ($pct>=0?'#0F6E56':'#A32D2D') : 'inherit' }}">
            {{ $pct !== null ? ($pct>=0?'+':'').$pct.'%' : '—' }}
          </td>
          @php
            $estadoBg  = $estado==='Activo' ? '#EAF3DE' : ($estado==='Próximo' ? '#E6F1FB' : '#F1EFE8');
            $estadoCol = $estado==='Activo' ? '#27500A' : ($estado==='Próximo' ? '#0C447C' : '#5F5E5A');
          @endphp
          <td style="text-align:center;"><span class="badge" style="background:{{ $estadoBg }};color:{{ $estadoCol }}">{{ $estado }}</span></td>
          <td style="text-align:center;">
            <button class="icon-btn" onclick="openContratoModal({{ $c->id }},'{{ $c->fecha_alta }}','{{ $c->fecha_baja }}',{{ $c->salario_base }},{{ $c->horas_semana ?? 'null' }})" title="Editar">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @if($contratos->count() > 1)
    <div style="margin-top:14px;border-top:0.5px solid rgba(0,0,0,.06);padding-top:14px;">
      <p style="font-size:11px;color:#888;margin:0 0 6px;">Evolución salarial</p>
      <div style="position:relative;height:130px;"><canvas id="salario-chart"></canvas></div>
    </div>
    @endif
  </div>

  {{-- Bonus --}}
  <div class="section-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="sec-title" style="margin:0;"><i class="ti ti-rosette" style="font-size:16px;"></i>Bonus aplicables</p>
      <button class="btn" onclick="openModal('modal-bonus')"><i class="ti ti-plus" style="font-size:13px;vertical-align:-2px;"></i> Bonus personal</button>
    </div>
    @if($bonus->isEmpty())
      <p style="font-size:13px;color:#aaa;margin:0;">No hay bonus definidos para este usuario.</p>
    @else
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
      <thead><tr>
        <th style="width:34%;">Concepto</th>
        <th style="width:18%;">Origen</th>
        <th style="width:30%;">Meses</th>
        <th style="text-align:right;width:14%;">Importe</th>
        <th style="width:4%;"></th>
      </tr></thead>
      <tbody>
        @foreach($bonus as $b)
        @php
          $mesesArr = array_map('intval', explode(',', $b->meses));
          $mesesStr = implode(', ', array_map(fn($m) => $mesesMap[$m] ?? $m, $mesesArr));
        @endphp
        <tr class="trow">
          <td>{{ $b->descripcion ?: '—' }}</td>
          <td><span class="badge" style="background:{{ $alcanceBg[$b->alcance]??'#F1EFE8' }};color:{{ $alcanceColor[$b->alcance]??'#5F5E5A' }}">{{ $alcanceLabels[$b->alcance]??$b->alcance }}</span></td>
          <td style="color:#888;font-size:12px;">{{ $mesesStr }}</td>
          <td style="text-align:right;font-weight:500;">{{ number_format($b->importe,2,',','.') }} €</td>
          <td style="display:flex;gap:2px;justify-content:flex-end;">
            @if($b->alcance==='usuario')
            <button class="icon-btn" onclick="openBonusModal({{ $b->id }},'{{ addslashes($b->descripcion) }}','{{ $b->meses }}',{{ $b->importe }},'{{ substr($b->fecha_inicio,0,10) }}','{{ $b->fecha_fin ? substr($b->fecha_fin,0,10) : '' }}')" title="Editar"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

  {{-- Nóminas --}}
  <div class="section-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="sec-title" style="margin:0;"><i class="ti ti-receipt" style="font-size:16px;"></i>Nóminas</p>
      <button class="btn" onclick="openNominaModal(null,null,null,null)"><i class="ti ti-plus" style="font-size:13px;vertical-align:-2px;"></i> Registrar mes</button>
    </div>
    @if($nominas->isEmpty())
      <p style="font-size:13px;color:#aaa;margin:0;">Sin registros.</p>
    @else
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
      <thead><tr>
        <th style="width:20%;">Mes</th>
        <th style="text-align:right;width:24%;">Devengado</th>
        <th style="text-align:right;width:24%;">Líquido</th>
        <th style="text-align:right;width:24%;">Coste empresa</th>
        <th style="width:8%;"></th>
      </tr></thead>
      <tbody>
        @foreach($nominas as $n)
        <tr class="trow">
          <td style="font-weight:500;">{{ \Carbon\Carbon::parse($n->mes)->translatedFormat('M Y') }}</td>
          <td style="text-align:right;">{{ number_format($n->devengado,2,',','.') }} €</td>
          <td style="text-align:right;">{{ number_format($n->liquido,2,',','.') }} €</td>
          <td style="text-align:right;font-weight:500;">{{ number_format($n->coste_total,2,',','.') }} €</td>
          <td style="text-align:right;">
            <button class="icon-btn" onclick="openNominaModal('{{ $n->mes }}',{{ $n->devengado }},{{ $n->liquido }},{{ $n->coste_total }})" title="Editar">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

  {{-- Ausencias --}}
  <div class="section-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="sec-title" style="margin:0;"><i class="ti ti-calendar-off" style="font-size:16px;"></i>Ausencias</p>
      <button class="btn" onclick="nuevaAusencia()"><i class="ti ti-plus" style="font-size:13px;vertical-align:-2px;"></i> Nueva</button>
    </div>

    {{-- Stats pills (renderizadas por JS según año seleccionado) --}}
    <div id="stat-pills" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;"></div>

    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
      <button class="btn" style="padding:2px 8px;" onclick="cambiarAnyo(-1)">‹</button>
      <span id="year-label" style="font-size:12px;font-weight:500;min-width:36px;text-align:center;">{{ now()->year }}</span>
      <button class="btn" style="padding:2px 8px;" onclick="cambiarAnyo(1)">›</button>
      <span id="cal-filter-label" style="font-size:11px;color:#aaa;margin-left:4px;"></span>
      <span style="font-size:11px;color:#ccc;margin-left:8px;">— el año aplica también al horario anual</span>
      <span style="font-size:10px;color:#ccc;margin-left:6px;">· Los días excluyen festivos{{ !in_array($usuario->cargo, ['Mantenimiento','Limpiadora']) ? ' y fines de semana' : '' }}</span>
    </div>
    <div id="cal-container" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;"></div>
  </div>

  {{-- Horario anual --}}
  <div class="section-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p class="sec-title" style="margin:0;"><i class="ti ti-calendar-stats" style="font-size:16px;"></i>Horario anual</p>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="display:flex;gap:8px;font-size:11px;flex-wrap:wrap;">
          @foreach(['Trabajo'=>['#DBEAFE','#1E40AF'],'Descanso'=>['#F3F4F6','#6B7280'],'Vacaciones'=>['#FEF3C7','#92400E'],'Baja'=>['#EDE9FE','#5B21B6'],'Compensación'=>['#FCE7F3','#9D174D'],'Asuntos propios'=>['#D1FAE5','#065F46'],'Absentismo'=>['#FEE2E2','#991B1B']] as $tipo=>[$bg,$col])
          <span style="display:flex;align-items:center;gap:3px;">
            <span style="width:10px;height:10px;border-radius:2px;background:{{ $bg }};border:0.5px solid {{ $col }}33;display:inline-block;"></span>
            <span style="color:#888;">{{ $tipo }}</span>
          </span>
          @endforeach
        </div>
      </div>
    </div>
    <div id="horario-grid" style="overflow-x:auto;"></div>
  </div>

  {{-- Ajustes HE --}}
  @if($ajustesHe->isNotEmpty())
  <div class="section-card">
    <p class="sec-title" style="margin:0 0 12px;"><i class="ti ti-adjustments-horizontal" style="font-size:16px;"></i>Ajustes de horas extra</p>
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead>
        <tr style="border-bottom:1px solid #e5e7eb;">
          <th style="text-align:left;padding:4px 8px 6px;color:#999;font-weight:500;">Fecha</th>
          <th style="text-align:left;padding:4px 8px 6px;color:#999;font-weight:500;">Ajuste</th>
          <th style="text-align:left;padding:4px 8px 6px;color:#999;font-weight:500;">Motivo</th>
          <th style="width:32px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($ajustesHe as $aj)
        @php
          $absMin = abs($aj->ajuste_he);
          $d = intdiv($absMin, 1440);
          $h = intdiv($absMin % 1440, 60);
          $m = $absMin % 60;
          $parts = [];
          if ($d) $parts[] = $d . 'd';
          if ($h || $d) $parts[] = $h . 'h';
          $parts[] = str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
          $human = ($aj->ajuste_he > 0 ? '+' : '−') . implode(' ', $parts);
        @endphp
        <tr style="border-bottom:0.5px solid #f3f4f6;">
          <td style="padding:6px 8px;color:#444;">{{ \Carbon\Carbon::parse($aj->fecha_fichaje)->locale('es')->isoFormat('D/MMM/YYYY') }}</td>
          <td style="padding:6px 8px;color:{{ $aj->ajuste_he > 0 ? '#16a34a' : '#dc2626' }};white-space:nowrap;">
            {{ ($aj->ajuste_he > 0 ? '+' : '') . $aj->ajuste_he }} min
            <span style="color:#888;margin-left:4px;">({{ $human }})</span>
          </td>
          <td style="padding:6px 8px;color:#444;">{{ $aj->ajuste_he_motivo ?: '—' }}</td>
          <td style="padding:6px 8px;text-align:center;">
            <a href="{{ route('vm.fichaje_form', [$project->slug, $aj->id]) }}" target="_blank"
               style="color:#6b7280;display:inline-flex;align-items:center;" title="Abrir fichaje">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
              </svg>
            </a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

{{-- MODALES --}}


<div class="modal-overlay" id="modal-contrato">
  <div class="modal">
    <p class="modal-title" id="contrato-title">Nuevo contrato</p>
    <input type="hidden" id="c-id">
    <div class="form-grid2">
      <div class="form-row"><label class="form-label">Fecha de alta *</label><input type="date" id="c-alta" required><p id="c-alta-error" style="font-size:11px;color:#A32D2D;margin:3px 0 0;display:none;"></p></div>
      <div class="form-row" id="c-baja-row"><label class="form-label">Fecha de baja</label><input type="date" id="c-baja"></div>
    </div>
    <div class="form-grid2">
      <div class="form-row"><label class="form-label">Salario bruto (€/año) *</label><input type="number" id="c-salario" min="0" step="50" required></div>
      <div class="form-row"><label class="form-label">Horas/semana *</label><input type="number" id="c-horas" min="0" step="0.5" required></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger-link" id="c-baja-btn" style="display:none;" onclick="darDeBajaContrato()">
        <i class="ti ti-user-minus" style="font-size:13px;vertical-align:-2px;"></i> Dar de baja
      </button>
      <button class="btn" onclick="closeModal('modal-contrato')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarContrato()">Guardar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-bonus">
  <div class="modal">
    <p class="modal-title" id="bonus-title">Nuevo bonus personal</p>
    <input type="hidden" id="b-id">
    <div class="form-row"><label class="form-label">Descripción</label><input type="text" id="b-desc" placeholder="Ej. Incentivo especial"></div>
    <div class="form-row">
      <label class="form-label">Meses en que se cobra</label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;" id="meses-pills">
        @foreach($mesesNombres as $nombre => $num)
        <span class="mes-pill" data-mes="{{ $num }}" onclick="toggleMes(this)">{{ $nombre }}</span>
        @endforeach
      </div>
    </div>
    <div class="form-grid2">
      <div class="form-row"><label class="form-label">Importe (€)</label><input type="number" id="b-importe" min="0" step="50" placeholder="0"></div>
      <div class="form-row"><label class="form-label">Fecha inicio</label><input type="date" id="b-inicio"></div>
    </div>
    <div class="form-row"><label class="form-label">Fecha fin <span style="font-size:10px;">(vacío = indefinido)</span></label><input type="date" id="b-fin"></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-bonus')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarBonus()">Guardar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-ausencia">
  <div class="modal">
    <input type="hidden" id="a-id">
    <p class="modal-title" id="a-title">Nueva ausencia</p>
    <div id="a-error" style="display:none;background:#FCEBEB;color:#A32D2D;border:1px solid #F7C1C1;border-radius:6px;padding:8px 12px;font-size:13px;margin-bottom:12px;"></div>
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
      <button class="btn btn-danger-link" id="a-delete-btn" style="display:none;margin-right:auto;" onclick="eliminarAusencia()">Eliminar</button>
      <button class="btn" onclick="closeModal('modal-ausencia')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarAusencia()">Guardar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-nomina">
  <div class="modal">
    <p class="modal-title">Registrar nómina</p>
    <div class="form-row"><label class="form-label">Mes</label><input type="month" id="n-mes"></div>
    <div class="form-grid2">
      <div class="form-row"><label class="form-label">Devengado (€)</label><input type="number" id="n-devengado" min="0" step="0.01" placeholder="0.00"></div>
      <div class="form-row"><label class="form-label">Líquido (€)</label><input type="number" id="n-liquido" min="0" step="0.01" placeholder="0.00"></div>
    </div>
    <div class="form-row"><label class="form-label">Coste empresa (€)</label><input type="number" id="n-coste" min="0" step="0.01" placeholder="0.00"></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-nomina')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarNomina()">Guardar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-reset">
  <div class="modal" style="width:320px;">
    <p class="modal-title">Resetear contraseña</p>
    <p style="font-size:13px;color:#888;margin:0 0 1rem;">La contraseña de <strong>{{ $usuario->nombre }}</strong> se establecerá a <strong style="font-family:monospace;color:#333;">bienvenido</strong>. El usuario deberá cambiarla en su próximo acceso.</p>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-reset')">Cancelar</button>
      <button class="btn btn-primary" onclick="resetPassword()">Restablecer</button>
    </div>
  </div>
</div>


<div class="modal-overlay" id="modal-delete">
  <div class="modal" style="width:320px;">
    <p class="modal-title">Borrar usuario</p>
    <p style="font-size:13px;color:#888;margin:0 0 1rem;">El usuario <strong>{{ $usuario->nombre }}</strong> quedará marcado como borrado y perderá el acceso. Esta acción es reversible.</p>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-delete')">Cancelar</button>
      <button class="btn" style="background:#FCEBEB;color:#A32D2D;border-color:#F7C1C1;" onclick="eliminarUsuario()">Borrar</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/vm_usuarios/" . $usuario->id) }}';

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

async function ajax(url, method, data) {
    const r = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    return r.json();
}

function enterEditMode() {
    document.getElementById('datos-view').style.display = 'none';
    document.getElementById('datos-edit').style.display = 'block';
    document.getElementById('btn-view-mode').style.display = 'none';
    document.getElementById('btn-edit-mode').style.display = 'flex';
}
function cancelEditMode() {
    document.getElementById('datos-view').style.display = 'grid';
    document.getElementById('datos-edit').style.display = 'none';
    document.getElementById('btn-view-mode').style.display = 'flex';
    document.getElementById('btn-edit-mode').style.display = 'none';
}
function guardarUsuario() {
    ajax(BASE + '/ficha', 'PATCH', {
        nombre:       document.getElementById('u-nombre').value,
        dni:          document.getElementById('u-dni').value,
        mail:         document.getElementById('u-mail').value,
        telefono:     document.getElementById('u-tel').value,
        departamento: document.getElementById('u-dept').value,
        cargo:        document.getElementById('u-cargo').value,
        id_rol:       document.getElementById('u-rol').value || null,
        acceso:       document.getElementById('u-acceso').value,
    }).then(() => location.reload());
}

const CONTRATOS_EXISTENTES = {!! $contratos->map(fn($c) => ['id'=>$c->id,'alta'=>substr($c->fecha_alta??'',0,10),'baja'=>substr($c->fecha_baja??'',0,10)])->values()->toJson() !!};

function openContratoModal(id, alta, baja, salario, horas) {
    if (!id) {
        const sinFin = CONTRATOS_EXISTENTES.filter(c => !c.baja);
        if (sinFin.length > 0) {
            alert('Hay contratos sin fecha de fin. Añade una fecha de fin a los contratos vigentes antes de crear uno nuevo.');
            return;
        }
    }
    document.getElementById('contrato-title').textContent = id ? 'Editar contrato' : 'Nuevo contrato';
    document.getElementById('c-id').value     = id || '';
    document.getElementById('c-alta').value   = alta ? alta.slice(0, 10) : '';
    document.getElementById('c-baja').value   = baja ? baja.slice(0, 10) : '';
    document.getElementById('c-salario').value = salario || '';
    document.getElementById('c-horas').value  = horas || '';
    const hoy = new Date().toISOString().slice(0, 10);
    document.getElementById('c-baja-btn').style.display =
        (id && (!baja || baja.slice(0, 10) >= hoy)) ? 'inline-flex' : 'none';
    document.getElementById('c-baja-row').style.display = id ? 'block' : 'none';
    openModal('modal-contrato');
}

function guardarContrato() {
    const id    = document.getElementById('c-id').value;
    const alta  = document.getElementById('c-alta').value;
    const sal   = document.getElementById('c-salario').value;
    const hor   = document.getElementById('c-horas').value;
    const errEl = document.getElementById('c-alta-error');
    errEl.style.display = 'none';
    if (!alta || !sal || !hor) { alert('Fecha de alta, salario y horas son obligatorios.'); return; }
    // Validar que fecha de fin es posterior a fecha de inicio
    const baja = document.getElementById('c-baja').value;
    if (baja && baja <= alta) {
        errEl.textContent = 'La fecha de fin debe ser posterior a la fecha de inicio.';
        errEl.style.display = 'block';
        return;
    }
    // Validar que la fecha de alta es posterior a la fecha de baja de todos los demás contratos
    const otros = CONTRATOS_EXISTENTES.filter(c => c.id != id);
    const conflicto = otros.find(c => c.baja && alta <= c.baja);
    if (conflicto) {
        errEl.textContent = 'La fecha de inicio debe ser posterior a la fecha de fin del contrato anterior (' + conflicto.baja + ').';
        errEl.style.display = 'block';
        return;
    }
    const data = {
        fecha_alta:   document.getElementById('c-alta').value,
        fecha_baja:   document.getElementById('c-baja').value || null,
        salario_base: document.getElementById('c-salario').value,
        horas_semana: document.getElementById('c-horas').value || null,
    };
    ajax(id ? BASE + '/contratos/' + id : BASE + '/contratos', id ? 'PATCH' : 'POST', data)
        .then(() => location.reload());
}

function darDeBajaContrato() {
    const id  = document.getElementById('c-id').value;
    const hoy = new Date().toISOString().slice(0, 10);
    ajax(BASE + '/contratos/' + id, 'PATCH', {
        fecha_alta:   document.getElementById('c-alta').value,
        fecha_baja:   hoy,
        salario_base: document.getElementById('c-salario').value,
        horas_semana: document.getElementById('c-horas').value || null,
    }).then(() => location.reload());
}

function toggleMes(el) { el.classList.toggle('sel'); }

function openBonusModal(id, desc, mesesStr, importe, inicio, fin) {
    document.getElementById('bonus-title').textContent = id ? 'Editar bonus personal' : 'Nuevo bonus personal';
    document.getElementById('b-id').value      = id || '';
    document.getElementById('b-desc').value    = desc || '';
    document.getElementById('b-importe').value = importe || '';
    document.getElementById('b-inicio').value  = inicio || '';
    document.getElementById('b-fin').value     = fin || '';
    const selMeses = mesesStr ? mesesStr.split(',').map(m => m.trim()) : [];
    document.querySelectorAll('.mes-pill').forEach(p => {
        p.classList.toggle('sel', selMeses.includes(p.dataset.mes));
    });
    openModal('modal-bonus');
}

function guardarBonus() {
    const meses = [...document.querySelectorAll('.mes-pill.sel')].map(p => parseInt(p.dataset.mes));
    if (!meses.length) { alert('Selecciona al menos un mes'); return; }
    const id = document.getElementById('b-id').value;
    const data = {
        descripcion:  document.getElementById('b-desc').value,
        meses,
        importe:      document.getElementById('b-importe').value,
        fecha_inicio: document.getElementById('b-inicio').value,
        fecha_fin:    document.getElementById('b-fin').value || null,
    };
    const url    = id ? BASE + '/bonus/' + id : BASE + '/bonus';
    const method = id ? 'PATCH' : 'POST';
    ajax(url, method, data).then(() => location.reload());
}

function deleteBonus(id, btn) {
    if (!confirm('¿Eliminar este bonus?')) return;
    ajax(BASE + '/bonus/' + id, 'DELETE', {}).then(() => btn.closest('tr').remove());
}

function onTipoAusenciaChange(val) {
    const esBaja = val.toLowerCase().includes('baja');
    document.getElementById('a-baja-aviso').style.display = esBaja ? 'block' : 'none';
    document.getElementById('a-anyo-row').style.display = val === 'Vacaciones' ? 'block' : 'none';
}

function verAusencia(id) {
    const aus = AUSENCIAS.find(a => a.id == id);
    if (!aus) return;

    document.getElementById('a-id').value = id;
    document.getElementById('a-title').textContent = 'Editar ausencia';
    document.getElementById('a-error').style.display = 'none';
    document.getElementById('a-delete-btn').style.display = '';

    // Tipo
    const sel = document.getElementById('a-tipo');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === aus.tipo) { sel.selectedIndex = i; break; }
    }
    onTipoAusenciaChange(aus.tipo);

    document.getElementById('a-inicio').value = aus.desde;
    document.getElementById('a-fin').value = aus.hasta;
    document.getElementById('a-anyo').value = aus.anyo_devengo || aus.desde.substring(0,4);
    document.getElementById('a-comentario').value = aus.comentario || '';

    // Reset fichero
    document.getElementById('a-fichero').value = '';
    const fLabel = aus.fichero
        ? '<span style="color:#166534;font-weight:600;">📎 Ya tiene justificante adjunto</span><br><span style="font-size:11px;color:#b9b9c3;">Selecciona otro para reemplazarlo</span>'
        : 'PDF, JPG o PNG · <span style="color:#7367f0;font-weight:600;text-decoration:underline;">selecciona</span>';
    document.getElementById('a-fichero-label').innerHTML = fLabel;

    openModal('modal-ausencia');
}

function nuevaAusencia() {
    document.getElementById('a-id').value = '';
    document.getElementById('a-title').textContent = 'Nueva ausencia';
    document.getElementById('a-error').style.display = 'none';
    document.getElementById('a-delete-btn').style.display = 'none';
    document.getElementById('a-inicio').value = '';
    document.getElementById('a-fin').value = '';
    document.getElementById('a-comentario').value = '';
    document.getElementById('a-fichero').value = '';
    document.getElementById('a-fichero-label').innerHTML = 'PDF, JPG o PNG · <span style="color:#7367f0;font-weight:600;text-decoration:underline;">selecciona</span>';
    onTipoAusenciaChange(document.getElementById('a-tipo').value);
    openModal('modal-ausencia');
}

function guardarAusencia() {
    const errorEl = document.getElementById('a-error');
    errorEl.style.display = 'none';

    const inicio = document.getElementById('a-inicio').value;
    const fin    = document.getElementById('a-fin').value;
    const ausId  = document.getElementById('a-id').value;

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
    form.append('tipo',         document.getElementById('a-tipo').value);
    form.append('fecha_inicio', inicio);
    form.append('fecha_fin',    fin);
    form.append('anyo_devengo', document.getElementById('a-anyo').value);
    form.append('comentario',   document.getElementById('a-comentario').value);
    if (fichero) form.append('fichero', fichero);

    const url    = ausId ? BASE + '/ausencias/' + ausId : BASE + '/ausencias';
    const method = ausId ? 'PATCH' : 'POST';
    if (ausId) form.append('_method', 'PATCH');

    fetch(url, {
        method: ausId ? 'POST' : 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: form,
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            errorEl.textContent = data.error;
            errorEl.style.display = 'block';
        } else {
            location.reload();
        }
    });
}

function eliminarAusencia() {
    const ausId = document.getElementById('a-id').value;
    if (!ausId || !confirm('¿Eliminar esta ausencia?')) return;
    fetch(BASE + '/ausencias/' + ausId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); } else { location.reload(); }
    });
}

function openNominaModal(mes, dev, liq, coste) {
    document.getElementById('n-mes').value       = mes ? mes.slice(0, 7) : '';
    document.getElementById('n-devengado').value = dev || '';
    document.getElementById('n-liquido').value   = liq || '';
    document.getElementById('n-coste').value     = coste || '';
    openModal('modal-nomina');
}

function guardarNomina() {
    ajax(BASE + '/nominas', 'POST', {
        mes:         document.getElementById('n-mes').value,
        devengado:   document.getElementById('n-devengado').value,
        liquido:     document.getElementById('n-liquido').value,
        coste_total: document.getElementById('n-coste').value,
    }).then(() => location.reload());
}

function resetPassword() {
    fetch('{{ route("ficha.reset-password", [$project->slug, "vm_usuarios", $usuario->id]) }}', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(() => { closeModal('modal-reset'); alert('Contraseña restablecida a «bienvenido».'); });
}

function eliminarUsuario() {
    fetch('{{ route("ficha.borrar", [$project->slug, "usuarios", $usuario->id]) }}', {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    }).then(() => window.location = '{{ route("listado", [$project->slug, "usuarios"]) }}');
}

@if($contratos->count() > 1)
@php
    $cincoAniosAtras = \Carbon\Carbon::now()->subYears(5)->startOfYear();
    $chartData = $contratos->reverse()->map(fn($c) => [
        'x' => \Carbon\Carbon::parse($c->fecha_alta)->format('Y-m-d'),
        'y' => (float) $c->salario_base,
    ])->values();
    $chartMin = $cincoAniosAtras->format('Y-m-d');
@endphp
new Chart(document.getElementById('salario-chart'), {
    type: 'line',
    data: {
        datasets: [{ data: {!! $chartData->toJson() !!}, borderColor: '#378ADD', backgroundColor: 'rgba(55,138,221,0.1)', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#378ADD', tension: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: {
            x: { type: 'time', min: '{{ $chartMin }}', time: { unit: 'year', tooltipFormat: 'dd/MM/yyyy', displayFormats: { year: 'yyyy' } }, adapters: { date: {} }, ticks: { font: { size: 10 }, color: '#aaa', maxRotation: 0 }, grid: { color: 'rgba(0,0,0,.05)' } },
            y: { ticks: { font: { size: 10 }, color: '#aaa', callback: v => v.toLocaleString('es-ES') + '€' }, grid: { color: 'rgba(0,0,0,.05)' } }
        }
    }
});
@endif

const AUSENCIAS  = {!! $ausenciasJs !!};
const FESTIVOS   = new Set({!! $festivos !!});
const EXCL_FINDE = !['Mantenimiento','Limpiadora'].includes('{{ $usuario->cargo }}');
let filtroTipo = null;
let calYear    = {{ now()->year }};

function diasHabiles(desde, hasta) {
    let count = 0;
    const d = new Date(desde);
    const h = new Date(hasta);
    for (let cur = new Date(d); cur <= h; cur.setDate(cur.getDate() + 1)) {
        const dow = cur.getDay(); // 0=dom, 6=sab
        if (EXCL_FINDE && (dow === 0 || dow === 6)) continue;
        const iso = `${cur.getFullYear()}-${String(cur.getMonth()+1).padStart(2,'0')}-${String(cur.getDate()).padStart(2,'0')}`;
        if (FESTIVOS.has(iso)) continue;
        count++;
    }
    return count;
}

const PIL_COLORS = {
    'Vacaciones':    ['#e8b800','#fff'],
    'Baja':          ['#7b3f8c','#fff'],
    'Asuntos propios':['#34c163','#fff'],
    'Comp. festivo': ['#e83e8c','#fff'],
    'Comp. horas':   ['#e83e8c','#fff'],
    'Compensación':  ['#e83e8c','#fff'],
    'Absentismo':    ['#dc3545','#fff'],
};

function renderPills(year) {
    const yearStart = year + '-01-01';
    const yearEnd   = year + '-12-31';

    // clave → { label, dias, muted, tipo }
    const conteos = {};

    for (const a of AUSENCIAS) {
        // El año de disfrute es el año en que cae la ausencia
        const anyoDisfrute = a.desde.slice(0, 4);

        if (a.tipo === 'Vacaciones') {
            const devengo = a.anyo_devengo || anyoDisfrute;
            // En la vista de `year`, mostramos todas las vacaciones con anyo_devengo == year
            if (String(devengo) !== String(year)) continue;
            // Agrupamos por año de disfrute
            const muted = anyoDisfrute != year;
            const key   = 'Vacaciones_' + anyoDisfrute;
            const label = muted ? 'Vacaciones ' + anyoDisfrute : 'Vacaciones';
            if (!conteos[key]) conteos[key] = { label, dias: 0, muted, tipo: 'Vacaciones' };
            conteos[key].dias += diasHabiles(a.desde, a.hasta);
        } else {
            // Para otros tipos: ausencias que caen en el año visualizado
            if (a.hasta < yearStart || a.desde > yearEnd) continue;
            if (!conteos[a.tipo]) conteos[a.tipo] = { label: a.tipo, dias: 0, muted: false, tipo: a.tipo };
            // Recortar al rango del año visualizado
            const desde = a.desde < yearStart ? yearStart : a.desde;
            const hasta = a.hasta > yearEnd   ? yearEnd   : a.hasta;
            conteos[a.tipo].dias += diasHabiles(desde, hasta);
        }
    }

    const cont = document.getElementById('stat-pills');
    cont.innerHTML = '';
    for (const [, info] of Object.entries(conteos)) {
        if (info.dias <= 0) continue;
        const [bg, col] = PIL_COLORS[info.tipo] || ['#F1EFE8','#5F5E5A'];
        const btn = document.createElement('button');
        btn.className = 'stat-pill';
        btn.dataset.tipo = info.tipo;
        btn.style.cssText = `background:${bg};border-color:${col}33;color:${col};${info.muted ? 'opacity:0.4;cursor:default;pointer-events:none;' : ''}`;
        btn.innerHTML = `${info.label}: <strong>${info.dias}</strong> d`;
        if (!info.muted) btn.onclick = function() { toggleFiltro(this); };
        cont.appendChild(btn);
    }
}

function ausenciaTipo(d) {
    const ds = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    for (const a of AUSENCIAS) { if (ds >= a.desde && ds <= a.hasta) return a; }
    return null;
}

const MESES_CAL = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

function renderCal(year) {
    document.getElementById('year-label').textContent = year;
    const cont = document.getElementById('cal-container');
    cont.innerHTML = '';
    for (let m = 0; m < 12; m++) {
        const firstDay = new Date(year, m, 1), lastDay = new Date(year, m + 1, 0);
        let dow = firstDay.getDay(); dow = dow === 0 ? 6 : dow - 1;
        let html = `<div><p style="font-size:10px;font-weight:500;color:#aaa;margin:0 0 3px;">${MESES_CAL[m]}</p><div class="cal-grid">`;
        for (let i = 0; i < dow; i++) html += `<div class="cd empty"></div>`;
        for (let d = 1; d <= lastDay.getDate(); d++) {
            const aus = ausenciaTipo(new Date(year, m, d));
            const tipo = aus ? aus.tipo : null;
            const show = !filtroTipo || tipo === filtroTipo;
            const safeClass = tipo ? tipo.replace(/[\s.\/]/g, '_') : '';
            const devengoDistinto = aus && aus.anyo_devengo && aus.anyo_devengo != year;
            const cls = tipo ? (show ? safeClass : safeClass + ' faded') : '';
            const iso = `${year}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const esFestivo = FESTIVOS.has(iso);
            const styleParts = [];
            if (devengoDistinto) styleParts.push('opacity:0.35');
            if (esFestivo) styleParts.push('color:#dc3545', 'font-weight:700');
            const styleAttr = styleParts.length ? ` style="${styleParts.join(';')}"` : '';
            const clickAttr = aus ? ` onclick="verAusencia(${aus.id})" style="${styleParts.join(';')};cursor:pointer;"` : styleAttr ? ` ${styleAttr}` : '';
            html += aus
                ? `<div class="cd ${cls}" onclick="verAusencia(${aus.id})" style="${styleParts.join(';')};cursor:pointer;">${d}</div>`
                : `<div class="cd ${cls}"${styleAttr}>${d}</div>`;
        }
        html += `</div></div>`;
        cont.innerHTML += html;
    }
}

function toggleFiltro(btn) {
    const tipo = btn.dataset.tipo;
    filtroTipo = filtroTipo === tipo ? null : tipo;
    document.querySelectorAll('.stat-pill').forEach(p => {
        p.style.opacity = (!filtroTipo || p.dataset.tipo === filtroTipo) ? '1' : '0.4';
    });
    document.getElementById('cal-filter-label').textContent =
        filtroTipo ? 'Filtrando: ' + filtroTipo : '';
    renderCal(calYear);
}

function cambiarAnyo(d) {
    calYear += d;
    renderCal(calYear);
    renderPills(calYear);
    renderHorarioGrid(calYear);
}
renderCal(calYear);
renderPills(calYear);

// ── Horario anual ──────────────────────────────────────────────
const DIAS_HE       = {!! json_encode($diasHe) !!};
const IMPUTACIONES  = {!! json_encode($imputacionesPorFecha) !!};
const FICHADOS      = {!! json_encode($fichadosPorFecha) !!};
const FICHAJES = {!! $fichajes->map(fn($f) => [
    'fecha'        => $f->fecha_fichaje,
    'hora_inicio'  => substr($f->hora_inicio, 0, 5),
    'hora_fin'     => substr($f->hora_fin, 0, 5),
    'pausa_inicio' => $f->pausa_inicio ? substr($f->pausa_inicio, 0, 5) : null,
    'pausa_fin'    => $f->pausa_fin    ? substr($f->pausa_fin, 0, 5)    : null,
])->keyBy('fecha')->toJson() !!};
const HORARIOS = {!! $horarios->map(fn($h) => [
    'fecha'      => $h->fecha,
    'tipo'       => $h->tipo,
    'hora_inicio'=> $h->hora_inicio ? substr($h->hora_inicio,0,5) : null,
    'hora_fin'   => $h->hora_fin    ? substr($h->hora_fin,0,5)    : null,
])->values()->toJson() !!};

const HOR_COLORS = {
    turno:       ['#DBEAFE','#1E40AF'],
    descanso:    ['#F3F4F6','#6B7280'],
    vacaciones:  ['#FEF3C7','#92400E'],
    baja:        ['#EDE9FE','#5B21B6'],
    comp_festivo:['#FCE7F3','#9D174D'],
    comp_horas:  ['#FCE7F3','#9D174D'],
    asuntos:     ['#D1FAE5','#065F46'],
    absentismo:  ['#FEE2E2','#991B1B'],
};

const AUS_COLORS = {
    'Vacaciones':    ['#FEF3C7','#92400E'],
    'Baja':          ['#EDE9FE','#5B21B6'],
    'Asuntos propios':['#D1FAE5','#065F46'],
    'Comp. festivo': ['#FCE7F3','#9D174D'],
    'Comp. horas':   ['#FCE7F3','#9D174D'],
    'Compensación':  ['#FCE7F3','#9D174D'],
    'Absentismo':    ['#FEE2E2','#991B1B'],
};

function calcHoras(ini, fin) {
    if (!ini || !fin) return null;
    const [ih, im] = ini.split(':').map(Number);
    const [fh, fm] = fin.split(':').map(Number);
    return ((fh * 60 + fm) - (ih * 60 + im)) / 60;
}

function ausenciaColorParaDia(fecha) {
    for (const a of AUSENCIAS) {
        if (fecha >= a.desde && fecha <= a.hasta) return AUS_COLORS[a.tipo] || null;
    }
    return null;
}

function renderHorarioGrid(year) {
    const horMap = {};
    HORARIOS.forEach(h => { if (h.fecha && h.fecha.startsWith(year + '')) horMap[h.fecha] = h; });

    const MESES_G = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const days = Array.from({length:31},(_,i)=>i+1);

    let html = `<table style="border-collapse:collapse;font-size:10px;min-width:700px;width:100%;">`;
    html += `<thead><tr>
      <th style="padding:3px 6px;text-align:left;color:#888;font-weight:500;white-space:nowrap;min-width:80px;">Mes</th>`;
    days.forEach(d => {
        html += `<th style="padding:2px;text-align:center;color:#aaa;font-weight:400;width:20px;">${d}</th>`;
    });
    html += `<th style="padding:2px 6px;text-align:right;color:#888;font-weight:500;white-space:nowrap;">H. extra</th></tr></thead><tbody>`;

    for (let m = 0; m < 12; m++) {
        const diasEnMes = new Date(year, m + 1, 0).getDate();
        let totalHoras = 0;
        html += `<tr style="border-top:0.5px solid rgba(0,0,0,.05);">
          <td style="padding:3px 6px;color:#666;white-space:nowrap;">${MESES_G[m]}</td>`;
        for (let d = 1; d <= 31; d++) {
            if (d > diasEnMes) {
                html += `<td style="background:rgba(0,0,0,.02);"></td>`;
                continue;
            }
            const fecha = `${year}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const h = horMap[fecha];

            // Color de fondo: horario > ausencia > vacío
            let bg = null, col = null, titleParts = [];
            const ausDelDia = AUSENCIAS.find(a => fecha >= a.desde && fecha <= a.hasta) || null;
            if (h) {
                [bg, col] = HOR_COLORS[h.tipo] || ['#F9FAFB','#374151'];
                titleParts.push(h.tipo);
            } else if (ausDelDia) {
                const ausColor = AUS_COLORS[ausDelDia.tipo] || null;
                if (ausColor) { [bg, col] = ausColor; }
                titleParts.push(ausDelDia.tipo);
            }

            // Flecha de horas extra
            let label = '';
            const heMin   = DIAS_HE[fecha] ?? null;
            const fich    = FICHAJES[fecha] ?? null;
            const impMin  = IMPUTACIONES[fecha] ?? null;

            // Fecha: "L 12/06"
            const fechaObj  = new Date(fecha + 'T00:00:00');
            const diasSem   = ['D','L','M','X','J','V','S'];
            const diaSem    = diasSem[fechaObj.getDay()];
            const diaN      = String(fechaObj.getDate()).padStart(2,'0');
            const mesN      = String(fechaObj.getMonth()+1).padStart(2,'0');
            const fechaLabel = diaSem + ' ' + diaN + '/' + mesN;

            const fmt = m => { const h = Math.floor(Math.abs(m)/60), min = Math.abs(m)%60; return h+'h'+(min?String(min).padStart(2,'0'):''); };
            if (fich) {
                const ficMin = FICHADOS[fecha] ?? null;
                if (ficMin !== null) titleParts.push('Fichado: ' + fich.hora_inicio + '–' + fich.hora_fin + ' (' + fmt(ficMin) + ')');
                if (impMin !== null) titleParts.push('Imputado: ' + fmt(impMin));
            }
            if (heMin !== null) titleParts.push('HE: ' + (heMin >= 0 ? '+' : '') + fmt(heMin));

            if (heMin !== null) {
                totalHoras += heMin / 60;
                if (heMin > 0)      { label = `<span style="color:#28a745;">&#9650;</span>`; }
                else if (heMin < 0) { label = `<span style="color:#dc3545;">&#9660;</span>`; }
            }

            const title = fechaLabel + (titleParts.length ? '\n' + titleParts.join('\n') : '');
            if (bg) {
                html += `<td style="background:${bg};color:${col};text-align:center;border-radius:2px;padding:2px 0;font-size:9px;line-height:1;" title="${title}">${label}</td>`;
            } else if (label) {
                html += `<td style="text-align:center;font-size:9px;line-height:1;" title="${title}">${label}</td>`;
            } else {
                html += `<td></td>`;
            }
        }
        const totalStr = totalHoras !== 0 ? ((Math.abs(totalHoras) % 1 < 0.05 ? Math.round(totalHoras) : parseFloat(totalHoras.toFixed(1)))) + '' : '—';
        html += `<td style="padding:3px 6px;text-align:right;font-weight:500;color:#374151;">${totalStr}</td></tr>`;
    }
    html += `</tbody></table>`;
    document.getElementById('horario-grid').innerHTML = html;
}

renderHorarioGrid(calYear);
</script>

</x-app-layout>
