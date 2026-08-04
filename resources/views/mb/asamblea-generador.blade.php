<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions">
    <button type="button" onclick="document.getElementById('ag-modal-nueva').classList.remove('hidden')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva asamblea
    </button>
</x-slot>

<style>
.ag-card{background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:16px 18px;margin-bottom:16px}
.ag-card h3{font-size:13px;font-weight:600;color:#7e93a1;text-transform:uppercase;letter-spacing:.03em;margin:0 0 12px}
.ag-field{margin-bottom:12px}
.ag-field label{display:block;font-size:11.5px;color:#7e93a1;margin-bottom:4px}
.ag-field input,.ag-field select{width:100%;font-size:13px;border:1px solid #dce6ee;border-radius:8px;padding:8px 10px;box-sizing:border-box}
.ag-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.ag-row .ag-field{flex:1;min-width:160px;margin-bottom:0}
.ag-btn{height:36px;padding:0 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;background:#185fa5;color:#fff;cursor:pointer}
.ag-btn:disabled{background:#cfd8e3;color:#8a97a8;cursor:default}
.ag-btn-secundario{height:36px;padding:0 14px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid #dce6ee;background:#fff;color:#374151;cursor:pointer}

.ag-pregunta{display:flex;gap:10px;align-items:flex-start;margin-bottom:12px}
.ag-pregunta-num{width:26px;height:34px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#7e93a1;flex-shrink:0}
.ag-editor{flex:1;border:1px solid #dce6ee;border-radius:8px;overflow:hidden}
.ag-toolbar{display:flex;gap:2px;background:#f7fafc;border-bottom:1px solid #dce6ee;padding:4px}
.ag-toolbar button{width:26px;height:24px;border:none;background:none;border-radius:5px;cursor:pointer;font-size:12px;color:#374151}
.ag-toolbar button:hover{background:#eaf1f6}
.ag-editable{padding:8px 10px;font-size:13px;min-height:20px;outline:none}
.ag-editable:empty:before{content:attr(data-placeholder);color:#b0bec9}
.ag-pregunta-guardado{font-size:10.5px;color:#166534;opacity:0;transition:opacity .3s;align-self:center}
.ag-pregunta-guardado.show{opacity:1}
.ag-btn-eliminar{width:26px;height:26px;flex-shrink:0;border:none;background:none;color:#cbd5e1;cursor:pointer;font-size:16px;line-height:1;align-self:center}
.ag-btn-eliminar:hover{color:#b91c1c}
</style>

<div class="ag-card">
  <h3>Asamblea</h3>
  <div class="ag-row">
    <form method="GET" class="ag-field" style="max-width:320px">
      <label>Seleccionar asamblea</label>
      <select name="id_asamblea" onchange="this.form.submit()">
        @foreach($asambleas as $a)
        <option value="{{ $a->id }}" {{ $asamblea && $asamblea->id === $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
        @endforeach
      </select>
    </form>
    @if($asamblea)
    <button type="button" class="ag-btn-secundario" onclick="abrirModalDescargar()">
      Descargar hojas de voto
    </button>
    @endif
  </div>
</div>

@if($asamblea)
<div class="ag-card">
  <h3>Preguntas</h3>
  <div id="ag-lista-preguntas">
    @foreach($preguntas as $p)
    <div class="ag-pregunta" data-numero="{{ $p->numero_pregunta }}">
      <div class="ag-pregunta-num">{{ $p->numero_pregunta }}.</div>
      <div class="ag-editor">
        <div class="ag-toolbar">
          <button type="button" onmousedown="event.preventDefault();document.execCommand('bold')"><b>B</b></button>
          <button type="button" onmousedown="event.preventDefault();document.execCommand('italic')"><i>I</i></button>
          <button type="button" onmousedown="event.preventDefault();document.execCommand('underline')"><u>U</u></button>
        </div>
        <div class="ag-editable" contenteditable="true" data-placeholder="Escribe la pregunta...">{!! $p->texto !!}</div>
      </div>
      <span class="ag-pregunta-guardado">Guardado</span>
      <button type="button" class="ag-btn-eliminar" title="Eliminar pregunta">&times;</button>
    </div>
    @endforeach
  </div>
  <button type="button" class="ag-btn-secundario" id="ag-anadir-pregunta">+ Añadir pregunta</button>
</div>
@endif

<div id="ag-modal-nueva" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('ag-modal-nueva').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div style="background:#fff;border-radius:12px;box-shadow:0 20px 45px -12px rgba(15,23,42,.35);width:100%;max-width:400px">
      <div style="padding:16px 20px;border-bottom:1px solid #eaf1f6">
        <h3 style="font-size:15px;font-weight:600;color:#16232b;margin:0">Nueva asamblea</h3>
      </div>
      <div style="padding:16px 20px">
        <div class="ag-field">
          <label>Fecha</label>
          <input type="date" id="ag-fecha">
        </div>
        <div class="ag-field">
          <label>Tipo</label>
          <select id="ag-tipo">
            <option value="Ordinaria">Ordinaria</option>
            <option value="Extraordinaria">Extraordinaria</option>
          </select>
        </div>
        <div class="ag-field">
          <label>Número de preguntas</label>
          <input type="number" id="ag-numero-preguntas" min="1" max="30" value="6">
        </div>
        <div id="ag-error" style="display:none;color:#b91c1c;font-size:12.5px;margin-top:6px"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #eaf1f6">
        <button type="button" onclick="document.getElementById('ag-modal-nueva').classList.add('hidden')"
                style="padding:8px 14px;font-size:13px;color:#6b7280;background:#f3f4f6;border:none;border-radius:8px;cursor:pointer">Cancelar</button>
        <button type="button" id="ag-crear" class="ag-btn">Crear</button>
      </div>
    </div>
  </div>
</div>

@if($asamblea)
<div id="ag-modal-descargar" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('ag-modal-descargar').classList.add('hidden')"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div style="background:#fff;border-radius:12px;box-shadow:0 20px 45px -12px rgba(15,23,42,.35);width:100%;max-width:380px">
      <div style="padding:16px 20px;border-bottom:1px solid #eaf1f6">
        <h3 style="font-size:15px;font-weight:600;color:#16232b;margin:0">Descargar hojas de voto</h3>
      </div>
      <div>
        <input type="hidden" id="ag-descargar-id-asamblea" value="{{ $asamblea->id }}">
        <div style="padding:16px 20px" class="ag-row">
          <div class="ag-field">
            <label>Desde</label>
            <input type="number" id="ag-descargar-desde" value="1" min="1" required>
          </div>
          <div class="ag-field">
            <label>Hasta</label>
            <input type="number" id="ag-descargar-hasta" value="500" min="1" required>
          </div>
        </div>
        <p style="font-size:11.5px;color:#9ca3af;margin:0 20px 12px">Puedes descargar un rango parcial (ej. 350 a 500) si necesitas una segunda tanda de impresión. Rangos de más de 100 hojas se entregan como un .zip con un PDF por cada 100 hojas.</p>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #eaf1f6">
          <button type="button" onclick="document.getElementById('ag-modal-descargar').classList.add('hidden')"
                  style="padding:8px 14px;font-size:13px;color:#6b7280;background:#f3f4f6;border:none;border-radius:8px;cursor:pointer">Cancelar</button>
          <button type="button" class="ag-btn" id="ag-btn-descargar" onclick="iniciarDescargaHojasVoto()">Descargar PDF</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="pdf-loading-overlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:36px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <svg style="width:48px;height:48px;margin-bottom:16px;animation:pdf-spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" stroke="#e5e7eb" stroke-width="3"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke="#7367f0" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <div style="font-size:16px;font-weight:600;color:#333;margin-bottom:6px;">Generando PDF</div>
        <div style="font-size:13px;color:#666;">Esto puede tardar varios minutos&hellip;</div>
    </div>
</div>
<style>@keyframes pdf-spin { to { transform: rotate(360deg); } }</style>
@endif

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/asamblea_generador") }}';
const ID_ASAMBLEA = {{ $asamblea->id ?? 'null' }};

function headers(json) {
  const h = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' };
  if (json) h['Content-Type'] = 'application/json';
  return h;
}

document.getElementById('ag-crear').addEventListener('click', async function () {
  const fecha = document.getElementById('ag-fecha').value;
  const tipo = document.getElementById('ag-tipo').value;
  const numeroPreguntas = document.getElementById('ag-numero-preguntas').value;
  const error = document.getElementById('ag-error');
  error.style.display = 'none';

  if (!fecha) { error.textContent = 'La fecha es obligatoria.'; error.style.display = 'block'; return; }

  const res = await fetch(`${BASE}/asamblea`, {
    method: 'POST', headers: headers(true),
    body: JSON.stringify({ fecha, tipo, numero_preguntas: numeroPreguntas }),
  });
  const data = await res.json();
  if (!res.ok) { error.textContent = data.error || 'No se pudo crear.'; error.style.display = 'block'; return; }

  window.location.href = `${window.location.pathname}?id_asamblea=${data.id}`;
});

async function guardarPregunta(numero, html) {
  await fetch(`${BASE}/preguntas`, {
    method: 'POST', headers: headers(true),
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, preguntas: { [numero]: html } }),
  });
}

function flashGuardado(fila) {
  const span = fila.querySelector('.ag-pregunta-guardado');
  span.classList.add('show');
  clearTimeout(fila._guardadoTimer);
  fila._guardadoTimer = setTimeout(() => span.classList.remove('show'), 1200);
}

function conectarEditor(fila) {
  const editable = fila.querySelector('.ag-editable');
  let debounceTimer = null;
  editable.addEventListener('input', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
      await guardarPregunta(fila.dataset.numero, editable.innerHTML);
      flashGuardado(fila);
    }, 600);
  });

  const btnEliminar = fila.querySelector('.ag-btn-eliminar');
  btnEliminar.addEventListener('click', async function () {
    if (!confirm(`¿Eliminar la pregunta ${fila.dataset.numero}?`)) return;
    const res = await fetch(`${BASE}/pregunta`, {
      method: 'DELETE', headers: headers(true),
      body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, numero_pregunta: fila.dataset.numero }),
    });
    const data = await res.json();
    if (!res.ok) { alert(data.error || 'No se pudo eliminar.'); return; }
    fila.remove();
  });
}

document.querySelectorAll('.ag-pregunta').forEach(conectarEditor);

function abrirModalDescargar() {
  document.getElementById('ag-modal-descargar').classList.remove('hidden');
}

function descargarPdf(url, filename) {
  const overlay = document.getElementById('pdf-loading-overlay');
  overlay.style.display = 'flex';
  fetch(url)
    .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.blob(); })
    .then(blob => {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    })
    .catch(e => alert('Error al generar el PDF: ' + e.message))
    .finally(() => { overlay.style.display = 'none'; });
}

function iniciarDescargaHojasVoto() {
  const idAsamblea = document.getElementById('ag-descargar-id-asamblea').value;
  const desde = parseInt(document.getElementById('ag-descargar-desde').value, 10);
  const hasta = parseInt(document.getElementById('ag-descargar-hasta').value, 10);
  if (!desde || !hasta || hasta < desde) { alert('Rango de hojas no válido.'); return; }

  document.getElementById('ag-modal-descargar').classList.add('hidden');
  const url = `${BASE}/pdf?id_asamblea=${idAsamblea}&desde=${desde}&hasta=${hasta}`;
  const extension = (hasta - desde + 1) > 100 ? 'zip' : 'pdf';
  descargarPdf(url, `hojas_voto_${desde}_a_${hasta}.${extension}`);
}

const btnAnadir = document.getElementById('ag-anadir-pregunta');
if (btnAnadir) {
  btnAnadir.addEventListener('click', async function () {
    const res = await fetch(`${BASE}/pregunta`, {
      method: 'POST', headers: headers(true),
      body: JSON.stringify({ id_asamblea: ID_ASAMBLEA }),
    });
    const data = await res.json();
    if (!res.ok) { alert(data.error || 'No se pudo añadir.'); return; }

    const fila = document.createElement('div');
    fila.className = 'ag-pregunta';
    fila.dataset.numero = data.numero_pregunta;
    fila.innerHTML = `
      <div class="ag-pregunta-num">${data.numero_pregunta}.</div>
      <div class="ag-editor">
        <div class="ag-toolbar">
          <button type="button" onmousedown="event.preventDefault();document.execCommand('bold')"><b>B</b></button>
          <button type="button" onmousedown="event.preventDefault();document.execCommand('italic')"><i>I</i></button>
          <button type="button" onmousedown="event.preventDefault();document.execCommand('underline')"><u>U</u></button>
        </div>
        <div class="ag-editable" contenteditable="true" data-placeholder="Escribe la pregunta..."></div>
      </div>
      <span class="ag-pregunta-guardado">Guardado</span>
      <button type="button" class="ag-btn-eliminar" title="Eliminar pregunta">&times;</button>
    `;
    document.getElementById('ag-lista-preguntas').appendChild(fila);
    conectarEditor(fila);
    fila.querySelector('.ag-editable').focus();
  });
}
</script>

</x-app-layout>
