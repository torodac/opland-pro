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
  @if($sinFiltrosEnUrl && $anyo !== '')
    <p style="color:#9ca3af;font-size:11.5px;margin:4px 0 0">
      Mostrando solo el último mes con movimientos pendientes, para que la página cargue rápido — usa los filtros para ver otros periodos.
    </p>
  @endif
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
  <select name="anyo" class="rv-select" style="max-width:130px">
    <option value="">Todos los años</option>
    @foreach($anyos as $a)
      <option value="{{ $a->id }}" @selected($anyo == $a->id)>{{ $a->nombre }}</option>
    @endforeach
  </select>
  <select name="mes" class="rv-select" style="max-width:150px">
    <option value="">Todos los meses</option>
    @foreach($meses as $m)
      <option value="{{ $m->id }}" @selected($mes == $m->id)>{{ $m->nombre }}</option>
    @endforeach
  </select>
  <button type="submit" class="rv-btn" style="background:#16232b">Filtrar</button>
  @if($q !== '' || $fase !== '' || $confianzaMin !== '' || $cuenta !== '' || $anyo !== '' || $mes !== '')
    <a href="{{ route('rodcar.movs-validacion', $project->slug) }}?q=&fase=&confianza_min=&cuenta=&anyo=&mes=" class="rv-filtros-limpiar">Ver todos (sin filtro)</a>
  @endif
</form>

