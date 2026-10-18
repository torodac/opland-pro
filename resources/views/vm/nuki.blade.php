@php
    $sinProp = $cerradura->propiedad === null || ($cerradura->prop_deleted !== null && (int) $cerradura->prop_deleted !== 0);
    $titulo = $sinProp ? $cerradura->nombre : $cerradura->propiedad;
    $bateriaBaja = $cerradura->bateria !== null && (int) $cerradura->bateria <= $umbral;
    $colorBat = $cerradura->bateria === null ? '#a1a1aa' : ($bateriaBaja ? '#dc2626' : '#16a34a');
    $fmt = fn($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y H:i') : null;
@endphp

<x-app-layout
    :breadcrumb="[
        ['label' => 'Gestión Nuki', 'url' => route('vm.nuki_list', $project->slug)],
        ['label' => $titulo ?: 'Cerradura', 'url' => ''],
    ]"
    :project="$project">

<div style="padding:0 0 3rem;">

    <style>
        .nkf-head{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:14px;padding:16px 18px;display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center}
        .nkf-head.baja{background:#fdecec;border-color:#f3b4b4}
        .nkf-head.sin{background:#fff8d6;border-color:#eed77a}
        .nkf-pct{font-size:40px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;text-align:right}
        .nkf-bar{height:8px;border-radius:99px;background:rgba(0,0,0,.08);overflow:hidden;margin-top:6px;min-width:160px}
        .nkf-bar>i{display:block;height:100%;border-radius:99px}
        .nkf-note{background:#fff8d6;border:0.5px solid #eed77a;border-radius:10px;padding:10px 14px;font-size:13px;color:#713f12;margin:12px 0}
        .nkf-fila{border-top:0.5px solid rgba(0,0,0,.06)}
        .nkf-fila:hover{background:rgba(0,0,0,.025)}
        .nkf-badge{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;white-space:nowrap}
        .nkf-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-variant-numeric:tabular-nums;letter-spacing:.08em}
        .nkf-btn{font-size:11px;color:#f97316;background:none;border:none;cursor:pointer;padding:0 0 0 6px}
        .dark .nkf-head{background:#1f1f22;border-color:rgba(255,255,255,.08)}
        .dark .nkf-head.baja{background:#3a1717;border-color:#7f2a2a}
        .dark .nkf-head.sin{background:#3a3210;border-color:#7a6a1c}
        .dark .nkf-note{background:#3a3210;border-color:#7a6a1c;color:#fde68a}
        @media (max-width:560px){ .nkf-head{grid-template-columns:1fr} .nkf-pct{text-align:left} }
    </style>

    <div class="nkf-head {{ $bateriaBaja ? 'baja' : ($sinProp ? 'sin' : '') }}">
        <div>
            <div style="font-size:18px;font-weight:600">{{ $titulo ?: 'Sin nombre' }}</div>
            <div style="font-size:12px;color:#777;margin-top:4px">
                Cerradura Nuki: {{ $cerradura->nombre ?: '—' }} · ID {{ $cerradura->nuki_smartlock_id }}
            </div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                @if($bateriaBaja)
                    <span class="nkf-badge" style="background:#dc2626;color:#fff">Batería baja</span>
                @endif
                @if($sinProp)
                    <span class="nkf-badge" style="background:#eab308;color:#422006">Sin propiedad activa</span>
                @endif
                @if($cerradura->bateria_critica)
                    <span class="nkf-badge" style="background:#7f1d1d;color:#fff">Batería crítica según Nuki</span>
                @endif
            </div>
        </div>
        <div>
            <div class="nkf-pct" style="color:{{ $colorBat }}">
                {{ $cerradura->bateria !== null ? $cerradura->bateria . ' %' : '—' }}
            </div>
            @if($cerradura->bateria !== null)
                <div class="nkf-bar"><i style="width:{{ max(0, min(100, (int) $cerradura->bateria)) }}%;background:{{ $colorBat }}"></i></div>
            @endif
            <div style="font-size:11px;color:#777;text-align:right;margin-top:6px">
                Leída {{ $fmt($cerradura->leido_at) ?? 'nunca' }}
            </div>
        </div>
    </div>

    @if($sinCaducidad > 0)
        <div class="nkf-note">
            <strong>{{ $sinCaducidad }} {{ $sinCaducidad === 1 ? 'acceso no tiene' : 'accesos no tienen' }} fecha de caducidad.</strong>
            Siguen activos hasta que alguien los borra a mano en Nuki.
        </div>
    @endif

    <div style="font-size:13px;color:#555;margin:16px 0 8px">
        Personas con acceso: <strong>{{ $accesos->count() }}</strong>
    </div>

    <div style="background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;overflow:auto">
        <table style="width:100%;border-collapse:collapse;min-width:640px">
            <thead>
                <tr style="background:rgba(0,0,0,.02)">
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Persona</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Tipo</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Código</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Desde</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Hasta</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Último uso</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse($accesos as $a)
                @php
                    $etiquetaTipo = ['codigo' => 'Código', 'teclado' => 'Teclado', 'app' => 'App', 'otro' => 'Otro'][$a->tipo] ?? 'Otro';
                @endphp
                <tr class="nkf-fila">
                    <td style="padding:8px 12px;font-size:13px;font-weight:500">{{ $a->nombre ?: '—' }}</td>
                    <td style="padding:8px 12px;font-size:12px;color:#555">{{ $etiquetaTipo }}</td>
                    <td style="padding:8px 12px;font-size:13px;white-space:nowrap">
                        @if($a->codigo_claro !== null)
                            <span class="nkf-code" data-codigo="{{ $a->codigo_claro }}" data-oculto="1">••••••</span>
                            <button type="button" class="nkf-btn" onclick="nkToggleCodigo(this)">mostrar</button>
                        @else
                            <span style="color:#aaa">—</span>
                        @endif
                    </td>
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap">{{ $fmt($a->fecha_desde) ?? 'Siempre' }}</td>
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap">
                        @if($a->fecha_hasta)
                            {{ $fmt($a->fecha_hasta) }}
                        @else
                            <span class="nkf-badge" style="background:#fef3c7;color:#92400e">Sin caducidad</span>
                        @endif
                    </td>
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap;color:#555">{{ $fmt($a->ultimo_uso_at) ?? '—' }}</td>
                    <td style="padding:8px 12px;font-size:12px">
                        @if($a->vigente)
                            <span class="nkf-badge" style="background:#e7f6ec;color:#166534">Vigente</span>
                        @elseif(!$a->habilitado)
                            <span class="nkf-badge" style="background:#e5e7eb;color:#374151">Deshabilitado</span>
                        @else
                            <span class="nkf-badge" style="background:#fee2e2;color:#991b1b">Fuera de fecha</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding:24px;text-align:center;color:#777;font-size:13px">
                        Esta cerradura no tiene autorizaciones guardadas. Se llenan en la pasada de las 06:50.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="font-size:13px;color:#555;margin:24px 0 8px;display:flex;align-items:center;gap:8px">
        <img src="{{ asset('img/breezeway.svg') }}" alt="" style="width:14px;height:14px;object-fit:contain">
        Histórico de tareas de batería en Breezeway: <strong>{{ $historico->count() }}</strong>
    </div>

    <div style="background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;overflow:auto">
        <table style="width:100%;border-collapse:collapse;min-width:560px">
            <thead>
                <tr style="background:rgba(0,0,0,.02)">
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Planificada</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Tarea</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Estado</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Finalizada</th>
                    <th style="text-align:left;padding:8px 12px;font-size:11px;color:#888;font-weight:500">Breezeway</th>
                </tr>
            </thead>
            <tbody>
            @forelse($historico as $h)
                @php $abierta = in_array($h->estado, \App\Http\Controllers\Vm\NukiController::ESTADOS_ABIERTOS, true); @endphp
                <tr class="nkf-fila">
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap">{{ $h->fecha?->format('d/m/Y') ?? '—' }}</td>
                    <td style="padding:8px 12px;font-size:13px">{{ $h->nombre }}</td>
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap">
                        <span class="nkf-badge" style="{{ $abierta ? 'background:#DBEAFE;color:#1E40AF' : 'background:#e7f6ec;color:#166534' }}">{{ $h->estado ?: 'Sin estado' }}</span>
                    </td>
                    <td style="padding:8px 12px;font-size:12px;white-space:nowrap;color:#555">{{ $h->fecha_finalizacion ? \Carbon\Carbon::parse($h->fecha_finalizacion)->format('d/m/Y') : '—' }}</td>
                    <td style="padding:8px 12px;font-size:12px">
                        <a href="{{ $h->url }}" target="_blank" rel="noopener" style="color:#1d4ed8;text-decoration:none;font-weight:600">Abrir ↗</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding:24px;text-align:center;color:#777;font-size:13px">
                        No hay tareas de batería de Nuki vinculadas a esta propiedad.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p style="font-size:11px;color:#888;margin-top:10px">
        Los datos vienen de Nuki (solo lectura) y se actualizan cada día a las 06:50. Los códigos se muestran
        ocultos y solo quien tiene acceso a esta pantalla puede verlos.
    </p>
</div>

<script>
function nkToggleCodigo(btn) {
    const span = btn.previousElementSibling;
    const oculto = span.dataset.oculto === '1';
    span.textContent = oculto ? span.dataset.codigo : '••••••';
    span.dataset.oculto = oculto ? '0' : '1';
    btn.textContent = oculto ? 'ocultar' : 'mostrar';
}
</script>

</x-app-layout>
