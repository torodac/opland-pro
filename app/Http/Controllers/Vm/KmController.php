<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\CircuitoFirmas;
use App\Services\VmJerarquiaAprobacion;
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

        $pasoActual = CircuitoFirmas::pasoActual(CircuitoFirmas::KM, $userId, $year, $month);

        return view('km-informe', array_merge($data, [
            'paso_actual'  => $pasoActual,
            'paso_labels'  => CircuitoFirmas::ETIQUETAS,
            'aprobaciones' => CircuitoFirmas::firmas(CircuitoFirmas::KM, $userId, $year, $month),
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

        $pdf = Pdf::loadView('km-informe-pdf', array_merge($data, [
            'year' => $year, 'month' => $month,
            'firmas' => $this->firmasParaPdf($userId, $year, $month),
        ]))->setPaper('a4', 'portrait');
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
        $user       = auth()->user();
        $isAdmin    = $user->isProjectAdmin($project);
        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId ? DB::table('vm_usuarios')->where('id', $authUserId)->value('id_rol') : null;
        $puedeVerTodos = $isAdmin || in_array((int) $authRol, [3, 11]); // Dirección general, Director RRHH
        if (!$puedeVerTodos) abort(403);

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        $allUsuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);
        $meses       = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $pages = [];
        foreach ($allUsuarios as $u) {
            $data = $this->getInformeKmData($u->id, $year, $month);
            if ($data['total_km'] == 0) continue; // omitir usuarios sin km
            $pages[] = view('km-informe-pdf', array_merge($data, [
                'year' => $year, 'month' => $month,
                'firmas' => $this->firmasParaPdf($u->id, $year, $month),
            ]))->render();
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

    // ── Circuito de firmas ────────────────────────────────────────────────────
    //
    // El mismo que el informe mensual (aprueba → rrhh → trabajador → direccion → completado),
    // sobre las mismas tablas, distinguido por informe='km'. La mecánica vive en
    // App\Services\CircuitoFirmas; aquí queda lo propio de este informe: su huella y el panel.
    //
    // A diferencia del mensual, al completarse NO congela nada. Los dos informes salen de las
    // mismas filas de vm_fichaje, así que si este bloqueara el mes, el mensual se quedaría sin
    // poder corregirse (y al revés). El bloqueo lo sigue haciendo solo el mensual.

    public function firmarAprueba(Request $request, Project $project)
    {
        return $this->firmarDesdeRequest($request, $project, 'aprueba');
    }

    public function firmarRrhh(Request $request, Project $project)
    {
        return $this->firmarDesdeRequest($request, $project, 'rrhh');
    }

    public function firmarTrabajador(Request $request, Project $project)
    {
        return $this->firmarDesdeRequest($request, $project, 'trabajador');
    }

    public function firmarDireccion(Request $request, Project $project)
    {
        return $this->firmarDesdeRequest($request, $project, 'direccion');
    }

    private function firmarDesdeRequest(Request $request, Project $project, string $step)
    {
        $user       = auth()->user();
        $isAdmin    = $user->isProjectAdmin($project);
        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId ? (int) DB::table('vm_usuarios')->where('id', $authUserId)->value('id_rol') : null;

        $userId = (int) $request->input('user_id');
        $year   = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month  = max(1,    min(12,   (int) $request->input('month', now()->month)));
        if (!$userId) {
            return response()->json(['error' => 'Falta el usuario del informe.'], 400);
        }

        // Quién puede firmar cada paso, con el mismo criterio que el informe mensual.
        $permitido = match ($step) {
            'aprueba'    => VmJerarquiaAprobacion::puedeFirmarAprueba($authUserId ? (int) $authUserId : null, $userId, $isAdmin, $authRol),
            'rrhh'       => $isAdmin || $authRol === VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH,
            'direccion'  => $isAdmin || $authRol === VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL,
            // El trabajador firma el suyo, y admin/RRHH pueden hacerlo en su lugar para desatascar.
            'trabajador' => $isAdmin
                || in_array($authRol, [VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL, VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH], true)
                || ($authUserId && (int) $authUserId === $userId),
            default      => false,
        };
        if (!$permitido) {
            return response()->json(['error' => 'No tienes permiso para firmar este paso.'], 403);
        }

        $resultado = $this->firmarPaso($userId, $year, $month, $step, (int) auth()->id(), $request);

        return isset($resultado['error'])
            ? response()->json(['error' => $resultado['error']], $resultado['status'] ?? 422)
            : response()->json($resultado);
    }

    /** Compartido por los cuatro pasos y por la PWA. */
    public function firmarPaso(int $userId, int $year, int $month, string $step, int $aprobadoPor, Request $request): array
    {
        // Un usuario sin kilómetros en el mes no tiene informe que firmar (quedan fuera del
        // circuito, igual que quedan fuera del PDF de todos).
        if ($this->getInformeKmData($userId, $year, $month)['total_km'] <= 0) {
            return ['error' => 'Este usuario no tiene kilómetros en el mes, así que no hay informe que firmar.', 'status' => 422];
        }

        return CircuitoFirmas::firmar(
            CircuitoFirmas::KM,
            $userId, $year, $month, $step, $aprobadoPor,
            fn() => $this->contentHash($userId, $year, $month),
            $request->ip()
        );
    }

    /**
     * Huella del informe en el momento de firmar: es lo que fija QUÉ se firmó. Solo entra lo que
     * el informe muestra -- los kilómetros y el trayecto de cada día, y el total --, no las horas:
     * cambiar una hora no invalida una firma de kilómetros.
     */
    private function contentHash(int $userId, int $year, int $month): string
    {
        $data = $this->getInformeKmData($userId, $year, $month);

        $dias = array_map(fn($d) => [
            'fecha'    => $d['fecha'],
            'km'       => $d['km'],
            'trayecto' => $d['trayecto'],
        ], $data['dias']);

        return hash('sha256', json_encode([
            'usuario'  => $userId,
            'anio'     => $year,
            'mes'      => $month,
            'dias'     => $dias,
            'total_km' => $data['total_km'],
        ], JSON_UNESCAPED_UNICODE));
    }

    /** Devuelve el informe al principio del circuito. Solo Dirección general, RRHH o admin. */
    public function reabrir(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        $authId  = $user->projectUserId($project);
        $authRol = $authId ? (int) DB::table('vm_usuarios')->where('id', $authId)->value('id_rol') : null;

        if (!$isAdmin && !in_array($authRol, [VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL, VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH], true)) {
            abort(403);
        }

        $userId = (int) $request->input('user_id');
        $year   = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month  = max(1,    min(12,   (int) $request->input('month', now()->month)));

        $paso = CircuitoFirmas::pasoActual(CircuitoFirmas::KM, $userId, $year, $month);
        if ($paso === 'completado') {
            return response()->json(['error' => 'Este informe ya está firmado por Dirección general. No se puede reiniciar.'], 423);
        }

        CircuitoFirmas::reabrir(CircuitoFirmas::KM, $userId, $year, $month);

        return response()->json(['ok' => true, 'paso_actual' => VmJerarquiaAprobacion::pasoInicial($userId)]);
    }

    // ── Panel de aprobaciones ─────────────────────────────────────────────────

    public function listado(Request $request, Project $project)
    {
        $user       = auth()->user();
        $isAdmin    = $user->isProjectAdmin($project);
        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId ? (int) DB::table('vm_usuarios')->where('id', $authUserId)->value('id_rol') : null;

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        // Mismo criterio que el panel del informe mensual: cada uno ve su rama de la jerarquía de
        // APROBACIÓN, y admin / Dirección general / RRHH ven a todos porque firman pasos globales.
        if (VmJerarquiaAprobacion::vePanelCompleto($isAdmin, $authRol)) {
            $usuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')
                ->get(['id', 'nombre', 'id_rol', 'admin_user_id']);
        } else {
            $idsRama  = $authUserId ? VmJerarquiaAprobacion::ramaDe((int) $authUserId) : [];
            $usuarios = $idsRama
                ? DB::table('vm_usuarios')->where('deleted', 0)->whereIn('id', $idsRama)
                    ->orderBy('nombre')->get(['id', 'nombre', 'id_rol', 'admin_user_id'])
                : collect();
        }

        $usuarios = $usuarios->reject(fn($u) => (int) $u->id === 1 || (int) $u->id_rol === 6)->values();
        $userIds  = $usuarios->pluck('id')->all();

        $estados = DB::table('vm_informes_estado')
            ->where('informe', CircuitoFirmas::KM)
            ->where('anio', $year)->where('mes', $month)
            ->whereIn('id_usuario', $userIds ?: [0])
            ->pluck('paso_actual', 'id_usuario');

        $firmasPorUsuario = DB::table('vm_usuarios as u')
            ->join('admin_users as a', 'a.id', '=', 'u.admin_user_id')
            ->whereIn('u.id', $userIds ?: [0])
            ->pluck('a.signature_path', 'u.id');

        $rolesMap = DB::table('vm_roles')->pluck('nombre', 'id');

        $filas = $usuarios->map(function ($u) use ($year, $month, $estados, $firmasPorUsuario, $rolesMap, $authUserId, $isAdmin, $authRol) {
            $data = $this->getInformeKmData($u->id, $year, $month);
            if ($data['total_km'] <= 0) return null;   // sin kilómetros, sin informe

            $diasConKm = count(array_filter($data['dias'], fn($d) => $d['km'] > 0));

            return (object) [
                'id'          => $u->id,
                'nombre'      => $u->nombre,
                'departamento' => $rolesMap[$u->id_rol] ?? null,
                'paso'        => $estados[$u->id] ?? VmJerarquiaAprobacion::pasoInicial((int) $u->id),
                'total_km'    => $data['total_km'],
                'dias_con_km' => $diasConKm,
                'tiene_firma' => (bool) ($firmasPorUsuario[$u->id] ?? null),
                'es_mi_informe' => $u->admin_user_id && (int) $u->admin_user_id === (int) auth()->id(),
                'puede_firmar_aprueba' => VmJerarquiaAprobacion::puedeFirmarAprueba(
                    $authUserId ? (int) $authUserId : null, (int) $u->id, $isAdmin, $authRol
                ),
            ];
        })->filter()->sortBy('nombre')->values();

        $defaultTab = match (true) {
            $authRol === VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH     => 'rrhh',
            $authRol === VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL => 'direccion',
            $isAdmin                                                   => 'todos',
            (bool) $authUserId                                         => 'aprueba',
            default                                                    => 'todos',
        };

        return view('km-informe-list', [
            'project'                => $project,
            'year'                   => $year,
            'month'                  => $month,
            'filas'                  => $filas,
            'default_tab'            => $defaultTab,
            'puede_firmar_rrhh'      => $isAdmin || $authRol === VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH,
            'puede_firmar_direccion' => $isAdmin || $authRol === VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL,
            'puede_reabrir'          => $isAdmin || in_array($authRol, [VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL, VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH], true),
            // Sin firma manuscrita en el perfil no se puede firmar ningún paso, así que el panel
            // lo avisa arriba en lugar de dejar los botones muertos sin explicación.
            'viewer_tiene_firma'     => (bool) DB::table('admin_users')->where('id', auth()->id())->value('signature_path'),
            'paso_labels'            => CircuitoFirmas::ETIQUETAS,
            'pasos_visibles'         => CircuitoFirmas::PASOS_VISIBLES,
            'breadcrumb'             => [['label' => 'Informe kilómetros', 'url' => '']],
        ]);
    }

    /** Firmas para estampar en el PDF, solo cuando el circuito está completado. */
    private function firmasParaPdf(int $userId, int $year, int $month): ?array
    {
        if (CircuitoFirmas::pasoActual(CircuitoFirmas::KM, $userId, $year, $month) !== 'completado') {
            return null;
        }

        $firmas = CircuitoFirmas::firmas(CircuitoFirmas::KM, $userId, $year, $month);
        if ($firmas->isEmpty()) return null;

        // Mismas claves que el PDF del informe mensual, para que el bloque de firmas sea
        // literalmente el mismo trozo de plantilla en los dos.
        return $firmas->map(fn($f) => [
            'step'           => CircuitoFirmas::ETIQUETAS[$f->step] ?? $f->step,
            'nombre'         => $f->aprobado_por_nombre,
            'aprobado_at'    => $f->aprobado_at,
            'signature_path' => $f->signature_path,
        ])->all();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveParams(Request $request, Project $project, $user, bool $isAdmin): array
    {
        $allUsuarios     = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);
        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;
        $canSelect       = $isAdmin || in_array((int) $authRol, [3, 11]); // Dirección general, Director RRHH

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
