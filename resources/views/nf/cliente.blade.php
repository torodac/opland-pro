@php
$initials = collect(explode(' ', $cliente->nombre))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode('');

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
@endphp

<x-app-layout :breadcrumb="[['label'=>'Clientes','url'=>route('listado',[$project->slug,'clientes'])],['label'=>$cliente->nombre,'url'=>route('nf.clientes_form',[$project->slug,$cliente->id])]]" :project="$project">

<x-slot name="actions">
    <div id="viewActions" style="display:flex;align-items:center;gap:6px;">
        <a href="{{ route('ficha', [$project->slug, 'clientes', $cliente->id]) }}"
           class="btn btn-grey" title="Ver ficha estándar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </a>
        <a href="{{ route('ficha.create', [$project->slug, 'clientes']) }}" class="btn btn-grey">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo
        </a>
        <button onclick="enterEdit()" class="btn btn-grey">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            Editar
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
  #nf-ficha .btn { font-size:13px;padding:6px 12px;border-radius:6px;cursor:pointer;border:.5px solid rgba(0,0,0,.15);background:#fff;color:#1B1B18;display:inline-flex;align-items:center;gap:5px;line-height:1.2; }
  #nf-ficha .btn-grey { background:rgba(0,0,0,.045);border-color:transparent;color:#888; }
  #nf-ficha .btn-primary { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4;font-weight:600; }
  #nf-ficha .btn-orange { background:#F97316;color:#fff;border-color:#F97316;font-weight:600; }
  #nf-ficha .icon-btn { background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;border-radius:6px; }
  #nf-ficha .icon-btn:hover { background:rgba(0,0,0,.06);color:#222; }

  #nf-ficha .head { display:flex;align-items:flex-start;gap:12px;margin-bottom:1.2rem; }
  #nf-ficha .avatar { width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:15px;flex-shrink:0; }
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
  #nf-ficha table.servicios th { text-align:left;padding:6px 8px;font-size:11px;color:#888;font-weight:500; }
  #nf-ficha table.servicios td { padding:8px;font-size:13px;vertical-align:middle; }
  #nf-ficha table.servicios tr.trow { border-top:.5px solid rgba(0,0,0,.06); }
  #nf-ficha .dot { width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0;border:1px solid rgba(0,0,0,.15);margin-right:6px;vertical-align:middle; }
  #nf-ficha .dia-pill { font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:5px;background:#fff;color:#bbb;border:.5px solid rgba(0,0,0,.1);margin-right:3px;display:inline-block; }
  #nf-ficha .dia-pill.on { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  #nf-ficha .empty-note { font-size:13px;color:#aaa;margin:0; }

  #nf-ficha .fecha-corta, #nf-ficha .dia-corta { display:none; }
  @media (max-width:640px) {
    #nf-ficha .fecha-full, #nf-ficha .dia-full { display:none; }
    #nf-ficha .fecha-corta, #nf-ficha .dia-corta { display:inline; }
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
  .day-row { display:flex;gap:8px;margin-bottom:10px; }
  .day-check { flex:1;display:flex;align-items:center;justify-content:center;gap:6px;border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px;font-size:12.5px;cursor:pointer;color:#888;user-select:none; }
  .day-check.active { background:#E6F1FB;color:#0C447C;border-color:#B5D4F4; }
  .hide { display:none !important; }
</style>

<div id="nf-ficha">

  <div class="head">
    <div class="avatar" id="nfAvatar" style="background:{{ $activo ? '#EAF3DE' : '#FCEBEB' }};color:{{ $activo ? '#27500A' : '#A32D2D' }};">{{ $initials }}</div>
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
        <th style="width:26%;">Servicio</th>
        <th style="width:28%;">Fechas</th>
        <th style="width:18%;">Días</th>
        <th style="width:18%;text-align:right;">Importe</th>
        <th style="width:10%;"></th>
      </tr></thead>
      <tbody>
        @foreach($contratos as $c)
        <tr class="trow">
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
            @else
              <span class="fecha-full">{{ \Carbon\Carbon::parse($c->fecha_inicio)->format('d/m/Y') }}</span>
              <span class="fecha-corta">{{ $fechaCorta($c->fecha_inicio) }}</span>
            @endif
          </td>
          <td>
            @if((int) $c->id_tipo === 2)
              <span class="dia-pill {{ $c->dia1 ? 'on' : '' }}"><span class="dia-full">Día 1</span><span class="dia-corta">d1</span></span>
              <span class="dia-pill {{ $c->dia2 ? 'on' : '' }}"><span class="dia-full">Día 2</span><span class="dia-corta">d2</span></span>
            @else
              <span style="color:#bbb;">—</span>
            @endif
          </td>
          <td style="text-align:right;font-weight:600;">
            {{ number_format($c->importe, 2, ',', '.') }} €{{ (int) $c->id_tipo === 2 ? '/mes' : '' }}
          </td>
          <td style="text-align:right;white-space:nowrap;">
            <button type="button" class="icon-btn" title="Editar"
                    data-contrato="{{ json_encode(['id_tipo' => (int) $c->id_tipo, 'id_grupo' => $c->id_grupo, 'fecha_inicio' => $c->fecha_inicio, 'fecha_fin' => $c->fecha_fin, 'dia1' => (bool) $c->dia1, 'dia2' => (bool) $c->dia2, 'importe' => $c->importe, 'descripcion' => $c->descripcion]) }}"
                    onclick="abrirModalEditar({{ $c->id }}, this)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            </button>
            <button type="button" class="icon-btn" title="Borrar"
                    onclick="confirmarBorrarContrato('{{ route('ficha.borrar', [$project->slug, 'contratos', $c->id]) }}', '{{ addslashes($c->nombre) }}')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
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

      <div class="modal-footer">
        <button type="button" class="btn btn-grey" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

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

  function openModal() {
    document.getElementById('modalContratoTitulo').textContent = 'Nuevo contrato';
    document.getElementById('formContrato').action = urlContratoStore;
    document.getElementById('metodoContrato').value = 'POST';
    document.getElementById('descripcion').value = '';
    document.getElementById('modalContrato').classList.add('open');
    setTipo('osteo');
  }

  function abrirModalEditar(id, btn) {
    const c = JSON.parse(btn.dataset.contrato);

    document.getElementById('modalContratoTitulo').textContent = 'Editar contrato';
    document.getElementById('formContrato').action = urlContratoUpdate.replace('__ID__', id);
    document.getElementById('metodoContrato').value = 'PUT';
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
