<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions">
    <a href="{{ route('listado', [$project->slug, $projectTable->name]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        Volver al listado
    </a>
</x-slot>

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Actualización masiva — {{ $projectTable->label }}</h2>
  <p style="color:#52697a;font-size:12.5px;margin:0">
    Pega los IDs de los registros a actualizar y marca solo los campos que quieres cambiar. Los campos sin marcar no se tocan.
  </p>
</div>

<div class="am-card" style="margin-bottom:16px">
  <div class="am-card-body">
    <label class="am-label" for="am-ids">IDs a actualizar (separados por comas)</label>
    <textarea id="am-ids" rows="3" class="am-textarea" placeholder="12,45,78,103"></textarea>
    <p class="am-hint" id="am-contador-ids">0 IDs detectados</p>
  </div>
</div>

<div class="am-card">
  <div class="am-card-body am-campos-grid">
    @foreach($campos as $campo)
      <div class="am-campo-row">
        <input type="checkbox" class="am-check" id="am-check-{{ $campo->name }}"
               onchange="document.getElementById('am-fieldset-{{ $campo->name }}').disabled = !this.checked">
        <div class="am-campo-body">
          <label for="campo_{{ $campo->name }}" class="am-campo-label">
            {{ $campo->label }}
            <span class="am-campo-name">({{ $campo->name }})</span>
          </label>
          <fieldset id="am-fieldset-{{ $campo->name }}" disabled class="am-fieldset">
            @include('partials.field', ['campo' => $campo, 'valor' => null])
          </fieldset>
        </div>
      </div>
    @endforeach
  </div>
</div>

<div style="margin-top:16px;display:flex;justify-content:flex-end">
  <button class="am-btn-aplicar" id="am-btn-aplicar" onclick="aplicarMasivo()">Aplicar actualización masiva</button>
</div>

<style>
.am-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06)}
.am-card-body{padding:16px 18px}
.am-label{display:block;font-size:12.5px;font-weight:700;color:#16232b;margin-bottom:6px}
.am-textarea{width:100%;font-size:13px;padding:8px 10px;border:1px solid #dce6ee;border-radius:8px;resize:vertical}
.am-hint{font-size:11px;color:#7e93a1;margin-top:4px}
.am-campos-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.am-campo-row{display:flex;align-items:flex-start;gap:10px;padding:12px;border-bottom:1px solid #eaf1f6}
.am-check{margin-top:22px;width:16px;height:16px;accent-color:#f97316;flex-shrink:0;cursor:pointer}
.am-campo-body{flex:1;min-width:0}
.am-campo-label{display:block;font-size:12px;font-weight:700;color:#16232b;margin-bottom:4px}
.am-campo-name{font-weight:400;color:#a3adb3;font-size:10.5px}
.am-fieldset{border:none;margin:0;padding:0}
.am-fieldset:disabled{opacity:.45}
.am-btn-aplicar{padding:9px 18px;font-size:13px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:8px;cursor:pointer;transition:background .15s}
.am-btn-aplicar:hover{background:#ea580c}
.am-btn-aplicar:disabled{background:#dce6ee;cursor:default}
@media (max-width: 900px){ .am-campos-grid{grid-template-columns:1fr} }
</style>

<script>
const CSRF = @json(csrf_token());
const ROUTE_BULK_UPDATE = @json(route('ficha.bulk-update', [$project->slug, $projectTable->name]));

function idsParseadas() {
  return document.getElementById('am-ids').value
    .split(',')
    .map(s => s.trim())
    .filter(s => s !== '' && /^\d+$/.test(s));
}

document.getElementById('am-ids').addEventListener('input', () => {
  document.getElementById('am-contador-ids').textContent = idsParseadas().length + ' IDs detectados';
});

async function aplicarMasivo() {
  const ids = idsParseadas();
  if (ids.length === 0) { alert('Pega al menos un ID válido.'); return; }

  const camposMarcados = Array.from(document.querySelectorAll('.am-check:checked'))
    .map(chk => chk.id.replace('am-check-', ''));
  if (camposMarcados.length === 0) { alert('Marca al menos un campo a aplicar.'); return; }

  if (!confirm(`Se va a aplicar el campo (${camposMarcados.join(', ')}) a ${ids.length} registro(s). ¿Continuar?`)) return;

  const btn = document.getElementById('am-btn-aplicar');
  btn.disabled = true;
  btn.textContent = 'Aplicando…';

  const form = new FormData();
  form.append('ids', ids.join(','));
  document.querySelectorAll('.am-fieldset:not(:disabled) input, .am-fieldset:not(:disabled) select, .am-fieldset:not(:disabled) textarea')
    .forEach(el => {
      if (el.type === 'checkbox' && el.name) {
        form.append(el.name, el.checked ? '1' : '0');
      } else if (el.name) {
        form.append(el.name, el.value);
      }
    });

  try {
    const res = await fetch(ROUTE_BULK_UPDATE, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: form,
    });
    const data = await res.json();
    if (!res.ok) {
      alert(data.message || 'Error al aplicar la actualización masiva.');
    } else {
      alert(`Actualizados ${data.afectados} de ${data.ids_recibidos} registros.`);
    }
  } catch (e) {
    alert('Error de red al aplicar la actualización masiva.');
  }

  btn.disabled = false;
  btn.textContent = 'Aplicar actualización masiva';
}
</script>

</x-app-layout>
