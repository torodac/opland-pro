<x-app-layout :breadcrumb="$breadcrumb" :project="$project">

<x-slot name="actions">
    <a href="{{ route('listado', [$project->slug, 'fichaje']) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
       title="Ver como listado estándar">
        <i class="fas fa-table text-gray-400"></i>
        Vista estándar
    </a>
</x-slot>

<div style="max-width:900px;margin:0 auto;padding:1.5rem 1rem;">

    <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;flex-wrap:wrap;">
        @if($mostrarSelector)
        <select onchange="window.location.href=this.value"
                style="font-size:12.5px;font-weight:500;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;cursor:pointer;">
            <option value="{{ request()->fullUrlWithQuery(['usuario' => 0]) }}" {{ $modoTodos ? 'selected' : '' }}>Todos</option>
            @foreach($usuarios as $u)
            <option value="{{ request()->fullUrlWithQuery(['usuario' => $u->id]) }}" {{ (!$modoTodos && $u->id == $usuarioId) ? 'selected' : '' }}>{{ $u->nombre }}</option>
            @endforeach
        </select>
        @endif

        <div style="display:flex;align-items:center;gap:4px;">
            <a href="{{ $urlAnterior }}" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;border-radius:8px;color:#6b7280;text-decoration:none;">‹</a>
            <span style="font-size:12.5px;font-weight:600;color:#111827;text-transform:capitalize;min-width:110px;text-align:center;">{{ $mesLabel }}</span>
            <a href="{{ $urlSiguiente }}" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;border-radius:8px;color:#6b7280;text-decoration:none;">›</a>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
        @if($filas->isEmpty())
        <p style="font-size:12.5px;color:#9ca3af;margin:0;padding:16px 18px;">Sin fichajes, ausencias ni descansos registrados en este mes.</p>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;">
                        @if($modoTodos)
                        <th style="text-align:left;padding:8px 10px;color:#6b7280;font-weight:600;">Usuario</th>
                        @endif
                        <th style="text-align:left;padding:8px 10px;color:#6b7280;font-weight:600;">Fecha</th>
                        <th style="text-align:left;padding:8px 10px;color:#6b7280;font-weight:600;">Entrada → Salida</th>
                        <th style="text-align:left;padding:8px 10px;color:#6b7280;font-weight:600;">Pausa</th>
                        <th style="text-align:left;padding:8px 10px;color:#6b7280;font-weight:600;">Estado</th>
                        <th style="text-align:right;padding:8px 10px;color:#6b7280;font-weight:600;">Fichado vs. imputado</th>
                        <th style="text-align:right;padding:8px 10px;color:#6b7280;font-weight:600;">Pendientes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($filas as $fila)
                    @php
                        [$y, $m, $d] = explode('-', $fila->fecha);
                        $fechaCorta = ((int) $d) . '/' . ((int) $m);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;{{ $fila->conflicto ? 'background:#fffbe6;' : '' }}{{ $fila->id ? 'cursor:pointer;' : '' }}"
                        @if($fila->id) onclick="window.location='{{ route('vm.fichaje_form', [$project->slug, $fila->id]) }}'" @endif>
                        @if($modoTodos)
                        <td style="padding:8px 10px;font-weight:600;color:#111827;">{{ $fila->usuarioNombre }}</td>
                        @endif
                        <td style="padding:8px 10px;">
                            <span style="color:#9ca3af;text-transform:uppercase;font-size:10.5px;">{{ $fila->diaSemana }}</span>
                            <span style="font-weight:600;color:#111827;margin-left:4px;">{{ $fechaCorta }}</span>
                        </td>
                        <td style="padding:8px 10px;color:#374151;">
                            {{ $fila->horaInicio ? substr($fila->horaInicio, 0, 5) : '--' }} → {{ $fila->horaFin ? substr($fila->horaFin, 0, 5) : '--' }}
                        </td>
                        <td style="padding:8px 10px;color:#9ca3af;">
                            {{ $fila->pausaMin !== null ? $fila->pausaMin . 'm' : '—' }}
                        </td>
                        <td style="padding:8px 10px;">
                            @foreach($fila->badges as [$label, $bg, $fg])
                            <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:600;background:{{ $bg }};color:{{ $fg }};margin-right:4px;">{{ $label }}</span>
                            @endforeach
                            @if($fila->badges->isEmpty())<span style="color:#d1d5db;">–</span>@endif
                        </td>
                        <td style="text-align:right;padding:8px 10px;font-weight:600;font-variant-numeric:tabular-nums;color:{{ $fila->diffMin === null ? '#d1d5db' : ($fila->diffMin >= 0 ? '#16a34a' : '#dc2626') }};">
                            @if(!$fila->esTurno)
                            <span style="color:#d1d5db;">—</span>
                            @elseif($fila->diffMin === null)
                            —
                            @else
                            {{ $fila->diffMin >= 0 ? '+' : '-' }}{{ sprintf('%02d:%02d', intdiv(abs($fila->diffMin), 60), abs($fila->diffMin) % 60) }}
                            @endif
                        </td>
                        <td style="text-align:right;padding:8px 10px;">
                            @if(!$fila->esTurno)
                            <span style="color:#d1d5db;">—</span>
                            @elseif($fila->pendientes > 0)
                            <a href="{{ $fila->pendientesUrl }}" onclick="event.stopPropagation()"
                               style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:50%;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:0 4px;text-decoration:none;"
                               title="Ver tareas pendientes de este día">{{ $fila->pendientes }}</a>
                            @else
                            <span style="color:#d1d5db;">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

</x-app-layout>
