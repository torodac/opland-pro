<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

@php
    $csrf = csrf_token();
@endphp

{{-- Cabecera: selector mes / año + botón PDF --}}
<div class="mb-6 flex items-center gap-3 no-print">
    <form method="GET" class="flex items-center gap-2">
        <select name="mes" onchange="this.form.submit()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            @foreach($meses as $n => $label)
                <option value="{{ $n }}" {{ $mes == $n ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="anio" onchange="this.form.submit()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            @foreach(range(now()->year - 2, now()->year + 1) as $y)
                <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </form>
    <span class="text-xs text-gray-400">
        {{ $byPropiedad->flatten()->count() }} reservas · {{ $byPropiedad->count() }} propiedades
    </span>
    <a href="{{ route('vm.liquidacion.pdf', $project->slug) }}?mes={{ $mes }}&anio={{ $anio }}"
       class="ml-auto flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition-colors"
       style="text-decoration:none;">
        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Exportar PDF
    </a>
</div>

@if($byPropiedad->isEmpty())
    <div class="text-center py-16 text-gray-400 text-sm no-print">No hay reservas con checkout en {{ $meses[$mes] }} {{ $anio }}.</div>
@else
<div class="overflow-x-auto rounded-xl border border-gray-200">
<table style="width:100%;border-collapse:collapse;min-width:580px;">
    <colgroup>
        <col style="width:33.33%">
        <col style="width:33.33%">
        <col style="width:33.33%">
    </colgroup>
    <thead>
        <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">
            <th style="text-align:left;padding:10px 14px;font-size:12px;font-weight:600;color:#6b7280;">Reserva</th>
            <th style="text-align:center;padding:10px 14px;font-size:12px;font-weight:600;color:#6b7280;">Importe propietario</th>
            <th style="text-align:center;padding:10px 14px;font-size:12px;font-weight:600;color:#6b7280;">Total CC</th>
        </tr>
    </thead>
    <tbody>
    @foreach($byPropiedad as $propiedad => $reservas)
        {{-- Fila cabecera propiedad --}}
        <tr style="background:#f1f5f9;border-top:2px solid #e2e8f0;">
            <td colspan="3" style="padding:7px 14px;">
                <span style="font-size:11px;font-weight:700;color:#374151;letter-spacing:.05em;text-transform:uppercase;">{{ $propiedad }}</span>
                <span style="font-size:11px;color:#94a3b8;margin-left:8px;">{{ $reservas->count() }} {{ $reservas->count() === 1 ? 'reserva' : 'reservas' }}</span>
            </td>
        </tr>
        {{-- Filas reservas --}}
        @foreach($reservas as $r)
        @php
            $checkin = \Carbon\Carbon::parse($r->check_in_date);
            $checkout = \Carbon\Carbon::parse($r->check_out_date);
            $noches  = $checkin->diffInDays($checkout);
            $impProp = $importesProp[$r->booking_id] ?? 0;
            $cc      = $comisionCanal[$r->booking_id] ?? null;
            $liq     = (bool) $r->liquidado;
        @endphp
        <tr class="liq-row" data-id="{{ $r->id }}"
            style="border-bottom:1px solid #f1f5f9;transition:background .15s;">
            {{-- Reserva + check juntos en la misma celda --}}
            <td style="padding:9px 14px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <button class="liq-check no-print" data-id="{{ $r->id }}"
                            style="flex-shrink:0;width:20px;height:20px;border-radius:4px;border:2px solid {{ $liq ? '#16a34a' : '#d1d5db' }};background:{{ $liq ? '#16a34a' : 'white' }};cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;"
                            title="{{ $liq ? 'Marcar como no liquidado' : 'Marcar como liquidado' }}">
                        @if($liq)
                            <svg style="width:11px;height:11px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                    <div style="min-width:0;overflow:hidden;">
                        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <span style="font-size:13px;font-weight:500;color:#111827;">{{ $r->booking_id }} · {{ $r->guest_name }}</span>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:1px;white-space:nowrap;">
                            {{ $checkin->format('d/m') }} → {{ $checkout->format('d/m') }} · {{ $noches }}n
                        </div>
                    </div>
                </div>
            </td>

            {{-- Importe propietario --}}
            <td style="text-align:center;padding:9px 14px;">
                @if($impProp > 0)
                    <span style="font-size:13px;font-weight:600;color:#111827;">{{ number_format($impProp, 2, ',', '.') }} €</span>
                @endif
            </td>
            {{-- Total CC --}}
            <td style="text-align:center;padding:9px 14px;font-size:13px;color:#111827;">
                @if(!is_null($cc) && $cc > 0)
                    CC: {{ number_format($cc, 2, ',', '.') }} €
                @else
                    MF:
                @endif
            </td>
        </tr>
        @endforeach
    @endforeach

    </tbody>
</table>
</div>
@endif

<script>
(function () {
    const CSRF = '{{ $csrf }}';

    document.querySelectorAll('.liq-check').forEach(btn => {
        btn.addEventListener('click', function () {
            const id  = this.dataset.id;
            const row = this.closest('tr');

            fetch('/{{ $project->slug }}/liquidacion/' + id + '/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({}),
            })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;
                const liq = data.liquidado === 1;
                this.style.borderColor = liq ? '#16a34a' : '#d1d5db';
                this.style.background  = liq ? '#16a34a' : 'white';
                this.innerHTML = liq
                    ? '<svg style="width:11px;height:11px;" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
                    : '';
                row.style.background = liq ? '#f0fdf4' : '';
                setTimeout(() => row.style.background = '', 800);
            });
        });
    });

    document.querySelectorAll('.liq-row').forEach(row => {
        row.addEventListener('mouseenter', () => { if (!row.style.background.includes('f0fdf4')) row.style.background = '#f9fafb'; });
        row.addEventListener('mouseleave', () => { if (!row.style.background.includes('f0fdf4')) row.style.background = ''; });
    });
})();
</script>

</x-app-layout>