<div class="rv-card">
  <div style="overflow-x:auto">
  <table class="rv-table">
    <thead>
      <tr>
        <th style="width:90px">Fecha</th>
        <th style="width:168px">Concepto</th>
        <th style="width:100px" class="num">Importe</th>
        <th style="width:140px">Cuenta</th>
        <th style="width:170px">Clasificador</th>
        <th style="width:200px">Tipo</th>
        <th style="width:200px">Subtipo</th>
        <th style="width:64px"></th>
      </tr>
    </thead>
    <tbody id="rv-tbody">
      @forelse($pendientes as $p)
        @php $rid = $p->origen . '-' . $p->id; @endphp
        <tr id="rv-row-{{ $rid }}">
          <td>
            {{ \Illuminate\Support\Carbon::parse($p->fecha_operacion)->format('d/m/y') }}
            <div>
              @if($p->origen === 'detalle')
                <span class="rv-tag-origen rv-tag-detalle" title="Línea de detalle de un cargo de tarjeta">DET</span>
              @else
                <span class="rv-tag-origen rv-tag-movimiento" title="Movimiento de cuenta/tarjeta">MOV</span>
              @endif
            </div>
          </td>
          <td>
            <div class="rv-nombre">{{ $p->nombre }}</div>
            @if($p->justificacion_ia)
              <div class="rv-justificacion" title="{{ $p->justificacion_ia }}">{{ \Illuminate\Support\Str::limit($p->justificacion_ia, 90) }}</div>
            @endif
          </td>
          <td class="num">{{ number_format($p->importe ?? 0, 2, ',', '.') }} €</td>
          <td>{{ $p->cuenta_nombre ?? '—' }}</td>
          <td>
            @if($p->fase_clasificacion)
              <span class="rv-chip rv-chip-{{ $p->fase_clasificacion == 2 ? 'similitud' : 'ia' }}">
                {{ $p->fase_clasificacion == 2 ? 'Similitud' : 'IA' }} · {{ $p->confianza_ia }}%
              </span>
            @else
              <span style="color:#c2c9ce">—</span>
            @endif
          </td>
          <td>
            <select class="rv-select" id="rv-tipo1-{{ $rid }}">
              <option value="">— Selecciona —</option>
              @foreach($tipos1 as $t)
                <option value="{{ $t->id }}" @selected($t->id == $p->id_movs_tipo1_propuesto)>{{ $t->nombre }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <select class="rv-select" id="rv-tipo2-{{ $rid }}">
              <option value="">— Ninguno —</option>
              @foreach($tipos2 as $t)
                <option value="{{ $t->id }}" @selected($t->id == $p->id_movs_tipo2_propuesto)>{{ $t->nombre }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:4px">
              <button class="rv-btn" onclick="confirmarFila('{{ $p->origen }}', {{ $p->id }}, false, this)" title="Confirmar solo esta fila" style="display:inline-flex;align-items:center;justify-content:center">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
              <button class="rv-btn rv-btn-mapeo" onclick="confirmarFila('{{ $p->origen }}', {{ $p->id }}, true, this)" title="Confirmar y recordar este concepto como mapeo" style="display:inline-flex;align-items:center;justify-content:center;gap:3px">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 017 7h2M15 7h2a5 5 0 010 10h-2M8 12h8"/></svg>
              </button>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;color:#7e93a1;padding:26px">No hay movimientos pendientes de validar 🎉</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>

<style>
.rv-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden}
.rv-table{width:100%;border-collapse:collapse;font-size:12.5px}
.rv-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rv-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6;vertical-align:top}
.rv-table td.num, .rv-table th.num{text-align:right}
.rv-nombre{font-weight:600;color:#16232b}
.rv-tag-origen{display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:99px;margin-top:3px;letter-spacing:.03em}
.rv-tag-movimiento{color:#1b5d73;background:#eaf1f6}
.rv-tag-detalle{color:#9a3412;background:#fff1e0}
.rv-justificacion{font-size:10.5px;color:#7e93a1;margin-top:2px;cursor:help}
.rv-chip{display:inline-flex;align-items:center;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap}
.rv-chip-similitud{background:#eaf1f6;color:#1b5d73}
.rv-chip-ia{background:#fff1e0;color:#c2570a}
.rv-select{width:100%;font-size:12px;padding:5px 6px;border:1px solid #dce6ee;border-radius:6px;background:#fff}
.rv-btn{padding:6px 10px;font-size:11.5px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:6px;cursor:pointer;transition:background .15s}
.rv-btn:hover{background:#ea580c}
.rv-btn:disabled{background:#dce6ee;cursor:default}
.rv-btn-mapeo{background:#2563eb}
.rv-btn-mapeo:hover{background:#1d4ed8}
.rv-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;padding:0 5px;margin-left:4px;font-size:10.5px;font-weight:700;background:rgba(255,255,255,.25);border-radius:99px}
.rv-filtros{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.rv-filtros-limpiar{font-size:11.5px;color:#7e93a1;text-decoration:underline;cursor:pointer}
</style>

@php
  $rutaValidarTpl = route('rodcar.movs-validacion.validar', [$project->slug, '__ORIGEN__', '__ID__']);
@endphp
<script>
const CSRF = @json(csrf_token());
const ROUTE_TPL = @json($rutaValidarTpl);
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

async function confirmarFila(origen, id, crearMapeo, btn) {
  const rid = origen + '-' + id;
  const tipo1 = document.getElementById('rv-tipo1-' + rid).value;
  const tipo2 = document.getElementById('rv-tipo2-' + rid).value;
  if (!tipo1) { alert('Selecciona un tipo antes de confirmar.'); return; }

  const fila = document.getElementById('rv-row-' + rid);
  fila.querySelectorAll('.rv-btn').forEach(b => b.disabled = true);
  const iconoOriginal = btn.innerHTML;
  btn.textContent = 'Guardando…';

  const res = await fetch(ROUTE_TPL.replace('__ORIGEN__', origen).replace('__ID__', id), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: JSON.stringify({ id_movs_tipo1: parseInt(tipo1), id_movs_tipo2: tipo2 ? parseInt(tipo2) : null, crear_mapeo: crearMapeo }),
  });

  if (!res.ok) {
    alert('Error al guardar.');
    fila.querySelectorAll('.rv-btn').forEach(b => b.disabled = false);
    btn.innerHTML = iconoOriginal;
    return;
  }

  const data = await res.json();
  if (data.extra_clasificados > 0) {
    alert(`Además de esta fila, se han clasificado automáticamente ${data.extra_clasificados} movimiento(s) más con el mismo concepto (mapeo aplicado). Se recarga la página para reflejarlo.`);
    window.location.reload();
    return;
  }

  fila.remove();
  const contador = document.getElementById('rv-contador');
  contador.textContent = Math.max(0, parseInt(contador.textContent) - 1);
  if (!document.querySelector('#rv-tbody tr')) {
    document.getElementById('rv-tbody').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#7e93a1;padding:26px">No hay movimientos pendientes de validar 🎉</td></tr>';
  }
}
</script>

</x-app-layout>
