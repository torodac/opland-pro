<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\InformeAprobacionGuard;
use App\Services\RoleHierarchy;
use App\Services\VmAusenciaTipos;
use App\Services\VmFichajePermisos;
use App\Services\VmHorasService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const BOOKING_STATUS_CANCELADO = ['cancelled', 'canceled'];

    public function validarConciliacion(Request $request, Project $project)
    {
        $idUsuario = (int) $request->id_usuario;
        $tipo      = $request->tipo;
        $fecha     = $request->fecha;
        $tipoAus   = VmAusenciaTipos::labelHorario($tipo);

        $dias = DB::table('vm_horarios as h')
            ->where('h.id_usuario', $idUsuario)
            ->where('h.tipo', $tipo)
            ->whereNotExists(function ($q) {
                $q->from('vm_ausencias as a')
                    ->whereColumn('a.id_usuarios', 'h.id_usuario')
                    ->whereColumn('a.fecha_inicio', '<=', 'h.fecha')
                    ->whereColumn('a.fecha_fin', '>=', 'h.fecha')
                    ->where('a.deleted', 0);
            })
            ->orderBy('h.fecha')
            ->pluck('h.fecha')
            ->map(fn($d) => Carbon::parse($d));

        if ($dias->isEmpty()) {
            return response()->json(['ok' => false, 'msg' => 'Sin días pendientes']);
        }

        $grupos  = [];
        $current = [];
        foreach ($dias as $d) {
            if (empty($current) || $d->diffInDays(end($current)) <= 1) {
                $current[] = $d;
            } else {
                $grupos[] = $current;
                $current  = [$d];
            }
        }
        $grupos[] = $current;

        $target = Carbon::parse($fecha);
        $grupo  = collect($grupos)->first(fn($g) =>
            $target->between(reset($g), end($g))
        ) ?? reset($grupos);

        $fechaIni = reset($grupo)->toDateString();
        $fechaFin = end($grupo)->toDateString();
        $usuario  = DB::table('vm_usuarios')->where('id', $idUsuario)->value('nombre');

        if (InformeAprobacionGuard::estaCompletado($idUsuario, $fechaIni)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion($idUsuario, $fechaIni)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $ausId = DB::table('vm_ausencias')->insertGetId([
            'nombre'       => Carbon::parse($fechaIni)->format('Y.m.d') . '_' . $usuario,
            'id_usuarios'  => $idUsuario,
            'tipo'         => $tipoAus,
            'fecha_inicio' => $fechaIni,
            'fecha_fin'    => $fechaFin,
            'anyo_devengo' => Carbon::parse($fechaIni)->year,
            'deleted'      => 0,
            'createdat'    => now(),
            'updatedat'    => now(),
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog($idUsuario, $fechaIni, 'vm_ausencias', 'insert', $ausId, $request);

        return response()->json(['ok' => true, 'fecha_inicio' => $fechaIni, 'fecha_fin' => $fechaFin, 'aviso_aprobacion' => $aviso]);
    }

    public function validarFichaje(Request $request, Project $project)
    {
        $fichajeId = (int) $request->id;
        $user = $this->vmUsuarioActual();
        $fichaje = DB::table('vm_fichaje')->where('id', $fichajeId)->first(['control_user', 'fecha_fichaje']);

        if ($fichaje) {
            if (InformeAprobacionGuard::estaCompletado((int) $fichaje->control_user, $fichaje->fecha_fichaje)) {
                return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
            }
            if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $fichaje->control_user, $fichaje->fecha_fichaje)) {
                return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
            }
        }

        DB::table('vm_fichaje')->where('id', $fichajeId)->update([
            'validado'     => true,
            'validado_por' => $user->id ?? null,
        ]);

        $aviso = $fichaje
            ? InformeAprobacionGuard::checkAndLog((int) $fichaje->control_user, $fichaje->fecha_fichaje, 'vm_fichaje', 'update', $fichajeId, $request)
            : null;

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function validarTarea(Request $request, Project $project)
    {
        $tipo  = $request->tipo === 'mantenimiento' ? 'mantenimiento' : 'limpieza';
        $tabla = 'vm_tareas_' . $tipo;
        $id    = (int) $request->id;
        $user  = $this->vmUsuarioActual();

        DB::table($tabla)->where('id', $id)->update([
            'validado'     => true,
            'validado_por' => $user->id ?? null,
        ]);
        return response()->json(['ok' => true]);
    }

    // Botón "Hecho" del recordatorio SSCC: la marca como propia (control_user = yo) y la
    // pasa a Finalizada, sin tocar imputaciones/fichaje -- no repercute en el informe mensual.
    public function marcarSsccHecho(Request $request, Project $project, int $id)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) {
            return response()->json(['error' => 'No se pudo identificar tu usuario en este proyecto.'], 403);
        }

        $updated = DB::table('vm_tareas_sscc')
            ->where('id', $id)
            ->where('deleted', 0)
            ->update([
                'control_user' => json_encode([$user->id]),
                'estado'       => 'Finalizada',
                'updateuser'   => auth()->id(),
                'updatedat'    => now(),
            ]);
        if (!$updated) {
            return response()->json(['error' => 'Tarea no encontrada.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function index(Request $request, Project $project)
    {
        $hoy    = Carbon::today()->toDateString();

        // ── Conciliaciones horario ↔ ausencias ──────────────────────────────
        // Detecta el horario especial SIN ninguna ausencia detrás. El caso complementario -- que
        // la ausencia exista pero sea de otro tipo -- lo cubre "Incidencias en el registro de
        // jornadas y ausencias", porque este whereNotExists solo mira si hay algo registrado, no
        // si dice lo mismo que el cuadrante.
        $conciliaciones = DB::table('vm_horarios as h')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'h.id_usuario')
                ->where('u.deleted', 0)
            )
            ->whereNotIn('h.tipo', ['turno', 'descanso'])
            ->where('h.fecha', '<', $hoy)
            ->whereNotExists(function ($q) {
                $q->from('vm_ausencias as a')
                    ->whereColumn('a.id_usuarios', 'h.id_usuario')
                    ->whereColumn('a.fecha_inicio', '<=', 'h.fecha')
                    ->whereColumn('a.fecha_fin', '>=', 'h.fecha')
                    ->where('a.deleted', 0);
            })
            ->orderByDesc('h.fecha')
            ->limit(50)
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'h.fecha', 'h.tipo']);

        // ── Tareas limpieza completadas sin imputación ───────────────────────
        // Sustituye al antiguo criterio "vencida" (fecha pasada + tiempo vacio): ahora es
        // estado=Completada (asignado por Breezeway o a mano) sin ninguna imputacion registrada.
        $allUsuarios = DB::table('vm_usuarios')->where('deleted', 0)->pluck('nombre', 'id');
        $sinImputacion = fn($tipo) => fn($q) => $q->from('vm_imputaciones as i')
            ->where('i.tipo', $tipo)
            ->whereColumn('i.id_tarea', 't.id');

        $resolverResponsables = function ($t) use ($allUsuarios) {
            $ids = json_decode($t->control_user ?? '[]', true) ?? [];
            $t->responsables = collect($ids)->map(fn($id) => $allUsuarios[$id] ?? "#{$id}")->values();
            return $t;
        };

        $tareasLimpieza = DB::table('vm_tareas_limpieza as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.estado', 'Completada')
            ->where(fn($q) => $q->whereNull('t.validado')->orWhere('t.validado', false))
            ->whereNull('t.usuario_breezeway_ausente')
            ->whereNotExists($sinImputacion('limpieza'))
            ->orderByDesc('t.fecha_planificada')
            ->limit(50)
            ->get(['t.id', 't.nombre', 't.control_user', 't.fecha_planificada', 'p.nombre as propiedad'])
            ->map($resolverResponsables);

        // ── Tareas mantenimiento completadas sin imputación (piscinas ya no aplica aqui) ──
        $tareasMantPisc = DB::table('vm_tareas_mantenimiento as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.estado', 'Completada')
            ->where(fn($q) => $q->whereNull('t.validado')->orWhere('t.validado', false))
            ->whereNull('t.usuario_breezeway_ausente')
            ->whereNotExists($sinImputacion('mantenimiento'))
            ->orderByDesc('t.fecha_planificada')
            ->limit(50)
            ->get(['t.id', 't.nombre', 't.control_user', 't.fecha_planificada', 'p.nombre as propiedad'])
            ->map($resolverResponsables);

        // ── Personas de Breezeway sin usuario mapeado en Opland ──────────────
        $breezewayPendientes = DB::table('vm_breezeway_pendientes')
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->get(['nombre', 'breezeway_id', 'fecha_alta', 'num_tareas']);

        // ── Fichaje vs imputaciones (diff > 30 min) ──────────────────────────
        // Mismo cálculo que el contador de pendientes del panel de aprobaciones
        // (InformeImputacionesController::pendientesValidacionDashboard): los dos salen del
        // helper para que no puedan divergir.
        // Por fecha (más reciente primero), igual que el resto de bloques del dashboard: lo
        // accionable es lo de estos días, no la desviación más grande de hace meses.
        $desviaciones = VmHorasService::desviacionesFichajeImputacion()
            ->sortByDesc('fecha')->values()->take(50);

        // ── Incidencias de fichaje ───────────────────────────────────────────
        // Un solo bloque con las tres casuísticas de un día problemático, porque comparten grano
        // (usuario + fecha), acciones y destinatario. Son excluyentes entre sí por construcción:
        // la primera exige que NO haya fichaje ese día y las otras dos exigen que sí lo haya.
        //
        // Los dos casos que salen de vm_horarios se limitan a los departamentos que el
        // planificador muestra (visible_horarios), como hace HorarioController: no tiene sentido
        // reclamar un turno contra un cuadrante que nadie ve ni puede editar. Deja fuera a quien
        // no tiene departamento (Selian S.L., Ugo), que arrastra filas de horario heredadas.
        // El caso 3 no depende del cuadrante, así que no lleva este filtro.
        $soloDeptoConHorario = fn($j) => $j
            ->whereColumn('dp.id', 'u.id_departamento')
            ->where('dp.visible_horarios', true)
            ->where('dp.deleted', 0);

        // Caso 1: turno planificado sin ningún fichaje
        $rawSinFichaje = DB::table('vm_horarios as h')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'h.id_usuario')
                ->where('u.deleted', 0)
            )
            ->join('vm_departamentos as dp', $soloDeptoConHorario)
            ->where('h.tipo', 'turno')
            ->where('h.fecha', '<', $hoy)
            ->whereNotExists(function ($q) {
                $q->from('vm_fichaje as f')
                    ->whereColumn('f.control_user', 'u.id')
                    ->whereColumn('f.fecha_fichaje', 'h.fecha')
                    ->where('f.deleted', 0);
            })
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'h.fecha']);

        // Caso 2: fichaje + horario descanso
        $rawDescanso = DB::table('vm_fichaje as f')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'f.control_user')
                ->where('u.deleted', 0)
            )
            ->join('vm_departamentos as dp', $soloDeptoConHorario)
            ->join('vm_horarios as h', fn($j) => $j
                ->whereColumn('h.id_usuario', 'u.id')
                ->whereColumn('h.fecha', 'f.fecha_fichaje')
                ->where('h.tipo', 'descanso')
            )
            ->where('f.deleted', 0)
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'f.fecha_fichaje as fecha', 'f.id as fichaje_id']);

        // Caso 3: fichaje + ausencia
        $rawAusencia = DB::table('vm_fichaje as f')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'f.control_user')
                ->where('u.deleted', 0)
            )
            ->join('vm_ausencias as a', fn($j) => $j
                ->whereColumn('a.id_usuarios', 'u.id')
                ->whereColumn('a.fecha_inicio', '<=', 'f.fecha_fichaje')
                ->whereColumn('a.fecha_fin', '>=', 'f.fecha_fichaje')
                ->where('a.deleted', 0)
            )
            ->where('f.deleted', 0)
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'f.fecha_fichaje as fecha', 'f.id as fichaje_id', 'a.id as ausencia_id', 'a.tipo as ausencia_tipo']);

        // ── Incidencias de ausencias en Horario (bloque de RRHH) ─────────────
        // El cuadrante dice una cosa y la ausencia registrada dice otra. Va en su propio bloque,
        // y no junto a las incidencias de fichaje, porque quien lo corrige es RRHH: son las dos
        // fuentes de la ausencia contradiciéndose, no un problema de jornada.
        // Distinto de "Ausencias en Horario no registradas por RRHH", que busca horarios
        // especiales SIN ninguna ausencia detrás: aquí la ausencia existe, pero es de otro tipo
        // (caso real: Saida Mounssi del 03 al 05/08/2026, horario "baja" contra "Comp. festivo").
        $rawHorarioDistinto = DB::table('vm_horarios as h')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'h.id_usuario')
                ->where('u.deleted', 0)
            )
            ->join('vm_departamentos as dp', $soloDeptoConHorario)
            ->join('vm_ausencias as a', fn($j) => $j
                ->whereColumn('a.id_usuarios', 'u.id')
                ->whereColumn('a.fecha_inicio', '<=', 'h.fecha')
                ->whereColumn('a.fecha_fin', '>=', 'h.fecha')
                ->where('a.deleted', 0)
            )
            ->whereNotIn('h.tipo', ['turno', 'descanso'])
            ->where('h.fecha', '<', $hoy)
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'h.fecha', 'h.tipo as horario_tipo',
                   'a.id as ausencia_id', 'a.tipo as ausencia_tipo']);

        // Agrupar por (id_usuario, fecha). Cada caso deja su propia marca en la fila; la vista las
        // enumera en la columna Casuística y decide con ellas qué botones ofrece.
        $incidenciasMap = [];
        $nuevaFila = fn($r, $fichajeId) => [
            'id_usuario'        => $r->id_usuario,
            'usuario'           => $r->usuario,
            'fecha'             => $r->fecha,
            'fichaje_id'        => $fichajeId,
            'sin_fichaje'       => false,
            'descanso'          => false,
        ];
        foreach ($rawSinFichaje as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            $incidenciasMap[$key] ??= $nuevaFila($r, null);
            $incidenciasMap[$key]['sin_fichaje'] = true;
        }
        foreach ($rawDescanso as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            $incidenciasMap[$key] ??= $nuevaFila($r, $r->fichaje_id);
            $incidenciasMap[$key]['descanso'] = true;
        }
        $incidenciasFichaje = collect(array_values($incidenciasMap))
            ->sortByDesc('fecha')->values()->take(50);

        // ── Bloque de RRHH ───────────────────────────────────────────────────
        // Los dos casos en los que el problema está en la ausencia y no en la jornada: se fichó un
        // día que tiene ausencia registrada, o el cuadrante y la ausencia se contradicen. Los dos
        // los corrige RRHH, así que van juntos y separados de las incidencias de fichaje.
        $ausenciasMap = [];
        $nuevaFilaAus = fn($r, $fichajeId) => [
            'id_usuario'       => $r->id_usuario,
            'usuario'          => $r->usuario,
            'fecha'            => $r->fecha,
            'fichaje_id'       => $fichajeId,
            'ausencias'        => [],
            'horario_distinto' => null,   // ['horario' => tipo del cuadrante, 'ausencia' => tipo registrado]
        ];
        foreach ($rawAusencia as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            $ausenciasMap[$key] ??= $nuevaFilaAus($r, $r->fichaje_id);
            $ausenciasMap[$key]['ausencias'][] = ['id' => $r->ausencia_id, 'tipo' => $r->ausencia_tipo];
        }
        foreach ($rawHorarioDistinto as $r) {
            if (VmAusenciaTipos::coincideConHorario($r->horario_tipo, $r->ausencia_tipo)) continue;

            $key = $r->id_usuario . '_' . $r->fecha;
            $ausenciasMap[$key] ??= $nuevaFilaAus($r, null);
            $ausenciasMap[$key]['horario_distinto'] = [
                'horario'  => VmAusenciaTipos::labelHorario($r->horario_tipo),
                'ausencia' => $r->ausencia_tipo,
            ];
            // Para que el botón "Ausencia" abra la que provoca el conflicto. Si ya venía por el
            // otro caso, no se duplica.
            if (!collect($ausenciasMap[$key]['ausencias'])->contains('id', $r->ausencia_id)) {
                $ausenciasMap[$key]['ausencias'][] = ['id' => $r->ausencia_id, 'tipo' => $r->ausencia_tipo];
            }
        }

        $incidenciasAusencias = collect(array_values($ausenciasMap))
            ->sortByDesc('fecha')->values()->take(50);

        // ── Conflictos de ausencias: dos o más ausencias el mismo día ─────────
        // El alta de ausencias ya valida solapes (AusenciaController::validarFechas), así que
        // estos entran por la ficha genérica, por importación o son anteriores a esa validación.
        // Se expanden a días solo las ausencias que solapan con otra, para no recorrer día a día
        // todo el histórico de ausencias.
        $rawAusConflicto = DB::select("
            select u.id as id_usuario, u.nombre as usuario, d.dia::date as fecha,
                   a.id as ausencia_id, a.tipo as ausencia_tipo
            from vm_ausencias a
            join vm_usuarios u on u.id = a.id_usuarios and u.deleted = 0
            cross join lateral generate_series(a.fecha_inicio, a.fecha_fin, interval '1 day') d(dia)
            where a.deleted = 0
              and exists (
                  select 1 from vm_ausencias b
                  where b.id_usuarios = a.id_usuarios and b.id <> a.id and b.deleted = 0
                    and b.fecha_inicio <= a.fecha_fin and b.fecha_fin >= a.fecha_inicio
              )
        ");

        $ausConflictoMap = [];
        foreach ($rawAusConflicto as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            if (!isset($ausConflictoMap[$key])) {
                $ausConflictoMap[$key] = ['id_usuario' => $r->id_usuario, 'usuario' => $r->usuario, 'fecha' => $r->fecha, 'ausencias' => []];
            }
            $ausConflictoMap[$key]['ausencias'][] = ['id' => $r->ausencia_id, 'tipo' => $r->ausencia_tipo];
        }

        // Dos ausencias que solapan solo coinciden en los días de la intersección: fuera de ella
        // cada día tiene una sola, y ese día no es un conflicto.
        $conflictosAusencias = collect(array_values($ausConflictoMap))
            ->filter(fn($c) => count($c['ausencias']) > 1)
            ->sortByDesc('fecha')->values()->take(50);

        // vm_usuarios del usuario web autenticado (para widget de fichaje)
        $vmUsuario = DB::table('vm_usuarios')
            ->where('admin_user_id', auth()->id())
            ->first(['id', 'nombre', 'id_rol', 'id_departamento']);

        // Alta de fichaje desde "Turno sin fichaje": la modal necesita los empleados visibles, y
        // el botón solo se ofrece si ese día aún se puede fichar (Dirección/RRHH no tienen ese
        // límite). Misma fuente de verdad que FichajeController, ver VmFichajePermisos.
        $visiblesFichaje   = VmFichajePermisos::usuariosVisibles($project);
        $usuariosFichaje   = DB::table('vm_usuarios')->where('deleted', 0)
            ->when($visiblesFichaje !== null, fn($q) => $q->whereIn('id', $visiblesFichaje))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $puedeFicharSinLimite = VmFichajePermisos::puedeSinLimiteFecha($project);
        $fechaMinimaFichaje   = VmFichajePermisos::fechaMinima();

        // Visibilidad por rol
        $rolId = (int) ($vmUsuario->id_rol ?? 0);
        $isAdmin = auth()->user()->isProjectAdmin($project);
        $verReservas    = $isAdmin || in_array($rolId, [3, 10, 5, 2]);   // Dir.gral, Dir.Op, Coord.mant, Coord.limp
        $verRRHH        = $isAdmin || in_array($rolId, [10, 5, 2, 11]);  // Dir.Op, Coord.mant, Coord.limp, Dir.RRHH
        $verAusenciasSin= $isAdmin || $rolId === 11;                     // Dir.RRHH
        $verLimpSinImp  = $isAdmin || in_array($rolId, [10, 2]);         // Dir.Op, Coord.limp
        $verMantSinImp  = $isAdmin || in_array($rolId, [10, 5]);         // Dir.Op, Coord.mant
        // "Fichaje vs imputaciones" es cosa de quien gestiona la imputación por tarea, no de RRHH:
        // mismos roles que $verRRHH pero sin Dir.RRHH (11).
        $verFichajeVsImput = $isAdmin || in_array($rolId, [10, 5, 2]);   // Dir.Op, Coord.mant, Coord.limp
        // Las incidencias de fichaje contra el cuadrante las corrige Operaciones; las de ausencia
        // contra el cuadrante, RRHH ($verAusenciasSin). Por eso van en dos bloques distintos.
        $verIncidenciasFichaje = $verFichajeVsImput;
        $verInformesPendientes = $isAdmin || in_array($rolId, [11, 3, 10]); // Dir.RRHH, Dir.gral (todos) / Dir.Op (su equipo)

        // ── Próximas ausencias del usuario actual ────────────────────────────
        $proximasAusencias = $vmUsuario
            ? DB::table('vm_ausencias')
                ->where('id_usuarios', $vmUsuario->id)
                ->where('deleted', 0)
                ->where('fecha_fin', '>=', $hoy)
                ->orderBy('fecha_inicio')
                ->limit(10)
                ->get(['id', 'tipo', 'fecha_inicio', 'fecha_fin', 'comentario'])
            : collect();

        // ── Recordatorios SSCC asignados a mí, mi rol o mi departamento ──────
        // vm_tareas_sscc no repercute en el informe mensual (no toca imputaciones/fichaje/
        // ausencias/horarios) -- son simples recordatorios visibles a quien corresponda.
        $recordatoriosSscc = collect();
        if ($vmUsuario) {
            $recordatoriosSscc = DB::table('vm_tareas_sscc')
                ->where('deleted', 0)
                ->where('hidden', 0)
                ->whereNotIn('estado', ['Finalizada', 'Completada', 'Cancelada', 'Descartada'])
                ->orderBy('fecha_planificada')
                ->get(['id', 'nombre', 'fecha_planificada', 'control_user', 'id_rol', 'id_departamento'])
                ->filter(function ($t) use ($vmUsuario) {
                    $ids = json_decode($t->control_user ?? '[]', true) ?? [];
                    if (in_array($vmUsuario->id, $ids)) return true;
                    if ($t->id_rol && (int) $t->id_rol === (int) $vmUsuario->id_rol) return true;
                    if ($t->id_departamento && $vmUsuario->id_departamento && (int) $t->id_departamento === (int) $vmUsuario->id_departamento) return true;
                    return false;
                })
                ->values();
        }

        // ── Informes mensuales pendientes de alguna firma ────────────────────
        // "Pendiente de firma" = flujo iniciado (en_aprobacion=true) y todavía no completado
        // (paso_actual != 'completado'; un reinicio por edición vuelve a 'rrhh' con
        // en_aprobacion=false -- ver InformeAprobacionGuard -- y esos no cuentan como pendientes).
        // Visibilidad: Dir.RRHH y Dir.gral ven todos; Dir.Op solo los de su equipo supervisado
        // (vm_roles.roles_supervisados a partir de su rol, mismo mecanismo que el paso
        // "coordinador" del propio flujo -- ver RoleHierarchy).
        $informesPendientes = collect();
        if ($verInformesPendientes) {
            $query = DB::table('vm_informes_estado as e')
                ->join('vm_usuarios as u', 'u.id', '=', 'e.id_usuario')
                ->where('e.en_aprobacion', true)
                ->where('e.paso_actual', '!=', 'completado');

            if (!$isAdmin && $rolId === 10) {
                $rolesEquipo = array_map('intval', RoleHierarchy::subordinateRoleIds('vm_roles', 10));
                $query->whereIn('u.id_rol', $rolesEquipo ?: [-1]);
            }

            $informesPendientes = $query
                ->orderBy('e.anio')->orderBy('e.mes')->orderBy('e.marcado_at')
                ->get(['u.id as id_usuario', 'u.nombre as usuario', 'e.anio', 'e.mes', 'e.paso_actual', 'e.marcado_at']);
        }

        return view('dashboard', compact(
            'project',
            'conciliaciones',
            'tareasLimpieza', 'tareasMantPisc', 'breezewayPendientes',
            'incidenciasFichaje', 'incidenciasAusencias', 'desviaciones', 'recordatoriosSscc',
            'conflictosAusencias', 'informesPendientes',
            'usuariosFichaje', 'puedeFicharSinLimite', 'fechaMinimaFichaje',
            'vmUsuario', 'proximasAusencias',
            'verReservas', 'verRRHH', 'verAusenciasSin', 'verLimpSinImp', 'verMantSinImp',
            'verFichajeVsImput', 'verIncidenciasFichaje',
            'verInformesPendientes'
        ));

    }

    // ── Widget "Flujo semanal y carga de limpieza" ───────────────────────────

    private function puedeVerCargaSemanal(Project $project): bool
    {
        $rolId = (int) (DB::table('vm_usuarios')->where('admin_user_id', auth()->id())->value('id_rol') ?? 0);
        return auth()->user()->isProjectAdmin($project) || in_array($rolId, [3, 10, 5, 2]);
    }

    public function cargaSemanal(Request $request, Project $project)
    {
        abort_unless($this->puedeVerCargaSemanal($project), 403);

        $offset    = (int) $request->input('offset', 0);
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($offset);
        $desde     = $weekStart->toDateString();
        $hasta     = $weekStart->copy()->addDays(6)->toDateString();

        $checkins = DB::table('vm_reservas')
            ->whereBetween('check_in_date', [$desde, $hasta])
            ->whereNotIn('booking_status', self::BOOKING_STATUS_CANCELADO)
            ->get(['id', 'id_propiedades', 'check_in_date']);

        $checkouts = DB::table('vm_reservas')
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->whereNotIn('booking_status', self::BOOKING_STATUS_CANCELADO)
            ->get(['id', 'id_propiedades', 'check_out_date']);

        $idsPropiedades = $checkins->pluck('id_propiedades')
            ->concat($checkouts->pluck('id_propiedades'))
            ->filter()->unique()->values();

        $propiedades = DB::table('vm_propiedades')
            ->whereIn('id', $idsPropiedades)
            ->get(['id', 'nombre', 'tiempo_limpieza'])
            ->keyBy('id');

        // La tarea de limpieza "oficial" del checkout es la que Breezeway enlaza vía id_reservas.
        // Se excluyen las Canceladas: una reserva puede tener una tarea cancelada + una activa
        // a la vez (ver VmGenerateCheckoutTasksCommand), y solo la activa cuenta aquí.
        $tareasPorReserva = DB::table('vm_tareas_limpieza')
            ->whereIn('id_reservas', $checkouts->pluck('id'))
            ->where('deleted', 0)
            ->where('estado', '!=', 'Cancelada')
            ->get(['id', 'id_reservas', 'control_user'])
            ->keyBy('id_reservas');

        $tieneAsignado = function (?string $controlUser): bool {
            if (!$controlUser) return false;
            $decoded = json_decode($controlUser, true);
            return is_array($decoded) && count($decoded) > 0;
        };

        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $fecha = $weekStart->copy()->addDays($i)->toDateString();

            $arrivalProps = $checkins->filter(fn($r) => $r->check_in_date === $fecha)
                ->map(function ($r) use ($propiedades) {
                    $p = $propiedades[$r->id_propiedades] ?? null;
                    return [
                        'property' => $p->nombre ?? 'Propiedad desconocida',
                        'hours'    => (float) ($p->tiempo_limpieza ?? 0),
                    ];
                })->values();

            $tasks = $checkouts->filter(fn($r) => $r->check_out_date === $fecha)
                ->map(function ($r) use ($propiedades, $tareasPorReserva, $tieneAsignado) {
                    $p     = $propiedades[$r->id_propiedades] ?? null;
                    $tarea = $tareasPorReserva[$r->id] ?? null;
                    return [
                        'property' => $p->nombre ?? 'Propiedad desconocida',
                        'hours'    => (float) ($p->tiempo_limpieza ?? 0),
                        'assigned' => $tarea ? $tieneAsignado($tarea->control_user) : false,
                        'has_task' => (bool) $tarea,
                        'task_id'  => $tarea->id ?? null,
                    ];
                })->values();

            $dias[] = [
                'date'           => $fecha,
                'arrivals'       => $arrivalProps->count(),
                'arrival_props'  => $arrivalProps,
                'departures'     => $tasks->count(),
                'tasks'          => $tasks,
                'assigned_hours' => round($tasks->where('assigned', true)->sum('hours'), 1),
                'pending_hours'  => round($tasks->where('assigned', false)->sum('hours'), 1),
            ];
        }

        return response()->json(['ok' => true, 'days' => $dias]);
    }

    // ── Widget de fichaje (dashboard web) ────────────────────────────────────

    private function vmUsuarioActual(): ?object
    {
        return DB::table('vm_usuarios')
            ->where('admin_user_id', auth()->id())
            ->where('deleted', 0)
            ->first(['id', 'nombre']);
    }

    public function fichajeHoy(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $hoy = now()->toDateString();

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        // Sede del usuario para filtrar festivos
        $sede = DB::table('vm_usuarios')->where('id', $user->id)->value('sede');

        $esFestivo = DB::table('vm_festivos')
            ->where('fecha_fecha', $hoy)
            ->where('deleted', 0)
            ->where(function ($q) use ($sede) {
                $q->whereNull('sede')->orWhere('sede', '')->orWhere('sede', $sede);
            })
            ->exists();

        $horario = DB::table('vm_horarios')
            ->where('id_usuario', $user->id)
            ->where('fecha', $hoy)
            ->value('tipo');

        // HE del dia (solo si ya hay hora_fin)
        $heMin = null;
        if ($fichaje && $fichaje->hora_fin) {
            $contrato = DB::table('vm_contratos')
                ->where('id_usuarios', $user->id)
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>=', $hoy);
                })
                ->where('fecha_alta', '<=', $hoy)
                ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
                ->orderByDesc('fecha_alta')
                ->first(['horas_semana', 'dias_semana']);

            $ini   = strtotime($fichaje->hora_inicio);
            $fin   = strtotime($fichaje->hora_fin);
            $tfMin = (int) round(($fin - $ini) / 60);
            $pMin  = null;
            if ($fichaje->pausa_inicio && $fichaje->pausa_fin) {
                $pMin  = (int) round((strtotime($fichaje->pausa_fin) - strtotime($fichaje->pausa_inicio)) / 60);
                $tfMin -= $pMin;
            }

            $tipoAusencia = DB::table('vm_ausencias')
                ->where('id_usuarios', $user->id)
                ->where('fecha_inicio', '<=', $hoy)
                ->where('fecha_fin',    '>=', $hoy)
                ->where('deleted', 0)
                ->value('tipo');

            $heMin = VmHorasService::calcularHeDia(
                tfMin:        $tfMin,
                pMin:         $pMin,
                tipoAusencia: $tipoAusencia,
                contrato:     $contrato,
                isFestivo:    $esFestivo,
                isFestTrab:   $esFestivo, // festivo trabajado = vm_festivos, ya no depende de vm_fichaje.festivo
                isDescanso:   VmHorasService::esDescansoEfectivo($hoy, $horario, VmHorasService::esDeptoTurno($user->id)),
                esTurno:      VmHorasService::esDeptoTurno($user->id),
            );
        }

        return response()->json([
            'fichaje'      => $fichaje,
            'festivo'      => $esFestivo,
            'tipo_horario' => $horario,
            'he_min'       => $heMin,
        ]);
    }

    public function fichajeEntrada(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $hoy   = now()->toDateString();
        $existe = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)->where('deleted', 0)->where('control_user', $user->id)->exists();

        if ($existe) return response()->json(['error' => 'Ya has fichado entrada hoy'], 409);

        if (InformeAprobacionGuard::estaCompletado((int) $user->id, $hoy)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $user->id, $hoy)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $hora   = now()->format('H:i:s');
        $nombre = now()->format('Y.m.d') . '_' . $user->nombre;
        $fichajeId = DB::table('vm_fichaje')->insertGetId([
            'fecha_fichaje' => $hoy, 'control_user' => $user->id, 'nombre' => $nombre,
            'hora_inicio'   => $hora, 'hora_ini_auto' => $hora,
            'createuser'    => auth()->id(), 'createdat' => now(), // admin_users.id, igual que el resto de la app
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog((int) $user->id, $hoy, 'vm_fichaje', 'insert', $fichajeId, $request);

        return response()->json(['ok' => true, 'hora' => now()->format('H:i'), 'aviso_aprobacion' => $aviso]);
    }

    public function fichajePausa(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', now()->toDateString())->where('deleted', 0)->where('control_user', $user->id)->first();

        if (!$fichaje || !$fichaje->hora_inicio) return response()->json(['error' => 'No has fichado entrada'], 404);

        $hora   = now()->format('H:i:s');
        $update = ['updateuser' => auth()->id(), 'updatedat' => now()]; // admin_users.id

        if (!$fichaje->pausa_inicio) {
            $update['pausa_inicio'] = $hora; $update['pausa_ini_auto'] = $hora;
        } elseif (!$fichaje->pausa_fin) {
            $update['pausa_fin'] = $hora; $update['pausa_fin_auto'] = $hora;
        } else {
            return response()->json(['error' => 'La pausa ya está registrada'], 409);
        }

        if (InformeAprobacionGuard::estaCompletado((int) $user->id, $fichaje->fecha_fichaje)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $user->id, $fichaje->fecha_fichaje)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        DB::table('vm_fichaje')->where('id', $fichaje->id)->update($update);

        $aviso = InformeAprobacionGuard::checkAndLog((int) $user->id, $fichaje->fecha_fichaje, 'vm_fichaje', 'update', $fichaje->id, $request);

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function fichajeSalida(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', now()->toDateString())->where('deleted', 0)->where('control_user', $user->id)->first();

        if (!$fichaje) return response()->json(['error' => 'No has fichado entrada'], 404);
        if ($fichaje->hora_fin) return response()->json(['error' => 'Ya has fichado salida'], 409);

        if (InformeAprobacionGuard::estaCompletado((int) $user->id, $fichaje->fecha_fichaje)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $user->id, $fichaje->fecha_fichaje)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $hora = now()->format('H:i:s');
        // hora_fin se cuadra con el contrato si el desvío es menor al margen; hora_fin_auto
        // conserva siempre el fichaje real (ver VmHorasService::ajustarHoraFinAlContrato).
        $horaAjustada = VmHorasService::ajustarHoraFinAlContrato(
            (int) $user->id, $fichaje->fecha_fichaje, $fichaje->hora_inicio, $hora, $fichaje->pausa_inicio, $fichaje->pausa_fin
        );
        DB::table('vm_fichaje')->where('id', $fichaje->id)
            ->update(['hora_fin' => $horaAjustada, 'hora_fin_auto' => $hora, 'updateuser' => auth()->id(), 'updatedat' => now()]); // admin_users.id

        $aviso = InformeAprobacionGuard::checkAndLog((int) $user->id, $fichaje->fecha_fichaje, 'vm_fichaje', 'update', $fichaje->id, $request);

        return response()->json(['ok' => true, 'hora' => substr($horaAjustada, 0, 5), 'aviso_aprobacion' => $aviso]);
    }
}
