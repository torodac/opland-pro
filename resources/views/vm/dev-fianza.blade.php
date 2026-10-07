@php
    $fmt = fn($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.') . ' €';
    $pendiente = empty($reserva->estado_fianza);
@endphp

<x-app-layout
    :breadcrumb="[
        ['label' => 'Dev. fianzas', 'url' => route('vm.dev-fianzas', $project->slug)],
        ['label' => $reserva->booking_id . ($reserva->nombre ? ' · ' . $reserva->nombre : ''), 'url' => ''],
    ]"
    :project="$project">

<style>
.df-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .df-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.df-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;display:flex;align-items:center;gap:6px}
.df-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.df-cell{background:rgba(0,0,0,.03);border-radius:8px;padding:.8rem}
.df-lbl{font-size:11px;color:#888;margin:0 0 3px}
.df-val{font-size:13px;font-weight:500;margin:0}
.df-modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:60}
#df-lightbox{background:rgba(0,0,0,.82)}
.df-modal.open{display:flex}
.df-modal-box{background:#fff;border-radius:12px;padding:1.25rem;width:min(460px,92vw)}
.dark .df-modal-box{background:#1a1a1a}
.df-btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff}
.df-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:0;
        background:rgba(255,255,255,.15);color:#fff;font-size:30px;line-height:1;cursor:pointer;
        display:flex;align-items:center;justify-content:center;padding:0 0 4px}
.df-nav:hover{background:rgba(255,255,255,.3)}
.df-nav[disabled]{opacity:.2;cursor:default}
</style>

<div style="padding:0 0 3rem;">

