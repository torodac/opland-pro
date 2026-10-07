@php
    $fmt = fn($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.') . ' €';
@endphp

{{-- El layout pinta el encabezado desde :breadcrumb (o :title); no tiene slot "header", así
     que lo que se le pasaba ahí se descartaba sin avisar y la página salía sin título. --}}
<x-app-layout
    :breadcrumb="[
        ['label' => 'Dev. fianzas', 'url' => ''],
    ]"
    :project="$project">

<div style="padding:0 0 3rem;">

    {{-- Buscador: el mismo de los listados estándar (misma forma, mismo sitio, mismo placeholder). --}}
    <form method="GET" class="flex gap-2 mb-4">
        <input type="text" name="q" value="{{ $q }}"
               placeholder="Buscar..."
               class="flex-1 max-w-xs text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
        <button type="submit"
                class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">
            Buscar
        </button>
        @if($q !== '')
            <a href="{{ route('vm.dev-fianzas', $project->slug) }}"
               class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 rounded-lg transition-colors">Quitar filtro</a>
        @endif
    </form>

    <style>
.df-fila{border-top:0.5px solid rgba(0,0,0,.06);cursor:pointer;transition:background .12s}
.df-fila:hover{background:rgba(0,0,0,.025)}
.dark .df-fila:hover{background:rgba(255,255,255,.04)}
</style>

<div style="background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;overflow:hidden">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:rgba(0,0,0,.02)">
                <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Checkout</th>
                <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Booking</th>
                <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Huésped</th>
                <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Propiedad</th>
                <th style="text-align:right;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Fianza</th>
                <th style="text-align:center;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Coment.</th>
                <th style="text-align:center;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Fotos</th>
                <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Estado</th>
            </tr>
        </thead>
        <tbody>
        @forelse($filas as $f)
            <tr class="df-fila" id="fila-{{ $f->id }}"
                data-url="{{ route('vm.dev-fianza', [$project->slug, $f->id]) }}">
                <td style="padding:8px 12px;font-size:13px;white-space:nowrap">
                    {{ \Carbon\Carbon::parse($f->check_out_date)->format('d/m/Y') }}
                </td>
                <td style="padding:8px 12px;font-size:13px;font-variant-numeric:tabular-nums;white-space:nowrap">
                    {{-- stopPropagation: este enlace va a la ficha de la RESERVA, no a la de
                         revisión, y sin esto el clic en la fila se lo llevaría por delante. --}}
                    <a href="{{ url($project->slug . '/reservas/' . $f->id) }}"
                       onclick="event.stopPropagation()"
                       style="color:#185FA5;text-decoration:none">{{ $f->booking_id }}</a>
                    {{-- Hay limpieza de salida: lo que hace que la reserva se pueda revisar de
                         verdad. Sin ella no hay comentarios ni fotos que mirar. --}}
                    @if($f->n_tareas)
                        <span title="{{ $f->n_tareas }} {{ $f->n_tareas === 1 ? 'limpieza' : 'limpiezas' }} de salida asociada{{ $f->n_tareas === 1 ? '' : 's' }}"
                              style="color:#1B7F3B;margin-left:5px;font-weight:600">✔</span>
                    @endif
                </td>
                <td style="padding:8px 12px;font-size:13px">{{ $f->nombre ?: '—' }}</td>
                <td style="padding:8px 12px;font-size:13px;color:#666">{{ $f->propiedad ?: '—' }}</td>
                <td style="padding:8px 12px;font-size:13px;text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap">
                    {{ $fmt($f->fianza) }}
                </td>
                <td style="padding:8px 12px;font-size:13px;text-align:center;color:{{ $f->n_comentarios ? '#222' : '#ccc' }}">
                    {{ $f->n_comentarios }}
                </td>
                <td style="padding:8px 12px;font-size:13px;text-align:center;color:{{ $f->n_fotos ? '#222' : '#ccc' }}">
                    {{ $f->n_fotos }}
                </td>
                <td style="padding:8px 12px;font-size:12px;white-space:nowrap" class="celda-estado">
                    @if($f->estado_fianza === \App\Http\Controllers\Vm\DevFianzasController::ESTADO_APROBADA)
                        <span style="background:#E7F6EC;color:#1B7F3B;padding:2px 8px;border-radius:6px">{{ $f->estado_fianza }}</span>
                    @elseif($f->estado_fianza)
                        <span style="background:#FDF0E3;color:#9A5B00;padding:2px 8px;border-radius:6px">{{ $f->estado_fianza }}</span>
                        @if($f->fianza_retencion)
                            <span style="color:#888;margin-left:4px">{{ $fmt($f->fianza_retencion) }}</span>
                        @endif
                    @else
                        <span style="color:#bbb">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="padding:1.5rem;text-align:center;font-size:13px;color:#999">
                @if($q !== '') Ninguna reserva coincide con «{{ $q }}». @else No hay reservas pendientes. @endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <p style="font-size:11px;color:#bbb;margin:10px 0 0">
        Salidas desde el 1 de octubre y anteriores a hoy. El día del checkout no entra todavía:
        la fianza se lee a las 06:00 y los comentarios de la limpieza llegan a las 22:00.
    </p>
</div>

<script>
// La fila entera abre la ficha de revisión. Las dos decisiones (Conforme y Retener) viven ahí:
// desde el listado no se puede decidir, porque decidir sin haber mirado los comentarios y las
// fotos es justo lo que esta pantalla existe para evitar.
document.querySelectorAll('.df-fila').forEach(function (tr) {
    tr.addEventListener('click', function () { location.href = tr.dataset.url; });
});
</script>
</x-app-layout>
