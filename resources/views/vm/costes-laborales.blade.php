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
.cl-stat-row{
  display:flex; background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;
  padding:10px 4px; margin-bottom:16px;
}
.dark .cl-stat-row{background:#1a1a1a;border-color:rgba(255,255,255,.08);}
@media (max-width:760px){ .cl-stat-row{flex-wrap:wrap;} .cl-stat-item{flex:1 1 45%!important;} }
.cl-stat-item{flex:1;padding:2px 14px;position:relative;}
.cl-stat-item + .cl-stat-item{border-left:1px solid rgba(0,0,0,.08);}
.dark .cl-stat-item + .cl-stat-item{border-color:rgba(255,255,255,.08);}
.cl-stat-top{display:flex;align-items:baseline;justify-content:space-between;gap:6px;}
.cl-stat-label{font-size:10.5px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.03em;}
.dark .cl-stat-label{color:#9ca3af;}
.cl-stat-pct{font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:9px;background:#ecfdf5;color:#15803d;white-space:nowrap;}
.cl-stat-pct.off{background:#fef2f2;color:#dc2626;}
.cl-stat-pct.na{background:transparent;color:#9ca3af;font-weight:600;padding:0;}
.cl-stat-value{font-size:19px;font-weight:700;margin:2px 0 6px;font-variant-numeric:tabular-nums;color:#111827;}
.dark .cl-stat-value{color:#f3f4f6;}
.cl-stat-bar{height:5px;border-radius:3px;background:#e5e7eb;position:relative;}
.dark .cl-stat-bar{background:#333;}
.cl-stat-bar-fill{height:100%;border-radius:3px;background:#2563eb;transition:width .2s;}
.cl-stat-bar-fill.off{background:#dc2626;}
.cl-stat-bar-marker{position:absolute;top:-2px;width:2px;height:9px;background:#111827;border-radius:1px;}
.dark .cl-stat-bar-marker{background:#f3f4f6;}
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
      @php
        $decimales = $s['unidad'] === '€' ? 2 : 1;
        // Barra: el mayor de los dos valores marca el 100% de la escala; la barra rellena hasta
        // el actual, y una marca vertical señala dónde cae el repartido -- si coinciden, la marca
        // queda justo al final del relleno; si hay hueco, se ve a simple vista.
        $escala = max($s['actual'], $s['repartido'], 0.01);
        $pctActual = min(100, ($s['actual'] / $escala) * 100);
        $pctRepartido = min(100, ($s['repartido'] / $escala) * 100);
      @endphp
      <div class="cl-stat-item">
        <div class="cl-stat-top">
          <span class="cl-stat-label">{{ $s['label'] }}</span>
          <span class="cl-stat-pct {{ $s['variacion'] === null ? 'na' : ($s['fueraDeRango'] ? 'off' : '') }}">
            @if($s['variacion'] === null)
              sin calcular
            @else
              {{ $s['variacion'] >= 0 ? '+' : '' }}{{ number_format($s['variacion'], 1, ',', '.') }}%
            @endif
          </span>
        </div>
        <div class="cl-stat-value">{{ number_format($s['actual'], $decimales, ',', '.') }} {{ $s['unidad'] }}</div>
        <div class="cl-stat-bar">
          <div class="cl-stat-bar-fill {{ $s['fueraDeRango'] ? 'off' : '' }}" style="width:{{ $pctActual }}%"></div>
          @if($s['variacion'] !== null)
            <div class="cl-stat-bar-marker" style="left:calc({{ $pctRepartido }}% - 1px)" title="Repartido: {{ number_format($s['repartido'], $decimales, ',', '.') }} {{ $s['unidad'] }}"></div>
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
    // Si algo revienta de forma síncrona (antes de que el fetch llegue a lanzarse), el modal y el
    // temporizador se quedaban colgados para siempre sin ningún aviso -- con el try/catch, un
    // error aquí para el timer y avisa igual que un fallo de red.
    const fallo = (msg) => { pararTimer(); alert(msg); btn.disabled = false; overlay.style.display = 'none'; };

    try {
        fetch('{{ route("vm.costes-laborales.recalcular", $project->slug) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ anio: {{ $anio }}, mes: {{ $mes }} }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) { fallo(data.error); return; }
            location.reload();
        })
        .catch(() => fallo('Error al recalcular.'));
    } catch (e) {
        fallo('Error al recalcular: ' + e.message);
    }
}
</script>

</x-app-layout>
