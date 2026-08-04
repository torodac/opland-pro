<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.rc-stat{border-radius:12px;padding:11px 16px;border:1px solid;display:inline-flex;align-items:center;gap:10px;margin-bottom:16px}
.rc-stat-num{font-size:1.25rem;font-weight:700}
.rc-stat-label{font-size:12px;font-weight:500}
.rc-card{background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:18px 20px;margin-bottom:14px}
.rc-pregunta{font-size:14px;font-weight:600;color:#16232b;margin:0 0 14px}
.rc-bars{display:flex;gap:24px}
.rc-col{flex:1;text-align:center}
.rc-col-label{font-size:11.5px;color:#7e93a1;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px}
.rc-col-num{font-size:28px;font-weight:700;font-variant-numeric:tabular-nums}
.rc-col-pct{font-size:12px;color:#9ca3af;margin-top:2px}
.rc-bar-track{height:6px;background:#f1f5f8;border-radius:3px;margin-top:8px;overflow:hidden}
.rc-bar-fill{height:100%;border-radius:3px}
.rc-si .rc-col-num{color:#166534}
.rc-no .rc-col-num{color:#b91c1c}
.rc-abs .rc-col-num{color:#7e93a1}
.rc-si .rc-bar-fill{background:#22c55e}
.rc-no .rc-bar-fill{background:#ef4444}
.rc-abs .rc-bar-fill{background:#cbd5e1}
</style>

<a class="rc-stat" style="background:#eff6ff;border-color:#93c5fd">
  <span class="rc-stat-num" style="color:#1d4ed8">{{ $totalHojas }}</span>
  <span class="rc-stat-label" style="color:#1e40af">hojas repartidas — la abstención se calcula por diferencia</span>
</a>

@foreach($preguntas as $p)
@php
  $si = $tallies[$p->numero_pregunta]['S'] ?? 0;
  $no = $tallies[$p->numero_pregunta]['N'] ?? 0;
  $abs = max($totalHojas - $si - $no, 0);
  $base = max($totalHojas, 1);
@endphp
<div class="rc-card" data-pregunta="{{ $p->numero_pregunta }}">
  <p class="rc-pregunta">{{ $p->numero_pregunta }}. {{ $p->texto }}</p>
  <div class="rc-bars">
    <div class="rc-col rc-si">
      <div class="rc-col-label">Sí</div>
      <div class="rc-col-num" id="si-{{ $p->numero_pregunta }}">{{ $si }}</div>
      <div class="rc-col-pct" id="si-pct-{{ $p->numero_pregunta }}">{{ round($si / $base * 100) }}%</div>
      <div class="rc-bar-track"><div class="rc-bar-fill" id="si-bar-{{ $p->numero_pregunta }}" style="width:{{ round($si / $base * 100) }}%"></div></div>
    </div>
    <div class="rc-col rc-no">
      <div class="rc-col-label">No</div>
      <div class="rc-col-num" id="no-{{ $p->numero_pregunta }}">{{ $no }}</div>
      <div class="rc-col-pct" id="no-pct-{{ $p->numero_pregunta }}">{{ round($no / $base * 100) }}%</div>
      <div class="rc-bar-track"><div class="rc-bar-fill" id="no-bar-{{ $p->numero_pregunta }}" style="width:{{ round($no / $base * 100) }}%"></div></div>
    </div>
    <div class="rc-col rc-abs">
      <div class="rc-col-label">Abstención</div>
      <div class="rc-col-num" id="abs-{{ $p->numero_pregunta }}">{{ $abs }}</div>
      <div class="rc-col-pct" id="abs-pct-{{ $p->numero_pregunta }}">{{ round($abs / $base * 100) }}%</div>
      <div class="rc-bar-track"><div class="rc-bar-fill" id="abs-bar-{{ $p->numero_pregunta }}" style="width:{{ round($abs / $base * 100) }}%"></div></div>
    </div>
  </div>
</div>
@endforeach

<script>
const BASE = '{{ url($project->slug . "/asamblea/recuento") }}';
const ID_ASAMBLEA = {{ $asamblea->id }};
const TOTAL_HOJAS = {{ $totalHojas }};

function pintar(pregunta, si, no) {
  const abs = Math.max(TOTAL_HOJAS - si - no, 0);
  const base = Math.max(TOTAL_HOJAS, 1);
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  const setW = (id, pct) => { const el = document.getElementById(id); if (el) el.style.width = pct + '%'; };

  set('si-' + pregunta, si);
  set('no-' + pregunta, no);
  set('abs-' + pregunta, abs);
  set('si-pct-' + pregunta, Math.round(si / base * 100) + '%');
  set('no-pct-' + pregunta, Math.round(no / base * 100) + '%');
  set('abs-pct-' + pregunta, Math.round(abs / base * 100) + '%');
  setW('si-bar-' + pregunta, Math.round(si / base * 100));
  setW('no-bar-' + pregunta, Math.round(no / base * 100));
  setW('abs-bar-' + pregunta, Math.round(abs / base * 100));
}

async function refrescar() {
  try {
    const res = await fetch(`${BASE}/tallies?id_asamblea=${ID_ASAMBLEA}`, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!data.tallies) return;
    Object.keys(data.tallies).forEach(pregunta => {
      pintar(pregunta, data.tallies[pregunta]['S'] || 0, data.tallies[pregunta]['N'] || 0);
    });
  } catch (e) {}
}

setInterval(refrescar, 4000);
</script>

</x-app-layout>
