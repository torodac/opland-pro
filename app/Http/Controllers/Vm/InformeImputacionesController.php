<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\RoleHierarchy;
use App\Services\VmHorasService;
use Illuminate\Support\Facades\DB;

class InformeImputacionesController extends Controller
{
    // ── Métodos públicos ──────────────────────────────────────────────────────

    public function index(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId, $allUsuarios, $canSelect, $canSelectTodos] = $this->resolveParams($request, $project, $user, $isAdmin);

        $data = $this->getInformeData($userId, $year, $month);

        $hoy = date('Y-m-d');
        $contratosUsuario = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja']);
        $sinContrato = $contratosUsuario->isNotEmpty()
            && $contratosUsuario->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
            && $contratosUsuario->every(fn($c) => $c->fecha_alta <= $hoy);
        $fechaFinContrato = $sinContrato
            ? $contratosUsuario->sortByDesc('fecha_baja')->first()?->fecha_baja
            : null;

        $usuarios = $canSelect
            ? $allUsuarios
            : collect();

        $estadoAprobacion = DB::table('vm_informes_estado')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->first();
        $pasoActual = $estadoAprobacion->paso_actual ?? 'rrhh';

        $aprobaciones = DB::table('vm_informes_aprobaciones as a')
            ->join('admin_users as u', 'u.id', '=', 'a.aprobado_por')
            ->where('a.id_usuario', $userId)->where('a.anio', $year)->where('a.mes', $month)
            ->orderByRaw("array_position(array['rrhh','coordinador','trabajador','direccion'], a.step)")
            ->get(['a.step', 'a.aprobado_at', 'u.name as aprobado_por_nombre']);

        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;
        $puedeFirmarCoordinador = $pasoActual === 'coordinador' && ($isAdmin || in_array((int) $authRol, [10, 3], true));
        $puedeFirmarDireccion   = $pasoActual === 'direccion'   && ($isAdmin || (int) $authRol === 3);
        $puedeFirmarTrabajador  = $pasoActual === 'trabajador'
            && (int) DB::table('vm_usuarios')->where('id', $userId)->value('admin_user_id') === (int) auth()->id();

