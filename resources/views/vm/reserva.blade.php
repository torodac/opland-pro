<x-app-layout
    :breadcrumb="[
        ['label' => 'Reservas', 'url' => route('listado', [$project->slug, 'reservas'])],
        ['label' => 'Reserva ' . $reserva->booking_id, 'url' => ''],
    ]"
    :project="$project">

{{-- Editar / Guardar en la cabecera, igual que en fichaje. Solo se editan los campos de fianza. --}}
<x-slot name="actions">
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

<div class="max-w-3xl mx-auto space-y-6 p-4">

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

  {{-- Los datos de la reserva vienen de Icnea: solo lectura --}}
  <section class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Reserva</h2>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
      <dt class="text-gray-500">Huésped</dt><dd class="text-gray-900">{{ $reserva->guest_name ?: '—' }}</dd>
      <dt class="text-gray-500">Correo del huésped</dt><dd class="text-gray-900">{{ $reserva->guest_email ?: '—' }}</dd>
      <dt class="text-gray-500">Entrada</dt><dd class="text-gray-900">{{ $reserva->check_in_date ? \Carbon\Carbon::parse($reserva->check_in_date)->format('d/m/Y') : '—' }}</dd>
      <dt class="text-gray-500">Salida</dt><dd class="text-gray-900">{{ $reserva->check_out_date ? \Carbon\Carbon::parse($reserva->check_out_date)->format('d/m/Y') : '—' }}</dd>
      <dt class="text-gray-500">Estado de la reserva</dt><dd class="text-gray-900">{{ $reserva->booking_status ?: '—' }}</dd>
      <dt class="text-gray-500">Fianza</dt><dd class="text-gray-900">{{ $reserva->fianza !== null ? number_format((float) $reserva->fianza, 2, ',', '.') . ' €' : '—' }}</dd>
    </dl>
  </section>

  {{-- Decisión de fianza: la única parte editable --}}
  <form id="form-fianza" method="POST" action="{{ route('vm.reserva_form.fianza', [$project->slug, $reserva->id]) }}"
        class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
    @csrf
    <h2 class="text-sm font-semibold text-gray-700">Fianza</h2>

    <div class="rounded-lg bg-amber-50 text-amber-800 text-xs px-3 py-2">
      Cambiar la decisión aquí no crea tareas SSCC ni envía correos. Para decidir, usa Dev. fianzas.
    </div>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
      <dt class="text-gray-500">Estado</dt>
      <dd>
        <span class="v-field text-gray-900">{{ $reserva->estado_fianza ?: '—' }}</span>
        <select name="estado_fianza" class="e-field w-full rounded-lg border border-gray-300 text-sm p-1.5" style="display:none">
          <option value="">— Selecciona —</option>
          @foreach($estados as $e)
            <option value="{{ $e }}" @selected(old('estado_fianza', $reserva->estado_fianza) === $e)>{{ $e }}</option>
          @endforeach
        </select>
      </dd>

      <dt class="text-gray-500">Canal de cobro</dt>
      <dd class="text-gray-900">{{ $reserva->fianza_canal_cobro ?: '—' }}</dd>

      <dt class="text-gray-500">Retención (€)</dt>
      <dd>
        <span class="v-field text-gray-900">{{ $reserva->fianza_retencion !== null ? number_format((float) $reserva->fianza_retencion, 2, ',', '.') . ' €' : '—' }}</span>
        <input type="number" step="0.01" min="0" name="fianza_retencion"
               value="{{ old('fianza_retencion', $reserva->fianza_retencion) }}"
               class="e-field w-full rounded-lg border border-gray-300 text-sm p-1.5" style="display:none">
      </dd>

      <dt class="text-gray-500">Motivo de la retención</dt>
      <dd>
        <span class="v-field text-gray-900 whitespace-pre-line">{{ $reserva->fianza_motivo_retencion ?: '—' }}</span>
        <input type="text" maxlength="500" name="fianza_motivo_retencion"
               value="{{ old('fianza_motivo_retencion', $reserva->fianza_motivo_retencion) }}"
               class="e-field w-full rounded-lg border border-gray-300 text-sm p-1.5" style="display:none">
      </dd>

      <dt class="text-gray-500">Decidido por</dt>
      <dd>
        <span class="v-field text-gray-900">{{ optional($usuarios->firstWhere('id', $reserva->fianza_usuario))->name ?: '—' }}</span>
        <select name="fianza_usuario" class="e-field w-full rounded-lg border border-gray-300 text-sm p-1.5" style="display:none">
          <option value="">— Ninguno —</option>
          @foreach($usuarios as $u)
            <option value="{{ $u->id }}" @selected((string) old('fianza_usuario', $reserva->fianza_usuario) === (string) $u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </dd>

      <dt class="text-gray-500">Fecha de decisión</dt>
      <dd>
        <span class="v-field text-gray-900">{{ $reserva->fianza_fecha ? \Carbon\Carbon::parse($reserva->fianza_fecha)->format('d/m/Y H:i') : '—' }}</span>
        <input type="datetime-local" name="fianza_fecha"
               value="{{ old('fianza_fecha', $reserva->fianza_fecha ? \Carbon\Carbon::parse($reserva->fianza_fecha)->format('Y-m-d\TH:i') : '') }}"
               class="e-field w-full rounded-lg border border-gray-300 text-sm p-1.5" style="display:none">
      </dd>
    </dl>
  </form>

  {{-- Limpiezas de salida de la reserva --}}
  <section class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Limpiezas de salida</h2>
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
  <section class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Tareas SSCC</h2>
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
