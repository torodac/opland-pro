@php
$badgeColorFor = function ($nombre, $idTipo) use ($colorGrupo) {
    if ((int) $idTipo === 1 || !isset($colorGrupo[$nombre])) {
        return ['bg' => '#F1EFE8', 'fg' => '#5F5E5A'];
    }
    return ['bg' => $colorGrupo[$nombre], 'fg' => '#fff'];
};
$estadoBadgeFor = fn ($estado) => match ($estado) {
    'Pagada'    => ['bg' => '#EAF3DE', 'fg' => '#27500A'],
    'Pendiente' => ['bg' => '#FCEFD9', 'fg' => '#92600A'],
    'Anulado'   => ['bg' => '#F1EFE8', 'fg' => '#5F5E5A'],
    default     => ['bg' => '#F1EFE8', 'fg' => '#5F5E5A'],
};
$linkFor = fn ($estadoLink) => route('nf.pagos_list', array_filter([
    'project' => $project->slug,
    'mes' => $mes,
    'nombre' => $nombre,
    'grupo' => $grupo,
    'estado' => $estadoLink,
]));
@endphp

<x-app-layout :breadcrumb="[['label'=>'Pagos','url'=>route('nf.pagos_list',[$project->slug])],['label'=>$mesLabel,'url'=>route('nf.pagos_list',[$project->slug,$mes])]]" :project="$project">

<x-slot name="actions">
    <form method="POST" action="{{ route('nf.pagos.generar', $project->slug) }}">
        @csrf
        <input type="hidden" name="mes" value="{{ $mes }}">
        <button type="submit" class="btn btn-orange">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            Generar pagos de {{ $mesLabel }}
        </button>
    </form>
</x-slot>

<div id="nf-pagos-list">

<style>
  #nf-pagos-list .btn { font-size:13px;padding:6px 12px;border-radius:6px;cursor:pointer;border:.5px solid rgba(0,0,0,.15);background:#fff;color:#1B1B18;display:inline-flex;align-items:center;gap:5px;line-height:1.2; }
  #nf-pagos-list .btn-orange { background:#F97316;color:#fff;border-color:#F97316;font-weight:600; }
  #nf-pagos-list .icon-btn { background:none;border:none;cursor:pointer;padding:5px;color:#888;display:inline-flex;align-items:center;border-radius:6px; }
  #nf-pagos-list .icon-btn:hover { background:rgba(0,0,0,.06);color:#222; }
  #nf-pagos-list .icon-btn.whatsapp:hover { background:#EAF3DE;color:#27500A; }

  #nf-pagos-list .month-nav { display:flex;align-items:center;gap:14px;margin-bottom:14px; }
  #nf-pagos-list .month-nav a { display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:.5px solid rgba(0,0,0,.12);color:#555;background:#fff;text-decoration:none;flex-shrink:0; }
  #nf-pagos-list .month-nav a:hover { background:rgba(0,0,0,.045); }
  #nf-pagos-list .month-label { font-weight:600;font-size:17px;text-transform:capitalize;min-width:150px;text-align:center; }

  #nf-pagos-list .toolbar { display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;justify-content:space-between; }
  #nf-pagos-list .stats-row { display:flex;gap:10px;flex-wrap:wrap; }
  #nf-pagos-list .stat-pill { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:10px;padding:8px 14px;font-size:12.5px;color:#888;display:flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer; }
  #nf-pagos-list .stat-pill b { color:#1B1B18;font-size:14px; }
  #nf-pagos-list .stat-pill.active { border-color:#F97316;background:#FFF4EC; }
  #nf-pagos-list .stat-pill.active b { color:#C2410C; }

  #nf-pagos-list .filter-form { display:flex;gap:8px;flex-wrap:wrap;align-items:center; }
  #nf-pagos-list .filter-form input[type="text"], #nf-pagos-list .filter-form select {
    border:.5px solid rgba(0,0,0,.15);border-radius:6px;padding:6px 10px;font-size:13px;background:#fff;font-family:inherit;
  }
  #nf-pagos-list .filter-form input[type="text"] { width:180px; }

  #nf-pagos-list .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:.4rem 1.1rem; }

  #nf-pagos-list table.pagos { width:100%;border-collapse:collapse; }
  #nf-pagos-list table.pagos th { text-align:left;padding:10px 8px;font-size:11px;color:#888;font-weight:500;white-space:nowrap; }
  #nf-pagos-list table.pagos td { padding:10px 8px;font-size:13px;vertical-align:middle;white-space:nowrap; }
  #nf-pagos-list table.pagos tr.trow { border-top:.5px solid rgba(0,0,0,.06); }
  #nf-pagos-list table.pagos tr.trow:hover { background:rgba(0,0,0,.015); }
  #nf-pagos-list .badge { font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap;display:inline-block; }
  #nf-pagos-list .empty-note { font-size:13px;color:#aaa;margin:0;padding:1.5rem 0;text-align:center; }
  #nf-pagos-list .table-scroll { overflow-x:auto; }

  @media (max-width:640px) {
    #nf-pagos-list .month-label { font-size:15px;min-width:120px; }
    #nf-pagos-list .toolbar { flex-direction:column;align-items:stretch; }
    #nf-pagos-list .filter-form input[type="text"] { width:auto;flex:1; }
  }
</style>

