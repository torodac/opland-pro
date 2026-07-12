<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KmController extends Controller
{
    // ── Consulta kilometraje (matriz usuario × día) ───────────────────────────

    public function index(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        $allUsuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);

        $hoy    = now()->toDateString();
        $desde  = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta  = $request->input('hasta', $hoy);

        // Limitar rango a 62 días para evitar matrices enormes
        $desdeC = Carbon::parse($desde);
        $hastaC = Carbon::parse($hasta);
        if ($hastaC->diffInDays($desdeC) > 61) {
            $hastaC = $desdeC->copy()->addDays(61);
            $hasta  = $hastaC->toDateString();
        }

        // Días del rango
        $dias = [];
        $cur  = $desdeC->copy();
        while ($cur->lte($hastaC)) {
            $dias[] = $cur->toDateString();
            $cur->addDay();
        }

        // Km por (control_user, fecha)
        $kmRaw = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$desde, $hasta])
            ->whereNotNull('km')
            ->where('km', '>', 0)
            ->get(['control_user', 'fecha_fichaje', 'km', 'trayecto']);

        // Agrupar: userId → fecha → {km, trayecto}
        $kmMap = [];
        foreach ($kmRaw as $r) {
            $kmMap[$r->control_user][$r->fecha_fichaje] = [
                'km'      => (float) $r->km,
                'trayecto'=> $r->trayecto ?? '',
            ];
        }

        // Solo usuarios con algún km en el rango
        $usuariosConKm = $allUsuarios->filter(fn($u) => isset($kmMap[$u->id]));

        // Totales por día
        $totalesDia = [];
        foreach ($dias as $d) {
            $totalesDia[$d] = 0.0;
            foreach ($usuariosConKm as $u) {
                $totalesDia[$d] += $kmMap[$u->id][$d]['km'] ?? 0;
            }
        }

        // Total por usuario
        $totalUsuario = [];
        foreach ($usuariosConKm as $u) {
            $totalUsuario[$u->id] = array_sum(array_column($kmMap[$u->id] ?? [], 'km'));
        }

        return view('km', [
            'project'        => $project,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'dias'           => $dias,
            'usuarios'       => $usuariosConKm,
            'kmMap'          => $kmMap,
            'totalesDia'     => $totalesDia,
            'totalUsuario'   => $totalUsuario,
            'totalGeneral'   => array_sum($totalUsuario),
            'breadcrumb'     => [['label' => 'Consulta kilometraje', 'url' => '']],
        ]);
    }

    // ── Informe kilómetros mensual (web) ─────────────────────────────────────

    public function informe(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId, $allUsuarios, $canSelect] = $this->resolveParams($request, $project, $user, $isAdmin);

        $data = $this->getInformeKmData($userId, $year, $month);

        return view('km-informe', array_merge($data, [
            'project'    => $project,
            'year'       => $year,
            'month'      => $month,
            'user_id'    => $userId,
            'usuarios'   => $canSelect ? $allUsuarios : collect(),
            'can_select' => $canSelect,
            'breadcrumb' => [['label' => 'Informe kilómetros', 'url' => '']],
        ]));
    }

    // ── PDF individual ────────────────────────────────────────────────────────

    public function informePdf(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);
        $data     = $this->getInformeKmData($userId, $year, $month);
        $meses    = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $nombre   = str_replace(' ', '_', $data['usuario']->nombre ?? 'usuario');
        $filename = "km_{$nombre}_{$meses[$month-1]}_{$year}.pdf";

        $pdf = Pdf::loadView('km-informe-pdf', array_merge($data, ['year' => $year, 'month' => $month]))->setPaper('a4', 'portrait');
        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    // ── PDF todos ─────────────────────────────────────────────────────────────

    public function informePdfTodos(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        if (!$isAdmin) abort(403);

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        $allUsuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);
        $meses       = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $pages = [];
        foreach ($allUsuarios as $u) {
            $data = $this->getInformeKmData($u->id, $year, $month);
            if ($data['total_km'] == 0) continue; // omitir usuarios sin km
            $pages[] = view('km-informe-pdf', array_merge($data, ['year' => $year, 'month' => $month]))->render();
        }

        if (empty($pages)) {
            $pages[] = '<p style="font-family:DejaVu Sans,sans-serif;padding:40px;">Sin registros de kilometraje para este mes.</p>';
        }

        $html = '';
        foreach ($pages as $i => $page) {
            $style = $i > 0 ? ' style="page-break-before:always"' : '';
            $html .= "<div{$style}>{$page}</div>";
        }
        $filename = "km_todos_{$meses[$month-1]}_{$year}.pdf";

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        return $pdf->download($filename);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveParams(Request $request, Project $project, $user, bool $isAdmin): array
    {
        $allUsuarios     = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);
        $currentVmUserId = $user->projectUserId($project);
        $canSelect       = $isAdmin;

        if ($canSelect) {
            $userId = (int) $request->input('user_id', $currentVmUserId ?? ($allUsuarios->first()->id ?? 0));
        } else {
            $userId = $currentVmUserId ?? 0;
        }

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        return [$year, $month, $userId, $allUsuarios, $canSelect];
    }

    private function getInformeKmData(int $userId, int $year, int $month): array
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();

        $mp  = str_pad($month, 2, '0', STR_PAD_LEFT);
        $ms  = "{$year}-{$mp}-01";
        $dim = (int) Carbon::parse($ms)->daysInMonth;
        $me  = "{$year}-{$mp}-{$dim}";

        $dowLabels = ['D','L','M','X','J','V','S'];

        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$ms, $me])
            ->get(['fecha_fichaje', 'km', 'trayecto'])
            ->keyBy('fecha_fichaje');

        $dias = [];
        for ($d = 1; $d <= $dim; $d++) {
            $fecha = "{$year}-{$mp}-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            $dow   = $dowLabels[(int) date('w', strtotime($fecha))];
            $f     = $fichajes->get($fecha);
            $km    = $f ? (float) ($f->km ?? 0) : 0;

            $dias[] = [
                'num'      => $d,
                'dow'      => $dow,
                'fecha'    => $fecha,
                'km'       => $km,
                'trayecto' => $f ? ($f->trayecto ?? '') : '',
                'weekend'  => in_array($dow, ['D', 'S']),
            ];
        }

        $total_km = array_sum(array_column($dias, 'km'));

        // Histórico km por mes del año
        $year_stats = [];
        $labels = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        $kmYear = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
            ->whereNotNull('km')
            ->where('km', '>', 0)
            ->selectRaw("EXTRACT(MONTH FROM fecha_fichaje)::int as mes, SUM(km) as total_km")
            ->groupBy('mes')
            ->pluck('total_km', 'mes');

        for ($m = 1; $m <= 12; $m++) {
            $year_stats[$m] = [
                'label' => $labels[$m - 1],
                'km'    => (float) ($kmYear[$m] ?? 0),
            ];
        }

        return [
            'usuario'    => $usuario,
            'dias'       => $dias,
            'dim'        => $dim,
            'total_km'   => $total_km,
            'year_stats' => $year_stats,
        ];
    }
}
