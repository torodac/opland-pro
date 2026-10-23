<x-app-layout
    :breadcrumb="[
        ['label' => 'Dashboard', 'url' => route('vm.dashboard', [$project->slug])],
        ['label' => $tarea->nombre ?: ('Tarea #' . $tarea->id), 'url' => ''],
    ]"
    :project="$project">

{{-- Mismo formato que la ficha de reservas: tarjetas de bloque, título en mayúsculas y filas etiqueta/valor. --}}
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

{{-- Editar / Guardar en la cabecera, igual que en la ficha de reservas. Solo el comentario se edita. --}}
<x-slot name="actions">
  @if($puedeComentar)
    <div id="btn-view-mode" style="display:flex;align-items:center;gap:6px;">
      <button type="button" onclick="enterEdit()"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <i class="ti ti-pencil text-sm"></i>Editar
      </button>
    </div>
    <div id="btn-edit-mode" style="display:none;align-items:center;gap:6px;">
      <button type="button" onclick="cancelEdit()"
        class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
        Cancelar
      </button>
      <button type="submit" form="form-sscc" id="btn-save"
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

  {{-- Cabecera: nombre y tipo. El estado solo se muestra; se cambia desde el dashboard. --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-xl font-semibold text-gray-900">{{ $tarea->nombre ?: ('Tarea #' . $tarea->id) }}</h1>
      <p class="text-sm text-gray-500">{{ $tarea->Tipo ?: 'Sin tipo' }}</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm px-3 py-1 rounded-full bg-gray-100 text-gray-700">{{ $tarea->estado ?: '—' }}</span>
      @if($puedeHecho)
        {{-- Mismo cierre que el dashboard: pide confirmación porque dispara lo que depende de la tarea --}}
        {{-- Color en línea: las clases de color de Tailwind no siempre están compiladas en este proyecto --}}
        <button type="button" id="ssc-hecho" onclick="marcarHecho()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg text-white"
                style="background:#16a34a;border:1px solid #16a34a;"
                onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
          <i class="ti ti-check text-sm"></i>Hecho
        </button>
      @endif
    </div>
  </div>

  {{-- Datos de la tarea, todos de solo lectura --}}
  <section class="section-card">
    <div class="sec-title">Datos de la tarea</div>
    <div class="reserva-grid">
      <div>
        <div class="field-row"><div class="field-lbl">Propiedad</div><div class="field-val">{{ $propiedad ?: '—' }}</div></div>
        <div class="field-row"><div class="field-lbl">Departamento</div><div class="field-val">{{ $departamento ?: '—' }}</div></div>
        <div class="field-row"><div class="field-lbl">Rol</div><div class="field-val">{{ $rol ?: '—' }}</div></div>
      </div>
      <div>
        <div class="field-row">
          <div class="field-lbl">Fecha planificada</div>
          <div class="field-val">{{ $tarea->fecha_planificada ? \Carbon\Carbon::parse($tarea->fecha_planificada)->format('d/m/Y') : '—' }}</div>
        </div>
        <div class="field-row">
          <div class="field-lbl">Fecha de finalización</div>
          <div class="field-val">{{ $tarea->fecha_finalizacion ? \Carbon\Carbon::parse($tarea->fecha_finalizacion)->format('d/m/Y') : '—' }}</div>
        </div>
      </div>
    </div>
    @if($tarea->descripcion)
      <div class="field-row" style="grid-template-columns:140px 1fr;margin-top:4px;">
        <div class="field-lbl">Descripción</div>
        <div class="field-val" style="white-space:pre-line">{{ $tarea->descripcion }}</div>
      </div>
    @endif
  </section>

  {{-- Accesos directos a las unidades de gestión relacionadas --}}
  @if(count($enlaces))
    <section class="section-card">
      <div class="sec-title">Relacionado</div>
      <div class="flex flex-wrap gap-2">
        @foreach($enlaces as $enlace)
          <a href="{{ $enlace['url'] }}"
             class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100">
            <i class="ti {{ $enlace['icono'] }}"></i>{{ $enlace['etiqueta'] }}
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Comentario: lo único que se escribe desde esta ficha. Solo se habilita en modo edición. --}}
  <section class="section-card">
    <div class="sec-title">Comentario</div>
    <form id="form-sscc" method="POST" action="{{ route('vm.sscc_form.comentario', [$project->slug, $tarea->id]) }}">
      @csrf
      <label for="comentario" class="sr-only">Comentario</label>
      <textarea id="comentario" name="comentario" rows="4" readonly
                data-original="{{ $tarea->comentario }}"
                class="f-input bg-gray-50">{{ old('comentario', $tarea->comentario) }}</textarea>
    </form>
  </section>

</div>

<script>
  async function marcarHecho() {
    if (!confirm('¿Marcar esta tarea SSCC como hecha?\n\nSi es la última de su fianza, la fianza pasará a Devuelta y se avisará al huésped.')) return;

    const btn = document.getElementById('ssc-hecho');
    btn.disabled = true;
    const r = await fetch(@json(route('vm.dashboard.sscc-hecho', [$project->slug, $tarea->id])), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({}));
    if (r.ok && data.ok) {
      location.reload();
    } else {
      btn.disabled = false;
      alert(data.error || 'No se pudo marcar la tarea como hecha.');
    }
  }

  // Mismo mecanismo que la ficha de reservas: se ocultan los bloques de lectura y se muestran los de edición.
  function enterEdit() {
    const t = document.getElementById('comentario');
    t.readOnly = false;
    t.classList.remove('bg-gray-50');
    document.getElementById('btn-view-mode').style.display = 'none';
    document.getElementById('btn-edit-mode').style.display = 'flex';
    t.focus();
  }

  function cancelEdit() {
    const t = document.getElementById('comentario');
    t.value = t.dataset.original;
    t.readOnly = true;
    t.classList.add('bg-gray-50');
    document.getElementById('btn-view-mode').style.display = 'flex';
    document.getElementById('btn-edit-mode').style.display = 'none';
  }
</script>
</x-app-layout>
