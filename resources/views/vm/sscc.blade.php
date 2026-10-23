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

<div class="space-y-6 p-4" style="max-width:1200px;margin:0 auto;">

  @if(session('ok'))
    <div class="rounded-lg bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('ok') }}</div>
  @endif

  {{-- Cabecera: nombre y tipo. El estado solo se muestra; el botón Hecho es el mismo del dashboard. --}}
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
          Hecho
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

  {{-- Comentarios: se guardan solos mientras se escribe, al salir del campo y al cerrar la página. --}}
  <section class="section-card">
    <div class="sec-title" style="justify-content:space-between">
      <span>Comentarios</span>
      <span id="comentario-estado" style="font-size:11px;font-weight:400;text-transform:none;letter-spacing:0;color:#999;"></span>
    </div>
    <label for="comentario" class="sr-only">Comentarios</label>
    <textarea id="comentario" rows="4" @unless($puedeComentar) readonly @endunless
              class="f-input">{{ $tarea->comentario }}</textarea>
  </section>

</div>

<script>
  const URL_COMENTARIO = @json(route('vm.sscc_form.comentario', [$project->slug, $tarea->id]));
  const CSRF_SSCC = @json(csrf_token());
  const campoComentario = document.getElementById('comentario');
  const indicador = document.getElementById('comentario-estado');
  let ultimoGuardado = campoComentario.value;
  let temporizador = null;

  // Guarda el texto si ha cambiado desde la última vez. Con «salir» (cerrar la página) va con keepalive,
  // para que la petición sobreviva a la descarga de la página.
  async function guardarComentario(salir = false) {
    clearTimeout(temporizador);
    const texto = campoComentario.value;
    if (texto === ultimoGuardado) return;

    indicador.textContent = 'Guardando…';
    try {
      const r = await fetch(URL_COMENTARIO, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_SSCC, 'Accept': 'application/json' },
        body: JSON.stringify({ comentario: texto }),
        keepalive: salir,
      });
      if (!r.ok) throw new Error(r.status);
      ultimoGuardado = texto;
      indicador.textContent = 'Guardado';
    } catch (e) {
      indicador.textContent = 'No se pudo guardar; se volverá a intentar';
      temporizador = setTimeout(() => guardarComentario(), 5000);
    }
  }

  campoComentario.addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => guardarComentario(), 800);
  });
  campoComentario.addEventListener('blur', () => guardarComentario());
  // Cerrar o cambiar de pestaña no siempre dispara el blur: por eso también se guarda al ocultarse la página.
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') guardarComentario(true); });
  window.addEventListener('pagehide', () => guardarComentario(true));

  async function marcarHecho() {
    if (!confirm('¿Marcar esta tarea SSCC como hecha?\n\nSi es la última de su fianza, la fianza pasará a Devuelta y se avisará al huésped.')) return;

    const btn = document.getElementById('ssc-hecho');
    btn.disabled = true;
    const r = await fetch(@json(route('vm.dashboard.sscc-hecho', [$project->slug, $tarea->id])), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF_SSCC, 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({}));
    if (r.ok && data.ok) {
      location.reload();
    } else {
      btn.disabled = false;
      alert(data.error || 'No se pudo marcar la tarea como hecha.');
    }
  }
</script>
</x-app-layout>
