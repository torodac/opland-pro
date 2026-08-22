<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.mi-drop{border:2px dashed #dce6ee;border-radius:14px;padding:40px 20px;text-align:center;background:#fff;cursor:pointer;transition:border-color .15s,background .15s}
.mi-drop.dragover{border-color:#185fa5;background:#eaf3fb}
.mi-drop p{margin:0;color:#7e93a1;font-size:13.5px}
.mi-drop strong{color:#185fa5}
#mi-file-input{display:none}

.mi-cuenta-select{margin-bottom:16px;display:flex;align-items:center;gap:10px}
.mi-cuenta-select select{font-size:13px;border:1px solid #dce6ee;border-radius:8px;padding:7px 10px}
.mi-cuenta-select label{font-size:12.5px;color:#7e93a1}

.mi-error{background:#fdecec;color:#b3261e;border-radius:10px;padding:12px 14px;font-size:13px;margin-top:16px}

.mi-result{display:none;background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:20px;margin-top:16px}
.mi-result.show{display:block}
.mi-result h3{font-size:14px;margin:0 0 12px}
.mi-result-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.mi-result-item{background:#f7fafc;border-radius:10px;padding:10px 12px;text-align:center}
.mi-result-item .n{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
.mi-result-item .l{font-size:11px;color:#7e93a1}
.mi-result-item.importadas .n{color:#166534}
.mi-result-item.duplicadas .n{color:#7e93a1}
.mi-result-item.sin-clasificar .n{color:#a15c07}

.mi-progress-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:70;display:none;align-items:center;justify-content:center}
.mi-progress-overlay.show{display:flex}
.mi-progress-box{background:#fff;border-radius:14px;padding:28px 36px;text-align:center;box-shadow:0 20px 45px -12px rgba(15,23,42,.35)}
.mi-spinner{width:32px;height:32px;border:3px solid #dce6ee;border-top-color:#185fa5;border-radius:50%;margin:0 auto 14px;animation:mi-spin 0.8s linear infinite}
@keyframes mi-spin{to{transform:rotate(360deg)}}
.mi-progress-box p{margin:0;font-size:13px;color:#374151;font-weight:600}

.mi-lotes{margin-top:24px}
.mi-lotes table{width:100%;border-collapse:collapse;font-size:12.5px}
.mi-lotes th{text-align:left;padding:7px 8px;color:#7e93a1;font-weight:600;border-bottom:1px solid #eaf1f6}
.mi-lotes td{padding:7px 8px;border-bottom:1px solid #f7fafc}
</style>

<div class="mi-cuenta-select">
  <label for="mi-cuenta">Cuenta (opcional, se detecta sola por el IBAN del fichero):</label>
  <select id="mi-cuenta">
    <option value="">Detectar automáticamente</option>
    @foreach($cuentas as $c)
      <option value="{{ $c->id }}">{{ $c->nombre }}</option>
    @endforeach
  </select>
</div>

<div class="mi-drop" id="mi-drop">
  <p><strong>Arrastra aquí</strong> el extracto <code>.xls</code> del banco (o haz clic para elegirlo)</p>
  <input type="file" id="mi-file-input" accept=".xls,.xlsx">
</div>

<div class="mi-error" id="mi-error" style="display:none"></div>

<div class="mi-result" id="mi-result">
  <h3 id="r-titulo">Importación completada</h3>
  <p style="font-size:12.5px;color:#7e93a1;margin:0 0 12px">Cuenta: <strong id="r-cuenta">-</strong></p>
  <div class="mi-result-grid">
    <div class="mi-result-item importadas"><div class="n" id="r-importadas">0</div><div class="l">movimientos importados</div></div>
    <div class="mi-result-item duplicadas"><div class="n" id="r-duplicadas">0</div><div class="l">ya existían (duplicados)</div></div>
    <div class="mi-result-item sin-clasificar"><div class="n" id="r-sin-clasificar">0</div><div class="l">sin clasificar</div></div>
  </div>
</div>

<div class="mi-lotes">
  <h3 style="font-size:14px;margin:0 0 10px">Importaciones anteriores</h3>
  @if($lotes->isEmpty())
    <p style="font-size:12.5px;color:#7e93a1">Todavía no se ha importado ningún extracto.</p>
  @else
    <table>
      <thead><tr><th>Fecha</th><th>Cuenta</th><th>Fichero</th><th style="text-align:right">Importadas</th><th style="text-align:right">Duplicadas</th></tr></thead>
      <tbody>
        @foreach($lotes as $l)
          <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($l->fecha_importacion)->format('d/m/Y H:i') }}</td>
            <td>{{ $l->cuenta_nombre }}</td>
            <td>{{ $l->fichero }}</td>
            <td style="text-align:right">{{ $l->filas_importadas }}</td>
            <td style="text-align:right">{{ $l->filas_duplicadas }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<div class="mi-progress-overlay" id="mi-progress-overlay">
  <div class="mi-progress-box">
    <div class="mi-spinner"></div>
    <p>Procesando fichero…</p>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/movimientos_bancarios") }}';

const drop = document.getElementById('mi-drop');
const fileInput = document.getElementById('mi-file-input');
const overlay = document.getElementById('mi-progress-overlay');
const errorBox = document.getElementById('mi-error');
const resultBox = document.getElementById('mi-result');
const cuentaSelect = document.getElementById('mi-cuenta');

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

async function subirFichero(file) {
  errorBox.style.display = 'none';
  resultBox.classList.remove('show');
  overlay.classList.add('show');

  const fd = new FormData();
  fd.append('file', file);
  if (cuentaSelect.value) fd.append('id_movs_cuenta', cuentaSelect.value);

  try {
    const res = await fetch(`${BASE}/import`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    overlay.classList.remove('show');

    if (!data.ok) {
      errorBox.textContent = data.error || 'Error al procesar el fichero.';
      errorBox.style.display = 'block';
      return;
    }

    document.getElementById('r-cuenta').textContent = data.cuenta;
    document.getElementById('r-importadas').textContent = data.importadas;
    document.getElementById('r-duplicadas').textContent = data.duplicadas;
    document.getElementById('r-sin-clasificar').textContent = data.sin_clasificar;
    resultBox.classList.add('show');
    setTimeout(() => location.reload(), 3000);
  } catch (e) {
    overlay.classList.remove('show');
    errorBox.textContent = 'Error de red al procesar el fichero.';
    errorBox.style.display = 'block';
  }
}
</script>

</x-app-layout>
