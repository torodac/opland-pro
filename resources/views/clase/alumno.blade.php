@php
$edad = $alumno->fecha_nacimiento ? \Carbon\Carbon::parse($alumno->fecha_nacimiento)->age : null;
$generoNombre = $alumno->genero ?? null;
$subParts = array_filter([$generoNombre, $edad!==null ? "{$edad} años" : null]);
$sub = implode(' · ', $subParts);
if ($alumno->fecha_nacimiento) {
    $sub .= ($sub?' ':'').'('.\Carbon\Carbon::parse($alumno->fecha_nacimiento)->format('d/m/Y').')';
}
$diasNombres = ['lunes'=>'L','martes'=>'M','miercoles'=>'X','jueves'=>'J','viernes'=>'V','sabado'=>'S','domingo'=>'D'];
$mesesEs = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

<x-app-layout
  :breadcrumb="[['label'=>'Alumnos','url'=>route('listado',[$project->slug,'clientes'])],['label'=>$alumno->nombre,'url'=>'']]"
  :project="$project">

<x-slot name="actions">
  <div id="viewActions" style="display:flex;align-items:center;gap:6px;">
    <a href="{{ route('ficha', [$project->slug, 'clientes', $alumno->id]) }}" class="btn btn-grey" title="Ver ficha estándar">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </a>
    <button onclick="duplicarAlumno()" class="btn btn-grey" title="Duplicar alumno (mismos tutores, ficha vacía)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      <span class="btn-label">Duplicar</span>
    </button>
    <a href="{{ route('ficha.create', [$project->slug, 'clientes']) }}" class="btn btn-grey">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      <span class="btn-label">Nuevo</span>
    </a>
    <button onclick="enterEdit()" class="btn btn-grey">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
      <span class="btn-label">Editar</span>
    </button>
  </div>
  <div id="editActions" style="display:none;align-items:center;gap:6px;">
    <button type="button" onclick="confirmarBorrar()" class="btn" style="color:#A32D2D;border-color:#F7C1C1;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
      Borrar
    </button>
    <button onclick="cancelEdit()" class="btn btn-grey">Cancelar</button>
    <button onclick="guardarAlumno()" class="btn btn-orange">Guardar</button>
  </div>
</x-slot>

