@php
$edad = $cliente->fecha_nacimiento ? \Carbon\Carbon::parse($cliente->fecha_nacimiento)->age : null;
$generoNombre = $cliente->id_genero ? ($generos->firstWhere('id', $cliente->id_genero)?->nombre) : null;
$subPartes = array_filter([
    $generoNombre,
    $edad !== null ? "{$edad} años" : null,
]);
$sub = implode(' · ', $subPartes);
if ($cliente->fecha_nacimiento) {
    $sub .= ($sub ? ' ' : '') . '(' . \Carbon\Carbon::parse($cliente->fecha_nacimiento)->format('d/m/Y') . ')';
}

$mesesCortos = ['01'=>'ene','02'=>'feb','03'=>'mar','04'=>'abr','05'=>'may','06'=>'jun','07'=>'jul','08'=>'ago','09'=>'sep','10'=>'oct','11'=>'nov','12'=>'dic'];
$fechaCorta = function ($fecha) use ($mesesCortos) {
    $d = \Carbon\Carbon::parse($fecha);
    return $mesesCortos[$d->format('m')] . "'" . $d->format('y');
};

// Calculado aparte (no inline dentro de @json en el <script>): el compilador de Blade trunca
// mal la expresión route('ficha.borrar', [$project->slug, 'contratos', '__ID__']) cuando se
// escribe directamente dentro de @json(...) -- deja el array sin cerrar a partir de la 2a
// cadena literal consecutiva. Precalcularla aquí evita el problema.
$urlContratoBorrarTpl = route('ficha.borrar', [$project->slug, 'contratos', '__ID__']);
$urlContratoArchivarTpl = route('ficha.archive', [$project->slug, 'contratos', '__ID__']);
@endphp

<x-app-layout :breadcrumb="[['label'=>'Clientes','url'=>route('listado',[$project->slug,'clientes'])],['label'=>$cliente->nombre,'url'=>route('nf.clientes_form',[$project->slug,$cliente->id])]]" :project="$project">

<x-slot name="actions">
    <div id="viewActions" style="display:flex;align-items:center;gap:6px;">
        <a href="{{ route('ficha', [$project->slug, 'clientes', $cliente->id]) }}"
           class="btn btn-grey" title="Ver ficha estándar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </a>
        <a href="{{ route('ficha.create', [$project->slug, 'clientes']) }}" class="btn btn-grey" title="Nuevo">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="btn-label">Nuevo</span>
        </a>
        <button onclick="enterEdit()" class="btn btn-grey" title="Editar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            <span class="btn-label">Editar</span>
        </button>
    </div>
    <div id="editActions" style="display:none;align-items:center;gap:6px;">
        <button type="button" onclick="confirmarBorrarCliente()"
                class="btn" style="color:#A32D2D;border-color:#F7C1C1;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            Borrar
        </button>
        <button onclick="cancelEdit()" class="btn btn-grey">Cancelar</button>
        <button onclick="guardarCliente()" class="btn btn-orange">Guardar</button>
    </div>
</x-slot>

