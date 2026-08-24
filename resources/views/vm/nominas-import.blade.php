<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.ni-drop{border:2px dashed #dce6ee;border-radius:14px;padding:40px 20px;text-align:center;background:#fff;cursor:pointer;transition:border-color .15s,background .15s}
.ni-drop.dragover{border-color:#185fa5;background:#eaf3fb}
.ni-drop p{margin:0;color:#7e93a1;font-size:13.5px}
.ni-drop strong{color:#185fa5}
#ni-file-input{display:none}

.ni-error{background:#fdecec;color:#b3261e;border-radius:10px;padding:12px 14px;font-size:13px;margin-top:16px}

.ni-result{display:none;background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:20px;margin-top:16px}
.ni-result.show{display:block}
.ni-result h3{font-size:14px;margin:0 0 12px}
.ni-result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.ni-result-item{background:#f7fafc;border-radius:10px;padding:10px 12px;text-align:center}
.ni-result-item .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
.ni-result-item .l{font-size:11px;color:#7e93a1}
.ni-result-item.importadas .n{color:#166534}
.ni-result-item.actualizadas .n{color:#185fa5}
.ni-result-item.sin-match .n{color:#a15c07}

.ni-sin-match{margin-top:16px}
.ni-sin-match table{width:100%;border-collapse:collapse;font-size:12.5px}
.ni-sin-match th{text-align:left;padding:7px 8px;color:#7e93a1;font-weight:600;border-bottom:1px solid #eaf1f6}
.ni-sin-match td{padding:7px 8px;border-bottom:1px solid #f7fafc}

.ni-progress-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:70;display:none;align-items:center;justify-content:center}
.ni-progress-overlay.show{display:flex}
.ni-progress-box{background:#fff;border-radius:14px;padding:28px 36px;text-align:center;box-shadow:0 20px 45px -12px rgba(15,23,42,.35)}
.ni-spinner{width:32px;height:32px;border:3px solid #dce6ee;border-top-color:#185fa5;border-radius:50%;margin:0 auto 14px;animation:ni-spin 0.8s linear infinite}
@keyframes ni-spin{to{transform:rotate(360deg)}}
.ni-progress-box p{margin:0;font-size:13px;color:#374151;font-weight:600}

.ni-modal{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.45)}
.ni-modal.show{display:flex}
.ni-modal-card{background:#fff;border-radius:14px;padding:24px;width:100%;max-width:520px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 45px -12px rgba(15,23,42,.35)}
.ni-modal-card h3{font-size:15px;margin:0 0 14px}
.ni-modal-warn{background:#fff7ed;border:1px solid #fdba74;color:#9a3412;border-radius:10px;padding:12px 14px;font-size:12.5px;margin-top:14px}
.ni-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}
.ni-btn{border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer}
.ni-btn-cancelar{background:#f1f5f9;color:#475569}
.ni-btn-continuar{background:#185fa5;color:#fff}
.ni-btn-continuar:disabled{opacity:.5;cursor:default}
</style>

<p style="font-size:12.5px;color:#7e93a1;margin:0 0 16px">
    Sube el PDF "Resumen Contable" del mes. Se cruza cada trabajador por NIF con <code>vm_usuarios.dni</code> y se guarda en Nóminas (Devengado, Líquido, Coste Total). Si ya existe una nómina para ese trabajador y mes, se actualiza en vez de duplicarse. Antes de guardar nada se muestra un resumen para confirmar.
</p>

<div class="ni-drop" id="ni-drop">
  <p><strong>Arrastra aquí</strong> el PDF del resumen contable (o haz clic para elegirlo)</p>
  <input type="file" id="ni-file-input" accept=".pdf">
</div>

<div class="ni-error" id="ni-error" style="display:none"></div>

{{-- Meses ya importados -- mismo patrón que la tabla de períodos de /vm/pyg_form --}}
@if(count($meses) > 0)
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:24px;">
    <thead>
        <tr style="border-bottom:2px solid #e5e7eb;">
            <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Mes</th>
            <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Nóminas</th>
            <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Devengado</th>
            <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Líquido</th>
            <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Coste Total</th>
        </tr>
    </thead>
    <tbody>
    @foreach($meses as $m)
    <tr style="border-bottom:1px solid #f3f4f6;">
        <td style="padding:8px 10px;font-weight:600;color:#111827;">
            {{ \Carbon\Carbon::parse($m->mes)->translatedFormat('F Y') }}
        </td>
        <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($m->num_nominas, 0, ',', '.') }}</td>
        <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($m->devengado, 2, ',', '.') }} €</td>
        <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($m->liquido, 2, ',', '.') }} €</td>
        <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($m->coste_total, 2, ',', '.') }} €</td>
    </tr>
    @endforeach
    </tbody>
</table>
@else
<p style="color:#9ca3af;font-size:13px;text-align:center;margin:2rem 0;">No hay meses importados todavía.</p>
@endif

<div class="ni-result" id="ni-result">
  <h3>Importación completada</h3>
  <div class="ni-result-grid">
    <div class="ni-result-item importadas"><div class="n" id="r-importadas">0</div><div class="l">nóminas nuevas</div></div>
    <div class="ni-result-item actualizadas"><div class="n" id="r-actualizadas">0</div><div class="l">actualizadas</div></div>
    <div class="ni-result-item sin-match"><div class="n" id="r-sin-match">0</div><div class="l">sin usuario (NIF no encontrado)</div></div>
  </div>
  <div class="ni-sin-match" id="ni-sin-match-wrap" style="display:none">
    <h3 style="margin-top:16px">Sin coincidencia — revisar a mano</h3>
    <table>
      <thead><tr><th>NIF</th><th>Nombre (en el PDF)</th><th style="text-align:right">Devengado</th></tr></thead>
      <tbody id="ni-sin-match-body"></tbody>
    </table>
  </div>
</div>

{{-- Modal de previsualización: se muestra ANTES de escribir nada, con el resumen de cuántas se
     van a crear/actualizar y, sobre todo, quién no tiene usuario en Opland -- el usuario decide
     si continúa o para aquí, igual que el "Ejecutar" de mb/movs_mapeo. --}}
<div class="ni-modal" id="ni-modal">
  <div class="ni-modal-card">
    <h3 id="ni-modal-titulo">Revisar antes de importar</h3>
    <div id="ni-modal-body"></div>
    <div class="ni-modal-actions">
      <button type="button" class="ni-btn ni-btn-cancelar" id="ni-btn-cancelar">Cancelar</button>
      <button type="button" class="ni-btn ni-btn-continuar" id="ni-btn-continuar">Continuar</button>
    </div>
  </div>
</div>

<div class="ni-progress-overlay" id="ni-progress-overlay">
  <div class="ni-progress-box">
    <div class="ni-spinner"></div>
    <p id="ni-progress-texto">Leyendo PDF…</p>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/nominas_import") }}';

const drop = document.getElementById('ni-drop');
const fileInput = document.getElementById('ni-file-input');
const overlay = document.getElementById('ni-progress-overlay');
const overlayTexto = document.getElementById('ni-progress-texto');
const errorBox = document.getElementById('ni-error');
const resultBox = document.getElementById('ni-result');
const modal = document.getElementById('ni-modal');
const modalBody = document.getElementById('ni-modal-body');
const btnContinuar = document.getElementById('ni-btn-continuar');
const btnCancelar = document.getElementById('ni-btn-cancelar');

let ficheroActual = null;

drop.addEventListener('click', () => fileInput.click());
drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop', (e) => {
  e.preventDefault();
  drop.classList.remove('dragover');
  if (e.dataTransfer.files.length) previsualizarFichero(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => {
  if (fileInput.files.length) previsualizarFichero(fileInput.files[0]);
  fileInput.value = '';
});
btnCancelar.addEventListener('click', () => { modal.classList.remove('show'); ficheroActual = null; });
btnContinuar.addEventListener('click', aplicarFichero);

function fmtEuro(v) {
  return Number(v).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function tablaSinMatch(sinMatch) {
  if (!sinMatch.length) return '';
  return `
    <div class="ni-modal-warn">
      <strong>${sinMatch.length} trabajador${sinMatch.length === 1 ? '' : 'es'} del PDF no ${sinMatch.length === 1 ? 'existe' : 'existen'} en Opland</strong> (NIF sin ningún usuario con ese DNI) — no se van a importar. Revísalos y, si hace falta, dalos de alta antes de continuar.
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:10px">
      <thead><tr><th style="text-align:left;padding:5px 6px;color:#7e93a1">NIF</th><th style="text-align:left;padding:5px 6px;color:#7e93a1">Nombre (PDF)</th><th style="text-align:right;padding:5px 6px;color:#7e93a1">Devengado</th></tr></thead>
      <tbody>
        ${sinMatch.map(f => `<tr><td style="padding:5px 6px;border-top:1px solid #f3f4f6">${f.nif}</td><td style="padding:5px 6px;border-top:1px solid #f3f4f6">${f.nombre}</td><td style="text-align:right;padding:5px 6px;border-top:1px solid #f3f4f6">${fmtEuro(f.devengado)}</td></tr>`).join('')}
      </tbody>
    </table>
  `;
}

function tablaAnomalos(anomalos) {
  if (!anomalos.length) return '';
  return `
    <div class="ni-modal-warn" style="background:#fdecec;border-color:#f3b0b0;color:#8a2424;">
      <strong>${anomalos.length} valor${anomalos.length === 1 ? '' : 'es'} anómalo${anomalos.length === 1 ? '' : 's'} detectado${anomalos.length === 1 ? '' : 's'}</strong> — la relación entre Devengado, Líquido y Coste Total no encaja con el resto del PDF. Puede ser un dato real inusual o un error de lectura del PDF; revísalos antes de continuar.
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:10px">
      <thead><tr>
        <th style="text-align:left;padding:5px 6px;color:#7e93a1">Nombre (PDF)</th>
        <th style="text-align:right;padding:5px 6px;color:#7e93a1">Devengado</th>
        <th style="text-align:right;padding:5px 6px;color:#7e93a1">Líquido</th>
        <th style="text-align:right;padding:5px 6px;color:#7e93a1">Coste Total</th>
        <th style="text-align:left;padding:5px 6px;color:#7e93a1">Motivo</th>
      </tr></thead>
      <tbody>
        ${anomalos.map(a => `<tr>
          <td style="padding:5px 6px;border-top:1px solid #f3f4f6">${a.nombre}</td>
          <td style="text-align:right;padding:5px 6px;border-top:1px solid #f3f4f6">${fmtEuro(a.devengado)}</td>
          <td style="text-align:right;padding:5px 6px;border-top:1px solid #f3f4f6">${fmtEuro(a.liquido)}</td>
          <td style="text-align:right;padding:5px 6px;border-top:1px solid #f3f4f6">${fmtEuro(a.coste_total)}</td>
          <td style="padding:5px 6px;border-top:1px solid #f3f4f6">${a.motivos.join('; ')}</td>
        </tr>`).join('')}
      </tbody>
    </table>
  `;
}

async function previsualizarFichero(file) {
  errorBox.style.display = 'none';
  resultBox.classList.remove('show');
  ficheroActual = file;
  overlayTexto.textContent = 'Leyendo PDF…';
  overlay.classList.add('show');

  const fd = new FormData();
  fd.append('file', file);

  try {
    const res = await fetch(`${BASE}/previsualizar`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    overlay.classList.remove('show');

    if (!data.ok) {
      errorBox.textContent = data.error || 'Error al leer el fichero.';
      errorBox.style.display = 'block';
      ficheroActual = null;
      return;
    }

    modalBody.innerHTML = `
      <p style="font-size:13px;color:#374151;margin:0">De <strong>${data.total_filas}</strong> trabajadores leídos en el PDF:</p>
      <ul style="font-size:13px;color:#374151;margin:8px 0 0;padding-left:20px">
        <li><strong style="color:#166534">${data.importadas}</strong> nóminas nuevas</li>
        <li><strong style="color:#185fa5">${data.actualizadas}</strong> ya existían y se actualizarían</li>
        <li><strong style="color:#a15c07">${data.sin_match.length}</strong> sin usuario en Opland</li>
        <li><strong style="color:#b3261e">${data.anomalos.length}</strong> valor${data.anomalos.length === 1 ? '' : 'es'} anómalo${data.anomalos.length === 1 ? '' : 's'}</li>
      </ul>
      ${tablaSinMatch(data.sin_match)}
      ${tablaAnomalos(data.anomalos)}
    `;
    btnContinuar.disabled = (data.importadas + data.actualizadas) === 0;
    modal.classList.add('show');
  } catch (e) {
    overlay.classList.remove('show');
    errorBox.textContent = 'Error de red al leer el fichero.';
    errorBox.style.display = 'block';
    ficheroActual = null;
  }
}

async function aplicarFichero() {
  if (!ficheroActual) return;
  modal.classList.remove('show');
  overlayTexto.textContent = 'Guardando nóminas…';
  overlay.classList.add('show');

  const fd = new FormData();
  fd.append('file', ficheroActual);

  try {
    const res = await fetch(`${BASE}/aplicar`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    overlay.classList.remove('show');
    ficheroActual = null;

    if (!data.ok) {
      errorBox.textContent = data.error || 'Error al guardar las nóminas.';
      errorBox.style.display = 'block';
      return;
    }

    document.getElementById('r-importadas').textContent = data.importadas;
    document.getElementById('r-actualizadas').textContent = data.actualizadas;
    document.getElementById('r-sin-match').textContent = data.sin_match.length;

    const wrap = document.getElementById('ni-sin-match-wrap');
    const body = document.getElementById('ni-sin-match-body');
    if (data.sin_match.length) {
      body.innerHTML = data.sin_match.map(f => `
        <tr><td>${f.nif}</td><td>${f.nombre}</td><td style="text-align:right">${fmtEuro(f.devengado)}</td></tr>
      `).join('');
      wrap.style.display = 'block';
    } else {
      wrap.style.display = 'none';
    }

    resultBox.classList.add('show');
  } catch (e) {
    overlay.classList.remove('show');
    ficheroActual = null;
    errorBox.textContent = 'Error de red al guardar las nóminas.';
    errorBox.style.display = 'block';
  }
}
</script>

</x-app-layout>
