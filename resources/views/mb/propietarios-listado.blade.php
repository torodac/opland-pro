<x-app-layout :project="$project" :breadcrumb="[['label'=>'Propietarios','url'=>'']]">

<x-slot name="actions">
    @if(auth()->user()?->isProjectAdmin($project))
    <a href="{{ route('ficha.create', [$project->slug, 'propietarios']) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo
    </a>
    @endif
</x-slot>

<form method="GET" style="margin-bottom:14px">
  <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por propietario o vivienda…"
         style="font-size:12.5px;border:1px solid #dce6ee;border-radius:8px;padding:7px 11px;width:320px">
</form>

<style>
.pr-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #dce6ee;border-radius:10px;overflow:hidden}
.pr-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 14px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.pr-table th a{color:inherit;text-decoration:none}
.pr-table th a:hover{color:#374151}
.pr-table td{padding:9px 14px;border-bottom:1px solid #eaf1f6;vertical-align:middle}
.pr-row{cursor:pointer}
.pr-row:hover{background:#f7fafc}
.pr-table td.num{text-align:right;font-variant-numeric:tabular-nums}
.pr-nombre{font-weight:600;color:#16232b;text-decoration:none}
.pr-nombre:hover{text-decoration:underline}
.pr-sort-arrow{font-size:9px;color:#f97316}
.pr-toggle{display:inline-flex;align-items:center;gap:6px;color:#9ca3af}
.pr-chevron{width:11px;height:11px;flex-shrink:0;transition:transform .12s}
.pr-chevron.open{transform:rotate(90deg)}
.pr-detail{display:none}
.pr-detail.open{display:table-row}
.pr-detail td{padding:0;background:#fafbfc}
.pr-detail-inner{padding:6px 14px 14px 40px}
.pr-viv-table{width:100%;border-collapse:collapse;font-size:12px}
.pr-viv-table th{text-align:left;font-size:10px;color:#9ca3af;padding:5px 8px;border-bottom:1px solid #eaf1f6}
.pr-viv-table td{padding:5px 8px;border-bottom:1px solid #f3f4f6}
.pr-viv-table tr.pasado td{color:#b0b8bf}
.pr-viv-table tr.pasado a{color:#b0b8bf}
.pr-viv-nombre{color:#2563eb;text-decoration:none}
.pr-viv-nombre:hover{text-decoration:underline}
</style>

@php
    $sortLink = fn ($field) => request()->fullUrlWithQuery(['sort' => $field, 'dir' => ($sortField === $field && $sortDir === 'asc') ? 'desc' : 'asc']);
    $arrow = fn ($field) => $sortField === $field ? ('<span class="pr-sort-arrow">' . ($sortDir === 'asc' ? '▲' : '▼') . '</span>') : '';
@endphp

<div style="overflow-x:auto">
<table class="pr-table">
  <thead>
    <tr>
      <th><a href="{{ $sortLink('nombre') }}">Propietario {!! $arrow('nombre') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('viviendas_actuales') }}">Viviendas actuales {!! $arrow('viviendas_actuales') !!}</a></th>
      <th class="num"><a href="{{ $sortLink('viviendas_total') }}">Viviendas (histórico) {!! $arrow('viviendas_total') !!}</a></th>
    </tr>
  </thead>
  <tbody>
    @forelse($propietarios as $p)
    @php $viviendas = $viviendasPorPropietario->get($p->id) ?? collect(); @endphp
    <tr class="pr-row" data-toggle="pr-detail-{{ $p->id }}">
      <td>
        <span class="pr-toggle">
          <svg class="pr-chevron" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l6 6-6 6"/></svg>
          <a class="pr-nombre" href="{{ route('ficha', [$project->slug, 'propietarios', $p->id]) }}" onclick="event.stopPropagation()">{{ $p->nombre }}</a>
        </span>
      </td>
      <td class="num">{{ $p->viviendas_actuales }}</td>
      <td class="num">{{ $p->viviendas_total }}</td>
    </tr>
    <tr class="pr-detail" id="pr-detail-{{ $p->id }}">
      <td colspan="3">
        <div class="pr-detail-inner">
          @if($viviendas->isEmpty())
            <span style="color:#9ca3af;font-size:12px">Sin viviendas asociadas.</span>
          @else
          <table class="pr-viv-table">
            <thead><tr><th>Vivienda</th><th>Desde</th><th>Hasta</th></tr></thead>
            <tbody>
              @foreach($viviendas as $v)
              <tr class="{{ $v->fecha_hasta ? 'pasado' : '' }}">
                <td><a class="pr-viv-nombre" href="{{ route('ficha', [$project->slug, 'viviendas', $v->id_vivienda]) }}">{{ $v->vivienda_nombre }}</a></td>
                <td>{{ $v->fecha_desde }}</td>
                <td>{{ $v->fecha_hasta ?? '—' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @endif
        </div>
      </td>
    </tr>
    @empty
    <tr><td colspan="3" style="text-align:center;color:#9ca3af;padding:24px">No hay propietarios que coincidan con la búsqueda.</td></tr>
    @endforelse
  </tbody>
</table>
</div>

<p style="font-size:11.5px;color:#9ca3af;margin-top:10px">{{ $propietarios->count() }} propietarios.</p>

<script>
document.querySelectorAll('.pr-row[data-toggle]').forEach(row => {
  row.addEventListener('click', () => {
    const detail = document.getElementById(row.dataset.toggle);
    const chevron = row.querySelector('.pr-chevron');
    detail.classList.toggle('open');
    chevron.classList.toggle('open');
  });
});
</script>

</x-app-layout>
