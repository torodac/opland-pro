<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Reparto · {{ $asamblea->nombre }}</title>
<meta name="theme-color" content="#185fa5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Reparto MB">
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
.divider { display:flex; align-items:center; gap:8px; margin:12px 0; }
.divider div { flex:1; border-top:0.5px solid rgba(0,0,0,.12); }
.divider span { font-size:11px; color:#aaa; }
input[type=text], input[type=number] { width:100%; height:46px; font-size:16px; border-radius:10px; border:1px solid rgba(0,0,0,.15); padding:0 12px; background:#fff; }
input:focus { outline:none; border-color:#185fa5; box-shadow:0 0 0 3px rgba(24,95,165,.12); }
.sugerencias { position:relative; }
.sugerencias-lista { position:absolute; left:0; right:0; top:100%; margin-top:4px; background:#fff; border:1px solid rgba(0,0,0,.1); border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.1); z-index:10; max-height:240px; overflow-y:auto; }
.sugerencias-lista div { padding:12px; font-size:14px; border-bottom:0.5px solid rgba(0,0,0,.06); }
.sugerencias-lista div:last-child { border-bottom:none; }
.res-nombre { font-size:28px; font-weight:700; text-align:center; margin:0 0 12px; line-height:1.15; }
.res-nombre.con-deuda { color:#FF0000; }
.badge { display:block; text-align:center; font-size:15px; font-weight:600; padding:10px; border-radius:10px; margin:0 auto; }
.badge.sin { background:#EAF3DE; color:#27500A; }
.badge.con { background:#FCEBEB; color:#A32D2D; }
.hoja-actual { text-align:center; font-size:12px; color:#888; margin:10px 0 0; }
.aviso { margin-top:10px; font-size:13px; background:#FAEEDA; color:#633806; border-radius:10px; padding:10px 12px; }
button.primary { width:100%; height:48px; font-size:16px; font-weight:600; background:#185fa5; color:#fff; border:none; border-radius:10px; margin-top:12px; }
button.primary:active { background:#0c447c; }
button.primary:disabled { background:#cfd8e3; color:#8a97a8; }
button.primary.verde { background:#1a7f37; }
button.primary.verde:active { background:#146c2e; }
button.secundario { width:100%; height:42px; font-size:14px; background:#fff; color:#444; border:1px solid rgba(0,0,0,.15); border-radius:10px; margin-top:8px; }
button.secundario.naranja { background:#f97316; color:#fff; border:none; }
button.secundario.naranja:active { background:#ea580c; }
.confirmado { background:#EAF3DE; border-radius:14px; padding:18px; text-align:center; }
.confirmado p { font-size:16px; font-weight:600; color:#27500A; margin:0; }
.hint { font-size:11px; color:#aaa; text-align:center; margin-top:8px; }
.oculto { display:none; }
.debug-scan { font-size:12px; color:#A32D2D; background:#FCEBEB; border-radius:8px; padding:8px 10px; margin-top:8px; word-break:break-all; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>{{ $asamblea->nombre }}</h1>
      <p>Reparto de hojas de votación</p>
    </div>
  </div>

  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <h2 style="margin:0;">1. Identificar vivienda</h2>
      <button class="secundario naranja" id="btn-reiniciar" style="width:auto;height:34px;margin-top:0;padding:0 12px;font-size:12px;">Reiniciar</button>
    </div>
    <div id="slot-buscando">
      <div id="reader-group">
        <div id="reader"></div>
        <p class="hint" id="reader-hint">Apunta la cámara al QR de la tarjeta del socio</p>
        <div id="debug-scan" class="debug-scan oculto"></div>
      </div>
    </div>
    <div id="bloque-buscar-nombre">
      <div class="divider"><div></div><span>o busca por nombre o código</span><div></div></div>
      <div class="sugerencias">
        <input type="text" id="buscar-nombre" placeholder="Nombre de la vivienda o código de la tarjeta...">
        <div id="lista-sugerencias" class="sugerencias-lista oculto"></div>
      </div>
    </div>
  </div>

  <div id="panel-resultado" class="oculto">
    <div class="card">
      <p class="res-nombre" id="res-nombre"></p>
      <p class="res-nombre" id="res-id" style="margin:-6px 0 12px;"></p>
      <span class="badge" id="res-deuda"></span>
      <p class="hoja-actual" id="res-hoja-actual"></p>
      <p class="res-nombre oculto" id="res-hoja-escaneada" style="text-transform:uppercase;margin:10px 0 0;"></p>
    </div>

    <div class="card">
      <h2>2. Registrar hoja entregada</h2>
      <div id="slot-hoja"></div>
      <input type="number" id="input-hoja" placeholder="Número de hoja" style="margin-top:10px;">
      <div id="aviso-sobreescribe" class="aviso oculto"></div>
      <button class="primary verde" id="btn-guardar" disabled>Confirmar</button>
    </div>
  </div>

  <div id="panel-confirmado" class="confirmado oculto">
    <p id="confirmado-texto"></p>
    <button class="secundario" id="btn-siguiente">Siguiente persona</button>
  </div>
</div>

<nav class="bottom-nav">
  <a href="{{ route('mb.asamblea.reparto', $project->slug) }}" class="active">Reparto</a>
  <a href="{{ route('mb.asamblea.reparto.historico', $project->slug) }}">Histórico</a>
</nav>

<script src="{{ asset('vendor/html5-qrcode.min.js') }}"></script>
<script>
const ID_ASAMBLEA = {{ $asamblea->id }};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '{{ url($project->slug . "/asamblea/reparto") }}';

let paso = 'buscando'; // 'buscando' | 'hoja'
let viviendaActual = null;
let codigoVivienda = null; // texto exacto del QR que identificó la vivienda actual
let ultimoTexto = null;
let ultimoTextoTs = 0;

function headers(json) {
  const h = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' };
  if (json) h['Content-Type'] = 'application/json';
  return h;
}

async function buscarPorQr(codigo) {
  const res = await fetch(`${BASE}/vivienda?id_asamblea=${ID_ASAMBLEA}&qr=${encodeURIComponent(codigo)}`, { headers: headers() });
  const data = await res.json();
  if (!res.ok) {
    flashError(`Sin coincidencia. Código leído: "${codigo}" — apunta este valor exacto en el campo "Código QR" de la vivienda.`);
    return;
  }
  document.getElementById('debug-scan').classList.add('oculto');
  codigoVivienda = codigo;
  mostrarResultado(data);
}

async function buscarPorId(id) {
  const res = await fetch(`${BASE}/vivienda?id_asamblea=${ID_ASAMBLEA}&id=${id}`, { headers: headers() });
  const data = await res.json();
  if (!res.ok) { flashError(data.error || 'Vivienda no encontrada.'); return; }
  codigoVivienda = null; // encontrada por nombre, no por QR
  mostrarResultado(data);
}

function flashError(msg) {
  const reader = document.getElementById('reader');
  reader.style.outline = '4px solid #A32D2D';
  setTimeout(() => { reader.style.outline = 'none'; }, 700);
  const debug = document.getElementById('debug-scan');
  debug.textContent = msg;
  debug.classList.remove('oculto');
  console.warn(msg);
}

function moverLectorA(slotId, hint) {
  document.getElementById(slotId).appendChild(document.getElementById('reader-group'));
  document.getElementById('reader-hint').textContent = hint;
  document.getElementById('debug-scan').classList.add('oculto');
}

function mostrarResultado(v) {
  viviendaActual = v;
  paso = 'hoja';
  document.getElementById('panel-confirmado').classList.add('oculto');
  document.getElementById('res-nombre').textContent = v.nombre;
  document.getElementById('res-id').textContent = 'ID ' + v.id;
  document.getElementById('res-nombre').classList.toggle('con-deuda', v.deuda > 0);
  document.getElementById('res-id').classList.toggle('con-deuda', v.deuda > 0);
  const badge = document.getElementById('res-deuda');
  if (v.deuda > 0) {
    badge.className = 'badge con';
    badge.textContent = 'Con deuda — ' + v.deuda.toLocaleString('es-ES', { minimumFractionDigits: 2 }) + ' €';
  } else {
    badge.className = 'badge sin';
    badge.textContent = 'Sin deuda';
  }
  document.getElementById('res-hoja-actual').textContent = v.hoja_actual
    ? 'Hoja actual: ' + v.hoja_actual
    : 'Sin hoja asignada todavía';
  document.getElementById('aviso-sobreescribe').classList.add('oculto');
  document.getElementById('input-hoja').value = '';
  actualizarBotonGuardar();
  document.getElementById('panel-resultado').classList.remove('oculto');
  document.getElementById('lista-sugerencias').classList.add('oculto');
  document.getElementById('buscar-nombre').value = '';
  document.getElementById('bloque-buscar-nombre').classList.add('oculto');
  document.getElementById('res-hoja-escaneada').classList.add('oculto');
  document.getElementById('reader-group').classList.remove('oculto');
  document.getElementById('input-hoja').classList.remove('oculto');
  moverLectorA('slot-hoja', 'Escanea cualquier QR de la hoja física');
}

function reiniciar() {
  paso = 'buscando';
  viviendaActual = null;
  codigoVivienda = null;
  ultimoTexto = null;
  ultimoTextoTs = 0;
  document.getElementById('panel-resultado').classList.add('oculto');
  document.getElementById('panel-confirmado').classList.add('oculto');
  document.getElementById('lista-sugerencias').classList.add('oculto');
  document.getElementById('buscar-nombre').value = '';
  document.getElementById('input-hoja').value = '';
  document.getElementById('aviso-sobreescribe').classList.add('oculto');
  document.getElementById('bloque-buscar-nombre').classList.remove('oculto');
  document.getElementById('res-hoja-escaneada').classList.add('oculto');
  document.getElementById('reader-group').classList.remove('oculto');
  document.getElementById('input-hoja').classList.remove('oculto');
  actualizarBotonGuardar();
  moverLectorA('slot-buscando', 'Apunta la cámara al QR de la tarjeta del socio');
}
document.getElementById('btn-reiniciar').addEventListener('click', reiniciar);

function onQrDecodificado(texto) {
  const limpio = texto.trim();
  const ahora = Date.now();
  // El lector sigue leyendo el mismo QR varias veces por segundo mientras esté enfocado.
  // Ignoramos repeticiones del mismo texto en menos de 2s para no reprocesar el QR de la
  // vivienda como si fuera el de la hoja justo tras cambiar de paso.
  if (limpio === ultimoTexto && (ahora - ultimoTextoTs) < 2000) return;
  ultimoTexto = limpio;
  ultimoTextoTs = ahora;

  if (paso === 'buscando') {
    buscarPorQr(limpio);
  } else if (paso === 'hoja') {
    if (limpio === codigoVivienda) return; // es el QR de la tarjeta, no el de la hoja
    const m = limpio.match(/(\d+)/);
    if (m) {
      const numero = parseInt(m[1], 10);
      document.getElementById('input-hoja').value = numero;
      avisarSiSobreescribe();
      actualizarBotonGuardar();
      document.getElementById('reader-group').classList.add('oculto');
      document.getElementById('input-hoja').classList.add('oculto');
      const hojaEscaneada = document.getElementById('res-hoja-escaneada');
      hojaEscaneada.textContent = 'Hoja ' + numero;
      hojaEscaneada.classList.remove('oculto');
    } else {
      flashError(`No se ha encontrado ningún número en el QR leído: "${limpio}".`);
    }
  }
}

function avisarSiSobreescribe() {
  const numero = document.getElementById('input-hoja').value;
  const aviso = document.getElementById('aviso-sobreescribe');
  if (viviendaActual && viviendaActual.hoja_actual && String(viviendaActual.hoja_actual) !== String(numero)) {
    aviso.textContent = `Esta vivienda ya tenía la hoja ${viviendaActual.hoja_actual} asignada — se sustituirá y quedará archivada.`;
    aviso.classList.remove('oculto');
  } else {
    aviso.classList.add('oculto');
  }
}

function actualizarBotonGuardar() {
  const numero = document.getElementById('input-hoja').value;
  document.getElementById('btn-guardar').disabled = numero === '';
}

document.getElementById('input-hoja').addEventListener('input', function () {
  avisarSiSobreescribe();
  actualizarBotonGuardar();
});

// Búsqueda por nombre (fallback teclado)
let debounceTimer = null;
document.getElementById('buscar-nombre').addEventListener('input', function () {
  clearTimeout(debounceTimer);
  const q = this.value.trim();
  const lista = document.getElementById('lista-sugerencias');
  if (q.length < 2) { lista.classList.add('oculto'); return; }
  debounceTimer = setTimeout(async () => {
    const res = await fetch(`${BASE}/buscar-nombre?q=${encodeURIComponent(q)}`, { headers: headers() });
    const data = await res.json();
    if (!data.resultados || !data.resultados.length) { lista.classList.add('oculto'); return; }
    lista.innerHTML = data.resultados.map(v => `<div data-id="${v.id}">${v.nombre}</div>`).join('');
    lista.classList.remove('oculto');
    lista.querySelectorAll('div').forEach(el => {
      el.addEventListener('click', () => buscarPorId(el.dataset.id));
    });
    // En móvil el teclado puede tapar el desplegable si el campo queda bajo en pantalla;
    // lo subimos al centro de la parte visible en cuanto hay resultados que mostrar.
    setTimeout(() => {
      document.getElementById('buscar-nombre').scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 50);
  }, 250);
});

async function guardarHoja(numero, forzar) {
  const res = await fetch(`${BASE}/hoja`, {
    method: 'POST',
    headers: headers(true),
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, id_viviendas: viviendaActual.id, numero_hoja: numero, forzar: forzar ? 1 : 0 }),
  });
  const data = await res.json();

  if (res.status === 409 && data.error_hoja_en_uso) {
    if (confirm(`Hoja ya repartida a ${data.vivienda_en_uso}. ¿Continuar de todas formas?`)) {
      await guardarHoja(numero, true);
    }
    return;
  }
  if (!res.ok) { alert(data.error || 'No se pudo guardar.'); return; }

  const nombreVivienda = viviendaActual.nombre;
  document.getElementById('panel-resultado').classList.add('oculto');
  reiniciar();
  document.getElementById('confirmado-texto').textContent = `Hoja ${numero} asignada a ${nombreVivienda}`;
  document.getElementById('panel-confirmado').classList.remove('oculto');
}

document.getElementById('btn-guardar').addEventListener('click', function () {
  const numero = document.getElementById('input-hoja').value;
  if (!viviendaActual || !numero) return;
  guardarHoja(numero, false);
});

document.getElementById('btn-siguiente').addEventListener('click', function () {
  document.getElementById('panel-confirmado').classList.add('oculto');
});

// Cámara: un único lector continuo, cambia de propósito según "paso"
const lector = new Html5Qrcode('reader');
lector.start(
  { facingMode: 'environment' },
  { fps: 10, qrbox: { width: 150, height: 150 } },
  (texto) => onQrDecodificado(texto),
  () => {}
).catch((err) => {
  document.getElementById('reader').outerHTML = '<p class="hint">No se pudo acceder a la cámara. Usa la búsqueda por nombre.</p>';
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('{{ asset("mb/sw.js") }}', { scope: '{{ url("mb") }}/' });
}
</script>
</body>
</html>
