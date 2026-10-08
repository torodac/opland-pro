<x-app-layout :project="$project" :breadcrumb="[['label'=>'Cobros','url'=>route('clase.cobros',$project->slug)],['label'=>'Recibos','url'=>'']]">

<div class="flex items-center justify-between mb-4">
  <h1 class="text-sm font-bold text-gray-900">Recibos</h1>
  <a href="{{ route('clase.cobros', $project->slug) }}"
     class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
    &larr; Cobros
  </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <table class="w-full text-xs">
    <thead>
      <tr class="border-b border-gray-200 bg-gray-50">
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Nº Recibo</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Fecha</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Mes</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Pagador</th>
        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Total</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Formas</th>
        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Estado</th>
        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-gray-400">Acciones</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
    @forelse($recibos as $r)
      @php
        $formas = json_decode($r->formas_pago ?? '[]', true);
        $formasStr = collect($formas)->map(fn($f) => $f['forma'] . ' ' . number_format($f['importe'],2,',','.') . '€')->join(' / ');
      @endphp
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3 font-semibold text-gray-900">{{ $r->numero_recibo }}</td>
        <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td>
        <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::parse($r->mes.'-01')->translatedFormat('M Y') }}</td>
        <td class="px-4 py-3 text-gray-700">{{ $r->pagador_nombre }}</td>
        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format((float)$r->importe_total,2,',','.') }} €</td>
        <td class="px-4 py-3 text-gray-400">{{ $formasStr ?: '—' }}</td>
        <td class="px-4 py-3">
          @if($r->numero_factura)
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700"
                  title="Facturado el {{ \Carbon\Carbon::parse($r->fecha_factura)->format('d/m/Y') }}">
              {{ $r->numero_factura }}
            </span>
          @else
            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-sky-50 text-sky-700">Recibo</span>
          @endif
        </td>
        <td class="px-4 py-3">
          <div class="flex justify-end items-center gap-2">
            <a href="{{ route('clase.recibos.pdf', [$project->slug, $r->id]) }}" target="_blank"
               class="inline-flex items-center gap-1 px-2.5 py-1 border border-gray-200 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
              </svg>
              PDF
            </a>
            @if(!$r->numero_factura)
            <button onclick="facturar({{ $r->id }}, this)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
              </svg>
              Facturar
            </button>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin recibos</td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="mt-4">{{ $recibos->links() }}</div>

<script>
const TOKEN = "{{ csrf_token() }}";
async function facturar(id, btn) {
    if (!confirm('¿Asignar número de factura a este recibo?\n\nSi el pagador tiene email se enviará automáticamente.')) return;
    btn.disabled = true;
    const url = "{{ route('clase.recibos.facturar', [$project->slug, '__ID__']) }}".replace('__ID__', id);
    const r = await fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':TOKEN}});
    const data = await r.json();
    if (data.ok) {
        if (data.enviado) alert('Factura enviada por email.');
        location.reload();
    } else {
        btn.disabled = false;
        alert(data.error || 'Error');
    }
}
</script>

</x-app-layout>