{{-- ── Bloque 1: los datos de la decisión ───────────────────────────────── --}}
<div class="df-card">
  <div class="df-title"><i class="ti ti-cash"></i>Fianza</div>

  <div class="df-grid">
    <div class="df-cell">
      <p class="df-lbl">Checkout</p>
      <p class="df-val">{{ \Carbon\Carbon::parse($reserva->check_out_date)->format('d/m/Y') }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Booking</p>
      <p class="df-val">
        <a href="{{ url($project->slug . '/reservas/' . $reserva->id) }}" style="color:#185FA5;text-decoration:none">
          {{ $reserva->booking_id }}
        </a>
      </p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Huésped</p>
      <p class="df-val">{{ $reserva->nombre ?: '—' }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Propiedad</p>
      <p class="df-val">{{ $reserva->propiedad ?: '—' }}</p>
    </div>

    {{-- Segunda fila: primero la evidencia (comentarios y fotos) y luego el dinero, que es el
         orden en que se mira para decidir. --}}
    <div class="df-cell">
      <p class="df-lbl">Comentarios</p>
      <p class="df-val">{{ $comentarios->count() }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Fotos</p>
      <p class="df-val">{{ $fotos->count() }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Fianza</p>
      <p class="df-val">{{ $fmt($reserva->fianza) }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Retención</p>
      <p class="df-val">{{ $reserva->fianza_retencion !== null ? $fmt($reserva->fianza_retencion) : '—' }}</p>
    </div>

    @if(!$pendiente)
    <div class="df-cell" style="grid-column:1/-1">
      <p class="df-lbl">Decisión</p>
      <p class="df-val">
        {{ $reserva->estado_fianza }}
        @if($reserva->decidido_por)
          <span style="font-weight:400;color:#888">
            · {{ $reserva->decidido_por }}
            @if($reserva->fianza_fecha), {{ \Carbon\Carbon::parse($reserva->fianza_fecha)->format('d/m/Y H:i') }}@endif
          </span>
        @endif
      </p>
      @if($reserva->fianza_motivo_retencion)
        <p style="font-size:13px;margin:6px 0 0;white-space:pre-line">{{ $reserva->fianza_motivo_retencion }}</p>
      @endif
    </div>
    @endif
  </div>

  @if($pendiente && $puedeEditar)
    <div style="display:flex;gap:8px;margin-top:12px">
      <button type="button" class="df-btn" onclick="conforme()"
              style="border-color:#B7E0C4;background:#F1FAF4;color:#1B7F3B">
        Conforme
      </button>
      <button type="button" class="df-btn" onclick="abrirRetener()"
              style="border-color:#E3C9A3;background:#FDF8F1;color:#9A5B00">
        Retener
      </button>
    </div>
  @elseif($pendiente)
    <p style="font-size:12px;color:#bbb;margin:12px 0 0">No tienes permiso para decidir sobre esta fianza.</p>
  @else
    <p style="font-size:12px;color:#bbb;margin:12px 0 0">
      Ya decidida. Para rehacerla, deja el estado en blanco en
      <a href="{{ url($project->slug . '/reservas/' . $reserva->id) }}" style="color:#185FA5">la ficha de la reserva</a>.
    </p>
  @endif
</div>

{{-- ── Bloque 2: comentarios de las limpiezas de salida ──────────────────── --}}
<div class="df-card">
  <div class="df-title">
    <i class="ti ti-message-2"></i>Comentarios de la limpieza de salida
    @if($comentarios->count())<span style="color:#bbb;font-weight:400">{{ $comentarios->count() }}</span>@endif
  </div>

  {{-- Agrupado por tarea: el nombre se ve siempre, tenga comentarios o no, porque saber qué
       limpieza se hizo (y poder abrirla) ya es información aunque nadie haya escrito nada. --}}
  @if($tareas->isNotEmpty())
    <div style="display:flex;flex-direction:column;gap:14px">
      @foreach($tareas as $t)
        @php $suyos = $comentarios->where('tarea_id', $t->id); @endphp
        <div>
          <div style="font-size:12px;margin-bottom:5px;display:flex;gap:6px;flex-wrap:wrap;align-items:baseline">
            <a href="{{ route('vm.tarea', [$project->slug, 'limpieza', $t->id]) }}"
               target="_blank" rel="noopener"
               style="color:#185FA5;text-decoration:none;font-weight:500">
              {{ $t->nombre }}
              <i class="ti ti-external-link" style="font-size:11px;opacity:.7"></i>
            </a>
            @if($t->fecha_planificada)
              <span style="color:#aaa;font-size:11px">{{ \Carbon\Carbon::parse($t->fecha_planificada)->format('d/m/Y') }}</span>
            @endif
            @if($t->estado)
              <span style="color:#aaa;font-size:11px">· {{ $t->estado }}</span>
            @endif
            <span style="color:#ccc;font-size:11px">· {{ $suyos->count() }} {{ $suyos->count() === 1 ? 'comentario' : 'comentarios' }}</span>
          </div>

          @if($suyos->count())
            <div style="display:flex;flex-direction:column;gap:8px">
              @foreach($suyos as $com)
                <div style="border-left:2px solid #e5e5e5;padding:2px 0 2px 10px">
                  <div style="font-size:11px;color:#aaa;margin-bottom:2px">
                    {{ $com->fecha ? \Carbon\Carbon::parse($com->fecha)->translatedFormat('D j M · H:i') : '' }}
                  </div>
                  <div style="font-size:13px;white-space:pre-line">{{ $com->comentario }}</div>
                </div>
              @endforeach
            </div>
          @else
            <p style="font-size:13px;color:#999;margin:0 0 0 12px">Sin comentarios.</p>
          @endif
        </div>
      @endforeach
    </div>
  @else
    <p style="font-size:13px;color:#999;margin:0">
      Esta reserva no tiene ninguna limpieza de salida asociada.
    </p>
  @endif
</div>

{{-- ── Bloque 3: galería ─────────────────────────────────────────────────── --}}
<div class="df-card">
  <div class="df-title">
    <i class="ti ti-camera"></i>Fotos de la limpieza de salida
    @if($fotos->count())<span style="color:#bbb;font-weight:400">{{ $fotos->count() }}</span>@endif
  </div>

  @if($fotos->count())
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px">
      @foreach($fotos as $foto)
        @php $url = asset('storage/' . $foto->file_foto); @endphp
        <div style="border-radius:8px;overflow:hidden;border:0.5px solid rgba(0,0,0,.08);cursor:zoom-in"
             onclick="ampliar({{ $loop->index }})" title="{{ $foto->tarea_nombre }}">
          <img src="{{ $url }}" alt="Foto de la limpieza" loading="lazy"
               style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
        </div>
      @endforeach
    </div>
  @else
    <p style="font-size:13px;color:#999;margin:0">Sin fotos.</p>
  @endif
</div>

</div>

{{-- Visor. Se navega con las flechas, con el teclado y con la rueda; se cierra pulsando fuera
     de la imagen o con Escape. Los controles paran la propagación para que pulsarlos no cuente
     como "pulsar fuera" y cierre el visor. --}}
<div id="df-lightbox" class="df-modal" onclick="cerrarVisor()">
  <button type="button" id="df-prev" onclick="event.stopPropagation(); mover(-1)"
          title="Anterior (←)" class="df-nav" style="left:16px">‹</button>

  <div onclick="event.stopPropagation()" style="display:flex;flex-direction:column;align-items:center;gap:8px">
    <img id="df-lightbox-img" src="" alt="" style="max-width:82vw;max-height:82vh;border-radius:8px">
    <div style="color:#fff;font-size:12px;text-align:center;text-shadow:0 1px 3px rgba(0,0,0,.6)">
      <span id="df-contador"></span>
      <span id="df-tarea" style="opacity:.75"></span>
    </div>
  </div>

  <button type="button" id="df-next" onclick="event.stopPropagation(); mover(1)"
          title="Siguiente (→)" class="df-nav" style="right:16px">›</button>
</div>

{{-- Modal de retención --}}
<div id="df-retener" class="df-modal">
  <div class="df-modal-box">
    <p style="font-weight:500;font-size:15px;margin:0 0 4px">Retener parte de la fianza</p>
    <p style="font-size:12px;color:#888;margin:0 0 14px">
      Fianza de la reserva: <strong>{{ $fmt($reserva->fianza) }}</strong>. La retención no puede superarla.
    </p>

    <label style="font-size:12px;color:#666;display:block;margin-bottom:3px">Importe a retener (€)</label>
    <input type="number" id="df-retencion" step="0.01" min="0.01" max="{{ (float) ($reserva->fianza ?? 0) }}"
           style="width:100%;font-size:13px;padding:6px 10px;border:0.5px solid rgba(0,0,0,.15);border-radius:6px;margin-bottom:12px">

    <label style="font-size:12px;color:#666;display:block;margin-bottom:3px">Motivo de la retención</label>
    <textarea id="df-motivo" rows="4" placeholder="Qué se ha encontrado y por qué se retiene"
              style="width:100%;font-size:13px;padding:6px 10px;border:0.5px solid rgba(0,0,0,.15);border-radius:6px;resize:vertical"></textarea>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem">
      <button type="button" class="df-btn" onclick="cerrarRetener()">Cancelar</button>
      <button type="button" class="df-btn" onclick="confirmarRetener()"
              style="border-color:#E3C9A3;background:#FDF8F1;color:#9A5B00">Confirmar</button>
    </div>
  </div>
</div>

<script>
const CSRF_DF = '{{ csrf_token() }}';
const BASE_DF = '{{ url($project->slug . "/dev-fianzas/" . $reserva->id) }}';

const DF_FOTOS = @json($fotos->map(fn($f) => [
    'url'   => asset('storage/' . $f->file_foto),
    'tarea' => $f->tarea_nombre,
])->values());

let dfIdx = 0;

function pintarVisor() {
    const f = DF_FOTOS[dfIdx];
    if (!f) return;
    document.getElementById('df-lightbox-img').src = f.url;
    document.getElementById('df-contador').textContent = (dfIdx + 1) + ' / ' + DF_FOTOS.length;
    document.getElementById('df-tarea').textContent = f.tarea ? ' · ' + f.tarea : '';
    // Sin ciclo: con una sola foto las dos flechas quedan desactivadas y se ve que no hay más.
    document.getElementById('df-prev').disabled = dfIdx === 0;
    document.getElementById('df-next').disabled = dfIdx === DF_FOTOS.length - 1;
}

function ampliar(i) {
    dfIdx = i;
    pintarVisor();
    document.getElementById('df-lightbox').classList.add('open');
}

function mover(paso) {
    const siguiente = dfIdx + paso;
    if (siguiente < 0 || siguiente >= DF_FOTOS.length) return;
    dfIdx = siguiente;
    pintarVisor();
}

function cerrarVisor() { document.getElementById('df-lightbox').classList.remove('open'); }

document.addEventListener('keydown', function (e) {
    const abierto = document.getElementById('df-lightbox')?.classList.contains('open');
    if (!abierto) return;
    if (e.key === 'ArrowLeft')  { e.preventDefault(); mover(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); mover(1); }
    if (e.key === 'Escape')     { cerrarVisor(); }
});
async function conforme() {
    if (!confirm('¿Aprobar la devolución completa de la fianza?')) return;

    const r = await fetch(BASE_DF + '/conforme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
    });
    const j = await r.json().catch(() => ({}));
    if (j.error) { alert(j.error); return; }
    location.href = '{{ route('vm.dev-fianzas', $project->slug) }}';
}

function abrirRetener()  { document.getElementById('df-retener').classList.add('open'); }
function cerrarRetener() { document.getElementById('df-retener').classList.remove('open'); }

async function confirmarRetener() {
    const retencion = document.getElementById('df-retencion').value;
    const motivo    = document.getElementById('df-motivo').value.trim();

    // Se valida aquí para no hacer el viaje, y otra vez en el servidor porque esto decide sobre
    // dinero y el navegador no es una autoridad.
    if (!retencion || parseFloat(retencion) <= 0) { alert('Indica el importe a retener.'); return; }
    if (!motivo) { alert('Indica el motivo de la retención.'); return; }

    const r = await fetch(BASE_DF + '/retener', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
        body: JSON.stringify({ retencion: retencion, motivo: motivo }),
    });
    const j = await r.json().catch(() => ({}));
    if (j.error) { alert(j.error); return; }
    if (j.errors) { alert(Object.values(j.errors).flat().join('\n')); return; }
    location.href = '{{ route('vm.dev-fianzas', $project->slug) }}';
}
</script>
</x-app-layout>
