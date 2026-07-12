@php
$tipoLabels = [
    'turno'        => 'Turno',
    'descanso'     => 'Descanso',
    'vacaciones'   => 'Vacaciones',
    'baja'         => 'Baja',
    'comp_festivo' => 'Comp. festivo',
    'comp_horas'   => 'Comp. horas',
    'asuntos'      => 'Asuntos propios',
    'absentismo'   => 'Absentismo',
];

$prevWeek = $semana->copy()->subWeek()->toDateString();
$nextWeek = $semana->copy()->addWeek()->toDateString();

$dates = collect();
for ($i = 0; $i < 7; $i++) $dates->push($semana->copy()->addDays($i));

$diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

$usuariosByDept = $usuarios->groupBy('departamento');

$breadcrumb = [['label' => 'Horarios']];
@endphp

<x-app-layout :breadcrumb="$breadcrumb" :project="$project">
<style>
.hor-nav { display:flex; align-items:center; gap:8px; margin-bottom:1.25rem; }
.hor-nav h2 { font-size:1rem; font-weight:600; flex:1; }
.hor-btn { display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;color:#374151;text-decoration:none;background:#fff; }
.hor-btn:hover { background:#f3f4f6; }
.dept-block { margin-bottom:1.5rem; }
.dept-title { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:6px; }
.lst-table { width:100%; border-collapse:collapse; font-size:12px; }
.lst-table th { background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:5px 10px;text-align:left;font-weight:600;color:#374151; }
.lst-table td { padding:5px 10px;border-bottom:1px solid #f3f4f6;color:#374151; }
.lst-table tr:last-child td { border-bottom:none; }
.badge { display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:500; }
.badge-turno        { background:#dbeafe;color:#1e40af; }
.badge-descanso     { background:#f3f4f6;color:#6b7280; }
.badge-vacaciones   { background:#dcfce7;color:#166534; }
.badge-baja         { background:#fee2e2;color:#991b1b; }
.badge-comp_festivo,.badge-comp_horas { background:#fef9c3;color:#854d0e; }
.badge-asuntos      { background:#ede9fe;color:#5b21b6; }
.badge-absentismo   { background:#ffedd5;color:#9a3412; }
.week-label { font-size:12px;color:#6b7280; }
</style>

<div class="hor-nav">
    <h2>Horarios — listado</h2>
    <a href="{{ route('horario', $project->slug) }}" class="hor-btn" title="Planificador semanal">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
    </a>
    <a href="?semana={{ $prevWeek }}" class="hor-btn">&#8592;</a>
    <span class="week-label">{{ $semana->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM YYYY') }}</span>
    <a href="?semana={{ $nextWeek }}" class="hor-btn">&#8594;</a>
    <a href="?" class="hor-btn">Esta semana</a>
</div>

@foreach($deptPermitidos as $dept)
@php
$usDept = $usuariosByDept->get($dept, collect());
if ($usDept->isEmpty()) continue;
@endphp
<div class="dept-block">
    <div class="dept-title">{{ $dept }}</div>
    <table class="lst-table">
        <thead>
            <tr>
                <th>Usuario</th>
                @foreach($dates as $di => $d)
                <th>{{ $diasNombres[$di] }} {{ $d->format('d/m') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($usDept as $u)
            <tr>
                <td>{{ $u->nombre }}</td>
                @foreach($dates as $d)
                @php
                    $ds = $d->toDateString();
                    $uHorarios = ($horarios[$u->id] ?? collect())->where('fecha', $ds);
                @endphp
                <td>
                    @forelse($uHorarios as $h)
                    <span class="badge badge-{{ $h->tipo }}">{{ $tipoLabels[$h->tipo] ?? $h->tipo }}</span>
                    @if($h->hora_inicio && $h->hora_fin)
                    <span style="font-size:10px;color:#9ca3af;margin-left:3px;">{{ substr($h->hora_inicio,0,5) }}–{{ substr($h->hora_fin,0,5) }}</span>
                    @endif
                    @empty
                    <span style="color:#d1d5db;">—</span>
                    @endforelse
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

</x-app-layout>
