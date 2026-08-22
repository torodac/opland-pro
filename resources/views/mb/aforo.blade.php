<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Gestión MB</title>
<meta name="theme-color" content="#185fa5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Mareny Blau">
<link rel="manifest" href="{{ asset('mb/manifest.json') }}">
<link rel="apple-touch-icon" href="{{ asset('mb/icon-192.png') }}">
<link rel="icon" href="{{ asset('mb/icon-192.png') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
* { box-sizing: border-box; }
body { margin:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background:#f5f5f4; color:#222; padding-bottom:calc(60px + env(safe-area-inset-bottom)); }
.bottom-nav { position:fixed; left:0; right:0; bottom:0; display:flex; background:#fff; border-top:0.5px solid rgba(0,0,0,.1); padding-bottom:env(safe-area-inset-bottom); z-index:50; }
.bottom-nav a { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; padding:14px 0; font-size:13px; font-weight:600; color:#aaa; text-decoration:none; }
.bottom-nav a.active { color:#185fa5; }
.wrap { max-width:420px; margin:0 auto; padding:16px 14px 24px; }
.top { display:flex; align-items:center; gap:10px; padding:6px 2px 16px; }
.top img { width:36px; height:36px; border-radius:8px; }
.top h1 { font-size:16px; font-weight:700; margin:0; }
.card { background:#fff; border:0.5px solid rgba(0,0,0,.08); border-radius:14px; padding:16px; margin-bottom:14px; }
.campo-label { font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:.02em; margin-bottom:6px; }
.campo-valor { width:100%; height:52px; font-size:26px; font-weight:700; text-align:center; border-radius:10px; border:1.5px solid rgba(0,0,0,.12); background:#fafafa; letter-spacing:.05em; }
.campo-valor.activo { border-color:#185fa5; background:#fff; box-shadow:0 0 0 3px rgba(24,95,165,.12); }
.campo-valor.vacio { color:#c2c2c2; }
.info-vivienda { text-align:center; margin:14px 0; }
.info-vivienda .nombre { font-size:20px; font-weight:700; margin:0; }
.info-vivienda .propietario { font-size:13px; color:#888; margin:2px 0 0; }
.info-vivienda .total { display:inline-block; margin-top:10px; font-size:14px; font-weight:600; background:#EAF3DE; color:#27500A; padding:8px 14px; border-radius:10px; }
.aviso { margin-top:10px; font-size:13px; background:#FCEBEB; color:#A32D2D; border-radius:10px; padding:10px 12px; text-align:center; }
.confirmado { background:#EAF3DE; border-radius:14px; padding:16px; text-align:center; margin-bottom:14px; }
.confirmado p { font-size:15px; font-weight:600; color:#27500A; margin:0; }
.teclado { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; }
.tecla { height:56px; font-size:20px; font-weight:600; background:#fff; border:0.5px solid rgba(0,0,0,.1); border-radius:10px; color:#222; }
.tecla:active { background:#eee; }
.tecla.borrar { color:#A32D2D; font-size:16px; }
.tecla.enter { background:#185fa5; color:#fff; font-size:13px; }
.tecla.enter:active { background:#0c447c; }
.tecla.enter:disabled { background:#cfd8e3; color:#8a97a8; }
.hint { font-size:11px; color:#aaa; text-align:center; margin-top:10px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <img src="{{ asset('mb/icon-192.png') }}" alt="">
        <h1>Gestión MB</h1>
    </div>

    <div class="card">
        <div class="campo-label">Tarjeta</div>
        <div id="valor-tarjeta" class="campo-valor activo vacio">‎</div>
    </div>

    <div id="bloque-vivienda" class="card" style="display:none">
        <div class="info-vivienda">
            <p class="nombre" id="v-nombre"></p>
            <p class="propietario" id="v-propietario"></p>
            <div class="total">Invitados ya registrados: <span id="v-total">0</span></div>
        </div>
        <div class="campo-label">Personas que entran ahora</div>
        <div id="valor-personas" class="campo-valor vacio">‎</div>
    </div>

    <div id="aviso" class="aviso" style="display:none"></div>
    <div id="confirmado" class="confirmado" style="display:none">
        <p id="confirmado-texto"></p>
    </div>

    <div class="teclado" id="teclado">
        <button type="button" class="tecla" data-d="1">1</button>
        <button type="button" class="tecla" data-d="2">2</button>
        <button type="button" class="tecla" data-d="3">3</button>
        <button type="button" class="tecla" data-d="4">4</button>
        <button type="button" class="tecla" data-d="5">5</button>
        <button type="button" class="tecla" data-d="6">6</button>
        <button type="button" class="tecla" data-d="7">7</button>
        <button type="button" class="tecla" data-d="8">8</button>
        <button type="button" class="tecla" data-d="9">9</button>
        <button type="button" class="tecla borrar" id="tecla-borrar">⌫</button>
        <button type="button" class="tecla" data-d="0">0</button>
        <button type="button" class="tecla enter" id="tecla-enter" disabled>Guardar</button>
    </div>

    <p class="hint">Teclea el número de tarjeta (4 dígitos)</p>
</div>

<div class="bottom-nav">
    <a href="{{ url($project->slug . '/asamblea/reparto') }}">Reparto</a>
    <a href="{{ url($project->slug . '/gestion_mb') }}" class="active">Aforo</a>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '{{ url($project->slug . "/gestion_mb") }}';

// foco: 'tarjeta' o 'personas'. Solo hay campo 'personas' activo tras identificar la tarjeta.
let foco = 'tarjeta';
let tarjeta = '';
let personas = '';
let idTarjetaActual = null;

const elTarjeta = document.getElementById('valor-tarjeta');
const elPersonas = document.getElementById('valor-personas');
const elBloqueVivienda = document.getElementById('bloque-vivienda');
const elAviso = document.getElementById('aviso');
const elConfirmado = document.getElementById('confirmado');
const elEnter = document.getElementById('tecla-enter');

function pintar() {
    elTarjeta.textContent = tarjeta || '‎';
    elTarjeta.classList.toggle('vacio', tarjeta === '');
    elTarjeta.classList.toggle('activo', foco === 'tarjeta');

    elPersonas.textContent = personas || '‎';
    elPersonas.classList.toggle('vacio', personas === '');
    elPersonas.classList.toggle('activo', foco === 'personas');

    elEnter.disabled = !(foco === 'personas' && personas !== '' && parseInt(personas, 10) > 0);
}

function ocultarAvisoYConfirmado() {
    elAviso.style.display = 'none';
    elConfirmado.style.display = 'none';
}

function reset() {
    tarjeta = '';
    personas = '';
    idTarjetaActual = null;
    foco = 'tarjeta';
    elBloqueVivienda.style.display = 'none';
    pintar();
}

async function buscarTarjeta() {
    ocultarAvisoYConfirmado();
    try {
        const res = await fetch(`${BASE}/tarjeta?codigo=${encodeURIComponent(tarjeta)}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok) {
            elAviso.textContent = data.error || 'Tarjeta no reconocida.';
            elAviso.style.display = 'block';
            tarjeta = '';
            pintar();
            return;
        }
        idTarjetaActual = data.id_tarjetas;
        document.getElementById('v-nombre').textContent = data.vivienda;
        document.getElementById('v-propietario').textContent = data.propietario || '';
        document.getElementById('v-total').textContent = data.total_actual;
        elBloqueVivienda.style.display = 'block';
        foco = 'personas';
        pintar();
    } catch (e) {
        elAviso.textContent = 'Error de red al buscar la tarjeta.';
        elAviso.style.display = 'block';
        tarjeta = '';
        pintar();
    }
}

async function guardar() {
    if (!idTarjetaActual || !personas || parseInt(personas, 10) < 1) return;
    elEnter.disabled = true;
    try {
        const res = await fetch(`${BASE}/registro`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ codigo: tarjeta, personas: parseInt(personas, 10) }),
        });
        const data = await res.json();
        if (!res.ok) {
            elAviso.textContent = data.error || 'No se ha podido guardar.';
            elAviso.style.display = 'block';
            elEnter.disabled = false;
            return;
        }
        document.getElementById('confirmado-texto').textContent =
            `Guardado. Total invitados de esta vivienda: ${data.total_actual}.`;
        elConfirmado.style.display = 'block';
        reset();
    } catch (e) {
        elAviso.textContent = 'Error de red al guardar.';
        elAviso.style.display = 'block';
        elEnter.disabled = false;
    }
}

document.getElementById('teclado').addEventListener('click', (ev) => {
    const btn = ev.target.closest('button');
    if (!btn) return;

    if (btn.id === 'tecla-enter') {
        guardar();
        return;
    }
    if (btn.id === 'tecla-borrar') {
        if (foco === 'tarjeta') tarjeta = tarjeta.slice(0, -1);
        else personas = personas.slice(0, -1);
        pintar();
        return;
    }

    const d = btn.dataset.d;
    if (d === undefined) return;

    if (foco === 'tarjeta') {
        if (tarjeta.length >= 4) return;
        tarjeta += d;
        pintar();
        if (tarjeta.length === 4) buscarTarjeta();
    } else {
        if (personas.length >= 3) return;
        personas += d;
        pintar();
    }
});

// Enter/backspace fisicos, por si se usa teclado real.
document.addEventListener('keydown', (ev) => {
    if (ev.key >= '0' && ev.key <= '9') {
        document.querySelector(`.tecla[data-d="${ev.key}"]`)?.click();
    } else if (ev.key === 'Backspace') {
        document.getElementById('tecla-borrar').click();
    } else if (ev.key === 'Enter') {
        if (!elEnter.disabled) guardar();
    }
});

pintar();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('{{ asset("mb/sw.js") }}', { scope: '{{ url("mb") }}/' });
}
</script>
</body>
</html>
