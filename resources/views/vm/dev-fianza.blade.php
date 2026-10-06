@php
    $fmt = fn($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.') . ' €';
    $pendiente = empty($reserva->estado_fianza);
@endphp

<x-app-layout :project="$project">
<x-slot name="header">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('vm.dev-fianzas', $project->slug) }}" class="hover:text-gray-700">Dev. fianzas</a>
        <span class="text-gray-300">/</span>
        <span class="font-medium text-gray-700">{{ $reserva->booking_id }}</span>
    </div>
</x-slot>

<style>
.df-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .df-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.df-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;display:flex;align-items:center;gap:6px}
.df-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.df-cell{background:rgba(0,0,0,.03);border-radius:8px;padding:.8rem}
.df-lbl{font-size:11px;color:#888;margin:0 0 3px}
.df-val{font-size:13px;font-weight:500;margin:0}
.df-modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:60}
.df-modal.open{display:flex}
.df-modal-box{background:#fff;border-radius:12px;padding:1.25rem;width:min(460px,92vw)}
.dark .df-modal-box{background:#1a1a1a}
.df-btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff}
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
      <p class="df-lbl">Fianza</p>
      <p class="df-val">{{ $fmt($reserva->fianza) }}</p>
    </div>

    <div class="df-cell">
      <p class="df-lbl">Comentarios</p>
      <p class="df-val">{{ $comentarios->count() }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Fotos</p>
      <p class="df-val">{{ $fotos->count() }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Retención</p>
      <p class="df-val">{{ $reserva->fianza_retencion !== null ? $fmt($reserva->fianza_retencion) : '—' }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Propiedad</p>
      <p class="df-val">{{ $reserva->propiedad ?: '—' }}</p>
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

  @if($comentarios->count())
    <div style="display:flex;flex-direction:column;gap:10px">
      @foreach($comentarios as $com)
        <div style="border-left:2px solid #e5e5e5;padding:2px 0 2px 10px">
          <div style="font-size:11px;color:#aaa;margin-bottom:2px;display:flex;gap:6px;flex-wrap:wrap">
            <a href="{{ route('vm.tarea', [$project->slug, 'limpieza', $com->tarea_id]) }}"
               style="color:#185FA5;text-decoration:none">{{ $com->tarea_nombre }}</a>
            <span>·</span>
            <span>{{ $com->fecha ? \Carbon\Carbon::parse($com->fecha)->translatedFormat('D j M · H:i') : '' }}</span>
          </div>
          <div style="font-size:13px;white-space:pre-line">{{ $com->comentario }}</div>
        </div>
      @endforeach
    </div>
  @else
    <p style="font-size:13px;color:#999;margin:0">
      Sin comentarios.
      @if($tareas->isEmpty())
        Esta reserva no tiene ninguna limpieza de salida asociada.
      @endif
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
             onclick="ampliar('{{ $url }}')" title="{{ $foto->tarea_nombre }}">
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

{{-- Lightbox --}}
<div id="df-lightbox" class="df-modal" onclick="this.classList.remove('open')">
  <img id="df-lightbox-img" src="" alt="" style="max-width:92vw;max-height:88vh;border-radius:8px">
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

function ampliar(url) {
    document.getElementById('df-lightbox-img').src = url;
    document.getElementById('df-lightbox').classList.add('open');
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