<div class="month-nav">
    <a href="{{ route('nf.pagos_list', array_filter(['project'=>$project->slug,'mes'=>$mesAnterior,'nombre'=>$nombre,'grupo'=>$grupo,'estado'=>$estado])) }}" title="Mes anterior">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="month-label">{{ $mesLabel }}</span>
    <a href="{{ route('nf.pagos_list', array_filter(['project'=>$project->slug,'mes'=>$mesSiguiente,'nombre'=>$nombre,'grupo'=>$grupo,'estado'=>$estado])) }}" title="Mes siguiente">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

<div class="toolbar">
    <div class="stats-row">
        <a href="{{ $linkFor('pendiente') }}" class="stat-pill {{ $estado === 'pendiente' ? 'active' : '' }}">Pendiente: <b>{{ number_format($totalPendiente, 2, ',', '.') }} €</b></a>
        <a href="{{ $linkFor('cobrado') }}" class="stat-pill {{ $estado === 'cobrado' ? 'active' : '' }}">Cobrado: <b>{{ number_format($totalPagado, 2, ',', '.') }} €</b></a>
        <div class="stat-pill">Pagos: <b>{{ $pagos->count() }}</b></div>
    </div>

    <form method="GET" action="{{ route('nf.pagos_list', [$project->slug, $mes]) }}" class="filter-form">
        <input type="hidden" name="estado" value="{{ $estado }}">
        <input type="text" name="nombre" value="{{ $nombre }}" placeholder="Buscar cliente...">
        <select name="grupo">
            <option value="">Todos los grupos</option>
            @foreach($grupos as $g)
            <option value="{{ $g->id }}" {{ (string) $grupo === (string) $g->id ? 'selected' : '' }}>{{ $g->nombre }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if($nombre || $grupo)
        <a href="{{ $linkFor($estado) }}" class="btn">Limpiar</a>
        @endif
    </form>
</div>

<div class="section-card">
    @if($pagos->isEmpty())
        <p class="empty-note">No hay pagos {{ $estado === 'cobrado' ? 'cobrados' : 'pendientes' }} para {{ $mesLabel }} con los filtros aplicados.</p>
    @else
    <div class="table-scroll">
    <table class="pagos">
        <thead><tr>
            <th>Cliente</th>
            <th>Servicio</th>
            <th style="text-align:right;">Importe</th>
            <th>Estado</th>
            <th>Fecha pago</th>
            <th></th>
        </tr></thead>
        <tbody>
            @foreach($pagos as $p)
            @php $badge = $badgeColorFor($p->contrato_nombre, $p->contrato_id_tipo); $estBadge = $estadoBadgeFor($p->estado_nombre); @endphp
            <tr class="trow">
                <td>{{ $p->cliente_nombre }}</td>
                <td>
                    <span class="badge" style="background:{{ $badge['bg'] }};color:{{ $badge['fg'] }};">{{ $p->contrato_nombre ?? '—' }}</span>
                </td>
                <td style="text-align:right;font-weight:600;">{{ number_format($p->cantidad, 2, ',', '.') }} €</td>
                <td>
                    <span class="badge" style="background:{{ $estBadge['bg'] }};color:{{ $estBadge['fg'] }};">{{ $p->estado_nombre ?? '—' }}</span>
                </td>
                <td>{{ \Carbon\Carbon::parse($p->fecha_pago)->format('d/m/Y') }}</td>
                <td style="text-align:right;">
                    @if($p->estado_nombre === 'Pendiente')
                        <form method="POST" action="{{ route('nf.pagos.pagar', [$project->slug, $p->id, 1]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="icon-btn" title="Confirmar cobro en efectivo">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('nf.pagos.pagar', [$project->slug, $p->id, 2]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="icon-btn" title="Confirmar cobro por banco">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V10.5L12 4l8 6.5V21M9 21v-6h6v6"/></svg>
                            </button>
                        </form>
                        @if($p->cliente_telefono)
                        <a href="https://wa.me/34{{ preg_replace('/\D/', '', $p->cliente_telefono) }}?text={{ rawurlencode('Hola, te escribo para recordarte que la cuota de este mes está pendiente. Abona tu cuota del 25 al 30 de cada mes. Quedo a la espera de que te pongas en contacto conmigo. Saludos') }}"
                           target="_blank" class="icon-btn whatsapp" title="Recordatorio WhatsApp">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-1.746-.874-2.888-1.559-4.035-3.532-.305-.526.305-.489.874-1.628.096-.198.048-.371-.05-.52-.099-.148-.669-1.611-.916-2.208-.24-.579-.487-.5-.669-.51-.173-.008-.372-.01-.571-.01-.198 0-.52.075-.792.372-.272.298-1.04 1.017-1.04 2.479s1.065 2.876 1.213 3.074c.148.198 2.05 3.132 4.986 4.27 2.394.933 2.394.622 2.94.583.545-.04 1.758-.72 2.005-1.413.247-.694.247-1.29.173-1.412-.074-.124-.297-.198-.594-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.98.577 3.83 1.573 5.396L2.5 22l4.75-1.045A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.2a8.19 8.19 0 01-4.176-1.145l-.3-.178-3.106.684.678-3.033-.196-.313A8.2 8.2 0 1120.2 12c0 4.53-3.67 8.2-8.2 8.2z"/></svg>
                        </a>
                        @endif
                    @else
                        <span style="color:#bbb;font-size:12px;">{{ $p->forma_pago_nombre ?? '—' }}</span>
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
