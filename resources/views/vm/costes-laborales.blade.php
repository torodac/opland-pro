<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff}
.dark .btn{background:#222;border-color:rgba(255,255,255,.15);color:#eee}
.cl-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.25rem;margin-bottom:16px;}
.dark .cl-card{background:#1a1a1a;border-color:rgba(255,255,255,.08);}
.cl-empty{font-size:13px;color:#9ca3af;text-align:center;padding:1.5rem 0;}
.cl-nav{display:flex;align-items:center;gap:8px;margin-bottom:16px;}
.cl-nav .btn{padding:4px 10px;}
.cl-nav .mes-label{font-size:15px;font-weight:500;min-width:140px;text-align:center;text-transform:capitalize;}
.cl-subtitle{font-size:13px;font-weight:600;margin:0 0 10px;color:#555;}
.dark .cl-subtitle{color:#ccc;}
.cl-stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;}
@media (max-width:760px){ .cl-stat-row{grid-template-columns:1fr 1fr;} }
.cl-stat-tile{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:14px 16px;}
.dark .cl-stat-tile{background:#1a1a1a;border-color:rgba(255,255,255,.08);}
.cl-stat-label{font-size:11.5px;color:#6b7280;font-weight:600;}
.dark .cl-stat-label{color:#9ca3af;}
.cl-stat-value{font-size:22px;font-weight:650;margin:4px 0 2px;font-variant-numeric:tabular-nums;color:#111827;}
.dark .cl-stat-value{color:#f3f4f6;}
.cl-stat-delta{font-size:12px;color:#6b7280;}
.dark .cl-stat-delta{color:#9ca3af;}
.cl-stat-delta.cl-off{color:#dc2626;font-weight:700;}
@keyframes cl-spin { to { transform: rotate(360deg); } }
</style>

<div style="padding:0 0 3rem;">

  <div class="cl-nav">
    <a class="btn" href="{{ $urlAnterior }}">‹</a>
    <span class="mes-label">{{ $mesLabel }}</span>
    <a class="btn" href="{{ $urlSiguiente }}">›</a>
    <button class="btn" style="margin-left:auto;" id="btn-recalcular" onclick="recalcular()">
      <i class="ti ti-refresh" style="font-size:14px;vertical-align:-2px;"></i> {{ $tieneCalculo ? 'Recalcular' : 'Calcular este mes' }}
    </button>
  </div>

  <div class="cl-stat-row">
    @foreach($statsRecalculo as $s)
      @php $decimales = $s['unidad'] === '€' ? 2 : 1; @endphp
      <div class="cl-stat-tile">
        <div class="cl-stat-label">{{ $s['label'] }}</div>
        <div class="cl-stat-value">{{ number_format($s['actual'], $decimales, ',', '.') }} {{ $s['unidad'] }}</div>
        <div class="cl-stat-delta {{ $s['fueraDeRango'] ? 'cl-off' : '' }}">
          @if($s['variacion'] === null)
            Sin calcular todavía
          @else
            {{ $s['variacion'] >= 0 ? '+' : '' }}{{ number_format($s['variacion'], 1, ',', '.') }}% vs. repartido
          @endif
        </div>
      </div>
    @endforeach
  </div>

  @if(!$tieneCalculo)
  <div class="cl-card">
    <p class="cl-empty">Este mes todavía no se ha calculado. Pulsa "Calcular este mes" para repartir el coste de las nóminas de Limpieza y Mantenimiento de {{ $mesLabel }} entre propiedades.</p>
  </div>
  @else
  <div class="cl-card">
    <p class="cl-subtitle">Por trabajador</p>
    @if($filasTrabajadores->isEmpty())
      <p class="cl-empty">No hay nóminas de Limpieza/Mantenimiento cargadas para {{ $mesLabel }}.</p>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="border-bottom:2px solid #e5e7eb;">
          <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Trabajador</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Limpieza</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Mantenimiento</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Total</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Horas imputadas</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Horas fichadas</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Coste/hora</th>
        </tr>
      </thead>
      <tbody>
        @foreach($filasTrabajadores as $f)
        <tr style="border-bottom:1px solid #f3f4f6;">
          <td style="padding:8px 10px;font-weight:600;color:#111827;">{{ $f->nombre }}</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->limpieza, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->mantenimiento, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->total, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->horas, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->horas_fichadas, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ $f->coste_hora !== null ? number_format($f->coste_hora, 2, ',', '.') . ' €' : '—' }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid #e5e7eb;">
          <td style="padding:8px 10px;font-weight:700;color:#111827;">Total</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totalesTrabajadores->limpieza, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totalesTrabajadores->mantenimiento, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totalesTrabajadores->total, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totalesTrabajadores->horas, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totalesTrabajadores->horas_fichadas, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ $totalesTrabajadores->coste_hora !== null ? number_format($totalesTrabajadores->coste_hora, 2, ',', '.') . ' €' : '—' }}</td>
        </tr>
      </tfoot>
    </table>
    @endif
  </div>

  <div class="cl-card">
    <p class="cl-subtitle">Por propiedad</p>
    @if($filas->isEmpty())
      <p class="cl-empty">Ninguna propiedad tiene coste repartido en {{ $mesLabel }}.</p>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="border-bottom:2px solid #e5e7eb;">
          <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Propiedad</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Limpieza</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">H. limp.</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Mantenimiento</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">H. mto.</th>
          <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($filas as $f)
        <tr style="border-bottom:1px solid #f3f4f6;">
          <td style="padding:8px 10px;font-weight:600;color:#111827;">{{ $f->propiedad }}</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->limpieza, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ $f->horas_limpieza > 0 ? number_format($f->horas_limpieza, 1, ',', '.') . ' h' : '—' }}</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->mantenimiento, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ $f->horas_mantenimiento > 0 ? number_format($f->horas_mantenimiento, 1, ',', '.') . ' h' : '—' }}</td>
          <td style="padding:8px 10px;text-align:right;color:#374151;">{{ number_format($f->total, 2, ',', '.') }} €</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid #e5e7eb;">
          <td style="padding:8px 10px;font-weight:700;color:#111827;">Total</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totales->limpieza, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totales->horas_limpieza, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totales->mantenimiento, 2, ',', '.') }} €</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totales->horas_mantenimiento, 1, ',', '.') }} h</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;color:#111827;">{{ number_format($totales->total, 2, ',', '.') }} €</td>
        </tr>
      </tfoot>
    </table>
    @endif
  </div>
  @endif

