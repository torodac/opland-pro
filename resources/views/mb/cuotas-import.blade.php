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

.ci-progress{display:none;text-align:center;padding:30px;color:#7e93a1;font-size:13px}
.ci-progress.show{display:block}

.ci-result{display:none;background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:20px;margin-top:16px}
.ci-result.show{display:block}
.ci-result h3{font-size:14px;margin:0 0 12px}
.ci-result-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.ci-result-item{background:#f7fafc;border-radius:10px;padding:10px 12px;text-align:center}
.ci-result-item .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
.ci-result-item .l{font-size:11px;color:#7e93a1}
.ci-result-item.nuevas .n{color:#166534}
.ci-result-item.actualizadas .n{color:#a15c07}
.ci-result-item.omitidas .n{color:#b91c1c}
.ci-list{font-size:12px;color:#374151;max-height:180px;overflow-y:auto;background:#f7fafc;border-radius:8px;padding:10px 12px;margin-top:6px}
.ci-list div{padding:2px 0}
.ci-error{background:#fdecec;color:#b3261e;border-radius:10px;padding:12px 14px;font-size:13px;margin-top:16px}

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
.ci-btn{padding:8px 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;cursor:pointer}
.ci-btn-primary{background:#185fa5;color:#fff}
.ci-btn-secondary{background:#f3f4f6;color:#374151}
</style>

<div class="ci-warn">⚠ Pantalla de prueba: los datos se cargan en <code>mb_cuotas_provisional</code>, no en las cuotas reales. No hay ningún efecto sobre producción.</div>

<div>
  <span class="ci-stat"><span class="ci-stat-num">{{ $totalProvisional }}</span><span class="ci-stat-label">filas en mb_cuotas_provisional{{ $ultimaCarga ? ' · última carga ' . \Illuminate\Support\Carbon::parse($ultimaCarga)->format('d/m/Y H:i') : '' }}</span></span>
</div>

<div class="ci-drop" id="ci-drop">
  <p><strong>Arrastra aquí</strong> el informe "Listado de recibos" (o haz clic para elegirlo)</p>
  <input type="file" id="ci-file-input" accept=".xls,.xlsx,.xml">
</div>

<div class="ci-progress" id="ci-progress">Procesando fichero… puede tardar hasta un minuto.</div>

<div class="ci-error" id="ci-error" style="display:none"></div>

<div class="ci-result" id="ci-result">
  <h3>Resultado de la carga</h3>
  <div class="ci-result-grid">
    <div class="ci-result-item nuevas"><div class="n" id="r-nuevas">0</div><div class="l">nuevas</div></div>
    <div class="ci-result-item actualizadas"><div class="n" id="r-actualizadas">0</div><div class="l">actualizadas</div></div>
    <div class="ci-result-item"><div class="n" id="r-sin-cambios">0</div><div class="l">sin cambios</div></div>
    <div class="ci-result-item omitidas"><div class="n" id="r-omitidas">0</div><div class="l">omitidas (ruido &lt;2010)</div></div>
  </div>
  <p style="font-size:12.5px;color:#7e93a1">Cambios de "pendiente" registrados en el histórico: <strong id="r-historico">0</strong></p>

  <div id="r-sin-vivienda-wrap" style="display:none">
    <p style="font-size:12.5px;font-weight:600;margin:14px 0 4px">Viviendas del fichero sin coincidencia (revisar <code>cuota_name</code>):</p>
    <div class="ci-list" id="r-sin-vivienda"></div>
  </div>

  <div id="r-propietarios-wrap" style="display:none">
    <p style="font-size:12.5px;font-weight:600;margin:14px 0 4px">Cambios de propietario detectados (no aplicados, solo informativo):</p>
    <div class="ci-list" id="r-propietarios"></div>
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
      <button type="button" class="ci-btn ci-btn-primary" id="ci-modal-confirmar">Clasificar y continuar</button>
    </div>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/cuotas_import") }}';

const drop = document.getElementById('ci-drop');
const fileInput = document.getElementById('ci-file-input');
const progress = document.getElementById('ci-progress');
const errorBox = document.getElementById('ci-error');
const resultBox = document.getElementById('ci-result');
const modal = document.getElementById('ci-modal');
const modalBody = document.getElementById('ci-modal-body');

let tmpIdActual = null;

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
});

function mostrarError(msg) {
  errorBox.textContent = msg;
  errorBox.style.display = 'block';
  progress.classList.remove('show');
}

async function subirFichero(file) {
  errorBox.style.display = 'none';
  resultBox.classList.remove('show');
  progress.classList.add('show');

  const fd = new FormData();
  fd.append('file', file);
  await enviar(fd);
}

async function enviar(fd) {
  try {
    const res = await fetch(`${BASE}/import`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    progress.classList.remove('show');

    if (data.needs_mapping) {
      tmpIdActual = data.tmp_id;
      mostrarModalMapeo(data);
      return;
    }
    if (!data.ok) {
      mostrarError(data.error || 'Error al procesar el fichero.');
      return;
    }
    mostrarResultado(data);
  } catch (e) {
    progress.classList.remove('show');
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
  progress.classList.add('show');

  const fd = new FormData();
  fd.append('tmp_id', tmpIdActual);
  mappings.forEach((m, i) => {
    fd.append(`mappings[${i}][concepto]`, m.concepto);
    fd.append(`mappings[${i}][ejercicio]`, m.ejercicio);
    fd.append(`mappings[${i}][tipo_cuota]`, m.tipo_cuota);
  });
  await enviar(fd);
});

function mostrarResultado(data) {
  document.getElementById('r-nuevas').textContent = data.nuevas;
  document.getElementById('r-actualizadas').textContent = data.actualizadas;
  document.getElementById('r-sin-cambios').textContent = data.sin_cambios;
  document.getElementById('r-omitidas').textContent = data.omitidas_ruido_historico.length;
  document.getElementById('r-historico').textContent = data.pendiente_historico;

  const sv = document.getElementById('r-sin-vivienda-wrap');
  if (data.sin_vivienda.length) {
    sv.style.display = 'block';
    document.getElementById('r-sin-vivienda').innerHTML = data.sin_vivienda.map(v => `<div>${v}</div>`).join('');
  } else {
    sv.style.display = 'none';
  }

  const pw = document.getElementById('r-propietarios-wrap');
  if (data.cambios_propietario_detectados.length) {
    pw.style.display = 'block';
    document.getElementById('r-propietarios').innerHTML = data.cambios_propietario_detectados.map(c =>
      `<div>Vivienda #${c.id_viviendas}: "${c.propietario_historico}" → "${c.propietario_fichero}"</div>`
    ).join('');
  } else {
    pw.style.display = 'none';
  }

  resultBox.classList.add('show');
}
</script>

</x-app-layout>
