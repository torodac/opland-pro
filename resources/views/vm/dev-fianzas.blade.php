@php
    $fmt = fn($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.') . ' €';
@endphp

<x-app-layout :project="$project">
<x-slot name="header">
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <span class="font-medium text-gray-700">Dev. fianzas</span>
        <span class="text-gray-300">·</span>
        <span>{{ $filas->count() }} {{ $filas->count() === 1 ? 'reserva' : 'reservas' }}</span>
    </div>
</x-slot>

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
                <th style="padding:8px 12px"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($filas as $f)
            <tr style="border-top:0.5px solid rgba(0,0,0,.06)" id="fila-{{ $f->id }}">
                <td style="padding:8px 12px;font-size:13px;white-space:nowrap">
                    {{ \Carbon\Carbon::parse($f->check_out_date)->format('d/m/Y') }}
                </td>
                <td style="padding:8px 12px;font-size:13px;font-variant-numeric:tabular-nums">
                    <a href="{{ url($project->slug . '/reservas/' . $f->id) }}"
                       style="color:#185FA5;text-decoration:none">{{ $f->booking_id }}</a>
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
                <td style="padding:8px 12px;text-align:right;white-space:nowrap" class="celda-acciones">
                    @if(!$f->estado_fianza && $puedeEditar)
                        <button type="button" onclick="conforme({{ $f->id }})"
                                style="font-size:12px;padding:4px 10px;border-radius:6px;border:0.5px solid #B7E0C4;background:#F1FAF4;color:#1B7F3B;cursor:pointer">
                            Conforme
                        </button>
                        <a href="{{ route('vm.dev-fianza', [$project->slug, $f->id]) }}"
                           style="font-size:12px;padding:4px 10px;border-radius:6px;border:0.5px solid #E3C9A3;background:#FDF8F1;color:#9A5B00;text-decoration:none;display:inline-block">
                            Revisar
                        </a>
                    @elseif(!$f->estado_fianza)
                        <span style="font-size:11px;color:#bbb">sin permiso</span>
                    @else
                        <a href="{{ route('vm.dev-fianza', [$project->slug, $f->id]) }}"
                           style="font-size:12px;color:#888;text-decoration:none">Ver</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="padding:1.5rem;text-align:center;font-size:13px;color:#999">
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
const CSRF_DF = '{{ csrf_token() }}';

async function conforme(id) {
    if (!confirm('¿Aprobar la devolución completa de la fianza?')) return;

    const r = await fetch('{{ url($project->slug . "/dev-fianzas") }}/' + id + '/conforme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
    });
    const j = await r.json().catch(() => ({}));
    if (j.error) { alert(j.error); return; }
    location.reload();
}
</script>
</x-app-layout>
