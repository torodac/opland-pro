<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.cl-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.25rem;margin-bottom:16px;}
.dark .cl-card{background:#1a1a1a;border-color:rgba(255,255,255,.08);}
.cl-empty{font-size:13px;color:#9ca3af;text-align:center;padding:1.5rem 0;}
.cl-nav{display:flex;align-items:center;gap:8px;margin-bottom:16px;}
.cl-nav .btn{padding:4px 10px;}
.cl-nav .mes-label{font-size:15px;font-weight:500;min-width:140px;text-align:center;text-transform:capitalize;}
.cl-subtitle{font-size:13px;font-weight:600;margin:0 0 10px;color:#555;}
.dark .cl-subtitle{color:#ccc;}
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

<script>
function recalcular() {
    const btn = document.getElementById('btn-recalcular');
    btn.disabled = true;
    fetch('{{ route("vm.costes-laborales.recalcular", $project->slug) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ anio: {{ $anio }}, mes: {{ $mes }} }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); btn.disabled = false; return; }
        location.reload();
    })
    .catch(() => { alert('Error al recalcular.'); btn.disabled = false; });
}
</script>

</x-app-layout>
