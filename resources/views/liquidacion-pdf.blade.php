<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 1.5cm 1cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; padding: 0.5cm 0.5cm 0; }
    h1 { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    .subtitle { font-size: 9px; color: #6b7280; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    colgroup col:nth-child(1) { width: 33.33%; }
    colgroup col:nth-child(2) { width: 33.33%; }
    colgroup col:nth-child(3) { width: 33.33%; }
    thead tr { background: #f3f4f6; border-bottom: 2px solid #d1d5db; }
    thead th { padding: 7px 10px; font-size: 9px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: .04em; }
    thead th:first-child { text-align: left; }
    thead th:not(:first-child) { text-align: center; }
    .prop-row td { background: #e5e7eb; padding: 5px 10px; font-size: 9px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .05em; border-top: 2px solid #d1d5db; }
    .res-row td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .res-row:nth-child(even) td { background: #fafafa; }
    .booking-id { font-size: 9px; font-weight: 600; color: #9ca3af; font-family: DejaVu Sans Mono, monospace; }
    .guest-name { font-size: 11px; font-weight: 500; }
    .dates { font-size: 9px; color: #9ca3af; margin-top: 1px; }
    .check-box { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #9ca3af; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
    .check-box.checked { background: #16a34a; border-color: #16a34a; }
    .amount { font-size: 11px; font-weight: 600; text-align: right; }
    td.right { text-align: right; }
    td.center { text-align: center; }
    td.truncate { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 0; }
</style>
</head>
<body>
<h1>Planilla de liquidación</h1>
<p class="subtitle">{{ $meses[$mes] }} {{ $anio }} &nbsp;·&nbsp; {{ $byPropiedad->flatten()->count() }} reservas &nbsp;·&nbsp; {{ $byPropiedad->count() }} propiedades</p>

{{-- Cabecera de columnas fija --}}
<table style="width:100%;border-collapse:collapse;margin-bottom:0;">
    <colgroup><col style="width:33.33%"><col style="width:33.33%"><col style="width:33.33%"></colgroup>
    <thead>
        <tr>
            <th style="text-align:left;">Reserva</th>
            <th style="text-align:center;">Importe propietario</th>
            <th style="text-align:center;">Total CC</th>
        </tr>
    </thead>
</table>

@foreach($byPropiedad as $propiedad => $reservas)
<div style="page-break-inside:avoid;">
<table style="width:100%;border-collapse:collapse;margin-bottom:0;">
    <colgroup><col style="width:33.33%"><col style="width:33.33%"><col style="width:33.33%"></colgroup>
    <tbody>
        <tr class="prop-row">
            <td colspan="3">{{ $propiedad }} <span style="font-weight:400;color:#6b7280;">({{ $reservas->count() }})</span></td>
        </tr>
        @foreach($reservas as $r)
        @php
            $checkin  = \Carbon\Carbon::parse($r->check_in_date);
            $checkout = \Carbon\Carbon::parse($r->check_out_date);
            $noches   = $checkin->diffInDays($checkout);
            $impProp  = $importesProp[$r->booking_id] ?? 0;
            $cc       = $comisionCanal[$r->booking_id] ?? null;
            $liq      = (bool) $r->liquidado;
        @endphp
        <tr class="res-row">
            <td>
                <span class="check-box {{ $liq ? 'checked' : '' }}"></span>
                <span class="guest-name">{{ $r->booking_id }} · {{ $r->guest_name }}</span>
                <br>
                <span style="margin-left:18px;" class="dates">{{ $checkin->format('d/m') }} → {{ $checkout->format('d/m') }} · {{ $noches }}n</span>
            </td>
            <td class="center">
                @if($impProp > 0)
                    <span class="amount">{{ number_format($impProp, 2, ',', '.') }} €</span>
                @endif
            </td>
            <td class="center">
                @if(!is_null($cc) && $cc > 0)
                    CC: {{ number_format($cc, 2, ',', '.') }} €
                @else
                    MF:
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
@endforeach
</body>
</html>
