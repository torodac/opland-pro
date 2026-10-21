<x-app-layout
    :breadcrumb="[
        ['label' => 'Reservas', 'url' => route('listado', [$project->slug, 'reservas'])],
        ['label' => 'Reserva ' . $reserva->booking_id, 'url' => ''],
    ]"
    :project="$project">

{{-- Mismo formato que la ficha de fichaje: tarjetas de bloque, título en mayúsculas y filas etiqueta/valor. --}}
<style>
.section-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .section-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.sec-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;display:flex;align-items:center;gap:6px}
.reserva-grid{display:grid;grid-template-columns:1fr 1fr;column-gap:32px}
@media(max-width:700px){.reserva-grid{grid-template-columns:1fr}}
.field-row{display:grid;grid-template-columns:140px 1fr;align-items:start;padding:8px 0;border-bottom:0.5px solid rgba(0,0,0,.05)}
.dark .field-row{border-bottom-color:rgba(255,255,255,.05)}
.field-lbl{font-size:12px;color:#999;padding-top:1px}
.field-val{font-size:13px;color:#111;min-width:0}
.dark .field-val{color:#eee}
.f-input{width:100%;box-sizing:border-box;border:0.5px solid rgba(0,0,0,.18);border-radius:6px;padding:6px 9px;font-size:13px;background:#fff;color:#111}
.dark .f-input{background:#111;color:#eee;border-color:rgba(255,255,255,.18)}
</style>

{{-- Editar / Guardar en la cabecera, igual que en fichaje. Solo se editan los campos de fianza. --}}
<x-slot name="actions">
  {{-- Acceso a la ficha estándar de la reserva, igual que en fichaje --}}
  <a href="{{ route('ficha', [$project->slug, 'reservas', $reserva->id]) }}"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors"
    title="Ver ficha estándar">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
  </a>
  @if($puedeEditar)
    <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
      <button type="button" onclick="enterEdit()"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <i class="ti ti-pencil text-sm"></i>Editar
      </button>
    </div>
    <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
      <button type="button" onclick="location.reload()"
        class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
        Cancelar
      </button>
      <button type="submit" form="form-fianza"
        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-orange-500 border border-orange-500 rounded-lg hover:bg-orange-600 transition-colors">
        Guardar
      </button>
    </div>
  @endif
</x-slot>

<div class="space-y-6 p-4" style="max-width:1200px;margin:0 auto;">

  @if(session('ok'))
    <div class="rounded-lg bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-lg bg-red-50 text-red-700 text-sm px-4 py-2">
      @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
  @endif

  {{-- Cabecera --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">Reserva {{ $reserva->booking_id }}</h1>
      <p class="text-sm text-gray-500">{{ $reserva->propiedad ?: 'Sin propiedad' }} · {{ $reserva->channel ?: 'Sin canal' }}</p>
    </div>
    @if($estado)
      @php([$fondo, $tinta] = colorEstadoFianza($estado))
      <span class="text-sm px-3 py-1 rounded-full" style="background:{{ $fondo }};color:{{ $tinta }}">{{ $estado }}</span>
    @endif
  </div>

  {{-- Bloques de datos. Registro no va aquí: se pinta al final. --}}
  @foreach($secciones as $titulo => $filas)
    @continue($titulo === 'Registro')
    @include('vm.partials.reserva-seccion', ['titulo' => $titulo, 'filas' => $filas])
  @endforeach

  {{-- Traza de cambios: lo que ha cambiado la sincronización con Icnea, lo más reciente primero --}}
  <section class="section-card">
    <div class="sec-title">Traza de cambios</div>
    @forelse($traza as $linea)
      <p class="text-sm text-gray-900 break-words">{{ $linea }}</p>
    @empty
      <p class="text-sm text-gray-500">Sin cambios registrados.</p>
    @endforelse
  </section>

  {{-- Auditoría de la devolución: cada paso del proceso, lo más reciente primero. Solo lectura. --}}
  <section class="section-card">
    <div class="sec-title">Auditoría de la devolución</div>
    @forelse($auditoria as $a)
      <div class="text-sm text-gray-900 break-words" style="display:grid;grid-template-columns:110px 150px 1fr;gap:4px 16px;">
        <span class="text-gray-500">{{ $a['fecha'] }}</span>
        <span class="text-gray-500">{{ $a['usuario'] }}</span>
        <span>
          <strong style="font-weight:500">{{ $a['accion'] }}</strong>@if($a['texto']) · {{ $a['texto'] }}@endif
        </span>
      </div>
    @empty
      <p class="text-sm text-gray-500">Todavía no hay pasos registrados. El registro empieza con las decisiones que se tomen a partir de ahora.</p>
    @endforelse
  </section>

  {{-- Limpiezas de salida de la reserva --}}
  <section class="section-card">
    <div class="sec-title">Limpiezas de salida</div>
    @forelse($limpiezas as $l)
      <a href="{{ route('vm.tarea', [$project->slug, 'limpieza', $l->id]) }}"
         class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-gray-200 text-sm hover:bg-gray-50">
        <span class="text-gray-900">{{ $l->nombre ?: ('Limpieza #' . $l->id) }}</span>
        <span class="text-gray-500">{{ $l->fecha_planificada ? \Carbon\Carbon::parse($l->fecha_planificada)->format('d/m/Y') : '—' }} · {{ $l->estado ?: '—' }}</span>
      </a>
    @empty
      <p class="text-sm text-gray-500">No hay limpiezas de salida para esta reserva.</p>
    @endforelse
  </section>

  {{-- Tareas SSCC de la fianza --}}
  <section class="section-card">
    <div class="sec-title">Tareas SSCC</div>
    @forelse($sscc as $t)
      <a href="{{ route('vm.sscc_form', [$project->slug, $t->id]) }}"
         class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-gray-200 text-sm hover:bg-gray-50">
        <span class="text-gray-900">{{ $t->Tipo ?: 'Sin tipo' }}</span>
        <span class="text-gray-500">{{ $t->estado ?: '—' }}</span>
      </a>
    @empty
      <p class="text-sm text-gray-500">Esta reserva no tiene tareas SSCC.</p>
    @endforelse
  </section>

  {{-- Registro: al final de la ficha --}}
  @include('vm.partials.reserva-seccion', ['titulo' => 'Registro', 'filas' => $secciones['Registro']])

</div>

<script>
  // Mismo mecanismo que fichaje: se ocultan los valores y se muestran los campos de edición.
  function enterEdit() {
    document.querySelectorAll('.v-field').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.e-field').forEach(el => el.style.display = '');
    document.getElementById('btn-view-mode').style.display = 'none';
    document.getElementById('btn-edit-mode').style.display = 'flex';
  }
</script>
</x-app-layout>
