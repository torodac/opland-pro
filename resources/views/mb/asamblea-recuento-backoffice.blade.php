<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.rc-stats-row{display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap}
.rc-stat{border-radius:12px;padding:11px 18px;border:1px solid;display:inline-flex;flex-direction:column;min-width:150px}
.rc-stat-num{font-size:1.5rem;font-weight:700;line-height:1.1}
.rc-stat-label{font-size:12px;font-weight:500}
.rc-stat-blue{background:#eff6ff;border-color:#93c5fd}
.rc-stat-blue .rc-stat-num{color:#1d4ed8}
.rc-stat-blue .rc-stat-label{color:#1e40af}
.rc-stat-green{background:#f0fdf4;border-color:#86efac}
.rc-stat-green .rc-stat-num{color:#15803d}
.rc-stat-green .rc-stat-label{color:#166534}

.rc-playstop-group{display:inline-flex;align-items:center;gap:8px}
.rc-btn{border-radius:10px;padding:11px 18px;border:1px solid #dce6ee;background:#fff;cursor:pointer;font-size:13px;font-weight:600}
.rc-btn:disabled{opacity:.4;cursor:default}
.rc-btn-play{color:#166534;border-color:#86efac}
.rc-btn-play:not(:disabled):hover{background:#f0fdf4}
.rc-btn-stop{color:#b91c1c;border-color:#fca5a5}
.rc-btn-stop:not(:disabled):hover{background:#fef2f2}
.rc-estado-dot{width:10px;height:10px;border-radius:50%;background:#cbd5e1;flex-shrink:0;margin-left:4px}
.rc-estado-dot.activo{background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.2)}
.rc-estado-label{font-size:12px;font-weight:600;color:#7e93a1}

.rc-scan-bar{margin-bottom:16px}
.rc-scan-input{width:100%;max-width:420px;border:1px solid #dce6ee;border-radius:10px;padding:10px 14px;font-size:13px}
.rc-scan-input:disabled{background:#f7fafc;color:#9ca3af}
.rc-scan-hint{font-size:11.5px;color:#9ca3af;margin-top:4px}

.rc-cards-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.rc-card{background:#fff;border:1px solid #dce6ee;border-left:3px solid #cbd5e1;border-radius:9px;padding:8px 10px;flex:0 1 170px;min-width:150px;transition:border-left-color .2s,background .2s}
.rc-card.gana-si{border-left-color:#22c55e;background:#fbfef9}
.rc-card.gana-no{border-left-color:#ef4444;background:#fef9f9}
.rc-card.empate{border-left-color:#f59e0b;background:#fffdf8}
.rc-pregunta{font-size:11px;font-weight:600;color:#16232b;margin:0 0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-bars{display:flex;gap:6px}
.rc-col{flex:1;text-align:center}
.rc-col-label{font-size:8.5px;color:#7e93a1;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}
.rc-col-num{font-size:15px;font-weight:700;font-variant-numeric:tabular-nums}
.rc-si .rc-col-num{color:#166534}
.rc-no .rc-col-num{color:#b91c1c}
.rc-abs .rc-col-num{color:#7e93a1}

.rl-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #dce6ee;border-radius:10px;overflow:hidden}
.rl-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rl-table th.num{text-align:center}
.rl-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6}
.rl-table td.voto{text-align:center;cursor:pointer;font-weight:700}
.rl-table td.voto.si{color:#166534}
.rl-table td.voto.no{color:#b91c1c}
.rl-table td.voto.vacio{color:#d1d5db;cursor:default}
.rl-table td.voto:not(.vacio):hover{background:#fef2f2}
.rl-hint{font-size:11.5px;color:#9ca3af;margin-bottom:10px}
</style>

<div class="rc-stats-row">
  <div class="rc-stat rc-stat-blue">
    <span class="rc-stat-num" id="stat-repartidas">{{ $totalHojas }}</span>
    <span class="rc-stat-label">hojas repartidas</span>
  </div>
  <div class="rc-stat rc-stat-green">
    <span class="rc-stat-num" id="stat-recontadas">{{ $hojasRecontadas }}</span>
    <span class="rc-stat-label">hojas recontadas</span>
  </div>
  <div class="rc-playstop-group">
    <button type="button" class="rc-btn rc-btn-play" id="btn-play" onclick="iniciarRecuento()">▶ Iniciar</button>
    <button type="button" class="rc-btn rc-btn-stop" id="btn-stop" onclick="detenerRecuento()" disabled>■ Finalizar</button>
    <span class="rc-estado-dot" id="estado-dot"></span>
    <span class="rc-estado-label" id="estado-label">Detenido</span>
  </div>
</div>

<div class="rc-scan-bar">
  <input type="text" id="scan-input" class="rc-scan-input" placeholder="Pulsa &quot;Iniciar recuento&quot; y escanea con el lector…" autocomplete="off" disabled>
  <p class="rc-scan-hint">El lector de códigos escribe aquí como si fuera un teclado. Mientras el recuento esté detenido, se ignora cualquier escaneo.</p>
</div>

<div class="rc-cards-row" id="cards-row">
  @foreach($preguntas as $p)
  @php
    $si = $tallies[$p->numero_pregunta]['S'] ?? 0;
    $no = $tallies[$p->numero_pregunta]['N'] ?? 0;
    $ganaClase = $si > $no ? 'gana-si' : ($no > $si ? 'gana-no' : 'empate');

    // El texto de la pregunta es HTML; para la tarjeta compacta solo nos interesa lo que
    // el propio texto marca en negrita (el resumen), no la frase completa.
    preg_match_all('/<b>(.*?)<\/b>/is', $p->texto, $negrita);
    $textoCorto = trim(implode(' ', array_map('trim', $negrita[1] ?? [])));
    if ($textoCorto === '') {
        $textoCorto = trim(strip_tags($p->texto));
    }
  @endphp
  <div class="rc-card {{ $ganaClase }}" data-pregunta="{{ $p->numero_pregunta }}" title="{{ strip_tags($p->texto) }}">
    <p class="rc-pregunta">{{ $p->numero_pregunta }}. {{ $textoCorto }}</p>
    <div class="rc-bars">
      <div class="rc-col rc-si">
        <div class="rc-col-label">Sí</div>
        <div class="rc-col-num" id="si-{{ $p->numero_pregunta }}">{{ $si }}</div>
      </div>
      <div class="rc-col rc-no">
        <div class="rc-col-label">No</div>
        <div class="rc-col-num" id="no-{{ $p->numero_pregunta }}">{{ $no }}</div>
      </div>
      <div class="rc-col rc-abs">
        <div class="rc-col-label">Abstención</div>
        <div class="rc-col-num" id="abs-{{ $p->numero_pregunta }}">{{ max($totalHojas - $si - $no, 0) }}</div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<p class="rl-hint">Clic en un voto para eliminarlo (por si se escaneó la pregunta equivocada). Las celdas en blanco no tienen voto registrado. La hoja con actividad más reciente aparece siempre arriba.</p>

<div style="overflow-x:auto">
<table class="rl-table">
  <thead><tr>
    <th>Hoja</th>
    <th>Vivienda</th>
    @foreach($preguntas as $p)
    <th class="num" title="{{ $p->texto }}">P{{ $p->numero_pregunta }}</th>
    @endforeach
  </tr></thead>
  <tbody id="listado-body"></tbody>
</table>
</div>

@php
$estadoInicial = [
    'totalHojas'      => $totalHojas,
    'hojasRecontadas' => $hojasRecontadas,
    'tallies'         => $tallies,
    'hojas'           => $hojas,
    'votosPorHoja'    => $votosPorHoja,
];
@endphp

<script>
const CSRF         = '{{ csrf_token() }}';
const BASE         = '{{ url($project->slug . "/asamblea/recuento") }}';
const ID_ASAMBLEA  = {{ $asamblea->id }};
const PREGUNTAS    = @json($preguntas);

let enRecuento  = false;
let pollTimer   = null;

const scanInput = document.getElementById('scan-input');
const btnPlay = document.getElementById('btn-play');
const btnStop = document.getElementById('btn-stop');
const estadoDot = document.getElementById('estado-dot');
const estadoLabel = document.getElementById('estado-label');
const listadoBody = document.getElementById('listado-body');

function pintarPregunta(numeroPregunta, si, no, totalHojas) {
  const abs = Math.max(totalHojas - si - no, 0);
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('si-' + numeroPregunta, si);
  set('no-' + numeroPregunta, no);
  set('abs-' + numeroPregunta, abs);

  const card = document.querySelector('.rc-card[data-pregunta="' + numeroPregunta + '"]');
  if (card) {
    card.classList.remove('gana-si', 'gana-no', 'empate');
    card.classList.add(si > no ? 'gana-si' : (no > si ? 'gana-no' : 'empate'));
  }
}

function celdaVoto(numeroHoja, numeroPregunta, voto) {
  if (voto === 'S') return `<td class="voto si" data-hoja="${numeroHoja}" data-pregunta="${numeroPregunta}">Sí</td>`;
  if (voto === 'N') return `<td class="voto no" data-hoja="${numeroHoja}" data-pregunta="${numeroPregunta}">No</td>`;
  return `<td class="voto vacio">—</td>`;
}

function renderListado(hojas, votosPorHoja) {
  if (!hojas.length) {
    listadoBody.innerHTML = `<tr><td colspan="${2 + PREGUNTAS.length}" style="text-align:center;color:#9ca3af;padding:24px">Todavía no hay hojas repartidas.</td></tr>`;
    return;
  }
  listadoBody.innerHTML = hojas.map(h => {
    const votos = votosPorHoja[h.numero_hoja] || {};
    const celdas = PREGUNTAS.map(p => celdaVoto(h.numero_hoja, p.numero_pregunta, votos[p.numero_pregunta])).join('');
    return `<tr><td>${h.numero_hoja}</td><td>${h.nombre}</td>${celdas}</tr>`;
  }).join('');
}

function renderTodo(data) {
  document.getElementById('stat-repartidas').textContent = data.totalHojas;
  document.getElementById('stat-recontadas').textContent = data.hojasRecontadas;
  PREGUNTAS.forEach(p => {
    const t = data.tallies[p.numero_pregunta] || {};
    pintarPregunta(p.numero_pregunta, t.S || 0, t.N || 0, data.totalHojas);
  });
  renderListado(data.hojas, data.votosPorHoja);
}

listadoBody.addEventListener('click', async function (ev) {
  const celda = ev.target.closest('td.voto:not(.vacio)');
  if (!celda) return;
  const hoja = celda.dataset.hoja;
  const pregunta = celda.dataset.pregunta;
  const texto = celda.textContent.trim();
  if (!confirm(`¿Eliminar el voto "${texto}" de la hoja ${hoja} en la pregunta ${pregunta}?`)) return;

  const res = await fetch(`${BASE}/voto`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, numero_hoja: hoja, numero_pregunta: pregunta }),
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'No se pudo eliminar.'); return; }
  renderTodo(data);
  if (enRecuento) refocarInput();
});

function refocarInput() {
  setTimeout(() => scanInput.focus(), 30);
}

scanInput.addEventListener('blur', () => { if (enRecuento) refocarInput(); });

// Se asume que el lector de códigos envía el texto seguido de Enter, igual que un teclado.
scanInput.addEventListener('keydown', async function (ev) {
  if (ev.key !== 'Enter') return;
  ev.preventDefault();
  const texto = scanInput.value.trim();
  scanInput.value = '';
  if (!texto || !enRecuento) return;

  const res = await fetch(`${BASE}/voto`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, texto }),
  });
  const data = await res.json();
  if (data.totalHojas !== undefined) renderTodo(data);
});

function iniciarRecuento() {
  if (enRecuento) return;
  enRecuento = true;
  btnPlay.disabled = true;
  btnStop.disabled = false;
  estadoDot.classList.add('activo');
  estadoLabel.textContent = 'En directo';
  scanInput.disabled = false;
  scanInput.focus();
  refrescar();
  pollTimer = setInterval(refrescar, 4000);
}

function detenerRecuento() {
  if (!enRecuento) return;
  enRecuento = false;
  btnPlay.disabled = false;
  btnStop.disabled = true;
  estadoDot.classList.remove('activo');
  estadoLabel.textContent = 'Detenido';
  scanInput.disabled = true;
  scanInput.blur();
  scanInput.value = '';
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

async function refrescar() {
  try {
    const res = await fetch(`${BASE}/estado?id_asamblea=${ID_ASAMBLEA}`, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    renderTodo(data);
  } catch (e) {}
}

renderTodo(@json($estadoInicial));
</script>

</x-app-layout>