<style>
  .btn { font-size:13px;padding:6px 12px;border-radius:6px;cursor:pointer;border:.5px solid rgba(0,0,0,.15);background:#fff;color:#1B1B18;display:inline-flex;align-items:center;gap:5px;line-height:1.2; }
  .btn-grey   { background:rgba(0,0,0,.045);border-color:transparent;color:#888; }
  .btn-primary{ background:#E6F1FB;color:#0C447C;border-color:#B5D4F4;font-weight:600; }
  .btn-orange { background:#F97316;color:#fff;border-color:#F97316;font-weight:600; }
  .btn-red    { background:#FEE2E2;color:#991B1B;border-color:#FCA5A5; }
  .icon-btn   { background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;justify-content:center;border-radius:6px; }
  .icon-btn:hover { background:rgba(0,0,0,.06);color:#222; }
  @media(max-width:480px){.btn-label{display:none;}}

  #al-ficha .head { display:flex;align-items:flex-start;gap:12px;margin-bottom:1.2rem; }
  #al-ficha .avatar-icon { width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  #al-ficha .name { font-weight:600;font-size:17px;margin:0; }
  #al-ficha .sub  { font-size:12.5px;color:#888;margin:2px 0 0; }
  #al-ficha .badge{ font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap; }

  #al-ficha .edit-box { display:none;background:#fff;border:.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1rem 1.1rem;margin-bottom:12px; }
  #al-ficha.editing .head .contact-line { display:none; }
  #al-ficha.editing .edit-box { display:block; }
  #al-ficha .form-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
  @media(max-width:480px){ #al-ficha .form-grid2 { grid-template-columns:1fr; } }
  #al-ficha .form-row { margin-bottom:10px; }
  #al-ficha .form-label { font-size:11.5px;color:#888;margin:0 0 4px;display:block; }
  #al-ficha .form-row input, #al-ficha .form-row select { width:100%;box-sizing:border-box;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 9px;font-size:13px;background:#fff;font-family:inherit; }

  #al-ficha .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.1rem;margin-bottom:12px; }
  #al-ficha .sec-head { display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;flex-wrap:wrap; }
  #al-ficha .sec-title { font-weight:600;font-size:14.5px;margin:0;display:flex;align-items:center;gap:7px; }
  #al-ficha .empty-note { font-size:13px;color:#aaa;margin:0; }

  /* tutores */
  #al-ficha .tutor-row { display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:.5px solid #f3f4f6; }
  #al-ficha .tutor-row:last-child { border-bottom:none; }
  #al-ficha .tutor-avatar { width:32px;height:32px;border-radius:50%;background:#E6F1FB;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#0C447C;flex-shrink:0; }
  #al-ficha .tutor-info { flex:1;min-width:0; }
  #al-ficha .tutor-name { font-size:13px;font-weight:600; }
  #al-ficha .tutor-meta { font-size:11px;color:#888;display:flex;flex-wrap:wrap;gap:6px;margin-top:2px; }
  #al-ficha .tutor-pill { padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700; }
  #al-ficha .tutor-pill.pag { background:#E6F1FB;color:#0C447C; }
  #al-ficha .tutor-pill.rec { background:#EAF3DE;color:#27500A; }
  #al-ficha .tutor-pill.com { background:#FEF3C7;color:#92400E; }

  /* contratos */
  #al-ficha table.ct-table { width:100%;border-collapse:collapse;font-size:13px; }
  #al-ficha table.ct-table th { text-align:left;padding:6px 8px;font-size:11px;color:#888;font-weight:500;border-bottom:.5px solid rgba(0,0,0,.08); }
  #al-ficha table.ct-table td { padding:8px;vertical-align:middle;border-bottom:.5px solid rgba(0,0,0,.04); }
  #al-ficha table.ct-table tr:last-child td { border-bottom:none; }
  #al-ficha table.ct-table tr.ct-row { cursor:pointer; }
  #al-ficha table.ct-table tr.ct-row:hover td { background:rgba(0,0,0,.025); }
  #al-ficha .dia-pill { font-size:10.5px;font-weight:700;padding:2px 5px;border-radius:5px;background:#fff;color:#bbb;border:.5px solid rgba(0,0,0,.1);margin-right:2px;display:inline-block; }
  #al-ficha .dia-pill.on { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  #al-ficha .circulo { display:inline-block;width:10px;height:10px;border-radius:50%;margin:1px 2px;background:#fff;border:1.5px solid #ccc;box-sizing:border-box;vertical-align:middle; }
  #al-ficha a.circulo { text-decoration:none;cursor:pointer; }
  #al-ficha a.circulo:hover { opacity:.75;transform:scale(1.25);transition:transform .12s,opacity .12s; }
  #al-ficha .circulo-cobrado { background:#3D8B5A;border-color:#3D8B5A; }
  #al-ficha .circulo-pendiente { background:#B5432F;border-color:#B5432F; }
  #al-ficha .circulo-sin_generar { background:#fff;border-color:#ccc; }
  #al-ficha .circulo-anulado { background:#fff;border-color:#ccc;position:relative; }
  #al-ficha .circulo-anulado::before { content:'✕';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:9px;color:#999;font-weight:700; }

  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:16px; }
  .modal-overlay.open { display:flex; }
  .modal-overlay .modal { background:#fff;border:.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.4rem;width:460px;max-width:100%;max-height:90vh;overflow-y:auto; }
  .modal-overlay .modal-title { font-weight:600;font-size:15px;margin:0 0 1rem; }
  .modal-overlay .modal-footer { display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;padding-top:.9rem;border-top:.5px solid rgba(0,0,0,.07); }
  .modal-overlay .form-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
  @media(max-width:480px){.modal-overlay .form-grid2{grid-template-columns:1fr;}}
  .modal-overlay .form-row { margin-bottom:10px; }
  .modal-overlay .form-label { font-size:11.5px;color:#888;margin:0 0 4px;display:block; }
  .modal-overlay .form-row input, .modal-overlay .form-row select, .modal-overlay .form-row textarea { width:100%;box-sizing:border-box;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 9px;font-size:13px;background:#fff;font-family:inherit;resize:vertical; }
  .modal-overlay .form-row input[type=checkbox] { width:auto; }
  .modal-overlay .check-row { display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:13px; }
  .day-row { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px; }
  .day-check { flex:0 0 auto;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:.5px solid rgba(0,0,0,.15);border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;color:#888;user-select:none; }
  .day-check.active   { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  .day-check.disabled { opacity:.25;cursor:not-allowed;pointer-events:none;background:#f9f9f9; }
  .hide { display:none !important; }
</style>

<div id="al-ficha">
  <div class="head">
    <div class="avatar-icon" style="background:{{ $activo?'#EAF3DE':'#FCEBEB' }};color:{{ $activo?'#27500A':'#A32D2D' }};">
      <svg width="52" height="52" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12Zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9Z"/></svg>
    </div>
    <div style="min-width:0;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <p class="name">{{ $alumno->nombre }}</p>
        <span class="badge" style="background:{{ $activo?'#EAF3DE':'#FCEBEB' }};color:{{ $activo?'#27500A':'#A32D2D' }};">{{ $activo?'Contrato vigente':'Sin contrato vigente' }}</span>
      </div>
      <p class="sub">{{ $sub ?: '—' }}</p>
      <div class="contact-line" style="display:flex;gap:14px;flex-wrap:wrap;margin-top:6px;">
        @if($alumno->telefono)
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:13px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.06 1.22 2 2 0 012.03 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.08 6.08l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          <a href="tel:{{ $alumno->telefono }}" style="color:inherit;text-decoration:none;">{{ $alumno->telefono }}</a>
        </span>
        @endif
      </div>
    </div>
  </div>

  {{-- Formulario edición --}}
  <form id="editForm" method="POST" action="{{ route('ficha.update', [$project->slug, 'clientes', $alumno->id]) }}">
    @csrf @method('PUT')
    <div class="edit-box">
      <div class="form-grid2">
        <div class="form-row"><label class="form-label">Nombre</label><input type="text" name="nombre" value="{{ $alumno->nombre }}" required></div>
        <div class="form-row"><label class="form-label">Teléfono</label><input type="text" name="telefono" value="{{ $alumno->telefono }}"></div>
        <div class="form-row"><label class="form-label">F. nacimiento</label><input type="date" name="fecha_nacimiento" value="{{ $alumno->fecha_nacimiento }}"></div>
        <div class="form-row"><label class="form-label">Género</label>
          <select name="genero">
            <option value="">—</option>
            @foreach($generos as $g)
            <option value="{{ $g->nombre }}" {{ $alumno->genero===$g->nombre?'selected':'' }}>{{ $g->nombre }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" value="{{ $alumno->email }}"></div>
        <div class="form-row"><label class="form-label">DNI</label><input type="text" name="dni" value="{{ $alumno->dni }}"></div>
        <div class="form-row" style="grid-column:1/-1;"><label class="form-label">Dirección</label><input type="text" name="direccion" value="{{ $alumno->direccion }}"></div>
      </div>
    </div>
  </form>

  {{-- TUTORES --}}
  <div class="section-card">
    <div class="sec-head">
      <p class="sec-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Tutores y familiares
      </p>
      <button class="btn btn-grey" type="button" onclick="abrirModalTutor()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Añadir
      </button>
    </div>
    <div id="tutores-lista">
      @forelse($tutores as $t)
      <div class="tutor-row" data-id="{{ $t->id }}">
        <div class="tutor-avatar">{{ mb_strtoupper(mb_substr($t->nombre,0,1)) }}</div>
        <div class="tutor-info">
          <div class="tutor-name">{{ $t->nombre }}</div>
          <div class="tutor-meta">
            <span style="color:#666;">{{ $t->tipo_tutor ?: '—' }}</span>
            @if($t->es_pagador)<span class="tutor-pill pag">Pagador {{ number_format($t->porcentaje_pago,0) }}%</span>@endif
            @if($t->puede_recoger)<span class="tutor-pill rec">Puede recoger</span>@endif
            @if($t->recibe_comunicaciones)<span class="tutor-pill com">Recibe comunicaciones</span>@endif
          </div>
        </div>
        <div style="margin-left:auto;">
          <button class="icon-btn" type="button" onclick="editarTutor({{ $t->id }},this)" title="Editar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
          </button>
        </div>
      </div>
      @empty
      <p class="empty-note" id="tutores-empty">Sin tutores registrados.</p>
      @endforelse
    </div>
  </div>

  {{-- CONTRATOS --}}
  <div class="section-card">
    <div class="sec-head">
      <p class="sec-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3v5h5M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
        Contratos
      </p>
      <button class="btn btn-grey" type="button" onclick="abrirModalContrato()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Nuevo
      </button>
    </div>

    @if($contratos->isEmpty())
    <p class="empty-note">Sin contratos.</p>
    @else
    <table class="ct-table">
      <thead><tr>
        <th>Grupo</th>
        <th>Fechas</th>
        <th>Días</th>
        <th>Cobros</th>
        <th style="text-align:right">Importe</th>
        <th></th>
      </tr></thead>
      <tbody>
      @foreach($contratos as $ct)
      @php
        $hoy = now()->toDateString();
        $vigente = $ct->fecha_inicio <= $hoy && (!$ct->fecha_fin || $ct->fecha_fin >= $hoy);
      @endphp
      <tr class="ct-row" style="{{ !$vigente?'opacity:.45;':'' }}">
        <td><strong>{{ $ct->grupo_nombre ?? '—' }}</strong></td>
        <td style="font-size:12px;color:#666;">
          Desde {{ \Carbon\Carbon::parse($ct->fecha_inicio)->format('d/m/Y') }}
          @if($ct->fecha_fin) hasta {{ \Carbon\Carbon::parse($ct->fecha_fin)->format('d/m/Y') }} @endif
        </td>
        <td>
          @foreach(['lunes'=>'L','martes'=>'M','miercoles'=>'X','jueves'=>'J','viernes'=>'V','sabado'=>'S','domingo'=>'D'] as $dia=>$letra)
          <span class="dia-pill {{ $ct->$dia?'on':'' }}">{{ $letra }}</span>
          @endforeach
        </td>
        <td>
          @foreach($ct->mesesCobro as $mc)
          @php $est = $mc['estado']; @endphp
          @if($mc['cobro_id'])
          <a href="{{ route('ficha', [$project->slug, 'cobros', $mc['cobro_id']]) }}" class="circulo circulo-{{ $est }}" title="{{ $mc['mes'] }} – {{ ucfirst($est) }}{{ $mc['importe']!==null?' – '.number_format($mc['importe'],2,',','.').' €':'' }}{{ $mc['pagador']?' ('.$mc['pagador'].')':'' }}"></a>
          @else
          <span class="circulo circulo-{{ $est }}" title="{{ $mc['mes'] }}"></span>
          @endif
          @endforeach
        </td>
        <td style="text-align:right;font-weight:600;">{{ number_format($ct->importe,0,',','.') }} €/mes</td>
        <td style="text-align:right;">
          <button type="button" class="icon-btn" title="Editar contrato"
            onclick="editarContrato(this)"
            data-id="{{ $ct->id }}"
            data-grupo="{{ $ct->id_grupo }}"
            data-inicio="{{ $ct->fecha_inicio }}"
            data-fin="{{ $ct->fecha_fin ?? '' }}"
            data-importe="{{ $ct->importe }}"
            data-desc="{{ e($ct->descripcion ?? '') }}"
            data-lunes="{{ $ct->lunes?1:0 }}" data-martes="{{ $ct->martes?1:0 }}" data-miercoles="{{ $ct->miercoles?1:0 }}"
            data-jueves="{{ $ct->jueves?1:0 }}" data-viernes="{{ $ct->viernes?1:0 }}" data-sabado="{{ $ct->sabado?1:0 }}" data-domingo="{{ $ct->domingo?1:0 }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
          </button>
        </td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>

</div>

{{-- MODAL TUTOR --}}
<div class="modal-overlay" id="modalTutor" onclick="if(event.target===this)cerrarModalTutor()">
<div class="modal">
  <p class="modal-title" id="modalTutorTitulo">Añadir tutor</p>
  <input type="hidden" id="tutorId" value="">
  <div id="tutBuscadorWrap" style="margin-bottom:12px;">
    <label class="form-label" style="font-size:11px;color:#888;margin-bottom:4px;display:block;">Buscar tutor existente</label>
    <div style="position:relative;">
      <input type="text" id="tutBuscar" placeholder="Escribe el nombre…" autocomplete="off"
             style="width:100%;box-sizing:border-box;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 9px 7px 32px;font-size:13px;background:#fff;font-family:inherit;">
      <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#9ca3af;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>
    <div id="tutBuscarResultados" style="display:none;border:.5px solid rgba(0,0,0,.12);border-radius:6px;margin-top:3px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:200px;overflow-y:auto;z-index:10;position:relative;"></div>
    <p style="font-size:10.5px;color:#9ca3af;margin:4px 0 0;">Selecciona uno para prellenar los campos o rellena manualmente.</p>
  </div>
  <hr style="border:none;border-top:.5px solid rgba(0,0,0,.08);margin:0 0 12px;">
  <div class="form-grid2">
    <div class="form-row" style="grid-column:1/-1;"><label class="form-label">Nombre *</label><input type="text" id="tutNombre"></div>
    <div class="form-row"><label class="form-label">Tipo</label>
      <select id="tutTipo">
        <option value="Padre">Padre</option><option value="Madre">Madre</option>
        <option value="Abuelo/a">Abuelo/a</option><option value="Otro parentesco">Otro parentesco</option>
        <option value="Otro">Otro</option>
      </select>
    </div>
    <div class="form-row"><label class="form-label">Teléfono</label><input type="text" id="tutTel"></div>
    <div class="form-row"><label class="form-label">Email</label><input type="email" id="tutEmail"></div>
  </div>
  <div class="check-row"><input type="checkbox" id="tutPagador" onchange="togglePct()"> <label for="tutPagador">Es pagador</label></div>
  <div class="form-row" id="pctRow" style="display:none;"><label class="form-label">% de pago</label><input type="number" id="tutPct" min="0" max="100" step="1" value="0"></div>
  <div class="check-row"><input type="checkbox" id="tutRecoger"> <label for="tutRecoger">Puede recoger al alumno</label></div>
  <div class="check-row"><input type="checkbox" id="tutComunicaciones" checked> <label for="tutComunicaciones">Recibe comunicaciones</label></div>
  <div id="pct-warning" style="display:none;font-size:11px;color:#e97316;margin-top:4px;padding:6px 10px;background:#FFF7ED;border-radius:6px;border-left:3px solid #F97316;"></div>
  <div class="modal-footer" style="justify-content:space-between;">
    <div><button type="button" class="btn hide" id="btnBorrarTutor" style="color:#A32D2D;border-color:#F7C1C1;" onclick="borrarTutorModal()">Borrar</button></div>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-grey" onclick="cerrarModalTutor()">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="guardarTutorModal()">Guardar</button>
    </div>
  </div>
</div>
</div>

{{-- MODAL CONTRATO --}}
<div class="modal-overlay" id="modalContrato" onclick="if(event.target===this)cerrarModalContrato()">
<div class="modal">
  <p class="modal-title" id="ctModalTitle">Nuevo contrato</p>
  <div class="form-row"><label class="form-label">Grupo *</label>
    <select id="ctGrupo" onchange="actualizarDiasGrupo()">
      @foreach($grupos as $g)
      <option value="{{ $g->id }}"
        data-lunes="{{ $g->lunes?1:0 }}" data-martes="{{ $g->martes?1:0 }}" data-miercoles="{{ $g->miercoles?1:0 }}"
        data-jueves="{{ $g->jueves?1:0 }}" data-viernes="{{ $g->viernes?1:0 }}" data-sabado="{{ $g->sabado?1:0 }}" data-domingo="{{ $g->domingo?1:0 }}">
        {{ $g->nombre }}
      </option>
      @endforeach
    </select>
  </div>
  <div class="form-grid2">
    <div class="form-row"><label class="form-label">Fecha inicio *</label><input type="date" id="ctInicio" value="{{ now()->format('Y-m-d') }}"></div>
    <div class="form-row"><label class="form-label">Fecha fin</label><input type="date" id="ctFin"></div>
  </div>
  <div class="form-row"><label class="form-label">Días activos en este contrato</label>
    <div class="day-row" id="ctDias">
      @foreach(['lunes'=>'L','martes'=>'M','miercoles'=>'X','jueves'=>'J','viernes'=>'V','sabado'=>'S','domingo'=>'D'] as $dia=>$letra)
      <div class="day-check" id="dct-{{ $dia }}" data-dia="{{ $dia }}" onclick="toggleDiaCt('{{ $dia }}')">{{ $letra }}</div>
      @endforeach
    </div>
  </div>
  <div class="form-row"><label class="form-label">Importe mensual (€) *</label><input type="number" id="ctImporte" step="0.01" min="0" placeholder="0,00"></div>
  <div class="form-row"><label class="form-label">Descripción</label><textarea id="ctDesc" rows="2"></textarea></div>
  <div class="modal-footer" style="justify-content:space-between;">
    <div><button type="button" class="btn hide" id="btnBorrarContrato" style="color:#A32D2D;border-color:#F7C1C1;" onclick="borrarContratoModal()">Borrar</button></div>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-grey" onclick="cerrarModalContrato()">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="guardarContratoModal()">Guardar</button>
    </div>
  </div>
</div>
</div>

<script>
const TOKEN       = "{{ csrf_token() }}";
const ALUMNO_ID   = {{ $alumno->id }};
const URL_TUTOR   = "{{ route('clase.tutores.store', $project->slug) }}";
const URL_CONTRATO = "{{ route('clase.contratos.store', [$project->slug, $alumno->id]) }}";
const URL_BORRAR_AL = "{{ route('ficha.borrar', [$project->slug, 'clientes', $alumno->id]) }}";
const URL_CONTRATO_UPDATE_TPL = "{{ route('clase.contratos.update', [$project->slug, '__ID__']) }}";
const URL_CONTRATO_BORRAR_TPL = "{{ route('clase.contratos.borrar', [$project->slug, '__ID__']) }}";
let editingContratoId = null;

// ── Edición alumno ────────────────────────────────────────────────────────
function enterEdit() {
    document.getElementById('al-ficha').classList.add('editing');
    document.getElementById('viewActions').style.display = 'none';
    document.getElementById('editActions').style.display = 'flex';
}
function cancelEdit() {
    document.getElementById('al-ficha').classList.remove('editing');
    document.getElementById('viewActions').style.display = 'flex';
    document.getElementById('editActions').style.display = 'none';
}
function guardarAlumno() { document.getElementById('editForm').submit(); }
function confirmarBorrar() {
    if (!confirm('¿Seguro que quieres borrar este alumno?')) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.action = URL_BORRAR_AL;
    f.innerHTML = `<input name="_token" value="${TOKEN}"><input name="_method" value="DELETE">`;
    document.body.append(f); f.submit();
}

// ── Modal tutor ───────────────────────────────────────────────────────────
let editingTutorId = null;
function abrirModalTutor(id, data) {
    editingTutorId = id || null;
    document.getElementById('modalTutorTitulo').textContent = id ? 'Editar tutor' : 'Añadir tutor';
    document.getElementById('tutorId').value = id || '';
    document.getElementById('tutBuscar').value = '';
    document.getElementById('tutBuscarResultados').style.display = 'none';
    // Ocultar buscador en modo edición (ya tiene datos)
    document.getElementById('tutBuscadorWrap').style.display = id ? 'none' : 'block';
    document.getElementById('tutBuscadorWrap').nextElementSibling.style.display = id ? 'none' : 'block';
    document.getElementById('tutNombre').value = data?.nombre || '';
    document.getElementById('tutTipo').value  = data?.tipo_tutor || 'Padre';
    document.getElementById('tutTel').value   = data?.telefono || '';
    document.getElementById('tutEmail').value = data?.email || '';
    document.getElementById('tutPagador').checked = !!(data?.es_pagador);
    document.getElementById('tutPct').value = data?.porcentaje_pago || 0;
    document.getElementById('tutRecoger').checked = data?.puede_recoger !== undefined ? !!data.puede_recoger : false;
    document.getElementById('tutComunicaciones').checked = data?.recibe_comunicaciones !== undefined ? !!data.recibe_comunicaciones : true;
    togglePct();
    document.getElementById('btnBorrarTutor').classList.toggle('hide', !id);
    document.getElementById('pct-warning').style.display = 'none';
    document.getElementById('modalTutor').classList.add('open');
    setTimeout(() => document.getElementById('tutNombre').focus(), 50);
}
function cerrarModalTutor() { document.getElementById('modalTutor').classList.remove('open'); }
function togglePct() {
    document.getElementById('pctRow').style.display = document.getElementById('tutPagador').checked ? 'block' : 'none';
}

// Validación email al marcar recibe_comunicaciones
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('tutComunicaciones').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('tutEmail').focus();
            document.getElementById('tutEmail').setAttribute('required', 'required');
        } else {
            document.getElementById('tutEmail').removeAttribute('required');
        }
    });
});

// Búsqueda tutores existentes
const URL_BUSCAR_TUTORES = "{{ route('clase.tutores.buscar', $project->slug) }}";
let buscarTimer = null;
document.addEventListener('DOMContentLoaded', function() {
    const inp = document.getElementById('tutBuscar');
    if (!inp) return;
    inp.addEventListener('input', function() {
        clearTimeout(buscarTimer);
        const q = this.value.trim();
        const res = document.getElementById('tutBuscarResultados');
        if (q.length < 2) { res.style.display = 'none'; return; }
        buscarTimer = setTimeout(async () => {
            const r = await fetch(URL_BUSCAR_TUTORES + '?q=' + encodeURIComponent(q));
            const data = await r.json();
            if (!data.length) { res.style.display = 'none'; return; }
            res.innerHTML = data.map(t => `
                <div onclick="seleccionarTutorExistente(${JSON.stringify(t).replace(/"/g, '&quot;')})" 
                     style="padding:8px 12px;cursor:pointer;border-bottom:.5px solid #f3f4f6;font-size:13px;" 
                     onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <strong>${t.nombre}</strong>
                    <span style="color:#9ca3af;font-size:11px;margin-left:6px;">${t.tipo_tutor||''}</span>
                    ${t.email ? `<span style="color:#9ca3af;font-size:11px;display:block;">${t.email}</span>` : ''}
                </div>`).join('');
            res.style.display = 'block';
        }, 250);
    });
    document.addEventListener('click', e => {
        if (!inp.contains(e.target)) res.style.display = 'none';
    });
});

function seleccionarTutorExistente(t) {
    document.getElementById('tutNombre').value    = t.nombre || '';
    document.getElementById('tutTipo').value     = t.tipo_tutor || 'Padre';
    document.getElementById('tutTel').value      = t.telefono || '';
    document.getElementById('tutEmail').value    = t.email || '';
    document.getElementById('tutPagador').checked           = !!t.es_pagador;
    document.getElementById('tutPct').value                 = t.porcentaje_pago || 0;
    document.getElementById('tutRecoger').checked           = !!t.puede_recoger;
    document.getElementById('tutComunicaciones').checked    = !!t.recibe_comunicaciones;
    togglePct();
    document.getElementById('tutBuscar').value = '';
    document.getElementById('tutBuscarResultados').style.display = 'none';
    document.getElementById('tutNombre').focus();
}

async function duplicarAlumno() {
    if (!confirm('¿Crear una ficha nueva (vacía) con los mismos tutores que este alumno?')) return;
    const url = "{{ route('clase.alumnos.duplicar', [$project->slug, $alumno->id]) }}";
    const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': TOKEN } });
    const data = await r.json();
    if (data.ok) window.location.href = data.url;
}
async function guardarTutorModal() {
    const nombre = document.getElementById('tutNombre').value.trim();
    if (!nombre) { alert('El nombre es obligatorio.'); return; }
    const recibe = document.getElementById('tutComunicaciones').checked;
    const email  = document.getElementById('tutEmail').value.trim();
    if (recibe && !email) {
        document.getElementById('pct-warning').textContent = 'El email es obligatorio si el tutor recibe comunicaciones.';
        document.getElementById('pct-warning').style.display = 'block';
        document.getElementById('tutEmail').focus();
        return;
    }
    document.getElementById('pct-warning').style.display = 'none';
    const payload = {
        id_alumno: ALUMNO_ID,
        nombre,
        tipo_tutor:             document.getElementById('tutTipo').value,
        telefono:               document.getElementById('tutTel').value,
        email:                  document.getElementById('tutEmail').value,
        es_pagador:             document.getElementById('tutPagador').checked ? 1 : 0,
        porcentaje_pago:        parseFloat(document.getElementById('tutPct').value || 0),
        puede_recoger:          document.getElementById('tutRecoger').checked ? 1 : 0,
        recibe_comunicaciones:  document.getElementById('tutComunicaciones').checked ? 1 : 0,
    };
    let url = URL_TUTOR, method = 'POST';
    if (editingTutorId) {
        url = URL_TUTOR.replace('/store', `/alumnos/${editingTutorId}`);
        payload._method = 'PUT';
    }
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify(payload) });
    const data = await r.json();
    if (data.ok) { location.reload(); return; }
    if (data.error) {
        document.getElementById('pct-warning').textContent = data.error;
        document.getElementById('pct-warning').style.display = 'block';
    }
}
function editarTutor(id, btn) {
    const row = btn.closest('.tutor-row');
    const name = row.querySelector('.tutor-name').textContent.trim();
    // Reopen with stored data (simpler: just reload via fetch)
    fetch(`{{ route('clase.alumnos_form', [$project->slug, '__ID__']) }}`.replace('__ID__', ALUMNO_ID))
        .then(() => {});
    // Just pass what we know from the DOM
    abrirModalTutor(id, {
        nombre: name,
        tipo_tutor: row.querySelector('.tutor-meta span')?.textContent?.trim() || '',
        es_pagador: !!row.querySelector('.tutor-pill.pag'),
        porcentaje_pago: parseInt(row.querySelector('.tutor-pill.pag')?.textContent?.match(/\d+/)?.[0] || 0),
        puede_recoger: !!row.querySelector('.tutor-pill.rec'),
        recibe_comunicaciones: !!row.querySelector('.tutor-pill.com'),
    });
}
async function borrarTutor(id, btn) {
    if (!confirm('¿Borrar este tutor?')) return;
    const url = URL_TUTOR.replace('/store', `/alumnos/${id}/borrar`);
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify({}) });
    if ((await r.json()).ok) btn.closest('.tutor-row').remove();
}
async function borrarTutorModal() {
    if (!editingTutorId || !confirm('¿Borrar este tutor?')) return;
    const url = URL_TUTOR.replace('/store', `/alumnos/${editingTutorId}/borrar`);
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify({}) });
    if ((await r.json()).ok) location.reload();
}

// ── Modal contrato ────────────────────────────────────────────────────────
let diasActivosCt = {};
function abrirModalContrato() {
    editingContratoId = null;
    document.getElementById('ctModalTitle').textContent = 'Nuevo contrato';
    document.getElementById('btnBorrarContrato').classList.add('hide');
    document.getElementById('ctInicio').value = '';
    document.getElementById('ctFin').value = '';
    document.getElementById('ctImporte').value = '';
    document.getElementById('ctDesc').value = '';
    actualizarDiasGrupo();
    document.getElementById('modalContrato').classList.add('open');
}
function editarContrato(btn) {
    const d = btn.dataset;
    editingContratoId = d.id;
    document.getElementById('ctModalTitle').textContent = 'Editar contrato';
    document.getElementById('btnBorrarContrato').classList.remove('hide');
    document.getElementById('ctGrupo').value = d.grupo;
    actualizarDiasGrupo();
    const dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    dias.forEach(dia => {
        const el = document.getElementById('dct-' + dia);
        if (!el.classList.contains('disabled')) {
            const on = d[dia] === '1';
            diasActivosCt[dia] = on;
            el.classList.toggle('active', on);
        }
    });
    document.getElementById('ctInicio').value = d.inicio;
    document.getElementById('ctFin').value = d.fin;
    document.getElementById('ctImporte').value = d.importe;
    document.getElementById('ctDesc').value = d.desc;
    document.getElementById('modalContrato').classList.add('open');
}
function cerrarModalContrato() { document.getElementById('modalContrato').classList.remove('open'); }
function actualizarDiasGrupo() {
    const sel = document.getElementById('ctGrupo');
    const opt = sel.options[sel.selectedIndex];
    const dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    dias.forEach(d => {
        const el = document.getElementById('dct-' + d);
        const disponible = opt.dataset[d] === '1';
        el.classList.toggle('disabled', !disponible);
        el.classList.toggle('active', disponible);
        diasActivosCt[d] = disponible;
    });
}
function toggleDiaCt(dia) {
    const el = document.getElementById('dct-' + dia);
    if (el.classList.contains('disabled')) return;
    diasActivosCt[dia] = !diasActivosCt[dia];
    el.classList.toggle('active', diasActivosCt[dia]);
}
async function guardarContratoModal() {
    const inicio = document.getElementById('ctInicio').value;
    const imp    = document.getElementById('ctImporte').value;
    if (!inicio || !imp) { alert('Fecha inicio e importe son obligatorios.'); return; }
    const payload = {
        id_grupo:    document.getElementById('ctGrupo').value,
        fecha_inicio: inicio,
        fecha_fin:   document.getElementById('ctFin').value || null,
        importe:     parseFloat(imp),
        descripcion: document.getElementById('ctDesc').value,
        ...Object.fromEntries(Object.entries(diasActivosCt).map(([k,v]) => [k, v ? 1 : 0]))
    };
    const url    = editingContratoId ? URL_CONTRATO_UPDATE_TPL.replace('__ID__', editingContratoId) : URL_CONTRATO;
    const method = editingContratoId ? 'PUT' : 'POST';
    const r = await fetch(url, { method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify(payload) });
    if ((await r.json()).ok) location.reload();
}
async function borrarContratoModal() {
    if (!editingContratoId || !confirm('¿Borrar este contrato? (No se borrarán los cobros asociados)')) return;
    const url = URL_CONTRATO_BORRAR_TPL.replace('__ID__', editingContratoId);
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify({_method:'DELETE'}) });
    if ((await r.json()).ok) location.reload();
}
async function borrarContrato(id, btn) {
    if (!confirm('¿Borrar este contrato? (No se borrarán los cobros asociados)')) return;
    const url = "{{ route('clase.contratos.borrar', [$project->slug, '__ID__']) }}".replace('__ID__', id);
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN}, body:JSON.stringify({_method:'DELETE'}) });
    if ((await r.json()).ok) location.reload();
}

// inicializar días del primer grupo
window.addEventListener('DOMContentLoaded', actualizarDiasGrupo);
</script>
</x-app-layout>
