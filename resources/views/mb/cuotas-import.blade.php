<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.ci-warn{display:inline-flex;align-items:center;gap:8px;background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-bottom:16px}
.ci-stat{border-radius:12px;padding:11px 16px;border:1px solid #dce6ee;background:#fff;display:inline-flex;align-items:center;gap:10px;margin-bottom:16px;margin-right:10px}
.ci-stat-num{font-size:1.1rem;font-weight:700;color:#185fa5}
.ci-stat-label{font-size:12px;color:#7e93a1}

.ci-drop{border:2px dashed #dce6ee;border-radius:14px;padding:40px 20px;text-align:center;background:#fff;cursor:pointer;transition:border-color .15s,background .15s}
.ci-drop.dragover{border-color:#185fa5;background:#eaf3fb}
.ci-drop p{margin:0;color:#7e93a1;font-size:13.5px}
.ci-drop strong{color:#185fa5}
#ci-file-input{display:none}

.ci-error{background:#fdecec;color:#b3261e;border-radius:10px;padding:12px 14px;font-size:13px;margin-top:16px}

.ci-result{display:none;background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:20px;margin-top:16px}
.ci-result.show{display:block}
.ci-result h3{font-size:14px;margin:0 0 12px}
.ci-result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.ci-result-item{background:#f7fafc;border-radius:10px;padding:10px 12px;text-align:center}
.ci-result-item .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
.ci-result-item .l{font-size:11px;color:#7e93a1}
.ci-result-item.nuevas .n{color:#166534}
.ci-result-item.cobradas .n{color:#166534}
.ci-result-item.devueltas .n{color:#b91c1c}
.ci-result-item.otros .n{color:#a15c07}
.ci-cobrado-total{font-size:12.5px;color:#166534;font-weight:600;margin:0 0 14px}
.ci-list{font-size:12px;color:#374151;max-height:220px;overflow-y:auto;background:#f7fafc;border-radius:8px;padding:10px 12px;margin-top:6px}
.ci-list div{padding:3px 0;border-bottom:1px solid #eaf1f6}
.ci-list div:last-child{border-bottom:none}
.ci-badge{display:inline-block;font-size:10px;font-weight:700;border-radius:5px;padding:1px 6px;margin-left:6px;vertical-align:middle}
.ci-badge-existente{background:#e0edf9;color:#185fa5}
.ci-badge-nuevo{background:#fef3c7;color:#92400e}
.ci-aviso-demandas{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:12px 14px;margin-top:16px}
.ci-aviso-demandas h4{margin:0 0 8px;font-size:12.5px;color:#991b1b}

.ci-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:14px;border-top:1px solid #eaf1f6}
.ci-btn{padding:9px 18px;font-size:13px;font-weight:600;border-radius:8px;border:none;cursor:pointer}
.ci-btn-primary{background:#166534;color:#fff}
.ci-btn-secondary{background:#f3f4f6;color:#374151}

.ci-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.4);z-index:60;display:none;align-items:center;justify-content:center;padding:20px}
.ci-modal-backdrop.show{display:flex}
.ci-modal{background:#fff;border-radius:14px;max-width:640px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 45px -12px rgba(15,23,42,.35)}
.ci-modal-head{padding:16px 20px;border-bottom:1px solid #eaf1f6}
.ci-modal-head h3{margin:0;font-size:15px}
.ci-modal-head p{margin:4px 0 0;font-size:12.5px;color:#7e93a1}
.ci-modal-body{padding:16px 20px}
.ci-concepto-row{border:1px solid #eaf1f6;border-radius:10px;padding:12px 14px;margin-bottom:10px}
.ci-concepto-row .titulo{font-weight:600;font-size:13px;margin-bottom:4px}
.ci-concepto-row .ejemplo{font-size:11.5px;color:#7e93a1;margin-bottom:8px}
.ci-concepto-row .campos{display:flex;gap:10px}
.ci-concepto-row input, .ci-concepto-row select{font-size:12.5px;border:1px solid #dce6ee;border-radius:7px;padding:6px 9px}
.ci-concepto-row input{width:110px}
.ci-modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #eaf1f6}

/* Overlay de progreso a pantalla completa -- bloquea cualquier interacción mientras se evalúa o confirma. */
.ci-progress-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:70;display:none;align-items:center;justify-content:center}
.ci-progress-overlay.show{display:flex}
.ci-progress-box{background:#fff;border-radius:14px;padding:28px 36px;text-align:center;box-shadow:0 20px 45px -12px rgba(15,23,42,.35)}
.ci-spinner{width:32px;height:32px;border:3px solid #dce6ee;border-top-color:#185fa5;border-radius:50%;margin:0 auto 14px;animation:ci-spin 0.8s linear infinite}
@keyframes ci-spin{to{transform:rotate(360deg)}}
.ci-progress-box p{margin:0;font-size:13px;color:#374151;font-weight:600}
.ci-progress-box small{display:block;margin-top:4px;font-size:11.5px;color:#9ca3af;font-weight:400}
</style>

<div class="ci-warn">⚠ Esta carga escribe directamente en <code>mb_cuotas</code> (producción). Los estados Demandada/Incobrable/Anulada nunca se pisan desde aquí -- pero sí se avisa si el fichero implicaría un cambio en una cuota con demanda activa.</div>

<div>
  <span class="ci-stat"><span class="ci-stat-num">{{ $totalProvisional }}</span><span class="ci-stat-label">filas en mb_cuotas{{ $ultimaCarga ? ' · última carga ' . \Illuminate\Support\Carbon::parse($ultimaCarga)->format('d/m/Y H:i') : '' }}</span></span>
</div>

<div class="ci-drop" id="ci-drop">
  <p><strong>Arrastra aquí</strong> el informe "Listado de recibos" (o haz clic para elegirlo)</p>
  <input type="file" id="ci-file-input" accept=".xls,.xlsx,.xml">
</div>

<div class="ci-error" id="ci-error" style="display:none"></div>

<div class="ci-result" id="ci-result">
  <h3 id="r-titulo">Evaluación de la carga (todavía no se ha aplicado nada)</h3>
  <p style="font-size:12.5px;color:#7e93a1;margin:0 0 12px">Fecha de exportación detectada: <strong id="r-fecha-exportacion">-</strong></p>

  <div class="ci-result-grid">
    <div class="ci-result-item nuevas"><div class="n" id="r-nuevas">0</div><div class="l">cuotas nuevas</div></div>
    <div class="ci-result-item cobradas"><div class="n" id="r-cobradas">0</div><div class="l">pasan a cobradas</div></div>
    <div class="ci-result-item devueltas"><div class="n" id="r-devueltas">0</div><div class="l">recibos devueltos</div></div>
    <div class="ci-result-item otros"><div class="n" id="r-otros">0</div><div class="l">actualizan otros datos</div></div>
    <div class="ci-result-item"><div class="n" id="r-sin-cambios">0</div><div class="l">sin cambios</div></div>
    <div class="ci-result-item"><div class="n" id="r-omitidas">0</div><div class="l">omitidas (ruido &lt;2010)</div></div>
  </div>
  <p class="ci-cobrado-total">Importe que pasaría a cobrado: <strong id="r-importe-cobrado">0,00 €</strong></p>
  <p style="font-size:12.5px;color:#7e93a1">Eventos que se registrarían en el histórico de estado: <strong id="r-historico">0</strong></p>

  <div id="r-avisos-demandas-wrap" class="ci-aviso-demandas" style="display:none">
    <h4>⚠ Cuotas con demanda activa cuyo estado no se va a tocar, pero el fichero muestra otro estado -- revisar a mano:</h4>
    <div class="ci-list" id="r-avisos-demandas"></div>
  </div>

  <div id="r-sin-vivienda-wrap" style="display:none">
    <p style="font-size:12.5px;font-weight:600;margin:14px 0 4px">Viviendas del fichero sin coincidencia (revisar <code>cuota_name</code>):</p>
    <div class="ci-list" id="r-sin-vivienda"></div>
  </div>

  <div id="r-propietarios-wrap" style="display:none">
    <p style="font-size:12.5px;font-weight:600;margin:14px 0 4px">Cambios de propietario que se aplicarían:</p>
    <div class="ci-list" id="r-propietarios"></div>
  </div>

  <div class="ci-actions" id="ci-actions-evaluar">
    <button type="button" class="ci-btn ci-btn-secondary" onclick="cancelarCarga()">Descartar (no se aplica nada)</button>
    <button type="button" class="ci-btn ci-btn-primary" onclick="confirmarCarga()">Confirmar y aplicar</button>
  </div>
</div>

<div class="ci-modal-backdrop" id="ci-modal">
  <div class="ci-modal">
    <div class="ci-modal-head">
      <h3>Conceptos nuevos sin clasificar</h3>
      <p>No estaban en la tabla de mapeo. Indica el ejercicio y el tipo de cuota para cada uno (se guardará para futuras cargas).</p>
    </div>
    <div class="ci-modal-body" id="ci-modal-body"></div>
    <div class="ci-modal-foot">
      <button type="button" class="ci-btn ci-btn-secondary" onclick="document.getElementById('ci-modal').classList.remove('show')">Cancelar</button>
      <button type="button" class="ci-btn ci-btn-primary" id="ci-modal-confirmar" style="background:#185fa5">Clasificar y evaluar</button>
    </div>
  </div>
</div>

<div class="ci-progress-overlay" id="ci-progress-overlay">
  <div class="ci-progress-box">
    <div class="ci-spinner"></div>
    <p id="ci-progress-texto">Procesando fichero…</p>
    <small>Puede tardar hasta un minuto. No cierres esta pestaña.</small>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/cuotas_import") }}';

const drop = document.getElementById('ci-drop');
const fileInput = document.getElementById('ci-file-input');
const overlay = document.getElementById('ci-progress-overlay');
const overlayTexto = document.getElementById('ci-progress-texto');
const errorBox = document.getElementById('ci-error');
const resultBox = document.getElementById('ci-result');
const modal = document.getElementById('ci-modal');
const modalBody = document.getElementById('ci-modal-body');

let tmpIdActual = null;

function mostrarOverlay(texto) {
  overlayTexto.textContent = texto;
  overlay.classList.add('show');
}
function ocultarOverlay() {
  overlay.classList.remove('show');
}

drop.addEventListener('click', () => fileInput.click());
drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop', (e) => {
  e.preventDefault();
  drop.classList.remove('dragover');
  if (e.dataTransfer.files.length) subirFichero(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => {
  if (fileInput.files.length) subirFichero(fileInput.files[0]);
  fileInput.value = '';
});

function mostrarError(msg) {
  errorBox.textContent = msg;
  errorBox.style.display = 'block';
  ocultarOverlay();
}

async function subirFichero(file) {
  errorBox.style.display = 'none';
  resultBox.classList.remove('show');
  mostrarOverlay('Procesando ' + file.name + '…');

  const fd = new FormData();
  fd.append('file', file);
  await enviarEvaluacion(fd);
}

async function enviarEvaluacion(fd) {
  try {
    const res = await fetch(`${BASE}/evaluar`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    ocultarOverlay();

    if (data.needs_mapping) {
      tmpIdActual = data.tmp_id;
      mostrarModalMapeo(data);
      return;
    }
    if (!data.ok) {
      mostrarError(data.error || 'Error al procesar el fichero.');
      return;
    }
    tmpIdActual = data.tmp_id;
    mostrarEvaluacion(data);
  } catch (e) {
    ocultarOverlay();
    mostrarError('Error de red al procesar el fichero.');
  }
}

function mostrarModalMapeo(data) {
  modalBody.innerHTML = data.conceptos.map((c, i) => `
    <div class="ci-concepto-row" data-concepto="${encodeURIComponent(c.concepto)}">
      <div class="titulo">${c.concepto}</div>
      <div class="ejemplo">Ej: ${c.ejemplo_vivienda} · ${c.ejemplo_fecha} · ${c.ejemplo_importe} €</div>
      <div class="campos">
        <input type="text" class="ci-ejercicio" value="${c.ejercicio_sugerido}" placeholder="Ejercicio">
        <select class="ci-tipo">
          ${data.tipos.map(t => `<option value="${t}">${t}</option>`).join('')}
        </select>
      </div>
    </div>`).join('');
  modal.classList.add('show');
}

document.getElementById('ci-modal-confirmar').addEventListener('click', async () => {
  const mappings = [];
  modalBody.querySelectorAll('.ci-concepto-row').forEach(row => {
    mappings.push({
      concepto: decodeURIComponent(row.dataset.concepto),
      ejercicio: row.querySelector('.ci-ejercicio').value.trim(),
      tipo_cuota: row.querySelector('.ci-tipo').value,
    });
  });
  modal.classList.remove('show');
  mostrarOverlay('Evaluando fichero…');

  const fd = new FormData();
  fd.append('tmp_id', tmpIdActual);
  mappings.forEach((m, i) => {
    fd.append(`mappings[${i}][concepto]`, m.concepto);
    fd.append(`mappings[${i}][ejercicio]`, m.ejercicio);
    fd.append(`mappings[${i}][tipo_cuota]`, m.tipo_cuota);
  });
  await enviarEvaluacion(fd);
});

function mostrarEvaluacion(data) {
  document.getElementById('r-titulo').textContent = 'Evaluación de la carga (todavía no se ha aplicado nada)';
  document.getElementById('r-nuevas').textContent = data.nuevas;
  document.getElementById('r-cobradas').textContent = data.pendiente_a_pagada;
  document.getElementById('r-devueltas').textContent = data.pagada_a_pendiente;
  document.getElementById('r-otros').textContent = data.actualizadas_otros_datos;
  document.getElementById('r-sin-cambios').textContent = data.sin_cambios;
  document.getElementById('r-omitidas').textContent = data.omitidas_ruido_historico.length;
  document.getElementById('r-fecha-exportacion').textContent = data.fecha_exportacion;
  document.getElementById('r-historico').textContent = data.estado_historico;
  document.getElementById('r-importe-cobrado').textContent = Number(data.importe_recien_cobrado).toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';

  const ad = document.getElementById('r-avisos-demandas-wrap');
  if (data.avisos_demandas.length) {
    ad.style.display = 'block';
    document.getElementById('r-avisos-demandas').innerHTML = data.avisos_demandas.map(a =>
      `<div>Cuota #${a.id_cuota} (${a.concepto}, ${a.fecha_emision}): estado actual <strong>${a.estado_actual}</strong>, el fichero muestra <strong>${a.estado_fichero}</strong></div>`
    ).join('');
  } else {
    ad.style.display = 'none';
  }

  const sv = document.getElementById('r-sin-vivienda-wrap');
  if (data.sin_vivienda.length) {
    sv.style.display = 'block';
    document.getElementById('r-sin-vivienda').innerHTML = data.sin_vivienda.map(v => `<div>${v}</div>`).join('');
  } else {
    sv.style.display = 'none';
  }

  const pw = document.getElementById('r-propietarios-wrap');
  if (data.cambios_propietario.length) {
    pw.style.display = 'block';
    document.getElementById('r-propietarios').innerHTML = data.cambios_propietario.map(c => {
      const badge = c.resolucion === 'nuevo'
        ? '<span class="ci-badge ci-badge-nuevo">propietario nuevo</span>'
        : '<span class="ci-badge ci-badge-existente">ya existe</span>';
      return `<div>${c.nombre_vivienda || ('Vivienda #' + c.id_viviendas)}: "${c.propietario_anterior}" → "${c.propietario_nuevo}"${badge}</div>`;
    }).join('');
  } else {
    pw.style.display = 'none';
  }

  document.getElementById('ci-actions-evaluar').style.display = 'flex';
  resultBox.classList.add('show');
}

async function confirmarCarga() {
  if (!tmpIdActual) return;
  mostrarOverlay('Aplicando cambios…');
  try {
    const res = await fetch(`${BASE}/confirmar`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ tmp_id: tmpIdActual }),
    });
    const data = await res.json();
    ocultarOverlay();
    if (!data.ok) {
      mostrarError(data.error || 'Error al aplicar la carga.');
      return;
    }
    document.getElementById('r-titulo').textContent = '✓ Carga aplicada correctamente';
    document.getElementById('ci-actions-evaluar').style.display = 'none';
    tmpIdActual = null;
    setTimeout(() => location.reload(), 2500);
  } catch (e) {
    ocultarOverlay();
    mostrarError('Error de red al aplicar la carga.');
  }
}

async function cancelarCarga() {
  if (!tmpIdActual) { resultBox.classList.remove('show'); return; }
  const tmpId = tmpIdActual;
  tmpIdActual = null;
  resultBox.classList.remove('show');
  try {
    await fetch(`${BASE}/cancelar`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ tmp_id: tmpId }),
    });
  } catch (e) {}
}
</script>

</x-app-layout>
