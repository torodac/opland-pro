<x-app-layout :breadcrumb="[['label'=>'Dashboard','url'=>route('nf.dashboard',[$project->slug])]]" :project="$project">

<div id="nf-dashboard">

<style>
  #nf-dashboard .ejercicio-nav { display:flex;align-items:center;gap:14px;margin-bottom:18px; }
  #nf-dashboard .ejercicio-nav a { display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:.5px solid rgba(0,0,0,.12);color:#555;background:#fff;text-decoration:none;flex-shrink:0; }
  #nf-dashboard .ejercicio-nav a:hover { background:rgba(0,0,0,.045); }
  #nf-dashboard .ejercicio-label { font-weight:600;font-size:17px;min-width:140px;text-align:center; }

  #nf-dashboard .section-card { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1rem 1.1rem;margin-bottom:14px; }
  #nf-dashboard .sec-title { font-weight:600;font-size:14.5px;margin:0 0 12px; }

  #nf-dashboard table.matriz { width:100%;border-collapse:collapse; }
  #nf-dashboard table.matriz th { text-align:left;padding:8px 10px;font-size:11px;color:#888;font-weight:500;border-bottom:.5px solid rgba(0,0,0,.08); }
  #nf-dashboard table.matriz th.num, #nf-dashboard table.matriz td.num { text-align:center; }
  #nf-dashboard table.matriz td { padding:10px;font-size:14px;vertical-align:middle; }
  #nf-dashboard table.matriz tr.trow { border-top:.5px solid rgba(0,0,0,.06); cursor:pointer; }
  #nf-dashboard table.matriz tr.trow:hover { background:rgba(0,0,0,.02); }
  #nf-dashboard table.matriz tr.trow.activa { background:#F1EFE8; }
  #nf-dashboard .badge { font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;white-space:nowrap;display:inline-block; }
  #nf-dashboard .num-value { font-weight:700;font-size:16px; }
  #nf-dashboard .num-value.zero { color:#bbb;font-weight:500; }
  #nf-dashboard .stats-row { display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap; }
  #nf-dashboard .stat-pill { background:#fff;border:.5px solid rgba(0,0,0,.08);border-radius:10px;padding:8px 14px;font-size:12.5px;color:#888;display:flex;align-items:center;gap:6px; }
  #nf-dashboard .stat-pill b { color:#1B1B18;font-size:14px; }

  #nf-dashboard .inscritos-hint { color:#aaa;font-size:13px;text-align:center;padding:1.2rem 0; }
  #nf-dashboard .inscritos-row { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 4px;border-top:.5px solid rgba(0,0,0,.06); }
  #nf-dashboard .inscritos-row:first-child { border-top:none; }
  #nf-dashboard .inscritos-nombre { font-size:14px; color:#1B1B18; text-decoration:none; }
  #nf-dashboard .inscritos-nombre:hover { text-decoration:underline; color:#3B6FA6; }
  #nf-dashboard .inscritos-dias { display:flex;gap:6px;flex-shrink:0; }
  #nf-dashboard .dia-pill { font-size:11px;padding:2px 8px;border-radius:6px;font-weight:600;background:#F1EFE8;color:#5F5E5A; }
</style>

<div class="ejercicio-nav">
    <a href="{{ route('nf.dashboard', [$project->slug, $ejercicioAnterior]) }}" title="Ejercicio anterior">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="ejercicio-label">{{ $ejercicioLabel }}</span>
    <a href="{{ route('nf.dashboard', [$project->slug, $ejercicioSiguiente]) }}" title="Ejercicio siguiente">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

<div class="stats-row">
    <div class="stat-pill">Contratos Fitness activos hoy: <b>{{ $totalContratos }}</b></div>
</div>

<div class="section-card">
    <p class="sec-title">Contratos activos hoy por grupo y día</p>
    <table class="matriz">
        <thead><tr>
            <th>Grupo</th>
            <th class="num">Día 1</th>
            <th class="num">Día 2</th>
        </tr></thead>
        <tbody>
            @forelse($matriz as $fila)
            <tr class="trow" data-grupo="{{ $fila['id'] }}" data-nombre="{{ $fila['nombre'] }}" onclick="nfToggleGrupo({{ $fila['id'] }}, this)">
                <td>
                    @if(isset($colorGrupo[$fila['nombre']]))
                        <span class="badge" style="background:{{ $colorGrupo[$fila['nombre']] }};color:#fff;">{{ $fila['nombre'] }}</span>
                    @else
                        <span class="badge" style="background:#F1EFE8;color:#5F5E5A;">{{ $fila['nombre'] }}</span>
                    @endif
                </td>
                <td class="num"><span class="num-value {{ $fila['dia1'] === 0 ? 'zero' : '' }}">{{ $fila['dia1'] }}</span></td>
                <td class="num"><span class="num-value {{ $fila['dia2'] === 0 ? 'zero' : '' }}">{{ $fila['dia2'] }}</span></td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center;color:#aaa;padding:1.5rem 0;">No hay grupos de Fitness configurados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section-card">
    <p class="sec-title" id="nf-inscritos-titulo">Inscritos — pulsa un grupo de la tabla</p>
    <div id="nf-inscritos-lista">
        <div class="inscritos-hint">Selecciona un grupo para ver quién está inscrito y en qué días.</div>
    </div>
</div>

</div>

<script>
const NF_INSCRITOS_POR_GRUPO = @json($inscritosPorGrupo);
const NF_URL_FICHA_CLIENTE = '{{ route('nf.clientes_form', [$project->slug, '__ID__']) }}';
let nfGrupoActivo = null;

function nfToggleGrupo(idGrupo, filaEl) {
    const yaActiva = nfGrupoActivo === idGrupo;
    document.querySelectorAll('#nf-dashboard table.matriz tr.trow').forEach(tr => tr.classList.remove('activa'));

    const lista = document.getElementById('nf-inscritos-lista');
    const titulo = document.getElementById('nf-inscritos-titulo');

    if (yaActiva) {
        nfGrupoActivo = null;
        lista.innerHTML = '<div class="inscritos-hint">Selecciona un grupo para ver quién está inscrito y en qué días.</div>';
        titulo.textContent = 'Inscritos — pulsa un grupo de la tabla';
        return;
    }

    nfGrupoActivo = idGrupo;
    filaEl.classList.add('activa');
    titulo.textContent = 'Inscritos — ' + filaEl.dataset.nombre;

    const inscritos = NF_INSCRITOS_POR_GRUPO[idGrupo] || [];
    if (!inscritos.length) {
        lista.innerHTML = '<div class="inscritos-hint">Nadie inscrito activo en este grupo.</div>';
        return;
    }
    lista.innerHTML = inscritos.map(p => {
        const dias = [];
        if (p.dia1) dias.push('<span class="dia-pill">Día 1</span>');
        if (p.dia2) dias.push('<span class="dia-pill">Día 2</span>');
        const url = NF_URL_FICHA_CLIENTE.replace('__ID__', p.id_cliente);
        return `<div class="inscritos-row"><a class="inscritos-nombre" href="${url}" target="_blank" rel="noopener">${nfEsc(p.nombre)}</a><span class="inscritos-dias">${dias.join('')}</span></div>`;
    }).join('');
}

function nfEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
</script>

</x-app-layout>
