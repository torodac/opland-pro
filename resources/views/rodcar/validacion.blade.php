<x-app-layout :project="$project" :breadcrumb="[['label'=>'Validación de movimientos','url'=>'']]">

<x-slot name="actions">
    <button class="rv-btn" id="rv-btn-clasificar" onclick="lanzarClasificacion()">
        Clasificar movimientos nuevos @if($sinClasificar) <span class="rv-badge">{{ $sinClasificar }}</span> @endif
    </button>
</x-slot>

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Validación de movimientos</h2>
  <p style="color:#52697a;font-size:12.5px;margin:0">
    <span id="rv-contador">{{ count($pendientes) }}</span> movimientos propuestos por el clasificador automático, pendientes de confirmar.
    @if($sinClasificar)
      · <span id="rv-sin-clasificar">{{ $sinClasificar }}</span> sin procesar todavía
    @endif
  </p>
</div>

<form method="GET" action="{{ route('rodcar.movs-validacion', $project->slug) }}" class="rv-filtros">
  <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por concepto…" class="rv-select" style="max-width:260px">
  <select name="fase" class="rv-select" style="max-width:160px">
    <option value="">Todas las fases</option>
    <option value="2" @selected($fase == '2')>Similitud</option>
    <option value="3" @selected($fase == '3')>IA</option>
  </select>
  <select name="confianza_min" class="rv-select" style="max-width:180px">
    <option value="">Cualquier confianza</option>
    <option value="90" @selected($confianzaMin == '90')>≥ 90%</option>
    <option value="75" @selected($confianzaMin == '75')>≥ 75%</option>
    <option value="50" @selected($confianzaMin == '50')>≥ 50%</option>
  </select>
  <select name="cuenta" class="rv-select" style="max-width:200px">
    <option value="">Todas las cuentas</option>
    @foreach($cuentas as $c)
      <option value="{{ $c->id }}" @selected($cuenta == $c->id)>{{ $c->nombre }}</option>
    @endforeach
  </select>
  <button type="submit" class="rv-btn" style="background:#16232b">Filtrar</button>
  @if($q !== '' || $fase !== '' || $confianzaMin !== '' || $cuenta !== '')
    <a href="{{ route('rodcar.movs-validacion', $project->slug) }}" class="rv-filtros-limpiar">Limpiar</a>
  @endif
</form>