<style>
  /* .btn/.icon-btn se usan tanto dentro de #nf-ficha (tabla, edit-box) como en el header de
     acciones del layout (#viewActions/#editActions), que es hermano de #nf-ficha en el DOM
     (ver x-slot="actions" -> <header>, separado de <main> donde vive #nf-ficha) -> sin acotar. */
  .btn { font-size:13px;padding:6px 12px;border-radius:6px;cursor:pointer;border:.5px solid rgba(0,0,0,.15);background:#fff;color:#1B1B18;display:inline-flex;align-items:center;gap:5px;line-height:1.2; }
  .btn-grey { background:rgba(0,0,0,.045);border-color:transparent;color:#888; }
  .btn-primary { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4;font-weight:600; }
  .btn-orange { background:#F97316;color:#fff;border-color:#F97316;font-weight:600; }
  .icon-btn { background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;justify-content:center;border-radius:6px; }
  .icon-btn:hover { background:rgba(0,0,0,.06);color:#222; }

  #nf-ficha .head { display:flex;align-items:flex-start;gap:12px;margin-bottom:1.2rem; }
  #nf-ficha .avatar-wrap { position:relative;width:96px;height:96px;flex-shrink:0; }
  #nf-ficha .avatar-photo { width:96px;height:96px;border-radius:50%;object-fit:cover;display:block; }
  #nf-ficha .avatar-icon { width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center; }
  #nf-ficha .avatar-edit-btn { position:absolute;bottom:-4px;right:-4px;width:32px;height:32px;border-radius:50%;background:#fff;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#555;padding:0; }
  #nf-ficha .avatar-edit-btn:hover { color:#1B1B18;background:#f5f5f4; }
  /* iOS/Safari no abre el selector de fichero con .click() si el <input type="file"> (o un
     ancestro) tiene display:none -- hay que mantenerlo "visible" para el navegador aunque sea
     invisible para el usuario (bug real detectado 2026-08-13, probado en iPhone). */
  #nf-ficha .visually-hidden { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
  #nf-ficha .name-row { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
  #nf-ficha .name { font-weight:600;font-size:17px;margin:0; }
  #nf-ficha .sub { font-size:12.5px;color:#888;margin:2px 0 0; }
  #nf-ficha .badge { font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap; }

  #nf-ficha .contact-line { display:flex;gap:14px;flex-wrap:wrap;margin-top:6px; }
  #nf-ficha .contact-item { display:inline-flex;align-items:center;gap:5px;font-size:13px;color:#333; }
  #nf-ficha .contact-item svg { color:#888;flex-shrink:0; }

  #nf-ficha .edit-box { display:none;background:#fff;border:.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1rem 1.1rem;margin-bottom:12px; }
  #nf-ficha.editing .head .contact-line { display:none; }
  #nf-ficha.editing .edit-box { display:block; }
  #nf-ficha .form-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
  @media (max-width:480px) { #nf-ficha .form-grid2 { grid-template-columns:1fr; } }
  #nf-ficha .form-row { margin-bottom:10px; }
  #nf-ficha .form-label { font-size:11.5px;color:#888;margin:0 0 4px;display:block; }
  #nf-ficha .form-row input, #nf-ficha .form-row select { width:100%;box-sizing:border-box;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 9px;font-size:13px;background:#fff;font-family:inherit; }

  #nf-ficha .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.1rem;margin-bottom:12px; }
  #nf-ficha .sec-head { display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;flex-wrap:wrap; }
  #nf-ficha .sec-title { font-weight:600;font-size:14.5px;margin:0;display:flex;align-items:center;gap:7px; }

  #nf-ficha table.servicios { width:100%;border-collapse:collapse;table-layout:fixed; }
  #nf-ficha .col-servicio { width:18%; }
  #nf-ficha .col-fechas { width:32%; }
  #nf-ficha .col-importe { width:14%; }
  #nf-ficha .col-cobros { width:36%; }
  #nf-ficha .circulo { display:inline-block;width:10px;height:10px;border-radius:50%;margin:1px 3px 1px 0;background:#fff;border:1.5px solid #ccc;box-sizing:border-box;text-decoration:none; }
  #nf-ficha a.circulo { cursor:pointer; }
  #nf-ficha a.circulo:hover { transform:scale(1.3); }
  #nf-ficha .circulo-pagado { background:#3D8B5A;border-color:#3D8B5A; }
  #nf-ficha .circulo-pendiente { background:#B5432F;border-color:#B5432F; }
  #nf-ficha .circulo-sin_generar { background:#fff;border-color:#ccc; }
  #nf-ficha .circulo-anulado { background:#fff;border-color:#ccc;position:relative;border-radius:50%; }
  #nf-ficha .circulo-anulado::before { content:'✕';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:9px;line-height:1;color:#999;font-weight:700; }
  #nf-ficha table.servicios th { text-align:left;padding:6px 8px;font-size:11px;color:#888;font-weight:500; }
  #nf-ficha table.servicios td { padding:8px;font-size:13px;vertical-align:middle; }
  #nf-ficha table.servicios tr.trow { border-top:.5px solid rgba(0,0,0,.06); }
  #nf-ficha table.servicios tr.trow-click { cursor:pointer; }
  #nf-ficha table.servicios tr.trow-click:hover { background:rgba(0,0,0,.025); }
  #nf-ficha .dot { width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0;border:1px solid rgba(0,0,0,.15);margin-right:6px;vertical-align:middle; }
  #nf-ficha .dia-pill { font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:5px;background:#fff;color:#bbb;border:.5px solid rgba(0,0,0,.1);margin-right:3px;display:inline-block; }
  #nf-ficha .dia-pill.on { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  #nf-ficha .empty-note { font-size:13px;color:#aaa;margin:0; }
  #nf-ficha .dias-wrap { display:inline;margin-left:6px; }

  #nf-ficha .fecha-corta, #nf-ficha .dia-corta { display:none; }
  @media (max-width:640px) {
    #nf-ficha .fecha-full, #nf-ficha .dia-full { display:none; }
    #nf-ficha .fecha-corta, #nf-ficha .dia-corta { display:inline; }
    /* Objetivos táctiles más grandes para dedos en pantallas pequeñas */
    .icon-btn { padding:9px;min-width:38px;min-height:38px; }
    .btn { padding:8px 12px;min-height:38px; }
    /* Anchos + paddings recortados y validados contra el peor caso real medido en el propio
       navegador: badge "Consulta", rango de fechas abreviado + las 2 píldoras de día en la
       misma línea, importe a 3 cifras -- a 375px de viewport el margen es muy ajustado, así
       que se recorta el padding de celdas/badges/píldoras para ganar sitio. */
    #nf-ficha .col-servicio { width:24%; }
    #nf-ficha .col-fechas { width:56%; }
    #nf-ficha .col-importe { width:20%; }
    #nf-ficha table.servicios td { padding:8px 4px; }
    #nf-ficha .badge { padding:2px 6px; }
    #nf-ficha .dia-pill { padding:2px 5px;margin-right:2px; }
    #nf-ficha .dias-wrap { margin-left:4px; }
    #nf-ficha .importe-mes { display:none; }
    /* Columna de círculos de cobro mensual: solo en la vista web (escritorio), no en móvil. */
    #nf-ficha .col-cobros { display:none; }
  }
  @media (max-width:480px) {
    .btn-label { display:none; }
  }

  /* El modal vive fuera de #nf-ficha (hermano en el DOM), así que estas reglas van sin acotar */
  .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:16px; }
  .modal-overlay.open { display:flex; }
  .modal-overlay .modal { background:#fff;border:.5px solid rgba(0,0,0,.1);border-radius:12px;padding:1.4rem;width:420px;max-width:100%;max-height:90vh;overflow-y:auto; }
  .modal-overlay .modal-title { font-weight:600;font-size:15px;margin:0 0 1rem; }
  .modal-overlay .modal-footer { display:flex;gap:8px;justify-content:flex-end;margin-top:1.1rem;padding-top:.9rem;border-top:.5px solid rgba(0,0,0,.07); }
  .modal-overlay .form-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
  @media (max-width:480px) { .modal-overlay .form-grid2 { grid-template-columns:1fr; } }
  .modal-overlay .form-row { margin-bottom:10px; }
  .modal-overlay .form-label { font-size:11.5px;color:#888;margin:0 0 4px;display:block; }
  .modal-overlay .form-row input, .modal-overlay .form-row select, .modal-overlay .form-row textarea { width:100%;box-sizing:border-box;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 9px;font-size:13px;background:#fff;font-family:inherit;resize:vertical; }
  .modal-overlay .btn { font-size:13px;padding:6px 12px;border-radius:6px;cursor:pointer;border:.5px solid rgba(0,0,0,.15);background:#fff;color:#1B1B18;display:inline-flex;align-items:center;gap:5px;line-height:1.2; }
  .modal-overlay .btn-grey { background:rgba(0,0,0,.045);border-color:transparent;color:#888; }
  .modal-overlay .btn-primary { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4;font-weight:600; }
  .segmented { display:flex;gap:6px;background:rgba(0,0,0,.03);border-radius:8px;padding:3px;margin-bottom:12px; }
  .segmented button { flex:1;border:none;background:none;padding:7px;border-radius:6px;font-weight:600;font-size:13px;color:#888;cursor:pointer;font-family:inherit; }
  .segmented button.active { background:#fff;color:#0C447C;box-shadow:0 1px 2px rgba(0,0,0,.08); }
  .segmented button:disabled { cursor:not-allowed;opacity:.55; }
  .day-row { display:flex;gap:8px;margin-bottom:10px; }
  .day-check { flex:1;display:flex;align-items:center;justify-content:center;gap:6px;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px;font-size:12.5px;cursor:pointer;color:#888;user-select:none; }
  .day-check.active { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  .hide { display:none !important; }
</style>

<div id="nf-ficha">

  <div class="head">
    <div class="avatar-wrap">
      @if($cliente->file_foto)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cliente->file_foto) }}" class="avatar-photo" alt="Foto de {{ $cliente->nombre }}">
      @else
        <div class="avatar-icon" style="background:{{ $activo ? '#EAF3DE' : '#FCEBEB' }};color:{{ $activo ? '#27500A' : '#A32D2D' }};">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12Zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9Z"/></svg>
        </div>
      @endif
      {{-- Subida de foto deshabilitada temporalmente hasta aprobación del presupuesto de este
           desarrollo por parte del cliente (2026-08-13). Para reactivar: quitar "style=display:none"
           de este botón. --}}
      <button type="button" class="avatar-edit-btn" onclick="document.getElementById('fotoInput').click()" title="Cambiar foto" style="display:none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
      </button>
    </div>
    <form id="fotoForm" method="POST" action="{{ route('ficha.update', [$project->slug, 'clientes', $cliente->id]) }}" enctype="multipart/form-data" class="visually-hidden">
      @csrf
      @method('PUT')
      <input type="hidden" name="nombre" value="{{ $cliente->nombre }}">
      <input type="file" name="file_foto" id="fotoInput" accept="image/*" onchange="document.getElementById('fotoForm').submit()">
    </form>
    <div style="min-width:0;">
      <div class="name-row">
        <p class="name">{{ $cliente->nombre }}</p>
        <span class="badge" style="background:{{ $activo ? '#EAF3DE' : '#FCEBEB' }};color:{{ $activo ? '#27500A' : '#A32D2D' }};">{{ $activo ? 'Contrato vigente' : 'Sin contrato vigente' }}</span>
      </div>
      <p class="sub">{{ $sub ?: '—' }}</p>
      <div class="contact-line">
        @if($cliente->telefono)
        <span class="contact-item">
          <a href="https://wa.me/34{{ preg_replace('/\D/', '', $cliente->telefono) }}" target="_blank" title="Abrir WhatsApp" style="display:inline-flex;color:#888;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-1.746-.874-2.888-1.559-4.035-3.532-.305-.526.305-.489.874-1.628.096-.198.048-.371-.05-.52-.099-.148-.669-1.611-.916-2.208-.24-.579-.487-.5-.669-.51-.173-.008-.372-.01-.571-.01-.198 0-.52.075-.792.372-.272.298-1.04 1.017-1.04 2.479s1.065 2.876 1.213 3.074c.148.198 2.05 3.132 4.986 4.27 2.394.933 2.394.622 2.94.583.545-.04 1.758-.72 2.005-1.413.247-.694.247-1.29.173-1.412-.074-.124-.297-.198-.594-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.98.577 3.83 1.573 5.396L2.5 22l4.75-1.045A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.2a8.19 8.19 0 01-4.176-1.145l-.3-.178-3.106.684.678-3.033-.196-.313A8.2 8.2 0 1120.2 12c0 4.53-3.67 8.2-8.2 8.2z"/></svg>
          </a>
          <a href="tel:{{ $cliente->telefono }}" style="color:inherit;text-decoration:none;">{{ $cliente->telefono }}</a>
        </span>
        @endif
        @if($cliente->email)
        <span class="contact-item">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M3 6.5l9 6.5 9-6.5"/></svg>
          {{ $cliente->email }}
        </span>
        @endif
      </div>
    </div>
  </div>

  {{-- Datos personales · edición --}}
  <form id="editForm" method="POST" action="{{ route('ficha.update', [$project->slug, 'clientes', $cliente->id]) }}">
    @csrf
    @method('PUT')
    <div class="edit-box">
      <div class="form-grid2">
        <div class="form-row"><label class="form-label">Nombre</label><input type="text" name="nombre" value="{{ $cliente->nombre }}" required></div>
        <div class="form-row"><label class="form-label">Teléfono</label><input type="text" name="telefono" value="{{ $cliente->telefono }}"></div>
        <div class="form-row"><label class="form-label">DNI</label><input type="text" name="dni" value="{{ $cliente->dni }}"></div>
        <div class="form-row"><label class="form-label">F. nacimiento</label><input type="date" name="fecha_nacimiento" value="{{ $cliente->fecha_nacimiento }}"></div>
        <div class="form-row"><label class="form-label">Género</label>
          <select name="id_genero">
            <option value="">—</option>
            @foreach($generos as $g)
            <option value="{{ $g->id }}" {{ $cliente->id_genero == $g->id ? 'selected' : '' }}>{{ $g->nombre }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" value="{{ $cliente->email }}"></div>
        <div class="form-row"><label class="form-label">Código postal</label><input type="text" name="cp" value="{{ $cliente->cp }}"></div>
        <div class="form-row"><label class="form-label">Dirección</label><input type="text" name="direccion" value="{{ $cliente->direccion }}" placeholder="Calle, número, piso..."></div>
        <div class="form-row"><label class="form-label">Población</label><input type="text" name="poblacion" value="{{ $cliente->poblacion }}" placeholder="Población"></div>
      </div>
    </div>
  </form>

  {{-- Servicios contratados --}}
  <div class="section-card">
    <div class="sec-head">
      <p class="sec-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3v5h5M6 3h8l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
        Servicios contratados
      </p>
      <button class="btn btn-grey" onclick="openModal()" type="button">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Nuevo
      </button>
    </div>

    @if($contratos->isEmpty())
      <p class="empty-note">Todavía no tiene ningún servicio contratado.</p>
    @else
    <table class="servicios">
      <thead><tr>
        <th class="col-servicio">Servicio</th>
        <th class="col-fechas">Fechas</th>
        <th class="col-cobros">Cobros</th>
        <th class="col-importe" style="text-align:right;">Importe</th>
      </tr></thead>
      <tbody>
        @foreach($contratos as $c)
        @php
          $mesInicioContrato = (int) \Carbon\Carbon::parse($c->fecha_inicio)->format('n');
          $ejercicioContrato = $mesInicioContrato >= 9
              ? (int) \Carbon\Carbon::parse($c->fecha_inicio)->format('Y')
              : (int) \Carbon\Carbon::parse($c->fecha_inicio)->format('Y') - 1;
          $esVigente = $ejercicioContrato === $anioActualEjercicio;
        @endphp
        <tr class="trow trow-click"
            style="{{ !$esVigente ? 'opacity:.4;' : '' }}"
            data-contrato="{{ json_encode(['nombre' => $c->nombre, 'id_tipo' => (int) $c->id_tipo, 'id_grupo' => $c->id_grupo, 'fecha_inicio' => $c->fecha_inicio, 'fecha_fin' => $c->fecha_fin, 'dia1' => (bool) $c->dia1, 'dia2' => (bool) $c->dia2, 'importe' => $c->importe, 'descripcion' => $c->descripcion, 'hidden' => (bool) $c->hidden]) }}"
            onclick="abrirModalEditar({{ $c->id }}, this)">
          <td>
            @if(isset($colorGrupo[$c->nombre]))
              <span class="badge" style="background:{{ $colorGrupo[$c->nombre] }};color:#fff;">{{ $c->nombre }}</span>
            @else
              <span class="badge" style="background:#F1EFE8;color:#5F5E5A;">{{ $c->nombre }}</span>
            @endif
          </td>
          <td style="font-size:12px;color:#555;">
            @if((int) $c->id_tipo === 2)
              <span class="fecha-full">Desde {{ \Carbon\Carbon::parse($c->fecha_inicio)->format('d/m/Y') }} hasta {{ \Carbon\Carbon::parse($c->fecha_fin)->format('d/m/Y') }}</span>
              <span class="fecha-corta">{{ $fechaCorta($c->fecha_inicio) }}-{{ $fechaCorta($c->fecha_fin) }}</span>
              <span class="dias-wrap">
                <span class="dia-pill {{ $c->dia1 ? 'on' : '' }}"><span class="dia-full">Día 1</span><span class="dia-corta">1</span></span>
                <span class="dia-pill {{ $c->dia2 ? 'on' : '' }}"><span class="dia-full">Día 2</span><span class="dia-corta">2</span></span>
              </span>
            @else
              <span class="fecha-full">{{ \Carbon\Carbon::parse($c->fecha_inicio)->format('d/m/Y') }}</span>
              <span class="fecha-corta">{{ $fechaCorta($c->fecha_inicio) }}</span>
            @endif
          </td>
          <td class="col-cobros">
            @if(!empty($c->mesesCobro))
              @php
                $tituloEstado = ['pagado' => 'Cobrado', 'pendiente' => 'Pendiente de cobro', 'anulado' => 'Pago anulado', 'sin_generar' => 'Pago no generado todavía'];
                $mesesEs = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                $tituloMes = function ($mesCobro) use ($tituloEstado, $mesesEs) {
                    [$anio, $mes] = explode('-', $mesCobro['mes']);
                    $txt = $mesesEs[(int) $mes] . ' ' . $anio . ' — ' . $tituloEstado[$mesCobro['estado']];
                    if ($mesCobro['importe'] !== null) {
                        $txt .= ' — ' . number_format($mesCobro['importe'], 2, ',', '.') . ' €';
                    }
                    return $txt;
                };
              @endphp
              @foreach($c->mesesCobro as $mesCobro)
                @if($mesCobro['pago_id'])
                  <a class="circulo circulo-{{ $mesCobro['estado'] }}" href="{{ route('ficha', [$project->slug, 'pagos', $mesCobro['pago_id']]) }}" target="_blank" rel="noopener" title="{{ $tituloMes($mesCobro) }}"></a>
                @else
                  <span class="circulo circulo-{{ $mesCobro['estado'] }}" title="{{ $tituloMes($mesCobro) }}"></span>
                @endif
              @endforeach
            @else
              <span style="color:#bbb;">—</span>
            @endif
          </td>
          <td style="text-align:right;font-weight:{{ $esVigente ? 600 : 400 }};">
            {{ number_format($c->importe, 0, ',', '.') }} €<span class="importe-mes">{{ (int) $c->id_tipo === 2 ? '/mes' : '' }}</span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

</div>

{{-- Modal: nuevo contrato / editar contrato --}}
<div class="modal-overlay" id="modalContrato">
  <div class="modal">
    <form method="POST" id="formContrato" action="{{ route('nf.clientes.contratos.store', [$project->slug, $cliente->id]) }}">
      @csrf
      <input type="hidden" name="_method" id="metodoContrato" value="POST">
      <p class="modal-title" id="modalContratoTitulo">Nuevo contrato</p>

      <div class="segmented">
        <button type="button" class="active" id="tipoOsteo" onclick="setTipo('osteo')">Osteopatía</button>
        <button type="button" id="tipoFitness" onclick="setTipo('fitness')">Fitness</button>
      </div>
      <input type="hidden" name="id_tipo" id="idTipo" value="1">

      <div class="form-row" id="grupoRow" style="display:none;">
        <label class="form-label">Grupo</label>
        <select name="id_grupo" id="grupoSelect">
          @foreach($gruposFitness as $g)
          <option value="{{ $g->id }}">{{ $g->nombre }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-grid2">
        <div class="form-row"><label class="form-label">Fecha inicio</label><input type="date" name="fecha_inicio" id="fechaInicio" required></div>
        <div class="form-row hide" id="fechaFinGroup"><label class="form-label">Fecha fin</label><input type="date" name="fecha_fin" id="fechaFin"></div>
      </div>

      <div class="day-row hide" id="diasGroup">
        <div class="day-check" id="dia1Toggle" onclick="toggleDia(1)">Día 1</div>
        <div class="day-check" id="dia2Toggle" onclick="toggleDia(2)">Día 2</div>
        <input type="hidden" name="dia1" id="dia1Input" value="0">
        <input type="hidden" name="dia2" id="dia2Input" value="0">
      </div>

      <div class="form-row">
        <label class="form-label" id="importeLabel">Importe</label>
        <input type="number" step="0.01" min="0" name="importe" id="importe" placeholder="0,00" required>
      </div>

      <div class="form-row">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="3" placeholder="Notas, observaciones..."></textarea>
      </div>

      <div class="modal-footer" style="justify-content:space-between;">
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn hide" id="btnBorrarContrato" style="color:#A32D2D;border-color:#F7C1C1;" onclick="borrarContratoDesdeModal()">Borrar</button>
          <button type="button" class="btn btn-grey hide" id="btnOcultarContrato" onclick="ocultarContratoDesdeModal()">Ocultar</button>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-grey" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<form method="POST" id="formOcultarContrato" style="display:none;">
  @csrf
  @method('PATCH')
</form>

{{-- Modal: confirmar borrado de cliente --}}
<div class="modal-overlay" id="modalBorrarCliente">
  <div class="modal" style="width:360px;">
    <p class="modal-title">Borrar cliente</p>
    <p style="font-size:13.5px;color:#555;margin:0 0 .5rem;">¿Seguro que quieres borrar a <strong>{{ addslashes($cliente->nombre) }}</strong>? Podrás restaurarlo después si hace falta.</p>
    <form method="POST" action="{{ route('ficha.borrar', [$project->slug, 'clientes', $cliente->id]) }}">
      @csrf
      @method('PATCH')
      <div class="modal-footer">
        <button type="button" class="btn btn-grey" onclick="closeModalBorrarCliente()">Cancelar</button>
        <button type="submit" class="btn" style="background:#FCEBEB;color:#A32D2D;border-color:#F7C1C1;font-weight:600;">Borrar</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: confirmar borrado de contrato --}}
<div class="modal-overlay" id="modalBorrarContrato">
  <div class="modal" style="width:360px;">
    <p class="modal-title">Borrar contrato</p>
    <p style="font-size:13.5px;color:#555;margin:0 0 .5rem;" id="textoBorrarContrato"></p>
    <form method="POST" id="formBorrarContrato">
      @csrf
      @method('PATCH')
      <div class="modal-footer">
        <button type="button" class="btn btn-grey" onclick="closeModalBorrar()">Cancelar</button>
        <button type="submit" class="btn" style="background:#FCEBEB;color:#A32D2D;border-color:#F7C1C1;font-weight:600;">Borrar</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: confirmar ocultar/mostrar contrato --}}
<div class="modal-overlay" id="modalOcultarContrato">
  <div class="modal" style="width:360px;">
    <p class="modal-title" id="tituloOcultarContrato">Ocultar contrato</p>
    <p style="font-size:13.5px;color:#555;margin:0 0 .5rem;" id="textoOcultarContrato"></p>
    <div class="modal-footer">
      <button type="button" class="btn btn-grey" onclick="closeModalOcultar()">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="confirmarOcultarContrato()" id="btnConfirmarOcultar">Ocultar</button>
    </div>
  </div>
</div>

<script>
  function enterEdit() {
    document.getElementById('nf-ficha').classList.add('editing');
    document.getElementById('viewActions').style.display = 'none';
    document.getElementById('editActions').style.display = 'flex';
  }
  function cancelEdit() {
    document.getElementById('nf-ficha').classList.remove('editing');
    document.getElementById('viewActions').style.display = 'flex';
    document.getElementById('editActions').style.display = 'none';
  }
  function guardarCliente() { document.getElementById('editForm').submit(); }

  function confirmarBorrarCliente() { document.getElementById('modalBorrarCliente').classList.add('open'); }
  function closeModalBorrarCliente() { document.getElementById('modalBorrarCliente').classList.remove('open'); }
  document.getElementById('modalBorrarCliente').addEventListener('click', function (e) {
    if (e.target === this) closeModalBorrarCliente();
  });

  const urlContratoStore = @json(route('nf.clientes.contratos.store', [$project->slug, $cliente->id]));
  const urlContratoUpdate = @json(route('nf.contratos.update', [$project->slug, '__ID__']));
  const urlContratoBorrar = @json($urlContratoBorrarTpl);
  const urlContratoArchivar = @json($urlContratoArchivarTpl);
  let contratoEditandoId = null;
  let contratoEditandoNombre = '';
  let contratoEditandoHidden = false;

  function openModal() {
    contratoEditandoId = null;
    document.getElementById('modalContratoTitulo').textContent = 'Nuevo contrato';
    document.getElementById('formContrato').action = urlContratoStore;
    document.getElementById('metodoContrato').value = 'POST';
    document.getElementById('descripcion').value = '';
    document.getElementById('btnBorrarContrato').classList.add('hide');
    document.getElementById('btnOcultarContrato').classList.add('hide');
    document.getElementById('tipoOsteo').disabled = false;
    document.getElementById('tipoFitness').disabled = false;
    document.getElementById('modalContrato').classList.add('open');
    setTipo('osteo');
  }

  function abrirModalEditar(id, el) {
    const c = JSON.parse(el.dataset.contrato);
    contratoEditandoId = id;
    contratoEditandoNombre = c.nombre || '';
    contratoEditandoHidden = !!c.hidden;

    document.getElementById('modalContratoTitulo').textContent = 'Editar contrato';
    document.getElementById('formContrato').action = urlContratoUpdate.replace('__ID__', id);
    document.getElementById('metodoContrato').value = 'PUT';
    document.getElementById('btnBorrarContrato').classList.remove('hide');
    document.getElementById('btnOcultarContrato').classList.remove('hide');
    document.getElementById('btnOcultarContrato').textContent = contratoEditandoHidden ? 'Mostrar' : 'Ocultar';
    document.getElementById('tipoOsteo').disabled = true;
    document.getElementById('tipoFitness').disabled = true;
    document.getElementById('modalContrato').classList.add('open');

    document.getElementById('dia1Toggle').classList.remove('active');
    document.getElementById('dia2Toggle').classList.remove('active');
    document.getElementById('dia1Input').value = '0';
    document.getElementById('dia2Input').value = '0';

    setTipo(c.id_tipo === 1 ? 'osteo' : 'fitness');
    document.getElementById('fechaInicio').value = c.fecha_inicio;
    if (c.id_tipo === 2) {
      document.getElementById('grupoSelect').value = c.id_grupo;
      document.getElementById('fechaFin').value = c.fecha_fin;
      if (c.dia1) document.getElementById('dia1Toggle').click();
      if (c.dia2) document.getElementById('dia2Toggle').click();
    }
    document.getElementById('importe').value = c.importe;
    document.getElementById('descripcion').value = c.descripcion || '';
  }

  function borrarContratoDesdeModal() {
    if (!contratoEditandoId) return;
    closeModal();
    confirmarBorrarContrato(urlContratoBorrar.replace('__ID__', contratoEditandoId), contratoEditandoNombre);
  }

  function ocultarContratoDesdeModal() {
    if (!contratoEditandoId) return;
    closeModal();
    const accion = contratoEditandoHidden ? 'mostrar' : 'ocultar';
    document.getElementById('tituloOcultarContrato').textContent = contratoEditandoHidden ? 'Mostrar contrato' : 'Ocultar contrato';
    document.getElementById('textoOcultarContrato').innerHTML = contratoEditandoNombre
      ? `¿Seguro que quieres ${accion} el contrato <strong>${contratoEditandoNombre}</strong>?`
      : `¿Seguro que quieres ${accion} este contrato?`;
    document.getElementById('btnConfirmarOcultar').textContent = contratoEditandoHidden ? 'Mostrar' : 'Ocultar';
    document.getElementById('modalOcultarContrato').classList.add('open');
  }

  function confirmarOcultarContrato() {
    const form = document.getElementById('formOcultarContrato');
    form.action = urlContratoArchivar.replace('__ID__', contratoEditandoId);
    form.submit();
  }
  function closeModalOcultar() { document.getElementById('modalOcultarContrato').classList.remove('open'); }
  document.getElementById('modalOcultarContrato').addEventListener('click', function (e) {
    if (e.target === this) closeModalOcultar();
  });

  function closeModal() { document.getElementById('modalContrato').classList.remove('open'); }
  document.getElementById('modalContrato').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
  });

  function confirmarBorrarContrato(url, nombre) {
    document.getElementById('textoBorrarContrato').innerHTML = nombre
      ? `¿Seguro que quieres borrar el contrato <strong>${nombre}</strong>? Podrás restaurarlo después si hace falta.`
      : '¿Seguro que quieres borrar este contrato?';
    document.getElementById('formBorrarContrato').action = url;
    document.getElementById('modalBorrarContrato').classList.add('open');
  }
  function closeModalBorrar() { document.getElementById('modalBorrarContrato').classList.remove('open'); }
  document.getElementById('modalBorrarContrato').addEventListener('click', function (e) {
    if (e.target === this) closeModalBorrar();
  });

  // Reglas del legacy: Osteopatía ("Consulta") es una sesión suelta, sin grupo que elegir,
  // sin días recurrentes y fecha_fin = fecha_inicio. Fitness es una inscripción por temporada:
  // hay que elegir grupo, fecha_fin por defecto al 31 de julio siguiente, y marcar a qué día(s)
  // semanales asiste.
  function setTipo(tipo) {
    document.getElementById('tipoOsteo').classList.toggle('active', tipo === 'osteo');
    document.getElementById('tipoFitness').classList.toggle('active', tipo === 'fitness');
    document.getElementById('idTipo').value = tipo === 'osteo' ? '1' : '2';
    const hoy = new Date().toISOString().slice(0, 10);
    document.getElementById('fechaInicio').value = hoy;

    if (tipo === 'osteo') {
      document.getElementById('grupoRow').style.display = 'none';
      document.getElementById('fechaFinGroup').classList.add('hide');
      document.getElementById('diasGroup').classList.add('hide');
      document.getElementById('fechaFin').value = hoy;
      document.getElementById('importeLabel').textContent = 'Importe';
      document.getElementById('dia1Toggle').classList.remove('active');
      document.getElementById('dia2Toggle').classList.remove('active');
      document.getElementById('dia1Input').value = '0';
      document.getElementById('dia2Input').value = '0';
    } else {
      document.getElementById('grupoRow').style.display = 'block';
      document.getElementById('fechaFinGroup').classList.remove('hide');
      document.getElementById('diasGroup').classList.remove('hide');
      const finTemporada = new Date(); finTemporada.setMonth(6, 31);
      if (finTemporada < new Date()) finTemporada.setFullYear(finTemporada.getFullYear() + 1);
      document.getElementById('fechaFin').value = finTemporada.toISOString().slice(0, 10);
      document.getElementById('importeLabel').textContent = 'Importe mensual';
    }
  }
  function toggleDia(n) {
    const el = document.getElementById('dia' + n + 'Toggle');
    el.classList.toggle('active');
    document.getElementById('dia' + n + 'Input').value = el.classList.contains('active') ? '1' : '0';
  }
</script>

</x-app-layout>
