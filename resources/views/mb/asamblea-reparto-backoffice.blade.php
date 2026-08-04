<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.rb-stat{border-radius:12px;padding:11px 16px;border:1px solid;display:inline-flex;align-items:center;gap:10px;margin-bottom:16px}
.rb-stat-num{font-size:1.25rem;font-weight:700}
.rb-stat-label{font-size:12px;font-weight:500}
.rb-card{background:#fff;border:1px solid #dce6ee;border-radius:12px;padding:16px 18px;margin-bottom:16px}
.rb-card h3{font-size:13px;font-weight:600;color:#7e93a1;text-transform:uppercase;letter-spacing:.03em;margin:0 0 12px}
.rb-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.rb-field{flex:1;min-width:200px;position:relative}
.rb-field label{display:block;font-size:11.5px;color:#7e93a1;margin-bottom:4px}
.rb-field input{width:100%;font-size:13px;border:1px solid #dce6ee;border-radius:8px;padding:8px 10px;box-sizing:border-box}
.rb-btn{height:36px;padding:0 16px;font-size:13px;font-weight:600;border-radius:8px;border:none;background:#185fa5;color:#fff;cursor:pointer}
.rb-btn:disabled{background:#cfd8e3;color:#8a97a8;cursor:default}
.rb-seleccion{margin-top:14px;padding:12px 14px;background:#f7fafc;border-radius:8px;font-size:13px;display:none}
.rb-seleccion.show{display:block}
.rb-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;margin-left:8px}
.rb-badge-con{background:#fef2f2;color:#b91c1c}
.rb-badge-sin{background:#eafcef;color:#166534}
.rb-aviso{margin-top:8px;font-size:12.5px;background:#fffbeb;color:#92400e;border-radius:6px;padding:6px 10px}
.rb-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #dce6ee;border-radius:10px;overflow:hidden}
.rb-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 14px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rb-table td{padding:8px 14px;border-bottom:1px solid #eaf1f6}
.rb-table td.num{text-align:right;font-variant-numeric:tabular-nums}
.rb-deuda{color:#b91c1c;font-weight:600}
.rb-anular{width:26px;height:26px;border:none;background:none;color:#cbd5e1;cursor:pointer;font-size:16px;line-height:1}
.rb-anular:hover{color:#b91c1c}
</style>

<a class="rb-stat" style="background:#eff6ff;border-color:#93c5fd">
  <span class="rb-stat-num" style="color:#1d4ed8"><span id="rb-stat-repartidas">{{ $totalRepartidas }}</span>/{{ $totalViviendas }}</span>
  <span class="rb-stat-label" style="color:#1e40af">hojas repartidas</span>
</a>

<div class="rb-card">
  <h3>Registrar hoja entregada</h3>
  <div class="rb-row">
    <div class="rb-field">
      <label>Vivienda</label>
      @include('opland.partials.fk-combo', [
          'id' => 'rb-vivienda-id',
          'opciones' => $viviendasOpciones,
          'seleccionado' => null,
          'onChangeJs' => 'onCambioViviendaFk(this.value)',
      ])
    </div>
    <div class="rb-field" style="max-width:140px">
      <label>Número de hoja</label>
      <input type="number" id="rb-hoja" disabled>
    </div>
    <button class="rb-btn" id="rb-guardar" disabled>Guardar</button>
  </div>
  <div id="rb-seleccion" class="rb-seleccion">
    <span id="rb-sel-nombre"></span>
    <span id="rb-sel-id" style="font-weight:700;margin-left:8px"></span>
    <span id="rb-sel-badge"></span>
    <span id="rb-sel-hoja-actual" style="color:#7e93a1;margin-left:10px"></span>
  </div>
  <div id="rb-aviso" class="rb-aviso" style="display:none"></div>
</div>

<form method="GET" style="margin-bottom:14px">
  <input type="text" name="q" value="{{ $q }}" placeholder="Buscar vivienda en el listado..."
         style="font-size:12.5px;border:1px solid #dce6ee;border-radius:8px;padding:7px 11px;width:320px">
</form>

<div style="overflow-x:auto">
<table class="rb-table">
  <thead><tr>
    <th>Vivienda</th>
    <th>Hoja</th>
    <th>Fecha de entrega</th>
    <th class="num">Deuda</th>
    <th></th>
  </tr></thead>
  <tbody id="rb-tbody">
    @forelse($viviendas as $v)
    <tr data-id-viviendas="{{ $v->id }}" data-nombre="{{ $v->nombre }}">
      <td>{{ $v->nombre }}</td>
      <td>{{ $v->numero_hoja ?? '—' }}</td>
      <td>{{ $v->fecha_entrega ? \Illuminate\Support\Carbon::parse($v->fecha_entrega)->format('d/m/Y H:i') : '—' }}</td>
      <td class="num {{ $v->deuda > 0 ? 'rb-deuda' : '' }}">{{ number_format($v->deuda, 2, ',', '.') }} €</td>
      <td><button type="button" class="rb-anular" title="Anular asignación" onclick="pedirConfirmacionAnular({{ $v->id }}, '{{ addslashes($v->nombre) }}')">&times;</button></td>
    </tr>
    @empty
    <tr id="rb-fila-vacia"><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">Todavía no se ha repartido ninguna hoja.</td></tr>
    @endforelse
  </tbody>
</table>
</div>

<div id="rb-modal-anular" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="cerrarModalAnular()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div style="background:#fff;border-radius:12px;box-shadow:0 20px 45px -12px rgba(15,23,42,.35);width:100%;max-width:380px">
      <div style="padding:16px 20px;border-bottom:1px solid #eaf1f6">
        <h3 style="font-size:15px;font-weight:600;color:#16232b;margin:0">Anular asignación de hoja</h3>
      </div>
      <div style="padding:16px 20px;font-size:13.5px;color:#374151" id="rb-modal-anular-texto"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #eaf1f6">
        <button type="button" onclick="cerrarModalAnular()"
                style="padding:8px 14px;font-size:13px;color:#6b7280;background:#f3f4f6;border:none;border-radius:8px;cursor:pointer">Cancelar</button>
        <button type="button" id="rb-modal-anular-confirmar"
                style="padding:8px 14px;font-size:13px;font-weight:600;color:#fff;background:#b91c1c;border:none;border-radius:8px;cursor:pointer">Anular</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/asamblea/reparto") }}';
const ID_ASAMBLEA = {{ $asamblea->id }};
let viviendaSeleccionada = null;

function onCambioViviendaFk(id) {
  if (!id) {
    viviendaSeleccionada = null;
    document.getElementById('rb-hoja').disabled = true;
    document.getElementById('rb-guardar').disabled = true;
    document.getElementById('rb-seleccion').classList.remove('show');
    return;
  }
  seleccionarVivienda(id);
}

async function seleccionarVivienda(id) {
  const res = await fetch(`${BASE}/vivienda?id_asamblea=${ID_ASAMBLEA}&id=${id}`, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'Vivienda no encontrada.'); return; }

  viviendaSeleccionada = data;
  document.getElementById('rb-sel-nombre').textContent = data.nombre;
  document.getElementById('rb-sel-id').textContent = 'ID ' + data.id;
  const badge = document.getElementById('rb-sel-badge');
  if (data.deuda > 0) {
    badge.className = 'rb-badge rb-badge-con';
    badge.textContent = 'Con deuda — ' + data.deuda.toLocaleString('es-ES', { minimumFractionDigits: 2 }) + ' €';
  } else {
    badge.className = 'rb-badge rb-badge-sin';
    badge.textContent = 'Sin deuda';
  }
  document.getElementById('rb-sel-hoja-actual').textContent = data.hoja_actual ? 'Hoja actual: ' + data.hoja_actual : 'Sin hoja asignada';
  document.getElementById('rb-seleccion').classList.add('show');
  document.getElementById('rb-hoja').disabled = false;
  document.getElementById('rb-hoja').value = '';
  document.getElementById('rb-aviso').style.display = 'none';
  actualizarBotonGuardar();
}

document.getElementById('rb-hoja').addEventListener('input', function () {
  const aviso = document.getElementById('rb-aviso');
  if (viviendaSeleccionada && viviendaSeleccionada.hoja_actual && String(viviendaSeleccionada.hoja_actual) !== String(this.value)) {
    aviso.textContent = `Esta vivienda ya tenía la hoja ${viviendaSeleccionada.hoja_actual} asignada — se sustituirá y quedará archivada.`;
    aviso.style.display = 'block';
  } else {
    aviso.style.display = 'none';
  }
  actualizarBotonGuardar();
});

function actualizarBotonGuardar() {
  const numero = document.getElementById('rb-hoja').value;
  document.getElementById('rb-guardar').disabled = !viviendaSeleccionada || numero === '';
}

async function guardarHoja(numero, forzar) {
  const res = await fetch(`${BASE}/hoja`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, id_viviendas: viviendaSeleccionada.id, numero_hoja: numero, forzar: forzar ? 1 : 0 }),
  });
  const data = await res.json();

  if (res.status === 409 && data.error_hoja_en_uso) {
    if (confirm(`Hoja ya repartida a ${data.vivienda_en_uso}. ¿Continuar de todas formas?`)) {
      await guardarHoja(numero, true);
    }
    return;
  }
  if (!res.ok) { alert(data.error || 'No se pudo guardar.'); return; }
  location.reload();
}

document.getElementById('rb-guardar').addEventListener('click', function () {
  const numero = document.getElementById('rb-hoja').value;
  if (!viviendaSeleccionada || !numero) return;
  guardarHoja(numero, false);
});

let idViviendaAAnular = null;

function pedirConfirmacionAnular(idViviendas, nombre) {
  idViviendaAAnular = idViviendas;
  document.getElementById('rb-modal-anular-texto').textContent =
    `¿Anular la hoja asignada a ${nombre}? La vivienda quedará sin hoja repartida.`;
  document.getElementById('rb-modal-anular').classList.remove('hidden');
}

function cerrarModalAnular() {
  idViviendaAAnular = null;
  document.getElementById('rb-modal-anular').classList.add('hidden');
}

document.getElementById('rb-modal-anular-confirmar').addEventListener('click', async function () {
  if (!idViviendaAAnular) return;
  const res = await fetch(`${BASE}/hoja`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, id_viviendas: idViviendaAAnular }),
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'No se pudo anular.'); return; }

  const fila = document.querySelector(`#rb-tbody tr[data-id-viviendas="${idViviendaAAnular}"]`);
  if (fila) fila.remove();

  const contador = document.getElementById('rb-stat-repartidas');
  contador.textContent = Math.max(0, parseInt(contador.textContent, 10) - 1);

  if (!document.querySelector('#rb-tbody tr')) {
    document.getElementById('rb-tbody').innerHTML =
      '<tr id="rb-fila-vacia"><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">Todavía no se ha repartido ninguna hoja.</td></tr>';
  }

  cerrarModalAnular();
});
</script>

</x-app-layout>