<div class="rv-card">
  <table class="rv-table">
    <thead>
      <tr>
        <th style="width:90px">Fecha</th>
        <th>Concepto</th>
        <th style="width:100px" class="num">Importe</th>
        <th style="width:140px">Cuenta</th>
        <th style="width:170px">Origen</th>
        <th style="width:200px">Tipo</th>
        <th style="width:200px">Subtipo</th>
        <th style="width:90px"></th>
      </tr>
    </thead>
    <tbody id="rv-tbody">
      @forelse($pendientes as $p)
        <tr id="rv-row-{{ $p->id }}">
          <td>{{ \Illuminate\Support\Carbon::parse($p->fecha_operacion)->format('d/m/y') }}</td>
          <td>
            <div class="rv-nombre">{{ $p->nombre }}</div>
            @if($p->justificacion_ia)
              <div class="rv-justificacion" title="{{ $p->justificacion_ia }}">{{ \Illuminate\Support\Str::limit($p->justificacion_ia, 90) }}</div>
            @endif
          </td>
          <td class="num">{{ number_format($p->importe ?? 0, 2, ',', '.') }} €</td>
          <td>{{ $p->cuenta_nombre ?? '—' }}</td>
          <td>
            <span class="rv-chip rv-chip-{{ $p->fase_clasificacion == 2 ? 'similitud' : 'ia' }}">
              {{ $p->fase_clasificacion == 2 ? 'Similitud' : 'IA' }} · {{ $p->confianza_ia }}%
            </span>
          </td>
          <td>
            <select class="rv-select" id="rv-tipo1-{{ $p->id }}">
              <option value="">— Selecciona —</option>
              @foreach($tipos1 as $t)
                <option value="{{ $t->id }}" @selected($t->id == $p->id_movs_tipo1_propuesto)>{{ $t->nombre }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <select class="rv-select" id="rv-tipo2-{{ $p->id }}">
              <option value="">— Ninguno —</option>
              @foreach($tipos2 as $t)
                <option value="{{ $t->id }}" @selected($t->id == $p->id_movs_tipo2_propuesto)>{{ $t->nombre }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <button class="rv-btn" onclick="confirmarFila({{ $p->id }})">Confirmar</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;color:#7e93a1;padding:26px">No hay movimientos pendientes de validar 🎉</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<style>
.rv-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden}
.rv-table{width:100%;border-collapse:collapse;font-size:12.5px}
.rv-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rv-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6;vertical-align:middle}
.rv-table td.num, .rv-table th.num{text-align:right}
.rv-nombre{font-weight:600;color:#16232b}
.rv-justificacion{font-size:10.5px;color:#7e93a1;margin-top:2px;cursor:help}
.rv-chip{display:inline-flex;align-items:center;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap}
.rv-chip-similitud{background:#eaf1f6;color:#1b5d73}
.rv-chip-ia{background:#fff1e0;color:#c2570a}
.rv-select{width:100%;font-size:12px;padding:5px 6px;border:1px solid #dce6ee;border-radius:6px;background:#fff}
.rv-btn{padding:6px 12px;font-size:11.5px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:6px;cursor:pointer;transition:background .15s}
.rv-btn:hover{background:#ea580c}
.rv-btn:disabled{background:#dce6ee;cursor:default}
.rv-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;padding:0 5px;margin-left:4px;font-size:10.5px;font-weight:700;background:rgba(255,255,255,.25);border-radius:99px}
.rv-filtros{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.rv-filtros-limpiar{font-size:11.5px;color:#7e93a1;text-decoration:underline;cursor:pointer}
</style>

<script>
const CSRF = @json(csrf_token());
const ROUTE_TPL = @json(route('rodcar.movs-validacion.validar', [$project->slug, '__ID__']));
const ROUTE_CLASIFICAR = @json(route('rodcar.movs-validacion.clasificar', $project->slug));

async function lanzarClasificacion() {
  const btn = document.getElementById('rv-btn-clasificar');
  btn.disabled = true;
  const textoOriginal = btn.innerHTML;
  btn.textContent = 'Lanzando…';

  const res = await fetch(ROUTE_CLASIFICAR, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
  });

  if (!res.ok) {
    alert('No se pudo lanzar la clasificación.');
    btn.disabled = false;
    btn.innerHTML = textoOriginal;
    return;
  }

  btn.textContent = 'En marcha — recarga en unos minutos';
}

async function confirmarFila(id) {
  const tipo1 = document.getElementById('rv-tipo1-' + id).value;
  const tipo2 = document.getElementById('rv-tipo2-' + id).value;
  if (!tipo1) { alert('Selecciona un tipo antes de confirmar.'); return; }

  const btn = document.querySelector(`#rv-row-${id} .rv-btn`);
  btn.disabled = true;
  btn.textContent = 'Guardando…';

  const res = await fetch(ROUTE_TPL.replace('__ID__', id), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: JSON.stringify({ id_movs_tipo1: parseInt(tipo1), id_movs_tipo2: tipo2 ? parseInt(tipo2) : null }),
  });

  if (!res.ok) {
    alert('Error al guardar.');
    btn.disabled = false;
    btn.textContent = 'Confirmar';
    return;
  }

  const row = document.getElementById('rv-row-' + id);
  row.remove();
  const contador = document.getElementById('rv-contador');
  contador.textContent = Math.max(0, parseInt(contador.textContent) - 1);
  if (!document.querySelector('#rv-tbody tr')) {
    document.getElementById('rv-tbody').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#7e93a1;padding:26px">No hay movimientos pendientes de validar 🎉</td></tr>';
  }
}
</script>

</x-app-layout>
