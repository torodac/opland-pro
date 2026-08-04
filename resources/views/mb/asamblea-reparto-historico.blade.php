<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Histórico · {{ $asamblea->nombre }}</title>
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
.fila { background:#fff; border:0.5px solid rgba(0,0,0,.08); border-radius:14px; padding:14px 16px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.fila-info { min-width:0; }
.fila-nombre { font-size:15px; font-weight:600; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fila-detalle { font-size:12px; color:#888; margin:2px 0 0; }
.fila-hoja { font-size:13px; font-weight:700; color:#185fa5; flex-shrink:0; }
.btn-anular { flex-shrink:0; width:32px; height:32px; border:none; background:#FCEBEB; color:#A32D2D; border-radius:50%; font-size:18px; line-height:1; cursor:pointer; }
.vacio { text-align:center; color:#aaa; font-size:13px; padding:40px 20px; }
.modal-overlay { position:fixed; inset:0; z-index:60; display:none; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.show { display:flex; }
.modal-fondo { position:absolute; inset:0; background:rgba(0,0,0,.4); }
.modal-caja { position:relative; background:#fff; border-radius:14px; padding:20px; max-width:340px; width:100%; }
.modal-caja p { font-size:14px; margin:0 0 16px; }
.modal-botones { display:flex; gap:8px; }
.modal-botones button { flex:1; height:42px; border-radius:10px; border:none; font-size:14px; font-weight:600; cursor:pointer; }
.modal-cancelar { background:#f3f4f6; color:#444; }
.modal-confirmar { background:#A32D2D; color:#fff; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>{{ $asamblea->nombre }}</h1>
      <p>Histórico de reparto</p>
    </div>
  </div>

  <div id="lista-asignaciones">
    @forelse($asignaciones as $a)
    <div class="fila" data-id-viviendas="{{ $a->id_viviendas }}">
      <div class="fila-info">
        <p class="fila-nombre">{{ $a->nombre }}</p>
        <p class="fila-detalle">{{ \Illuminate\Support\Carbon::parse($a->fecha_entrega)->format('d/m/Y H:i') }}</p>
      </div>
      <span class="fila-hoja">Hoja {{ $a->numero_hoja }}</span>
      <button type="button" class="btn-anular" title="Anular asignación" onclick="pedirConfirmacion({{ $a->id_viviendas }}, '{{ addslashes($a->nombre) }}', {{ $a->numero_hoja }})">&times;</button>
    </div>
    @empty
    <p class="vacio" id="fila-vacia">Todavía no se ha repartido ninguna hoja.</p>
    @endforelse
  </div>
</div>

<nav class="bottom-nav">
  <a href="{{ route('mb.asamblea.reparto', $project->slug) }}">Reparto</a>
  <a href="{{ route('mb.asamblea.recuento', $project->slug) }}">Recuento</a>
  <a href="{{ route('mb.asamblea.reparto.historico', $project->slug) }}" class="active">Histórico</a>
</nav>

<div id="modal-anular" class="modal-overlay">
  <div class="modal-fondo" onclick="cerrarModal()"></div>
  <div class="modal-caja">
    <p id="modal-texto"></p>
    <div class="modal-botones">
      <button type="button" class="modal-cancelar" onclick="cerrarModal()">Cancelar</button>
      <button type="button" class="modal-confirmar" id="modal-confirmar-btn">Anular</button>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '{{ url($project->slug . "/asamblea/reparto") }}';
const ID_ASAMBLEA = {{ $asamblea->id }};
let idViviendaAAnular = null;

function pedirConfirmacion(idViviendas, nombre, numeroHoja) {
  idViviendaAAnular = idViviendas;
  document.getElementById('modal-texto').textContent = `¿Anular la hoja ${numeroHoja} asignada a ${nombre}?`;
  document.getElementById('modal-anular').classList.add('show');
}

function cerrarModal() {
  idViviendaAAnular = null;
  document.getElementById('modal-anular').classList.remove('show');
}

document.getElementById('modal-confirmar-btn').addEventListener('click', async function () {
  if (!idViviendaAAnular) return;
  const res = await fetch(`${BASE}/hoja`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, id_viviendas: idViviendaAAnular }),
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'No se pudo anular.'); return; }

  const fila = document.querySelector(`.fila[data-id-viviendas="${idViviendaAAnular}"]`);
  if (fila) fila.remove();

  if (!document.querySelector('.fila')) {
    document.getElementById('lista-asignaciones').innerHTML = '<p class="vacio" id="fila-vacia">Todavía no se ha repartido ninguna hoja.</p>';
  }

  cerrarModal();
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('{{ asset("mb/sw.js") }}', { scope: '{{ url("mb") }}/' });
}
</script>
</body>
</html>
