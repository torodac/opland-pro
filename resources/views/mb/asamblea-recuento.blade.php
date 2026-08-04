<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Recuento · {{ $asamblea->nombre }}</title>
<meta name="theme-color" content="#185fa5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Recuento MB">
<link rel="manifest" href="{{ asset('mb/manifest.json') }}">
<link rel="apple-touch-icon" href="{{ asset('mb/icon-192.png') }}">
<link rel="icon" href="{{ asset('mb/icon-192.png') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
* { box-sizing: border-box; }
body { margin:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background:#f5f5f4; color:#222; padding-bottom:calc(60px + env(safe-area-inset-bottom)); }
.wrap { max-width:480px; margin:0 auto; padding:12px 12px; }
.bottom-nav { position:fixed; left:0; right:0; bottom:0; display:flex; background:#fff; border-top:0.5px solid rgba(0,0,0,.1); padding-bottom:env(safe-area-inset-bottom); z-index:50; }
.bottom-nav a { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; padding:14px 0; font-size:13px; font-weight:600; color:#aaa; text-decoration:none; }
.bottom-nav a.active { color:#185fa5; }
.top { display:flex; align-items:center; justify-content:space-between; padding:10px 4px 14px; }
.top h1 { font-size:15px; font-weight:600; margin:0; }
.top p { font-size:12px; color:#888; margin:2px 0 0; }
.card { background:#fff; border:0.5px solid rgba(0,0,0,.08); border-radius:14px; padding:16px; margin-bottom:12px; }
.card h2 { font-size:13px; font-weight:600; color:#888; margin:0 0 10px; text-transform:uppercase; letter-spacing:.02em; }
#reader { width:50%; margin:0 auto; border-radius:10px; overflow:hidden; background:#000; }
#reader video { width:100% !important; border-radius:10px; }
.hint { font-size:11px; color:#aaa; text-align:center; margin-top:8px; }
.oculto { display:none; }
.feedback { font-size:13px; text-align:center; border-radius:10px; padding:10px 12px; margin-top:10px; font-weight:600; word-break:break-all; }
.feedback.ok { background:#EAF3DE; color:#27500A; }
.feedback.dup { background:#FAEEDA; color:#633806; }
.feedback.err { background:#FCEBEB; color:#A32D2D; }
.pregunta-row { padding:10px 0; border-bottom:0.5px solid rgba(0,0,0,.06); }
.pregunta-row:last-child { border-bottom:none; }
.pregunta-texto { font-size:13px; margin:0 0 8px; }
.pregunta-tallies { display:flex; gap:8px; }
.tally { flex:1; text-align:center; font-size:11px; color:#888; background:#f5f5f4; border-radius:8px; padding:8px 4px; }
.tally b { display:block; font-size:20px; margin-top:2px; }
.tally-si b { color:#27500A; }
.tally-no b { color:#A32D2D; }
.tally-abs b { color:#888; }
.total-hojas { font-size:12px; color:#888; text-align:center; margin:0 0 12px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>{{ $asamblea->nombre }}</h1>
      <p>Recuento de votos</p>
    </div>
  </div>

  <div class="card">
    <h2>Escanear votos</h2>
    <div id="reader"></div>
    <p class="hint">Pasa la cámara por cada QR de Sí/No — se cuenta solo, sin tocar nada</p>
    <div id="feedback" class="feedback oculto"></div>
  </div>

  <div class="card">
    <h2>Recuento en vivo</h2>
    <p class="total-hojas">{{ $totalHojas }} hojas repartidas — la abstención se calcula por diferencia</p>
    @foreach($preguntas as $p)
    <div class="pregunta-row">
      <p class="pregunta-texto"><b>{{ $p->numero_pregunta }}.</b> {{ $p->texto }}</p>
      <div class="pregunta-tallies">
        <span class="tally tally-si">Sí<b id="si-{{ $p->numero_pregunta }}">{{ $tallies[$p->numero_pregunta]['S'] ?? 0 }}</b></span>
        <span class="tally tally-no">No<b id="no-{{ $p->numero_pregunta }}">{{ $tallies[$p->numero_pregunta]['N'] ?? 0 }}</b></span>
        <span class="tally tally-abs">Abst.<b id="abs-{{ $p->numero_pregunta }}">{{ $totalHojas - (($tallies[$p->numero_pregunta]['S'] ?? 0) + ($tallies[$p->numero_pregunta]['N'] ?? 0)) }}</b></span>
      </div>
    </div>
    @endforeach
  </div>
</div>

<nav class="bottom-nav">
  <a href="{{ route('mb.asamblea.reparto', $project->slug) }}">Reparto</a>
  <a href="{{ route('mb.asamblea.recuento', $project->slug) }}" class="active">Recuento</a>
  <a href="{{ route('mb.asamblea.reparto.historico', $project->slug) }}">Histórico</a>
</nav>

<script src="{{ asset('vendor/html5-qrcode.min.js') }}"></script>
<script>
const ID_ASAMBLEA = {{ $asamblea->id }};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '{{ url($project->slug . "/asamblea/recuento") }}';
const TOTAL_HOJAS = {{ $totalHojas }};

let ultimoTexto = null;
let ultimoTextoTs = 0;

function mostrarFeedback(clase, texto) {
  const el = document.getElementById('feedback');
  el.className = 'feedback ' + clase;
  el.textContent = texto;
  clearTimeout(window._feedbackTimer);
  window._feedbackTimer = setTimeout(() => el.classList.add('oculto'), 2500);
}

function pintarTallies(tallies) {
  Object.keys(tallies).forEach(pregunta => {
    const si = tallies[pregunta]['S'] || 0;
    const no = tallies[pregunta]['N'] || 0;
    const elSi = document.getElementById('si-' + pregunta);
    const elNo = document.getElementById('no-' + pregunta);
    const elAbs = document.getElementById('abs-' + pregunta);
    if (elSi) elSi.textContent = si;
    if (elNo) elNo.textContent = no;
    if (elAbs) elAbs.textContent = Math.max(TOTAL_HOJAS - si - no, 0);
  });
}

async function registrarVoto(texto) {
  const res = await fetch(`${BASE}/voto`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, texto }),
  });
  const data = await res.json();

  if (!res.ok) {
    mostrarFeedback('err', data.error + (data.texto ? ` ("${data.texto}")` : ''));
    return;
  }
  if (data.duplicado) {
    mostrarFeedback('dup', `Hoja ${data.numero_hoja}, pregunta ${data.numero_pregunta}: ya estaba contada`);
    return;
  }
  mostrarFeedback('ok', `Hoja ${data.numero_hoja} · pregunta ${data.numero_pregunta} · ${data.voto === 'S' ? 'Sí' : 'No'}`);
  pintarTallies(data.tallies);
}

function onQrDecodificado(texto) {
  const limpio = texto.trim();
  const ahora = Date.now();
  // Igual que en reparto: ignoramos repeticiones del mismo QR en menos de 1.5s para no
  // contar dos veces el mismo código mientras la cámara se demora sobre él.
  if (limpio === ultimoTexto && (ahora - ultimoTextoTs) < 1500) return;
  ultimoTexto = limpio;
  ultimoTextoTs = ahora;
  registrarVoto(limpio);
}

const lector = new Html5Qrcode('reader');
lector.start(
  { facingMode: 'environment' },
  { fps: 10, qrbox: { width: 150, height: 150 } },
  (texto) => onQrDecodificado(texto),
  () => {}
).catch((err) => {
  document.getElementById('reader').outerHTML = '<p class="hint">No se pudo acceder a la cámara.</p>';
});

// Refresco periódico por si hay varios móviles recontando a la vez.
setInterval(async () => {
  try {
    const res = await fetch(`${BASE}/tallies?id_asamblea=${ID_ASAMBLEA}`, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (data.tallies) pintarTallies(data.tallies);
  } catch (e) {}
}, 4000);

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('{{ asset("mb/sw.js") }}', { scope: '{{ url("mb") }}/' });
}
</script>
</body>
</html>
