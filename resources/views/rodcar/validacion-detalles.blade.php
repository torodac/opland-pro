<x-app-layout :project="$project" :breadcrumb="[['label'=>'Validación detalles','url'=>'']]">

<style>
  .vd-summary { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
  .vd-badge   { padding:8px 18px; border-radius:20px; font-size:12px; font-weight:700; }
  .vd-badge.ok  { background:#dcfce7; color:#15803d; }
  .vd-badge.err { background:#fee2e2; color:#dc2626; }
  .vd-badge.tot { background:#f1f5f9; color:#374151; }

  .vd-wrap { background:var(--surface,#fff); border:1px solid var(--border,#e5e7eb);
             border-radius:12px; overflow:hidden; }
  .vd-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
  .vd-tabla th { background:#f8fafc; padding:9px 14px; text-align:left; font-size:11px;
                 font-weight:700; color:#64748b; border-bottom:2px solid var(--border,#e5e7eb);
                 white-space:nowrap; }
  .vd-tabla th.num { text-align:right; }
  .vd-tabla td { padding:8px 14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
  .vd-tabla td.num { text-align:right; font-weight:600; font-variant-numeric:tabular-nums; }
  .vd-tabla tr:last-child td { border-bottom:none; }
  .vd-tabla tr.ok  td { background:#f0fdf4; }
  .vd-tabla tr.err td { background:#fff8f8; }
  .vd-tabla tr:hover td { filter:brightness(.97); cursor:pointer; }

  .vd-dif-ok  { color:#15803d; font-weight:700; }
  .vd-dif-err { color:#dc2626; font-weight:700; }

  .vd-icon { display:inline-block; width:18px; height:18px; border-radius:50%;
             text-align:center; line-height:18px; font-size:11px; font-weight:800; }
  .vd-icon.ok  { background:#dcfce7; color:#15803d; }
  .vd-icon.err { background:#fee2e2; color:#dc2626; }

  /* Modal detalles */
  .vd-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
                z-index:500; align-items:center; justify-content:center; padding:16px; }
  .vd-overlay.open { display:flex; }
  .vd-modal { background:var(--surface,#fff); border-radius:14px; width:100%;
              max-width:720px; max-height:88vh; display:flex; flex-direction:column;
              box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .vd-modal-head { padding:18px 22px 12px; border-bottom:1px solid var(--border,#e5e7eb);
                   display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .vd-modal-head h2 { font-size:14px; font-weight:800; margin:0; line-height:1.3; }
  .vd-modal-head .vd-modal-sub { font-size:11px; color:#94a3b8; margin-top:3px; }
  .vd-modal-body { overflow-y:auto; padding:16px 22px; flex:1; }
  .vd-det-tabla { width:100%; border-collapse:collapse; font-size:12px; }
  .vd-det-tabla th { text-align:left; font-size:11px; font-weight:700; color:#64748b;
                     padding:6px 10px; border-bottom:2px solid var(--border,#e5e7eb);
                     background:#f8fafc; }
  .vd-det-tabla th.num { text-align:right; }
  .vd-det-tabla td { padding:6px 10px; border-bottom:1px solid #f3f4f6; }
  .vd-det-tabla td.num { text-align:right; font-weight:600; }
  .vd-det-foot { display:flex; align-items:center; justify-content:space-between;
                 padding:12px 22px; border-top:1px solid var(--border,#e5e7eb);
                 font-size:12.5px; }
  .vd-det-foot .vd-sumas { display:flex; gap:24px; }
  .vd-det-foot .lbl { color:#9ca3af; font-size:11px; }
  .vd-det-foot .val { font-weight:800; font-size:14px; }
  .btn-close { background:none; border:1.5px solid var(--border,#e5e7eb); border-radius:8px;
               padding:5px 12px; cursor:pointer; font-size:12px; color:#6b7280; }
</style>

@php
$total  = count($rows);
$ok     = collect($rows)->filter(fn($r) => abs((float)$r->diferencia) < 0.01)->count();
$errors = $total - $ok;

function fmtM(float $v): string {
    $s = number_format(abs($v), 2, ',', '.');
    return ($v < 0 ? '-' : '') . $s . ' €';
}
@endphp

{{-- Resumen --}}
<div class="vd-summary">
  <span class="vd-badge tot">{{ $total }} movimientos FICTICIO con detalles</span>
  <span class="vd-badge ok">✓ {{ $ok }} cuadran</span>
  @if($errors > 0)
  <span class="vd-badge err">⚠ {{ $errors }} con diferencia</span>
  @endif
</div>

<div class="vd-wrap">
<table class="vd-tabla">
  <thead>
    <tr>
      <th style="width:24px"></th>
      <th>Fecha</th>
      <th>Nombre movimiento</th>
      <th class="num">Importe mov.</th>
      <th class="num">Nº det.</th>
      <th class="num">Suma detalles</th>
      <th class="num">Diferencia</th>
    </tr>
  </thead>
  <tbody>
  @foreach($rows as $r)
    @php
      $dif   = (float)$r->diferencia;
      $ok_r  = abs($dif) < 0.01;
      $cls   = $ok_r ? 'ok' : 'err';
    @endphp
    <tr class="{{ $cls }}" onclick="verDetalles({{ $r->id }})">
      <td><span class="vd-icon {{ $cls }}">{{ $ok_r ? '✓' : '!' }}</span></td>
      <td>{{ $r->fecha_operacion ? date('d/m/Y', strtotime($r->fecha_operacion)) : '—' }}</td>
      <td>{{ $r->nombre }}</td>
      <td class="num">{{ fmtM((float)$r->importe) }}</td>
      <td class="num">{{ $r->n_det }}</td>
      <td class="num">{{ fmtM((float)$r->sum_det) }}</td>
      <td class="num {{ $ok_r ? 'vd-dif-ok' : 'vd-dif-err' }}">
        {{ $ok_r ? '✓ 0,00 €' : fmtM($dif) }}
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>

{{-- Modal --}}
<div class="vd-overlay" id="vd-overlay" onclick="if(event.target===this)cerrarModal()">
  <div class="vd-modal">
    <div class="vd-modal-head">
      <div>
        <h2 id="vd-modal-title">Cargando...</h2>
        <div class="vd-modal-sub" id="vd-modal-sub"></div>
      </div>
      <button class="btn-close" onclick="cerrarModal()">✕</button>
    </div>
    <div class="vd-modal-body">
      <table class="vd-det-tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Concepto detalle</th>
            <th>Tipo</th>
            <th class="num">Importe</th>
          </tr>
        </thead>
        <tbody id="vd-det-body"></tbody>
      </table>
    </div>
    <div class="vd-det-foot">
      <div class="vd-sumas">
        <div><div class="lbl">Importe movimiento</div><div class="val" id="vd-imp-mov">—</div></div>
        <div><div class="lbl">Suma detalles</div><div class="val" id="vd-imp-sum">—</div></div>
        <div><div class="lbl">Diferencia</div><div class="val" id="vd-imp-dif">—</div></div>
      </div>
      <button class="btn-close" onclick="cerrarModal()">Cerrar</button>
    </div>
  </div>
</div>

<script>
const LOAD_URL = "{{ url('/' . $project->slug . '/validacion-detalles') }}";

function fmt(v) {
  const neg = v < 0;
  const s = new Intl.NumberFormat('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Math.abs(v));
  return (neg ? '-' : '') + s + ' €';
}

async function verDetalles(id) {
  document.getElementById('vd-modal-title').textContent = 'Cargando...';
  document.getElementById('vd-overlay').classList.add('open');

  const res  = await fetch(`${LOAD_URL}/${id}`);
  const data = await res.json();

  const mov = data.mov;
  document.getElementById('vd-modal-title').textContent = mov.nombre;
  document.getElementById('vd-modal-sub').textContent   =
    (mov.fecha_operacion ? new Date(mov.fecha_operacion).toLocaleDateString('es-ES') : '') +
    '  ·  Importe: ' + fmt(parseFloat(mov.importe));

  const tbody = document.getElementById('vd-det-body');
  tbody.innerHTML = '';
  let sumDet = 0;
  data.detalles.forEach(d => {
    sumDet += parseFloat(d.importe) || 0;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${d.fecha_operacion ? new Date(d.fecha_operacion).toLocaleDateString('es-ES') : '—'}</td>
      <td>${d.nombre}</td>
      <td style="color:#6b7280">${[d.tipo0, d.tipo1].filter(Boolean).join(' › ') || '—'}</td>
      <td class="num">${fmt(parseFloat(d.importe)||0)}</td>`;
    tbody.appendChild(tr);
  });

  const impMov = parseFloat(mov.importe) || 0;
  const dif    = impMov - sumDet;
  const ok     = Math.abs(dif) < 0.01;
  document.getElementById('vd-imp-mov').textContent = fmt(impMov);
  document.getElementById('vd-imp-sum').textContent = fmt(sumDet);
  document.getElementById('vd-imp-dif').style.color = ok ? '#15803d' : '#dc2626';
  document.getElementById('vd-imp-dif').textContent = ok ? '✓ 0,00 €' : fmt(dif);
}

function cerrarModal() {
  document.getElementById('vd-overlay').classList.remove('open');
}
</script>

</x-app-layout>