        return view('informe-imputaciones', array_merge($data, [
            'project'            => $project,
            'year'               => $year,
            'month'              => $month,
            'user_id'            => $userId,
            'usuarios'           => $usuarios,
            'can_select'         => $canSelect,
            'can_select_todos'   => $canSelectTodos,
            'sin_contrato'       => $sinContrato,
            'fecha_fin_contrato' => $fechaFinContrato,
            'en_aprobacion'      => (bool) ($estadoAprobacion->en_aprobacion ?? false),
            'validado_at'        => $estadoAprobacion->marcado_at ?? null,
            'paso_actual'        => $pasoActual,
            'aprobaciones'       => $aprobaciones,
            'puede_firmar_coordinador' => $puedeFirmarCoordinador,
            'puede_firmar_direccion'   => $puedeFirmarDireccion,
            'puede_firmar_trabajador'  => $puedeFirmarTrabajador,
            'breadcrumb' => [
                ['label' => 'Informe mensual', 'url' => ''],
            ],
        ]));
    }

    // Panel de aprobaciones: todos los usuarios visibles para el rol de quien entra, con sus
    // métricas principales y el botón de firma correspondiente a su paso actual, sin tener que
    // abrir la ficha completa de cada uno. El filtrado por paso (pestañas) es en el cliente
    // (mismo patrón que el resto de listados con pestañas de esta app); aquí solo se decide qué
    // usuarios puede ver quién entra y qué puede firmar.
    public function listado(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, , $allUsuarios, $canSelect, $canSelectTodos] = $this->resolveParams($request, $project, $user, $isAdmin);

        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;

        // Mismos gates que cada botón de firma en la ficha individual (validar/firmarCoordinador/
        // firmarDireccion) -- se reutilizan tal cual, no se inventa un permiso nuevo para esta vista.
        $puedeFirmarRrhh        = $canSelectTodos; // isAdmin || authRol en [3,11]
        // Direccion general (3) puede actuar tambien como Coordinador, igual que ya puede como
        // RRHH (canSelectTodos, arriba) -- rol superior cubre los pasos de aprobacion inferiores.
        $puedeFirmarCoordinador = $isAdmin || in_array((int) $authRol, [10, 3], true);
        $puedeFirmarDireccion   = $isAdmin || (int) $authRol === 3;
        $viewerSignaturePath    = DB::table('admin_users')->where('id', auth()->id())->value('signature_path');

        // Pestaña inicial según el rol de quien entra: cada rol de aprobación aterriza
        // directamente en su cola de trabajo, en vez de en "Todos".
        $defaultTab = match (true) {
            (int) $authRol === 11 => 'rrhh',
            (int) $authRol === 10 => 'coordinador',
            (int) $authRol === 3  => 'direccion',
            $isAdmin               => 'todos',
            $currentVmUserId       => 'trabajador',
            default                 => 'todos',
        };

        // Si no puede seleccionar a nadie más, el listado se reduce a su propia fila (su
        // "Informe mensual" personal, mismo caso que hoy al entrar en la ficha individual).
        $usuarios = $canSelect ? $allUsuarios : DB::table('vm_usuarios')
            ->where('id', $currentVmUserId)->where('deleted', 0)
            ->get(['id', 'nombre', 'id_rol', 'admin_user_id']);

        // Fuera de este panel: Santi Ramón-Llin (id 1, ficha de Dirección general que no pasa
        // por el flujo de aprobación) y el rol "Proveedor limpieza" (6, externo, no informe
        // mensual propio). Pedido explícitamente, no es un criterio genérico de vm_usuarios.
        $usuarios = $usuarios->reject(fn($u) => (int) $u->id === 1 || (int) $u->id_rol === 6)->values();

        // Pills de filtro por rol (limpiadora=1, mantenimiento=4, sscc=resto).
        $rolFiltro = in_array($request->input('rol'), ['limpiadora', 'mantenimiento', 'sscc'], true) ? $request->input('rol') : null;
        if ($rolFiltro === 'limpiadora') {
            $usuarios = $usuarios->where('id_rol', 1)->values();
        } elseif ($rolFiltro === 'mantenimiento') {
            $usuarios = $usuarios->where('id_rol', 4)->values();
        } elseif ($rolFiltro === 'sscc') {
            $usuarios = $usuarios->reject(fn($u) => in_array((int) $u->id_rol, [1, 4], true))->values();
        }

        $userIds = $usuarios->pluck('id')->all();

        $estados = DB::table('vm_informes_estado')
            ->where('anio', $year)->where('mes', $month)
            ->whereIn('id_usuario', $userIds)
            ->pluck('paso_actual', 'id_usuario');

        $editadosTrasInicio = DB::table('vm_informes_ediciones_log')
            ->where('anio', $year)->where('mes', $month)
            ->whereIn('id_usuario', $userIds)
            ->distinct()->pluck('id_usuario')->flip();

        $firmasPorUsuario = DB::table('vm_usuarios as u')
            ->join('admin_users as a', 'a.id', '=', 'u.admin_user_id')
            ->whereIn('u.id', $userIds)
            ->pluck('a.signature_path', 'u.id');

        $rolesMap      = DB::table('vm_roles')->pluck('nombre', 'id');
        $claseAusencia = ['C' => 'Compensacion', 'V' => 'Vacaciones', 'B' => 'Baja', 'AA' => 'Asuntos', 'otro' => 'Absentismo'];
        $pendientesValidacion = $this->pendientesValidacionDashboard($userIds);

        $filas = $usuarios->map(function ($u) use ($year, $month, $estados, $editadosTrasInicio, $firmasPorUsuario, $rolesMap, $claseAusencia, $pendientesValidacion) {
            $paso     = $estados[$u->id] ?? 'rrhh';
            $metricas = $this->metricasResumen($u->id, $year, $month);

            $ausenciasPills = collect($metricas['ausencias'])->map(fn($count, $nombre) => [
                'nombre' => $nombre,
                'count'  => $count,
                'clase'  => $claseAusencia[VmHorasService::categoriaAusencia($nombre)] ?? 'Absentismo',
            ])->values();

            return (object) [
                'id'                  => $u->id,
                'nombre'              => $u->nombre,
                'id_rol'              => $u->id_rol,
                'departamento'        => $rolesMap[$u->id_rol] ?? null,
                'paso'                => $paso,
                'dias_trabajados'     => $metricas['dias_trabajados'],
                // Solo días con fichaje real -- no incluye el ajuste de un día entero que
                // generan las ausencias de tipo Compensación (ver metricasResumen()).
                'horas_extra'         => $metricas['horas_extra_fichado'],
                'ausencias'           => $ausenciasPills,
                'desviacion'          => $metricas['desviacion'],
                'pct_imputado'        => $metricas['pct_imputado'],
                'editado_tras_inicio' => $editadosTrasInicio->has($u->id),
                'tiene_firma'         => (bool) ($firmasPorUsuario[$u->id] ?? null),
                'es_mi_informe'       => $u->admin_user_id && (int) $u->admin_user_id === (int) auth()->id(),
                'es_turno'            => $metricas['es_turno'],
                'dias_turno'          => $metricas['dias_turno'],
                'dias_descanso'       => $metricas['dias_descanso'],
                'dias_sin_asignar'    => $metricas['dias_sin_asignar'],
                'pendientes_validacion' => $pendientesValidacion[$u->id] ?? 0,
            ];
        })->sortBy('nombre')->values();

        return view('informe-imputaciones-list', [
            'project'                => $project,
            'year'                   => $year,
            'month'                  => $month,
            'filas'                  => $filas,
            'rol_filtro'               => $rolFiltro,
            'default_tab'              => $defaultTab,
            'puede_firmar_rrhh'        => $puedeFirmarRrhh,
            'puede_firmar_coordinador' => $puedeFirmarCoordinador,
            'puede_firmar_direccion'   => $puedeFirmarDireccion,
            'viewer_tiene_firma'       => (bool) $viewerSignaturePath,
            'breadcrumb' => [
                ['label' => 'Informe mensual', 'url' => ''],
            ],
        ]);
    }

    // Cuenta, por usuario, cuántos elementos tiene pendientes en los 3 bloques del dashboard que
    // tienen una acción "Validar" real (DashboardController::validarConciliacion/validarTarea/
    // validarFichaje) -- deliberadamente NO se comparten las consultas con DashboardController
    // (que están limitadas a 50 filas y pensadas para pintar una lista, no para contar) para no
    // tocar el dashboard en vivo; misma lógica de negocio, repetida sin el limit().
    private function pendientesValidacionDashboard(array $userIds): array
    {
        $hoy = now()->toDateString();
        $pendientes = array_fill_keys($userIds, 0);

        // Conciliaciones: horario especial sin ausencia creada.
        DB::table('vm_horarios as h')
            ->whereNotIn('h.tipo', ['turno', 'descanso'])
            ->where('h.fecha', '<', $hoy)
            ->whereIn('h.id_usuario', $userIds)
            ->whereNotExists(function ($q) {
                $q->from('vm_ausencias as a')
                    ->whereColumn('a.id_usuarios', 'h.id_usuario')
                    ->whereColumn('a.fecha_inicio', '<=', 'h.fecha')
                    ->whereColumn('a.fecha_fin', '>=', 'h.fecha')
                    ->where('a.deleted', 0);
            })
            ->select('h.id_usuario', DB::raw('count(*) as n'))
            ->groupBy('h.id_usuario')
            ->get()
            ->each(function ($r) use (&$pendientes) { $pendientes[$r->id_usuario] = ($pendientes[$r->id_usuario] ?? 0) + $r->n; });

        // Tareas limpieza/mantenimiento completadas sin imputar y sin validar -- control_user es
        // un array JSON (puede haber varios asignados), se cuenta para cada uno.
        $sinImputacion = fn($tipo) => fn($q) => $q->from('vm_imputaciones as i')
            ->where('i.tipo', $tipo)->whereColumn('i.id_tarea', 't.id');
        foreach (['limpieza' => 'vm_tareas_limpieza', 'mantenimiento' => 'vm_tareas_mantenimiento'] as $tipo => $tabla) {
            DB::table("{$tabla} as t")
                ->where('t.deleted', 0)->where('t.estado', 'Completada')
                ->where(fn($q) => $q->whereNull('t.validado')->orWhere('t.validado', false))
                ->whereNull('t.usuario_breezeway_ausente')
                ->whereNotExists($sinImputacion($tipo))
                ->get(['t.control_user'])
                ->each(function ($t) use (&$pendientes) {
                    foreach (json_decode($t->control_user ?? '[]', true) ?? [] as $id) {
                        $id = (int) $id;
                        if (isset($pendientes[$id])) $pendientes[$id]++;
                    }
                });
        }

        // Fichaje vs imputaciones (diff > 30 min, sin validar) -- mismo cálculo que el dashboard.
        $usuariosRol = DB::table('vm_usuarios')->where('deleted', 0)->whereIn('id_rol', [1, 4])->pluck('id', 'nombre');
        $imputacionesDia = DB::table('vm_imputaciones')
            ->where('fecha_imputacion', '<', $hoy)->whereNotNull('duracion')
            ->selectRaw('id_usuario, fecha_imputacion, SUM(duracion) as total_min')
            ->groupBy('id_usuario', 'fecha_imputacion')
            ->get()->keyBy(fn($r) => $r->id_usuario . '_' . $r->fecha_imputacion);

        DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('validado')->orWhere('validado', false))
            ->where('fecha_fichaje', '<', $hoy)
            ->whereNotNull('hora_fin')
            ->get(['nombre', 'fecha_fichaje', 'hora_inicio', 'hora_fin', 'pausa_inicio', 'pausa_fin'])
            ->each(function ($f) use (&$pendientes, $usuariosRol, $imputacionesDia) {
                $nombreUsuario = preg_replace('/^\d{4}\.\d{2}\.\d{2}_/', '', (string) $f->nombre);
                $idUsuario = $usuariosRol[$nombreUsuario] ?? null;
                if (!$idUsuario || !isset($pendientes[$idUsuario])) return;

                $mins = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                if ($f->pausa_inicio && $f->pausa_fin) {
                    $mins -= VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio);
                }
                $impMin = (int) ($imputacionesDia[$idUsuario . '_' . $f->fecha_fichaje]->total_min ?? 0);
                if (abs($mins - $impMin) > 30) $pendientes[$idUsuario]++;
            });

        return $pendientes;
    }

    // Métricas de un usuario para una fila del panel de aprobaciones. Reutiliza getInformeData()
    // una sola vez (mismo cálculo que resumenParaPwa()) y de paso extrae la desviación
    // fichaje/imputación del mes -- mismo criterio que el bloque del dashboard (>30 min, solo
    // roles que imputan tiempo por tarea: Limpieza=1, Mantenimiento=4), sin repetir consultas.
    private function metricasResumen(int $userId, int $year, int $month): array
    {
        $data  = $this->getInformeData($userId, $year, $month);
        $stats = $data['year_stats'][$month] ?? null;

        $ausencias = collect($data['dias'])
            ->filter(fn($d) => $d['tipo'])
            ->groupBy(fn($d) => $d['tipo']->nombre)
            ->map(fn($g) => $g->count());

        // Horas extra solo de días con fichaje real -- excluye el ajuste negativo de un día
        // entero (-esperadoMin) que generan las ausencias de tipo Compensación al no haber
        // fichaje ese día (VmHorasService::calcularHeDia). Es una suma distinta de 'ep'/'en' de
        // year_stats, que sí las incluye.
        $heMinFichado = 0;
        foreach ($data['dias'] as $d) {
            if ($d['tf_min'] === null) continue;
            $heMinFichado += $d['he_min'] ?? 0;
        }

        $rol = $data['usuario']->id_rol ?? null;
        $desviacion = null;
        if (in_array((int) $rol, [1, 4], true)) {
            $diasConDesviacion = 0;
            $sumaDesviacionMin = 0;
            foreach ($data['dias'] as $d) {
                if ($d['tf_min'] === null) continue;
                $mins = $d['p_min'] !== null ? $d['tf_min'] - $d['p_min'] : $d['tf_min'];
                $diff = abs($mins - $d['ht_min']);
                if ($diff > 30) {
                    $diasConDesviacion++;
                    $sumaDesviacionMin += $diff;
                }
            }
            if ($diasConDesviacion > 0) {
                $desviacion = ['dias' => $diasConDesviacion, 'total_min' => $sumaDesviacionMin];
            }
        }

        // % de horas de tareas imputadas sobre horas reales fichadas en el mes (agregado, no
        // día a día como la desviación). fichadoMin=0 con imputadoMin>0 (imputó sin fichar) o un
        // porcentaje fuera de 0-300% se marca como "fuera de rango" en vez de un número engañoso.
        $pctImputado = null;
        if (in_array((int) $rol, [1, 4], true)) {
            $fichadoMinTotal = 0;
            $imputadoMinTotal = 0;
            foreach ($data['dias'] as $d) {
                $imputadoMinTotal += $d['ht_min'] ?? 0;
                if ($d['tf_min'] === null) continue;
                $fichadoMinTotal += $d['p_min'] !== null ? $d['tf_min'] - $d['p_min'] : $d['tf_min'];
            }
            if ($fichadoMinTotal > 0) {
                $pct = ($imputadoMinTotal / $fichadoMinTotal) * 100;
                $pctImputado = ($pct < 0 || $pct > 300) ? 'fuera_de_rango' : (int) round($pct);
            } elseif ($imputadoMinTotal > 0) {
                $pctImputado = 'fuera_de_rango';
            }
        }

        // Departamentos con horario de turnos (visible_horarios): cada día debería llevar
        // 'turno', 'descanso' o una ausencia -- lo que no encaja en ninguna de las tres es un
        // hueco real en la planificación, no un domingo implícito (esos usuarios no tienen
        // fin de semana fijo, ver VmHorasService::esDescansoEfectivo()).
        $esTurno = VmHorasService::esDeptoTurno($userId);
        $diasTurno = 0;
        $diasDescanso = 0;
        $diasSinAsignar = 0;
        if ($esTurno) {
            foreach ($data['dias'] as $d) {
                if ($d['horario_tipo'] === 'turno') { $diasTurno++; continue; }
                if ($d['horario_tipo'] === 'descanso') { $diasDescanso++; continue; }
                if ($d['tipo']) continue;
                $diasSinAsignar++;
            }
        }

        return [
            'usuario'               => $data['usuario']->nombre ?? null,
            'dias_trabajados'       => $stats['dias_col']['T'] ?? 0,
            'horas_extra_positivas' => $stats['ep'] ?? 0,
            'horas_extra_negativas' => $stats['en'] ?? 0,
            'horas_extra_fichado'   => $heMinFichado / 60,
            'ausencias'             => $ausencias,
            'desviacion'            => $desviacion,
            'pct_imputado'          => $pctImputado,
            'es_turno'              => $esTurno,
            'dias_turno'            => $diasTurno,
            'dias_descanso'         => $diasDescanso,
            'dias_sin_asignar'      => $diasSinAsignar,
        ];
    }

    // Paso 1/4 del flujo de aprobación: RRHH (o Dirección general/admin) firma. A partir de
    // ahí, cualquier edición de ausencias/fichaje/horarios/imputaciones de ese usuario+mes
    // muestra un aviso, queda registrada en vm_informes_ediciones_log y reinicia todo el
    // flujo (InformeAprobacionGuard) -- nunca bloquea el guardado, solo avisa y reinicia.
    public function validar(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        [$year, $month, $userId, , , $canSelectTodos] = $this->resolveParams($request, $project, $user, $isAdmin);
        if (!$canSelectTodos) {
            return response()->json(['error' => 'No tienes permiso para validar este informe.'], 403);
        }

        return $this->responderFirma($this->firmarPaso($userId, $year, $month, 'rrhh', (int) auth()->id(), $request));
    }

    // Paso 2/4: el coordinador del equipo (rol 10, Dirección de Operaciones) firma.
    public function firmarCoordinador(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);

        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;
        if (!($isAdmin || in_array((int) $authRol, [10, 3], true))) {
            return response()->json(['error' => 'Solo el coordinador (Dirección de Operaciones) o Dirección general pueden firmar este paso.'], 403);
        }

        return $this->responderFirma($this->firmarPaso($userId, $year, $month, 'coordinador', (int) auth()->id(), $request));
    }

    // Paso 3/4: el propio trabajador firma su informe (autofirma, sin rol especial).
    // Accesible desde backoffice y desde la PWA (VacationmarbellaPwaController delega aquí).
    public function firmarTrabajador(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);

        $ownerAdminUserId = DB::table('vm_usuarios')->where('id', $userId)->value('admin_user_id');
        if (!($ownerAdminUserId && (int) $ownerAdminUserId === (int) auth()->id())) {
            return response()->json(['error' => 'Solo puedes firmar tu propio informe.'], 403);
        }

        return $this->responderFirma($this->firmarPaso($userId, $year, $month, 'trabajador', (int) auth()->id(), $request));
    }

    // Paso 4/4: Dirección general (rol 3) firma y el flujo queda completo.
    public function firmarDireccion(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);

        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;
        if (!($isAdmin || (int) $authRol === 3)) {
            return response()->json(['error' => 'Solo Dirección general puede firmar este paso.'], 403);
        }

        return $this->responderFirma($this->firmarPaso($userId, $year, $month, 'direccion', (int) auth()->id(), $request));
    }

    // Traduce el resultado de firmarPaso() (que nunca lanza abort(), para que el error real
    // llegue como JSON al fetch() -- esta app renderiza abort() como página HTML incluso
    // cuando se pide Accept: application/json) a la respuesta HTTP correspondiente.
    private function responderFirma(array $result)
    {
        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], $result['status'] ?? 400);
        }
        return response()->json($result);
    }

    // Reinicia el flujo completo (las 4 firmas) al principio. Mismo gate que el paso 1
    // (RRHH/Dirección general/admin), pensado para corregir una validación errónea.
    public function anularValidacion(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        [$year, $month, $userId, , , $canSelectTodos] = $this->resolveParams($request, $project, $user, $isAdmin);
        if (!$canSelectTodos) {
            return response()->json(['error' => 'No tienes permiso para reiniciar este flujo.'], 403);
        }

        // "completado" (firmado por Dirección general) es un estado terminal: los registros ya
        // quedaron bloqueados (bloquearRegistrosDelMes) y no hay vuelta atrás desde aquí.
        $pasoActual = DB::table('vm_informes_estado')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->value('paso_actual');
        if ($pasoActual === 'completado') {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede reiniciar.'], 423);
        }

        DB::table('vm_informes_estado')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->update(['en_aprobacion' => false, 'paso_actual' => 'rrhh', 'updatedat' => now()]);

        DB::table('vm_informes_aprobaciones')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->delete();

        return response()->json(['ok' => true, 'en_aprobacion' => false, 'paso_actual' => 'rrhh']);
    }

    // Registra la firma de $step (validando que sea justo el paso en curso y que quien firma
    // tenga su firma manuscrita guardada), calcula el hash del informe en ese instante y avanza
    // al siguiente paso (o a 'completado' si era el último). Compartido por los 4 métodos de
    // firma de arriba y por VacationmarbellaPwaController::firmarInformeTrabajador().
    public function firmarPaso(int $userId, int $year, int $month, string $step, int $aprobadoPor, Request $request): array
    {
        // El paso "coordinador" (rol 10, Dirección de Operaciones) solo tiene sentido si el rol
        // del usuario está realmente supervisado por él (vm_roles.roles_supervisados, ver
        // RoleHierarchy). Si no tiene coordinador, el flujo salta directo de RRHH a trabajador.
        $rolUsuario = (int) DB::table('vm_usuarios')->where('id', $userId)->value('id_rol');
        $rolesSupervisados = array_map('intval', RoleHierarchy::subordinateRoleIds('vm_roles', 10));
        $tieneCoordinador = in_array($rolUsuario, $rolesSupervisados, true);

        $siguientePaso = [
            'rrhh'        => $tieneCoordinador ? 'coordinador' : 'trabajador',
            'coordinador' => 'trabajador',
            'trabajador'  => 'direccion',
            'direccion'   => 'completado',
        ];
        if (!isset($siguientePaso[$step])) {
            return ['error' => 'Paso de aprobación desconocido.', 'status' => 400];
        }

        $pasoActual = DB::table('vm_informes_estado')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->value('paso_actual') ?? 'rrhh';
        if ($pasoActual !== $step) {
            return ['error' => "El informe no está en el paso '{$step}' (está en '{$pasoActual}').", 'status' => 409];
        }

        $signaturePath = DB::table('admin_users')->where('id', $aprobadoPor)->value('signature_path');
        if (!$signaturePath) {
            return ['error' => 'Debes registrar tu firma en tu perfil antes de continuar.', 'status' => 422];
        }

        $hash  = $this->contentHash($userId, $year, $month);
        $ahora = now();

        DB::table('vm_informes_aprobaciones')->upsert(
            [[
                'id_usuario' => $userId, 'anio' => $year, 'mes' => $month, 'step' => $step,
                'aprobado_por' => $aprobadoPor, 'content_hash' => $hash, 'ip_address' => $request->ip(),
                'aprobado_at' => $ahora, 'createdat' => $ahora, 'updatedat' => $ahora,
            ]],
            ['id_usuario', 'anio', 'mes', 'step'],
            ['aprobado_por', 'content_hash', 'ip_address', 'aprobado_at', 'updatedat']
        );

        $nuevoPaso = $siguientePaso[$step];
        DB::table('vm_informes_estado')->upsert(
            [[
                'id_usuario' => $userId, 'anio' => $year, 'mes' => $month,
                'en_aprobacion' => true, 'paso_actual' => $nuevoPaso,
                'marcado_por' => $aprobadoPor, 'marcado_at' => $ahora,
                'createdat' => $ahora, 'updatedat' => $ahora,
            ]],
            ['id_usuario', 'anio', 'mes'],
            ['en_aprobacion', 'paso_actual', 'marcado_por', 'marcado_at', 'updatedat']
        );

        if ($nuevoPaso === 'completado') {
            $this->bloquearRegistrosDelMes($userId, $year, $month);
        }

        return ['ok' => true, 'paso_actual' => $nuevoPaso];
    }

    // Al llegar al paso terminal "completado" (firmado por Dirección general), todos los
    // registros del usuario/mes quedan con blocked=1 en las 4 tablas que alimentan el informe
    // -- de aquí no se vuelve atrás (ver el rechazo en anularValidacion() de arriba).
    private function bloquearRegistrosDelMes(int $userId, int $year, int $month): void
    {
        $desde = Carbon::create($year, $month, 1)->toDateString();
        $hasta = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        DB::table('vm_fichaje')->where('control_user', $userId)
            ->whereBetween('fecha_fichaje', [$desde, $hasta])->update(['blocked' => 1]);

        DB::table('vm_ausencias')->where('id_usuarios', $userId)
            ->where('fecha_inicio', '<=', $hasta)->where('fecha_fin', '>=', $desde)
            ->update(['blocked' => 1]);

        DB::table('vm_horarios')->where('id_usuario', $userId)
            ->whereBetween('fecha', [$desde, $hasta])->update(['blocked' => 1]);

        DB::table('vm_imputaciones')->where('id_usuario', $userId)
            ->whereBetween('fecha_imputacion', [$desde, $hasta])->update(['blocked' => 1]);
    }

    // Resumen simplificado del informe mensual para la pantalla "Mi informe" de la PWA
    // (sin la rejilla día a día, solo los totales que ya calcula getInformeData()/getYearStats()).
    public function resumenParaPwa(int $userId, int $year, int $month): array
    {
        $m = $this->metricasResumen($userId, $year, $month);
        unset($m['desviacion']);
        return $m;
    }

    // Hash determinista del contenido de negocio del informe (fichajes/ausencias/imputaciones
    // ya resueltos día a día), usado solo como evidencia adjunta a cada firma -- no bloquea nada.
    private function contentHash(int $userId, int $year, int $month): string
    {
        $data = $this->getInformeData($userId, $year, $month);

        $dias = array_map(fn($d) => [
            'fecha'     => $d['fecha'],
            'entrada'   => $d['entrada'],
            'salida'    => $d['salida'],
            'tf_min'    => $d['tf_min'],
            'p_min'     => $d['p_min'],
            'he_min'    => $d['he_min'],
            'ajuste_he' => $d['ajuste_he'],
            'ht_min'    => $d['ht_min'],
            'tipo'      => $d['tipo']->nombre ?? null,
        ], $data['dias']);

        $stats   = $data['year_stats'][$month] ?? null;
        $resumen = $stats ? [
            'ep'       => $stats['ep'],
            'en'       => $stats['en'],
            'total'    => $stats['total'],
            'dias_col' => $stats['dias_col'],
        ] : null;

        return hash('sha256', json_encode(['dias' => $dias, 'resumen' => $resumen], JSON_UNESCAPED_UNICODE));
    }

    public function pdf(Request $request, Project $project)
    {
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);

        [$year, $month, $userId] = $this->resolveParams($request, $project, $user, $isAdmin);

        return $this->buildPdf($userId, $year, $month)->download($this->pdfFilename($userId, $year, $month));
    }

    // Construye el PDF del informe (mismo documento tanto si lo pide el backoffice como la
    // PWA -- VacationmarbellaPwaController::miInformePdf() reutiliza esto para su propio informe).
    public function buildPdf(int $userId, int $year, int $month): \Barryvdh\DomPDF\PDF
    {
        $data = $this->getInformeData($userId, $year, $month);

        $hoy = date('Y-m-d');
        $contratosUsuario = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja']);
        $sinContrato = $contratosUsuario->isNotEmpty()
            && $contratosUsuario->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
            && $contratosUsuario->every(fn($c) => $c->fecha_alta <= $hoy);
        $fechaFinContrato = $sinContrato
            ? $contratosUsuario->sortByDesc('fecha_baja')->first()?->fecha_baja
            : null;

        return Pdf::loadView('informe-imputaciones-pdf', array_merge($data, [
            'year'             => $year,
            'month'            => $month,
            'sin_contrato'     => $sinContrato,
            'fecha_fin_contrato' => $fechaFinContrato,
            'aprobaciones_firmadas' => $this->aprobacionesParaPdf($userId, $year, $month),
        ]))->setPaper('a4', 'portrait');
    }

    private function pdfFilename(int $userId, int $year, int $month): string
    {
        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $nombre = str_replace(' ', '_', DB::table('vm_usuarios')->where('id', $userId)->value('nombre') ?? 'usuario');
        return "informe_{$nombre}_{$meses[$month-1]}_{$year}.pdf";
    }

    // Devuelve las 4 firmas (con la ruta absoluta de la imagen y el nombre del firmante) solo
    // si el flujo de aprobación de ese usuario+mes está completo; null en cualquier otro caso.
    private function aprobacionesParaPdf(int $userId, int $year, int $month): ?array
    {
        $pasoActual = DB::table('vm_informes_estado')
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->value('paso_actual');
        if ($pasoActual !== 'completado') return null;

        $orden = ['rrhh' => 0, 'coordinador' => 1, 'trabajador' => 2, 'direccion' => 3];
        $labels = ['rrhh' => 'RRHH', 'coordinador' => 'Coordinador', 'trabajador' => 'Trabajador', 'direccion' => 'Dirección'];

        $rows = DB::table('vm_informes_aprobaciones as a')
            ->join('admin_users as u', 'u.id', '=', 'a.aprobado_por')
            ->where('a.id_usuario', $userId)->where('a.anio', $year)->where('a.mes', $month)
            ->get(['a.step', 'a.aprobado_at', 'u.name as nombre', 'u.signature_path']);

        if ($rows->count() !== 4) return null;

        return $rows->sortBy(fn($r) => $orden[$r->step] ?? 99)->map(fn($r) => [
            'step'           => $labels[$r->step] ?? $r->step,
            'nombre'         => $r->nombre,
            'aprobado_at'    => $r->aprobado_at,
            'signature_path' => $r->signature_path ? storage_path('app/public/' . $r->signature_path) : null,
        ])->values()->all();
    }

    public function pdfTodos(Request $request, Project $project)
    {
        ini_set('memory_limit', '512M');
        $user    = auth()->user();
        $isAdmin = $user->isProjectAdmin($project);
        if (!$isAdmin) abort(403);

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        $allUsuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $hoy = date('Y-m-d');
        $pages = [];
        foreach ($allUsuarios as $u) {
            $data = $this->getInformeData($u->id, $year, $month);

            $contratosU = DB::table('vm_contratos')
                ->where('id_usuarios', $u->id)
                ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
                ->orderBy('fecha_alta')
                ->get(['fecha_alta', 'fecha_baja']);
            $sinContrato = $contratosU->isNotEmpty()
                && $contratosU->every(fn($c) => $c->fecha_baja && $c->fecha_baja <= $hoy)
                && $contratosU->every(fn($c) => $c->fecha_alta <= $hoy);
            $fechaFinContrato = $sinContrato
                ? $contratosU->sortByDesc('fecha_baja')->first()?->fecha_baja
                : null;

            $pages[] = view('informe-imputaciones-pdf', array_merge($data, [
                'year'               => $year,
                'month'              => $month,
                'sin_contrato'       => $sinContrato,
                'fecha_fin_contrato' => $fechaFinContrato,
            ]))->render();
        }

        $html = '';
        foreach ($pages as $i => $page) {
            $style = $i > 0 ? ' style="page-break-before:always"' : '';
            $html .= "<div{$style}>{$page}</div>";
        }
        $filename = "informes_{$meses[$month-1]}_{$year}.pdf";

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        return $pdf->download($filename);
    }

    // ── Lógica de datos ───────────────────────────────────────────────────────

    private function resolveParams(Request $request, Project $project, $user, bool $isAdmin): array
    {
        $currentVmUserId = $user->projectUserId($project);
        $authRol         = $currentVmUserId ? DB::table('vm_usuarios')->where('id', $currentVmUserId)->value('id_rol') : null;

        // Selección sin restricción: ven y pueden generar el PDF de todos.
        $canSelectTodos = $isAdmin || in_array((int) $authRol, [3, 11]); // Dirección general, Director RRHH
        // Selección limitada a su equipo (vm_roles.roles_supervisados), mismo mecanismo que
        // fichajes/listados (RoleHierarchy) — sin PDF de todos, solo su equipo.
        $canSelectEquipo = !$canSelectTodos && in_array((int) $authRol, [10, 5, 2]); // Dir. Operaciones, Coord. mantenimiento, Coord. limpieza
        $canSelect       = $canSelectTodos || $canSelectEquipo;

        if ($canSelectTodos) {
            $allUsuarios = DB::table('vm_usuarios')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre', 'id_rol', 'admin_user_id']);
        } elseif ($canSelectEquipo) {
            $visibleIds = RoleHierarchy::visibleUserIds(
                $project->slug . '_roles', $project->slug . '_usuarios',
                (int) $currentVmUserId, (int) $authRol
            );
            $allUsuarios = DB::table('vm_usuarios')
                ->where('deleted', 0)
                ->whereIn('id', array_map('intval', $visibleIds))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'id_rol', 'admin_user_id']);
        } else {
            $allUsuarios = collect();
        }

        if ($canSelect) {
            $userId = (int) $request->input('user_id', $currentVmUserId ?? ($allUsuarios->first()->id ?? 0));
            // Si pide un user_id fuera de su equipo (manipulando el parámetro), se cae a su propio informe.
            if ($canSelectEquipo && !$allUsuarios->contains('id', $userId)) {
                $userId = (int) $currentVmUserId;
            }
        } else {
            $userId = $currentVmUserId ?? 0;
        }

        $year  = max(2020, min(2040, (int) $request->input('year',  now()->year)));
        $month = max(1,    min(12,   (int) $request->input('month', now()->month)));

        return [$year, $month, $userId, $allUsuarios, $canSelect, $canSelectTodos];
    }


    private function getInformeData(int $userId, int $year, int $month): array
    {
        $usuario = DB::table('vm_usuarios')->where('id', $userId)->first();
        $sede    = $usuario->sede ?? '';
        $esTurno = VmHorasService::esDeptoTurno($userId);

        $mp  = str_pad($month, 2, '0', STR_PAD_LEFT);
        $ms  = "{$year}-{$mp}-01";
        $dim = (int) Carbon::parse($ms)->daysInMonth;
        $me  = "{$year}-{$mp}-{$dim}";

        $festivosDia = VmHorasService::festivosSet($sede, $ms, $me);

        // Fichajes del mes
        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereBetween('fecha_fichaje', [$ms, $me])
            ->get(['fecha_fichaje','hora_inicio','hora_fin','pausa_inicio','pausa_fin',
                   'km','ajuste_he','ajuste_he_motivo'])
            ->keyBy('fecha_fichaje');

        // Tipos de ausencia (valores fijos, sin tabla separada)
        $tiposNombres = ['Compensación','Vacaciones','Baja','Asuntos propios','Comp. festivo','Comp. horas','Absentismo'];
        $tipos = collect($tiposNombres)->mapWithKeys(fn($n) => [$n => (object)['nombre' => $n]]);

        // Ausencias del mes expandidas por día
        $ausenciasRaw = DB::table('vm_ausencias')
            ->where('id_usuarios', $userId)
            ->where('fecha_inicio', '<=', $me)
            ->where('fecha_fin',    '>=', $ms)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->get();

        $ausDia = [];
        foreach ($ausenciasRaw as $a) {
            $cur = max($a->fecha_inicio, $ms);
            $lim = min($a->fecha_fin,   $me);
            while ($cur <= $lim) {
                $ausDia[$cur] = $a;
                $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
            }
        }

        // Horas tareas por día (vm_imputaciones): suma de duracion (minutos) registrada en cada imputación
        $tareasMin = DB::table('vm_imputaciones')
            ->where('id_usuario', $userId)
            ->whereBetween('fecha_imputacion', [$ms, $me])
            ->groupBy('fecha_imputacion')
            ->select('fecha_imputacion', DB::raw('SUM(duracion) as total'))
            ->pluck('total', 'fecha_imputacion');

        // Horarios del mes (descanso, etc.)
        $horariosRaw = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->whereBetween('fecha', [$ms, $me])
            ->get(['fecha', 'tipo']);
        $horarioDia = $horariosRaw->keyBy('fecha');

        // Contratos del usuario ordenados por fecha_alta
        $contratos = DB::table('vm_contratos')
            ->where('id_usuarios', $userId)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $dowLabels = ['D','L','M','X','J','V','S'];
        $dias = [];

        for ($d = 1; $d <= $dim; $d++) {
            $fecha = "{$year}-{$mp}-" . str_pad($d, 2, '0', STR_PAD_LEFT);
            $dow   = $dowLabels[(int) date('w', strtotime($fecha))];
            $f     = $fichajes->get($fecha);
            $aus   = $ausDia[$fecha] ?? null;
            $hor   = $horarioDia->get($fecha);

            $tfMin = null;
            $pMin  = null;
            if ($f && ($f->hora_inicio ?? null) && ($f->hora_fin ?? null)) {
                $tfMin = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                if (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null)) {
                    $pMin = VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio);
                }
            }

            $tipoObj = ($aus && !empty($aus->tipo))
                ? (object)['nombre' => $aus->tipo] : null;

            $htMin = (int) ($tareasMin->get($fecha, 0));

            $contratoDia = null;
            foreach ($contratos as $c) {
                if ($c->fecha_alta <= $fecha && (is_null($c->fecha_baja) || $c->fecha_baja >= $fecha)) {
                    $contratoDia = $c;
                    break;
                }
            }

            $isFestivo      = isset($festivosDia[$fecha]);
            $isDescansoEf   = VmHorasService::esDescansoEfectivo($fecha, $hor->tipo ?? null, $esTurno);
            $isCompensacion = $tipoObj && VmHorasService::categoriaAusencia($tipoObj->nombre) === 'C';

            // Festivo trabajado y "rotatorio" ya no dependen de los checkboxes manuales
            // vm_fichaje.festivo/fuera_de_turno (sustituidos por vm_festivos y el horario real):
            // festivo trabajado = hay fichaje y el día es festivo según vm_festivos; rotatorio =
            // ese festivo trabajado coincide además con el día de descanso asignado.
            $isFestTrab  = $isFestivo && (bool) $f;
            $isRotatorio = $isFestTrab && $isDescansoEf;

            $heMin = VmHorasService::calcularHeDia(
                $tfMin, $pMin, $tipoObj?->nombre ?? null, $contratoDia,
                $isFestivo, $isFestTrab, (bool) $f,
                $isDescansoEf,
                (int) ($f?->ajuste_he ?? 0),
                $esTurno
            );
            $pausaResaltada = $contratoDia && $pMin !== null
                && VmHorasService::pausaDeducible($pMin, (float) $contratoDia->horas_semana) > 0;

            $dias[] = [
                'num'             => $d,
                'dow'             => $dow,
                'fecha'           => $fecha,
                'entrada'         => $f ? substr($f->hora_inicio ?? '', 0, 5) : null,
                'salida'          => ($f && ($f->hora_fin ?? null)) ? substr($f->hora_fin, 0, 5) : null,
                'tf_min'          => $tfMin,
                'p_min'           => $pMin,
                'he_min'          => $heMin,
                'ajuste_he'       => (int) ($f?->ajuste_he ?? 0),
                'ht_min'          => $htMin,
                'km'              => $f ? (float) ($f->km ?? 0) : null,
                'tipo'            => $tipoObj,
                'aus'             => $aus,
                'weekend'         => in_array($dow, ['D', 'S']),
                'is_rotatorio'    => $isRotatorio,
                'is_fest_trab'    => $isFestTrab,
                'is_festivo'      => $isFestivo,
                'pausa_resaltada' => $pausaResaltada,
                'horario_tipo'    => $hor ? $hor->tipo : null,
                'es_descanso_efectivo' => $isDescansoEf,
            ];
        }

        $histExtras    = VmHorasService::saldoAcumuladoHoras($userId, $me);
        $yearStats     = $this->getYearStats($userId, $year, $month, $tipos, $contratos, $sede);
        $saldoPrevYear = VmHorasService::saldoAcumuladoHoras($userId, ($year - 1) . '-12-31');
        $festivosCompensados = $this->getFestivosCompensados($userId, $year, $sede);

        return [
            'usuario'          => $usuario,
            'es_turno'         => $esTurno,
            'dias'             => $dias,
            'ajustes_anio'     => DB::table('vm_fichaje')
                ->where('control_user', $userId)
                ->where('deleted', 0)
                ->where('ajuste_he', '!=', 0)
                ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
                ->orderBy('fecha_fichaje')
                ->get(['id', 'fecha_fichaje', 'ajuste_he', 'ajuste_he_motivo']),
            'tipos'            => $tipos,
            'dim'              => $dim,
            'year_stats'       => $yearStats,
            'hist_extras'      => $histExtras['total'],
            'hist_extras_dias_fest'   => $histExtras['dias_fest'],
            'hist_extras_horas_resto' => $histExtras['horas_resto'],
            'saldo_prev_year'  => $saldoPrevYear['total'],
            'is_liquidado'     => false,
            'liquidado_fecha'  => null,
            'fecha_horas_extra'=> null,
            'festivos_compensados' => $festivosCompensados,
        ];
    }

    // Lista, en orden cronológico, los festivos trabajados del año emparejados posicionalmente
    // (1º con 1º, 2º con 2º...) con los días de "Comp. festivo" tomados ese mismo año. No hay
    // vínculo real festivo↔compensación en la base de datos, así que un festivo puede quedar sin
    // compensación a la derecha (aún no descansado) o una compensación sin festivo a la izquierda
    // (sobrante) si los conteos no coinciden.
    private function getFestivosCompensados(int $userId, int $year, string $sede): array
    {
        $desde = "{$year}-01-01";
        $hasta = "{$year}-12-31";

        $festivosYear = VmHorasService::festivosSet($sede, $desde, $hasta);

        $festivosTrabajados = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_fin')
            ->whereIn('fecha_fichaje', array_keys($festivosYear))
            ->orderBy('fecha_fichaje')
            ->pluck('fecha_fichaje')
            ->map(fn($d) => (string) $d)
            ->values()
            ->all();

        $compensaciones = DB::table('vm_ausencias')
            ->where('id_usuarios', $userId)
            ->where('tipo', 'Comp. festivo')
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_inicio')
            ->get(['fecha_inicio', 'fecha_fin']);

        $diasCompensacion = [];
        foreach ($compensaciones as $a) {
            $cur = max($a->fecha_inicio, $desde);
            $lim = min($a->fecha_fin, $hasta);
            while ($cur <= $lim) {
                $diasCompensacion[] = $cur;
                $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
            }
        }
        sort($diasCompensacion);

        $total = max(count($festivosTrabajados), count($diasCompensacion));
        $filas = [];
        for ($i = 0; $i < $total; $i++) {
            $filas[] = [
                'compensacion' => $diasCompensacion[$i] ?? null,
                'festivo'      => $festivosTrabajados[$i] ?? null,
            ];
        }
        return $filas;
    }

    private function getYearStats(int $userId, int $year, int $hastaMs, $tipos, $contratos, string $sede = ''): array
    {
        $labels = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        $esTurno = VmHorasService::esDeptoTurno($userId);

        $fichajesYear = DB::table('vm_fichaje')
            ->where('control_user', $userId)
            ->where('deleted', 0)
            ->whereNotNull('hora_inicio')
            ->whereBetween('fecha_fichaje', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin',
                   'pausa_inicio', 'pausa_fin', 'ajuste_he'])
            ->groupBy(fn($f) => (int) substr($f->fecha_fichaje, 5, 2));

        $festivosYear = VmHorasService::festivosSet($sede, "{$year}-01-01", "{$year}-12-31");

        $descansosDias = DB::table('vm_horarios')
            ->where('id_usuario', $userId)
            ->where('tipo', 'descanso')
            ->whereBetween('fecha', ["{$year}-01-01", "{$year}-12-31"])
            ->pluck('fecha')->flip()->all();

        $esDescanso = fn(string $fecha) => $esTurno
            ? isset($descansosDias[$fecha])
            : ((int) date('N', strtotime($fecha)) >= 6); // 6=sábado, 7=domingo

        $stats = [];
        for ($m = 1; $m <= $hastaMs; $m++) {
            $mp = str_pad($m, 2, '0', STR_PAD_LEFT);
            $ms = "{$year}-{$mp}-01";
            $me = "{$year}-{$mp}-" . date('t', strtotime($ms));

            $ep      = 0.0;
            $en      = 0.0;
            $tCount  = 0;
            $fichajesFechas = [];

            foreach (($fichajesYear[$m] ?? []) as $f) {
                $hasFin = !empty($f->hora_fin);
                $isFestivo    = isset($festivosYear[$f->fecha_fichaje]);
                // Festivo trabajado = vm_festivos, ya no depende de vm_fichaje.festivo.
                $isFest = $isFestivo;
                $isDescansoEf = $esDescanso($f->fecha_fichaje);
                $fichajesFechas[$f->fecha_fichaje] = true;

                $contratoDia = null;
                foreach ($contratos as $c) {
                    if ($c->fecha_alta <= $f->fecha_fichaje && (is_null($c->fecha_baja) || $c->fecha_baja >= $f->fecha_fichaje)) {
                        $contratoDia = $c;
                        break;
                    }
                }

                $tCount++;

                if ($contratoDia && $contratoDia->horas_semana) {
                    $esperadoMin = (int) round(($contratoDia->horas_semana / 5) * 60);
                    if ($hasFin) {
                        $tf   = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
                        $pMin = (($f->pausa_inicio ?? null) && ($f->pausa_fin ?? null))
                            ? VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio)
                            : null;
                        $ded  = VmHorasService::pausaDeducible($pMin, (float) $contratoDia->horas_semana);
                        // Festivo trabajado: la extra es siempre la jornada diaria del contrato,
                        // no el tiempo realmente fichado ese día (mismo criterio que calcularHeDia()).
                        $he   = $isFest ? $esperadoMin : $tf - $esperadoMin - $ded;
                    } else {
                        continue;
                    }
                    // Bono solo si el festivo/descanso no se ha trabajado ya (si no, ya cuenta
                    // arriba) -- y las horas de contrato del día, no un fijo de 8h para todos.
                    if (($isFestivo || $isDescansoEf) && !$isFest) $he += $esperadoMin;
                    $ajMin = (int) ($f->ajuste_he ?? 0);
                    $he += $ajMin;
                    if ($he > 0) $ep += $he;
                    else         $en += $he;
                }
            }

            // Bono festivo por descanso en festivo sin fichaje -- solo departamentos con horario
            // visible (ver misma nota en VmHorasService::saldoAcumuladoHoras()).
            if ($esTurno) {
                foreach ($festivosYear as $fDate => $_) {
                    if ($fDate < $ms || $fDate > $me) continue;
                    if (isset($fichajesFechas[$fDate])) continue; // ya contado
                    if (!$esDescanso($fDate)) continue;
                    foreach ($contratos as $c) {
                        if ($c->fecha_alta <= $fDate && (is_null($c->fecha_baja) || $c->fecha_baja >= $fDate)) {
                            $ep += (int) round(($c->horas_semana / 5) * 60);
                            break;
                        }
                    }
                }
            }

            $diasCol = ['T' => $tCount, 'C' => 0, 'V' => 0, 'B' => 0, 'AA' => 0];

            $ausRaw = DB::table('vm_ausencias')
                ->where('id_usuarios', $userId)
                ->where('fecha_inicio', '<=', $me)
                ->where('fecha_fin',    '>=', $ms)
                ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
                ->get(['fecha_inicio', 'fecha_fin', 'tipo']);

            foreach ($ausRaw as $a) {
                $nombreTipo = $a->tipo ?? '';
                $cat        = VmHorasService::categoriaAusencia($nombreTipo);
                if (!array_key_exists($cat, $diasCol)) continue;
                $cur = max($a->fecha_inicio, $ms);
                $lim = min($a->fecha_fin,   $me);
                while ($cur <= $lim) {
                    $diasCol[$cat]++;
                    if ($cat === 'C') {
                        foreach ($contratos as $c) {
                            if ($c->fecha_alta <= $cur && (is_null($c->fecha_baja) || $c->fecha_baja >= $cur)) {
                                $en -= (int) round(($c->horas_semana / 5) * 60);
                                break;
                            }
                        }
                    }
                    $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
                }
            }

            // Días laborables (L-V, sin festivos — no hay tabla vm_festivos)
            $lab    = 0;
            $curLab = new \DateTime($ms);
            $endLab = new \DateTime($me);
            while ($curLab <= $endLab) {
                if ((int) $curLab->format('N') <= 5) $lab++;
                $curLab->modify('+1 day');
            }

            $hasAjuste = collect($fichajesYear[$m] ?? [])->contains(fn($f) => (int)($f->ajuste_he ?? 0) !== 0);
            $stats[$m] = [
                'label'      => $labels[$m - 1],
                'ep'         => $ep / 60,
                'en'         => $en / 60,
                'total'      => ($ep + $en) / 60,
                'has_ajuste' => $hasAjuste,
                'dias_col'   => $diasCol,
                'total_dias' => array_sum($diasCol),
                'lab'        => $lab,
            ];
        }

        return $stats;
    }

    // ── Helpers de formato (vista) ────────────────────────────────────────────

    public static function fmtMin(?int $min, bool $sign = false): string
    {
        if ($min === null || $min === 0) return '';
        $neg = $min < 0;
        $abs = abs($min);
        $h   = (int) floor($abs / 60);
        $m   = $abs % 60;
        $s   = $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
        return ($neg ? '-' : ($sign ? '+' : '')) . $s;
    }

    public static function fmtHoras($h, bool $sign = false): string
    {
        if ($h === null || $h == 0) return '';
        $neg  = $h < 0;
        $abs  = abs((float) $h);
        $hrs  = (int) floor($abs);
        $mins = (int) round(($abs - $hrs) * 60);
        $s    = $hrs . 'h ' . str_pad($mins, 2, '0', STR_PAD_LEFT) . 'm';
        return ($neg ? '-' : ($sign ? '+' : '')) . $s;
    }
}
