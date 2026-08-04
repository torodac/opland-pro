<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

<x-slot name="actions"></x-slot>

<style>
.rl-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff;border:1px solid #dce6ee;border-radius:10px;overflow:hidden}
.rl-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rl-table th.num{text-align:center}
.rl-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6}
.rl-table td.voto{text-align:center;cursor:pointer;font-weight:700}
.rl-table td.voto.si{color:#166534}
.rl-table td.voto.no{color:#b91c1c}
.rl-table td.voto.vacio{color:#d1d5db;cursor:default}
.rl-table td.voto:not(.vacio):hover{background:#fef2f2}
.rl-hint{font-size:11.5px;color:#9ca3af;margin-bottom:12px}
</style>

<p class="rl-hint">Clic en un voto para eliminarlo (por si se escaneó la pregunta equivocada). Las celdas en blanco no tienen voto registrado.</p>

<div style="overflow-x:auto">
<table class="rl-table">
  <thead><tr>
    <th>Hoja</th>
    <th>Vivienda</th>
    @foreach($preguntas as $p)
    <th class="num" title="{{ $p->texto }}">P{{ $p->numero_pregunta }}</th>
    @endforeach
  </tr></thead>
  <tbody>
    @forelse($hojas as $h)
    <tr>
      <td>{{ $h->numero_hoja }}</td>
      <td>{{ $h->nombre }}</td>
      @foreach($preguntas as $p)
      @php $voto = $votosPorHoja[$h->numero_hoja][$p->numero_pregunta] ?? null; @endphp
      @if($voto === 'S')
      <td class="voto si" data-hoja="{{ $h->numero_hoja }}" data-pregunta="{{ $p->numero_pregunta }}">Sí</td>
      @elseif($voto === 'N')
      <td class="voto no" data-hoja="{{ $h->numero_hoja }}" data-pregunta="{{ $p->numero_pregunta }}">No</td>
      @else
      <td class="voto vacio">—</td>
      @endif
      @endforeach
    </tr>
    @empty
    <tr><td colspan="{{ 2 + count($preguntas) }}" style="text-align:center;color:#9ca3af;padding:24px">Todavía no hay hojas repartidas.</td></tr>
    @endforelse
  </tbody>
</table>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
const BASE = '{{ url($project->slug . "/asamblea/recuento") }}';
const ID_ASAMBLEA = {{ $asamblea->id }};

document.querySelectorAll('td.voto:not(.vacio)').forEach(function (celda) {
  celda.addEventListener('click', async function () {
    const hoja = this.dataset.hoja;
    const pregunta = this.dataset.pregunta;
    const texto = this.textContent.trim();
    if (!confirm(`¿Eliminar el voto "${texto}" de la hoja ${hoja} en la pregunta ${pregunta}?`)) return;

    const res = await fetch(`${BASE}/voto`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_asamblea: ID_ASAMBLEA, numero_hoja: hoja, numero_pregunta: pregunta }),
    });
    const data = await res.json();
    if (!res.ok) { alert(data.error || 'No se pudo eliminar.'); return; }

    this.textContent = '—';
    this.classList.remove('si', 'no');
    this.classList.add('vacio');
  });
});
</script>

</x-app-layout>