</div>

<div id="cl-loading-overlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:36px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <svg style="width:48px;height:48px;margin-bottom:16px;animation:cl-spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" stroke="#e5e7eb" stroke-width="3"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke="#7367f0" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <div style="font-size:16px;font-weight:600;color:#333;margin-bottom:6px;">Calculando coste laboral</div>
        <div style="font-size:13px;color:#666;">Repartiendo las nóminas de Limpieza y Mantenimiento entre propiedades&hellip;</div>
        <div style="font-size:12px;color:#999;margin-top:4px;">Normalmente tarda unos segundos</div>
        <div id="cl-timer" style="font-size:20px;font-weight:700;color:#7367f0;margin-top:10px;font-variant-numeric:tabular-nums;">0:00</div>
    </div>
</div>

<script>
function recalcular() {
    const btn = document.getElementById('btn-recalcular');
    const overlay = document.getElementById('cl-loading-overlay');
    const timerEl = document.getElementById('cl-timer');
    btn.disabled = true;
    overlay.style.display = 'flex';

    const inicio = Date.now();
    timerEl.textContent = '0:00';
    const timerInterval = setInterval(() => {
        const segs = Math.floor((Date.now() - inicio) / 1000);
        const m = Math.floor(segs / 60);
        const s = String(segs % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
    }, 1000);
    const pararTimer = () => clearInterval(timerInterval);

    fetch('{{ route("vm.costes-laborales.recalcular", $project->slug) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ anio: {{ $anio }}, mes: {{ $mes }} }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { pararTimer(); alert(data.error); btn.disabled = false; overlay.style.display = 'none'; return; }
        location.reload();
    })
    .catch(() => { pararTimer(); alert('Error al recalcular.'); btn.disabled = false; overlay.style.display = 'none'; });
}
</script>

</x-app-layout>
