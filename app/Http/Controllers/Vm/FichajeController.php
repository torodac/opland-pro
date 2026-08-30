<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\InformeAprobacionGuard;
use App\Services\VmHorasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FichajeController extends Controller
{
    // Roles con privilegios ampliados sobre fichajes: Dirección general (3) y Director RRHH (11).
    // Mismo criterio que ya usaba update() para saltarse el límite de 2 días al editar.
    private const ROLES_SIN_LIMITE = [3, 11];

    private const DIAS_SEMANA = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    // Mismo criterio que FichaController::resolveVisibleUserIds() (visibilidad estándar de
    // control_user en toda la plataforma, según todos_registros/roles_supervisados del rol).
    private function resolveVisibleUserIds(Project $project): ?array
    {
        $user = auth()->user();
        if (!$user || $user->isProjectAdmin($project)) return null;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return null;

        $role = $user->getProjectRolePublic($project);
        if (!$role || ($role->todos_registros ?? null) === 'todos') return null;

        if (($role->todos_registros ?? null) === 'supervisados') {
            return \App\Services\RoleHierarchy::visibleUserIds(
                $project->slug . '_roles',
                $project->slug . '_usuarios',
                (int) $projectUserId,
                (int) $role->id
            );
        }

        return [(string) $projectUserId];
    }

    // Listado admin con la misma información que el histórico de fichajes de la PWA
    // (public/pwa/app.js: fichajeHistoricoHtml/diffCounter), pero navegable por cualquier
    // trabajador visible y por mes, en vez de limitarse al usuario logueado y al mes en curso.
    public function listado(Request $request, Project $project)
    {
        abort_unless(auth()->user()->canViewTable($project, 'fichaje'), 403);

        $authUserId = auth()->user()->projectUserId($project);
        $visibleIds = $this->resolveVisibleUserIds($project);

        $usuarios = DB::table('vm_usuarios')->where('deleted', 0)
            ->when($visibleIds !== null, fn($q) => $q->whereIn('id', $visibleIds))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // 'usuario' ausente en la URL -> por defecto el propio usuario (o el primero visible);
        // 'usuario=0' -> opción explícita "Todos", sin seleccionar a nadie en concreto.
        $usuarioParam = $request->query('usuario');
        $modoTodos    = $usuarioParam !== null && (string) $usuarioParam === '0';

        if ($modoTodos) {
            $usuarioId = null;
        } else {
            $usuarioId = (int) ($usuarioParam ?: ($authUserId ?: ($usuarios->first()->id ?? 0)));
            if ($visibleIds !== null && !in_array((string) $usuarioId, $visibleIds, true)) {
                abort(403);
            }
        }

        // Ids sobre los que se consulta — SIEMPRE acotados a lo que este usuario puede ver
        // (restricción de control_user real): en modo "Todos" nunca se consulta la tabla
        // vm_usuarios completa, solo $visibleIds (o, si el rol ve todo, los mismos ids ya
        // filtrados arriba en $usuarios).
        $idsConsulta = $modoTodos
            ? $usuarios->pluck('id')->map(fn($i) => (int) $i)->all()
            : [$usuarioId];

        $anio = (int) ($request->input('anio') ?: now()->year);
        $mes  = (int) ($request->input('mes') ?: now()->month);

        $inicioMes  = Carbon::create($anio, $mes, 1);
        $esMesActual = $anio === now()->year && $mes === now()->month;
        $finMes     = $esMesActual ? Carbon::today() : $inicioMes->copy()->endOfMonth();
        $desde      = $inicioMes->toDateString();
        $hasta      = $finMes->toDateString();

        $registros = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->whereIn('control_user', $idsConsulta)
            ->whereBetween('fecha_fichaje', [$desde, $hasta])
            ->get()
            ->groupBy('control_user')
            ->map(fn($rows) => $rows->keyBy(fn($f) => (string) $f->fecha_fichaje));

        $ausencias = DB::table('vm_ausencias')
            ->whereIn('id_usuarios', $idsConsulta)
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->get(['id_usuarios', 'tipo', 'fecha_inicio', 'fecha_fin'])
            ->groupBy('id_usuarios');

        $horarios = DB::table('vm_horarios')
            ->whereIn('id_usuario', $idsConsulta)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('tipo', '!=', 'turno')
            ->get(['id_usuario', 'fecha', 'tipo'])
            ->groupBy('id_usuario');

        $imputadoPorDia = DB::table('vm_imputaciones')
            ->whereIn('id_usuario', $idsConsulta)
            ->whereBetween('fecha_imputacion', [$desde, $hasta])
            ->selectRaw('id_usuario, fecha_imputacion, SUM(duracion) as total')
            ->groupBy('id_usuario', 'fecha_imputacion')
            ->get()
            ->groupBy('id_usuario')
            ->map(fn($rows) => $rows->pluck('total', 'fecha_imputacion'));

        $pendientesPorDia = $this->pendientesPorDia($idsConsulta, $desde, $hasta);

        // Sede y "departamento de turnos" (horario visible) de cada usuario consultado --
        // mismo criterio que VmHorasService::esDeptoTurno(), en una sola consulta en lote.
        $deptoInfo = DB::table('vm_usuarios as u')
            ->leftJoin('vm_departamentos as d', 'd.id', '=', 'u.id_departamento')
            ->whereIn('u.id', $idsConsulta)
            ->get(['u.id', 'u.sede', DB::raw('COALESCE(d.visible_horarios, false) as es_turno')])
            ->keyBy('id');

        $festivosPorSede = [];
        $festivosParaSede = function (string $sede) use (&$festivosPorSede, $desde, $hasta) {
            return $festivosPorSede[$sede] ??= VmHorasService::festivosSet($sede, $desde, $hasta);
        };

        // Días a mostrar: TODOS los del mes (o hasta hoy si es el mes en curso), tengan o no
        // datos, para poder ver el mes completo de un vistazo.
        $diasMes = collect();
        for ($d = $inicioMes->copy(); $d->lte($finMes); $d->addDay()) {
            $diasMes->push($d->toDateString());
        }
        $diasMes = $diasMes->sortDesc()->values();

        $usuariosConsulta = $modoTodos ? $usuarios : $usuarios->where('id', $usuarioId)->values();

        $filas = collect();
        foreach ($usuariosConsulta as $u) {
            $uid = (int) $u->id;
            $regsUsuario = $registros->get($uid, collect());
            $ausUsuario  = $ausencias->get($uid, collect());
            $horUsuario  = $horarios->get($uid, collect());
            $impUsuario  = $imputadoPorDia->get($uid, collect());
            $pendUsuario = $pendientesPorDia[$uid] ?? [];

            $sede    = $deptoInfo[$uid]->sede ?? '';
            $esTurno = (bool) ($deptoInfo[$uid]->es_turno ?? false);
            $festivosSet = $festivosParaSede((string) $sede);

            foreach ($diasMes as $fecha) {
                $f = $regsUsuario->get($fecha);
                $hor = $horUsuario->firstWhere('fecha', $fecha);
                $aus = $ausUsuario->first(fn($a) => $fecha >= $a->fecha_inicio && $fecha <= $a->fecha_fin);

                // Mismo algoritmo de badges que el informe mensual
                // (informe-imputaciones.blade.php), para que ambas pantallas muestren
                // exactamente el mismo estado de cada día.
                $isFestivo      = isset($festivosSet[$fecha]);
                $entrada        = $f && $f->hora_inicio;
                $isDescansoEf   = VmHorasService::esDescansoEfectivo($fecha, $hor->tipo ?? null, $esTurno);
                $isFestTrab     = $isFestivo && (bool) $f;
                $isRotatorio    = $isFestTrab && $isDescansoEf;
                $trabajaFestivo  = $entrada && $isFestivo;
                $trabajaDescanso = $entrada && $isDescansoEf && !$isFestivo;

                $badges = collect();
                if ($isRotatorio) {
                    $badges->push(['Desc. Fest.', '#6f42c1', '#fff']);
                } elseif ($isFestTrab || $trabajaFestivo) {
                    $badges->push(['Trab. fest.', '#0d6efd', '#fff']);
                } elseif ($trabajaDescanso) {
                    $badges->push(['Trab. desc.', '#0d6efd', '#fff']);
                } elseif ($aus) {
                    $badges->push([$aus->tipo, self::tipoColor($aus->tipo), '#fff']);
                } elseif ($entrada) {
                    $badges->push(['Trabajo', '#74aaf8', '#fff']);
                } elseif ($isFestivo) {
                    $badges->push(['Festivo', '#ffe0e0', '#cc0000']);
                }
                if ($esTurno && $isDescansoEf && !$trabajaFestivo && !$trabajaDescanso) {
                    $badges->push(['Descanso', '#F3F4F6', '#6B7280']);
                }

                $minP = ($f && $f->pausa_inicio && $f->pausa_fin)
                    ? VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio)
                    : null;

                $diffMin = null;
                if ($f && $f->hora_inicio && $f->hora_fin) {
                    $bruto     = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                    $efectivas = $bruto - ($minP ?? 0);
                    $diffMin   = $efectivas - (int) ($impUsuario[$fecha] ?? 0);
                }

                $pendDia = $pendUsuario[$fecha] ?? null;

                $filas->push((object) [
                    'usuarioId'     => $uid,
                    'usuarioNombre' => $u->nombre,
                    'esTurno'    => $esTurno,
                    'id'         => $f->id ?? null,
                    'fecha'      => $fecha,
                    'diaSemana'  => self::DIAS_SEMANA[Carbon::parse($fecha)->dayOfWeek],
                    'horaInicio' => $f->hora_inicio ?? null,
                    'horaFin'    => $f->hora_fin ?? null,
                    'pausaMin'   => $minP,
                    'badges'     => $badges,
                    'diffMin'    => $diffMin,
                    'pendientes' => $pendDia['count'] ?? 0,
                    'pendientesUrl' => $pendDia
                        ? route('listado', [$project->slug, $pendDia['tabla']]) . '?ids=' . implode(',', $pendDia['ids'])
                        : null,
                    'conflicto'  => $badges->count() > 1,
                ]);
            }
        }

        if ($modoTodos) {
            $filas = $filas->sortBy([['usuarioNombre', 'asc'], ['fecha', 'desc']])->values();
        }

        $mesLabel     = $inicioMes->translatedFormat('F Y');
        $anterior     = $inicioMes->copy()->subMonth();
        $siguiente    = $inicioMes->copy()->addMonth();
        $urlAnterior  = request()->fullUrlWithQuery(['anio' => $anterior->year, 'mes' => $anterior->month]);
        $urlSiguiente = request()->fullUrlWithQuery(['anio' => $siguiente->year, 'mes' => $siguiente->month]);

        return view('vm.fichaje-list', [
            'project'         => $project,
            'usuarios'        => $usuarios,
            'usuarioId'       => $usuarioId,
            'modoTodos'       => $modoTodos,
            'mostrarSelector' => $visibleIds === null || count($visibleIds) > 1,
            'anio'            => $anio,
            'mes'             => $mes,
            'mesLabel'        => $mesLabel,
            'urlAnterior'     => $urlAnterior,
            'urlSiguiente'    => $urlSiguiente,
            'filas'           => $filas,
            'breadcrumb'      => [
                ['label' => 'Fichajes', 'url' => ''],
            ],
        ]);
    }

    // Mismo color/criterio que tipoColor() en informe-imputaciones.blade.php, para que el
    // badge de un tipo de ausencia se vea exactamente igual en ambas pantallas.
    private static function tipoColor(string $nombre): string
    {
        $mapa = [
            'Asuntos propios' => '#34c163',
            'Baja'            => '#7b3f8c',
            'Compensación'    => '#e83e8c',
            'Revisar'         => '#fd7e14',
            'Vacaciones'      => '#e8b800',
            'Absentismo'      => '#dc3545',
        ];
        if (isset($mapa[$nombre])) return $mapa[$nombre];
        $n = mb_strtolower($nombre);
        if (str_starts_with($n, 'comp'))    return '#e83e8c';
        if (str_contains($n, 'vacac'))      return '#e8b800';
        if (str_contains($n, 'baja'))       return '#7b3f8c';
        if (str_contains($n, 'asunto'))     return '#34c163';
        return '#888';
    }

    // Tareas planificadas de cada usuario del array dado, en el rango de fechas, sin ninguna
    // imputación suya todavía — mismo criterio que
    // VacationmarbellaPwaController::fichajeHoy(). Devuelve
    // [id_usuario][fecha] => ['count' => n, 'tabla' => 'tareas_limpieza', 'ids' => [...]].
    private function pendientesPorDia(array $usuarioIds, string $desde, string $hasta): array
    {
        $pendientes = [];
        $tablasPorTipo = [
            'tareas_limpieza'      => 'limpieza',
            'tareas_mantenimiento' => 'mantenimiento',
            'tareas_piscinas'      => 'piscina',
        ];

        foreach ($tablasPorTipo as $tablaBare => $tipoLabel) {
            $tareasDia = DB::table('vm_' . $tablaBare)
                ->where('deleted', 0)
                ->whereBetween('fecha_planificada', [$desde, $hasta])
                // Mismo criterio de "tarea todavía abierta" que TareaController/NovacionesController:
                // una tarea Completada/Cancelada/Descartada no es trabajo pendiente. Sin este
                // filtro, una Descartada (que además queda oculta automáticamente, ver
                // BreezewaySyncTasksCommand) se contaba como pendiente pero el enlace a verla
                // no mostraba nada, al estar filtrada por el listado genérico.
                ->where(fn($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['Completada', 'Cancelada', 'Descartada']))
                ->get(['id', 'fecha_planificada', 'control_user']);

            if ($tareasDia->isEmpty()) continue;

            // control_user es un array jsonb de ids; se filtra en PHP contra $usuarioIds (el
            // conjunto ya viene acotado por la visibilidad del rol desde listado()).
            $tareasPorUsuario = []; // uid => fecha => [taskIds]
            foreach ($tareasDia as $t) {
                $asignados = json_decode($t->control_user ?? '[]', true) ?: [];
                foreach ($asignados as $uid) {
                    $uid = (int) $uid;
                    if (!in_array($uid, $usuarioIds, true)) continue;
                    $tareasPorUsuario[$uid][$t->fecha_planificada][] = $t->id;
                }
            }

            foreach ($tareasPorUsuario as $uid => $porFecha) {
                $todosLosIds = collect($porFecha)->flatten()->unique();
                $idsConImputacion = DB::table('vm_imputaciones')
                    ->where('tipo', $tipoLabel)
                    ->where('id_usuario', $uid)
                    ->whereIn('id_tarea', $todosLosIds)
                    ->pluck('id_tarea')
                    ->unique();

                foreach ($porFecha as $fecha => $taskIds) {
                    $pendientesIds = array_values(array_diff($taskIds, $idsConImputacion->all()));
                    if (!$pendientesIds) continue;

                    if (!isset($pendientes[$uid][$fecha])) {
                        $pendientes[$uid][$fecha] = ['count' => 0, 'tabla' => $tablaBare, 'ids' => []];
                    }
                    $pendientes[$uid][$fecha]['count'] += count($pendientesIds);
                    $pendientes[$uid][$fecha]['ids'] = array_merge($pendientes[$uid][$fecha]['ids'], $pendientesIds);
                }
            }
        }

        return $pendientes;
    }

    public function create(Project $project)
    {
        abort_unless(auth()->user()->canViewTable($project, 'fichaje'), 403);

        $authUserId = auth()->user()->projectUserId($project);

        $visibleIds = $this->resolveVisibleUserIds($project);
        $usuarios = DB::table('vm_usuarios')->where('deleted', 0)
            ->when($visibleIds !== null, fn($q) => $q->whereIn('id', $visibleIds))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('vm.fichaje-nuevo', [
            'project'         => $project,
            'usuarios'        => $usuarios,
            'controlUserPorDefecto' => $authUserId,
            'fechaPorDefecto' => now()->toDateString(),
            'breadcrumb'      => [
                ['label' => 'Fichajes', 'url' => route('listado', [$project->slug, 'fichaje'])],
                ['label' => 'Nuevo', 'url' => ''],
            ],
        ]);
    }

    public function store(Request $request, Project $project)
    {
        abort_unless(auth()->user()->canViewTable($project, 'fichaje'), 403);

        $user       = auth()->user();
        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId
            ? DB::table($project->slug . '_usuarios')->where('id', $authUserId)->value('id_rol')
            : null;
        $puedeSinLimiteFecha = $user->isAdmin()
            || $user->isProjectAdmin($project)
            || in_array((int) $authRol, self::ROLES_SIN_LIMITE);

        $data = $request->validate([
            'control_user'   => 'required|integer',
            'fecha_fichaje'  => 'required|date',
            'hora_inicio'    => 'nullable|date_format:H:i',
            'hora_fin'       => 'nullable|date_format:H:i',
            'pausa_inicio'   => 'nullable|date_format:H:i',
            'pausa_fin'      => 'nullable|date_format:H:i',
            'festivo'        => 'nullable|boolean',
            'fuera_de_turno' => 'nullable|boolean',
            'observacion'    => 'nullable|string|max:1000',
        ]);

        // Visibilidad: el control_user elegido tiene que estar dentro de lo que el usuario puede ver/crear.
        $visibleIds = $this->resolveVisibleUserIds($project);
        if ($visibleIds !== null && !in_array((string) $data['control_user'], $visibleIds, true)) {
            return response()->json(['error' => 'No tienes permiso para fichar por ese empleado.'], 422);
        }

        if ($data['fecha_fichaje'] > now()->toDateString()) {
            return response()->json(['error' => 'No se puede crear un fichaje de una fecha futura.'], 422);
        }

        if (!$puedeSinLimiteFecha && $data['fecha_fichaje'] < now()->subDays(2)->toDateString()) {
            return response()->json(['error' => 'Solo se pueden crear fichajes de los últimos 2 días.'], 422);
        }

        $horarioError = \App\Services\FichajeValidator::validarHorario(
            $data['hora_inicio']  ?? null,
            $data['hora_fin']     ?? null,
            $data['pausa_inicio'] ?? null,
            $data['pausa_fin']    ?? null,
        );
        if ($horarioError) {
            return response()->json(['error' => $horarioError], 422);
        }

        $yaExiste = DB::table('vm_fichaje')
            ->where('control_user', $data['control_user'])
            ->where('fecha_fichaje', $data['fecha_fichaje'])
            ->where('deleted', 0)
            ->exists();
        if ($yaExiste) {
            return response()->json(['error' => 'Ese empleado ya tiene un fichaje ese día.'], 422);
        }

        $nombreUsuario = DB::table('vm_usuarios')->where('id', $data['control_user'])->value('nombre');

        if (InformeAprobacionGuard::estaCompletado((int) $data['control_user'], $data['fecha_fichaje'])) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $data['control_user'], $data['fecha_fichaje'])) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $id = DB::table('vm_fichaje')->insertGetId([
            'nombre'         => $data['fecha_fichaje'] . '_' . $nombreUsuario,
            'control_user'   => $data['control_user'],
            'fecha_fichaje'  => $data['fecha_fichaje'],
            'hora_inicio'    => $data['hora_inicio']  ?? null,
            'hora_fin'       => $data['hora_fin']     ?? null,
            'pausa_inicio'   => $data['pausa_inicio'] ?? null,
            'pausa_fin'      => $data['pausa_fin']    ?? null,
            'festivo'        => (int) ($data['festivo'] ?? 0),
            'fuera_de_turno' => (int) ($data['fuera_de_turno'] ?? 0),
            'observacion'    => $data['observacion'] ?? null,
            'deleted'        => 0,
            'createuser'     => $user->id, // admin_users.id, igual que el resto de la app (Auth::id())
            'createdat'      => now(),
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog((int) $data['control_user'], $data['fecha_fichaje'], 'vm_fichaje', 'insert', $id, $request);

        return response()->json([
            'ok'               => true,
            'redirect'         => route('vm.fichaje_form', [$project->slug, $id]),
            'aviso_aprobacion' => $aviso,
        ]);
    }

    public function show(Project $project, int $id)
    {
        abort_unless(auth()->user()->canViewTable($project, 'fichaje'), 403);
        $fichaje = DB::table('vm_fichaje')->where('id', $id)->where('deleted', 0)->firstOrFail();

        $usuario  = DB::table('vm_usuarios')->where('id', $fichaje->control_user)->first();
        $usuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);

        // ── Imputaciones del día ─────────────────────────────────────────────
        $imputacionesRaw = DB::table('vm_imputaciones')
            ->where('id_usuario', $fichaje->control_user)
            ->where('fecha_imputacion', $fichaje->fecha_fichaje)
            ->get(['id', 'tipo', 'id_tarea', 'duracion', 'observacion']);

        // Nombres de tareas: union de las tres tablas por los ids relevantes
        $porTipo = $imputacionesRaw->groupBy('tipo');
        $nombresTarea = [];
        foreach (['limpieza', 'mantenimiento', 'piscina'] as $tipo) {
            $tabla = 'vm_tareas_' . ($tipo === 'piscina' ? 'piscinas' : $tipo);
            $ids   = ($porTipo[$tipo] ?? collect())->pluck('id_tarea')->unique()->values()->all();
            if ($ids) {
                DB::table($tabla)->whereIn('id', $ids)->get(['id', 'nombre'])->each(function ($t) use ($tipo, &$nombresTarea) {
                    $nombresTarea[$tipo . '_' . $t->id] = $t->nombre;
                });
            }
        }
        $imputaciones = $imputacionesRaw->map(function ($i) use ($nombresTarea) {
            $i->tarea_nombre = $nombresTarea[$i->tipo . '_' . $i->id_tarea] ?? ('Tarea #' . $i->id_tarea);
            return $i;
        });

        $totalImputado = $imputaciones->sum('duracion');

        // ── Cálculo de tarjetas ──────────────────────────────────────────────
        $hms = fn(?string $t) => $t ? VmHorasService::hmsToMinutes($t) : null;

        $inicioMin = $hms($fichaje->hora_inicio);
        $finMin    = $hms($fichaje->hora_fin);
        $pausaIMin = $hms($fichaje->pausa_inicio);
        $pausaFMin = $hms($fichaje->pausa_fin);

        $tfMin = ($inicioMin !== null && $finMin !== null) ? $finMin - $inicioMin : null;
        $pMin  = ($pausaIMin !== null && $pausaFMin !== null) ? $pausaFMin - $pausaIMin : null;

        // Contrato vigente
        $contrato = DB::table('vm_contratos')
            ->where('id_usuarios', $fichaje->control_user)
            ->where('fecha_alta', '<=', $fichaje->fecha_fichaje)
            ->where(function ($q) use ($fichaje) {
                $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>=', $fichaje->fecha_fichaje);
            })
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderByDesc('fecha_alta')
            ->first(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $esperadoMin = $contrato?->horas_semana
            ? (int) round(($contrato->horas_semana / 5) * 60)
            : null;

        $dedPausa = ($contrato && $pMin !== null)
            ? VmHorasService::pausaDeducible($pMin, (float) $contrato->horas_semana)
            : 0;

        $fichadoMin   = $tfMin !== null ? $tfMin - $dedPausa : null;
        $efectivasMin = ($tfMin !== null && $pMin !== null) ? $tfMin - $pMin : $tfMin;

        // Horas extra (reutiliza VmHorasService)
        $sede      = $usuario?->sede ?? '';
        $festivos  = VmHorasService::festivosSet($sede, $fichaje->fecha_fichaje, $fichaje->fecha_fichaje);
        $isFestivo = isset($festivos[$fichaje->fecha_fichaje]);

        $horario = DB::table('vm_horarios')
            ->where('id_usuario', $fichaje->control_user)
            ->where('fecha', $fichaje->fecha_fichaje)
            ->first(['tipo']);

        $esTurno = VmHorasService::esDeptoTurno($fichaje->control_user);

        $heMin = VmHorasService::calcularHeDia(
            $tfMin, $pMin, null, $contrato,
            $isFestivo,
            $isFestivo, // festivo trabajado = vm_festivos, ya no depende de vm_fichaje.festivo
            $tfMin !== null,
            VmHorasService::esDescansoEfectivo($fichaje->fecha_fichaje, $horario->tipo ?? null, $esTurno),
            (int) ($fichaje->ajuste_he ?? 0),
            $esTurno
        );

        // Roles permitidos para ver/editar el ajuste HE
        $authUserId = auth()->user()->projectUserId($project);
        $authRol    = $authUserId
            ? DB::table($project->slug . '_usuarios')->where('id', $authUserId)->value('id_rol')
            : null;
        $puedeAjustar = auth()->user()->isAdmin()
            || auth()->user()->isProjectAdmin($project)
            || in_array((int) $authRol, [3, 11]);
        $puedeSinLimiteFecha = $puedeAjustar;

        // "Pendiente" solo tiene sentido para el mismo caso que el bloque del dashboard
        // "Fichaje vs imputaciones (diff > 30 min)": roles que imputan tiempo por tarea
        // (Limpieza=1, Mantenimiento=4), fichaje ya cerrado y de un día pasado, con una
        // desviación real de más de 30 min entre lo fichado y lo imputado ese día.
        $pendienteValidar = !$fichaje->validado
            && $fichaje->hora_fin
            && $fichaje->fecha_fichaje < now()->toDateString()
            && in_array((int) ($usuario->id_rol ?? 0), [1, 4], true)
            && $efectivasMin !== null
            && abs($efectivasMin - $totalImputado) > 30;

        return view('vm.fichaje', compact(
            'project', 'fichaje', 'usuario', 'usuarios',
            'imputaciones', 'totalImputado',
            'fichadoMin', 'esperadoMin', 'heMin', 'efectivasMin',
            'puedeAjustar', 'puedeSinLimiteFecha', 'pendienteValidar'
        ));
    }

    public function update(Request $request, Project $project, int $id)
    {
        abort_unless(auth()->user()->canViewTable($project, 'fichaje'), 403);

        $user       = auth()->user();
        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId
            ? DB::table($project->slug . '_usuarios')->where('id', $authUserId)->value('id_rol')
            : null;
        $puedeSinLimiteFecha = $user->isAdmin()
            || $user->isProjectAdmin($project)
            || in_array((int) $authRol, [3, 11]);

        $data = $request->validate([
            'control_user'   => 'required|integer',
            'fecha_fichaje'  => 'required|date',
            'hora_inicio'    => 'nullable|date_format:H:i',
            'hora_fin'       => 'nullable|date_format:H:i',
            'pausa_inicio'   => 'nullable|date_format:H:i',
            'pausa_fin'      => 'nullable|date_format:H:i',
            'hora_ini_auto'  => 'nullable|date_format:H:i',
            'hora_fin_auto'  => 'nullable|date_format:H:i',
            'pausa_ini_auto' => 'nullable|date_format:H:i',
            'pausa_fin_auto' => 'nullable|date_format:H:i',
            'festivo'        => 'nullable|boolean',
            'fuera_de_turno' => 'nullable|boolean',
            'validado'       => 'nullable|boolean',
            'km'             => 'nullable|numeric|min:0',
            'trayecto'       => 'nullable|string|max:255',
            'observacion'      => 'nullable|string|max:1000',
            'ajuste_he'        => 'nullable|integer',
            'ajuste_he_motivo' => 'nullable|string|max:500',
            'deleted'        => 'nullable|integer',
        ]);

        if (!$puedeSinLimiteFecha && $data['fecha_fichaje'] < now()->subDays(2)->toDateString()) {
            return response()->json(['error' => 'Solo se pueden editar fichajes de los últimos 2 días'], 422);
        }

        $horarioError = \App\Services\FichajeValidator::validarHorario(
            $data['hora_inicio']  ?? null,
            $data['hora_fin']     ?? null,
            $data['pausa_inicio'] ?? null,
            $data['pausa_fin']    ?? null,
        );
        if ($horarioError) {
            return response()->json(['error' => $horarioError], 422);
        }

        $yaExiste = DB::table('vm_fichaje')
            ->where('control_user', $data['control_user'])
            ->where('fecha_fichaje', $data['fecha_fichaje'])
            ->where('id', '!=', $id)
            ->where('deleted', 0)
            ->exists();
        if ($yaExiste) {
            return response()->json(['error' => 'Ese empleado ya tiene otro fichaje ese día'], 422);
        }

        if (InformeAprobacionGuard::estaCompletado((int) $data['control_user'], $data['fecha_fichaje'])) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $data['control_user'], $data['fecha_fichaje'])) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $data['ajuste_he']        = (int) ($data['ajuste_he'] ?? 0);
        $data['festivo']        = (int) ($data['festivo'] ?? 0);
        $data['fuera_de_turno'] = (int) ($data['fuera_de_turno'] ?? 0);
        $data['validado']       = (bool) ($data['validado'] ?? false);
        $data['updatedat']      = now();

        DB::table('vm_fichaje')->where('id', $id)->update($data);

        $aviso = InformeAprobacionGuard::checkAndLog((int) $data['control_user'], $data['fecha_fichaje'], 'vm_fichaje', 'update', $id, $request);

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }
}
