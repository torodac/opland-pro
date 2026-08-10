<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\VmHorasService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Mapeo tipo horario → tipo ausencia
    private const TIPO_MAP = [
        'vacaciones'  => 'Vacaciones',
        'baja'        => 'Baja médica',
        'comp_festivo'=> 'Comp. festivo',
        'comp_horas'  => 'Comp. horas',
        'asuntos'     => 'Asuntos propios',
        'absentismo'  => 'Absentismo',
    ];

    private const BOOKING_STATUS_CANCELADO = ['cancelled', 'canceled'];

    public function validarConciliacion(Request $request, Project $project)
    {
        $idUsuario = (int) $request->id_usuario;
        $tipo      = $request->tipo;
        $fecha     = $request->fecha;
        $tipoAus   = self::TIPO_MAP[$tipo] ?? ucfirst($tipo);

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

        DB::table('vm_ausencias')->insert([
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

        return response()->json(['ok' => true, 'fecha_inicio' => $fechaIni, 'fecha_fin' => $fechaFin]);
    }

    public function validarFichaje(Request $request, Project $project)
    {
        $fichajeId = (int) $request->id;
        $user = $this->vmUsuarioActual();
        DB::table('vm_fichaje')->where('id', $fichajeId)->update([
            'validado'     => true,
            'validado_por' => $user->id ?? null,
        ]);
        return response()->json(['ok' => true]);
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

    public function index(Request $request, Project $project)
    {
        $hoy    = Carbon::today()->toDateString();

        // ── Conciliaciones horario ↔ ausencias ──────────────────────────────
        $conciliaciones = DB::table('vm_horarios as h')
            ->join('vm_usuarios as u', 'u.id', '=', 'h.id_usuario')
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
            ->whereNotExists($sinImputacion('limpieza'))
            ->orderBy('t.fecha_planificada')
            ->limit(50)
            ->get(['t.id', 't.nombre', 't.control_user', 't.fecha_planificada', 'p.nombre as propiedad'])
            ->map($resolverResponsables);

        // ── Tareas mantenimiento completadas sin imputación (piscinas ya no aplica aqui) ──
        $tareasMantPisc = DB::table('vm_tareas_mantenimiento as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.estado', 'Completada')
            ->where(fn($q) => $q->whereNull('t.validado')->orWhere('t.validado', false))
            ->whereNotExists($sinImputacion('mantenimiento'))
            ->orderBy('t.fecha_planificada')
            ->limit(50)
            ->get(['t.id', 't.nombre', 't.control_user', 't.fecha_planificada', 'p.nombre as propiedad'])
            ->map($resolverResponsables);

        // ── Personas de Breezeway sin usuario mapeado en Opland ──────────────
        $breezewayPendientes = DB::table('vm_breezeway_pendientes')
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->get(['nombre', 'breezeway_id', 'fecha_alta', 'num_tareas']);

        // ── Turno sin fichaje ────────────────────────────────────────────────
        $turnoSinFichaje = DB::table('vm_horarios as h')
            ->join('vm_usuarios as u', 'u.id', '=', 'h.id_usuario')
            ->where('h.tipo', 'turno')
            ->where('h.fecha', '<', $hoy)
            ->whereNotExists(function ($q) {
                $q->from('vm_fichaje as f')
                    ->whereColumn('f.control_user', 'u.id')
                    ->whereColumn('f.fecha_fichaje', 'h.fecha')
                    ->where('f.deleted', 0);
            })
            ->orderByDesc('h.fecha')
            ->limit(50)
            ->get(['u.nombre as usuario', 'u.id as id_usuario', 'h.fecha']);

        // ── Fichaje vs imputaciones (diff > 30 min) ──────────────────────────
        // Solo Limpieza (rol 1) y Mantenimiento (rol 4): son los únicos que imputan tiempo por
        // tarea, así que comparar fichaje contra imputaciones no tiene sentido para otros roles.
        $usuarios     = DB::table('vm_usuarios')->where('deleted', 0)->whereIn('id_rol', [1, 4])->pluck('id', 'nombre');
        $imputaciones = DB::table('vm_imputaciones')
            ->where('fecha_imputacion', '<', $hoy)
            ->whereNotNull('duracion')
            ->selectRaw('id_usuario, fecha_imputacion, SUM(duracion) as total_min')
            ->groupBy('id_usuario', 'fecha_imputacion')
            ->get()
            ->keyBy(fn($r) => $r->id_usuario . '_' . $r->fecha_imputacion);

        $fichajes = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('validado')->orWhere('validado', false))
            ->where('fecha_fichaje', '<', $hoy)
            ->whereNotNull('hora_fin')
            ->get(['id', 'nombre', 'fecha_fichaje', 'hora_inicio', 'hora_fin', 'pausa_inicio', 'pausa_fin']);

        $desviaciones = collect();
        foreach ($fichajes as $f) {
            $nombreUsuario = preg_replace('/^\d{4}\.\d{2}\.\d{2}_/', '', $f->nombre);
            $idUsuario     = $usuarios[$nombreUsuario] ?? null;
            if (!$idUsuario) continue;

            // fin - inicio (siempre positivo para jornada normal)
            $ini  = Carbon::parse($f->hora_inicio);
            $fin  = Carbon::parse($f->hora_fin);
            $mins = ($fin->timestamp - $ini->timestamp) / 60;
            if ($f->pausa_inicio && $f->pausa_fin) {
                $pIni  = Carbon::parse($f->pausa_inicio);
                $pFin  = Carbon::parse($f->pausa_fin);
                $mins -= ($pFin->timestamp - $pIni->timestamp) / 60;
            }
            $mins = (int) round($mins);

            $key    = $idUsuario . '_' . $f->fecha_fichaje;
            $impMin = (int) ($imputaciones[$key]->total_min ?? 0);
            $diff   = abs($mins - $impMin);

            if ($diff > 30) {
                $desviaciones->push((object)[
                    'fichaje_id'     => $f->id,
                    'usuario'        => $nombreUsuario,
                    'fecha'          => $f->fecha_fichaje,
                    'fichaje_min'    => $mins,
                    'imputado_min'   => $impMin,
                    'diferencia_min' => $diff,
                ]);
            }
        }
        $desviaciones = $desviaciones->sortByDesc('diferencia_min')->values()->take(50);

        // ── Conflictos fichaje: descanso o ausencia el mismo día ─────────────
        // Caso 1: fichaje + horario descanso
        $rawDescanso = DB::table('vm_fichaje as f')
            ->join('vm_usuarios as u', fn($j) => $j
                ->whereColumn('u.id', 'f.control_user')
                ->where('u.deleted', 0)
            )
            ->join('vm_horarios as h', fn($j) => $j
                ->whereColumn('h.id_usuario', 'u.id')
                ->whereColumn('h.fecha', 'f.fecha_fichaje')
                ->where('h.tipo', 'descanso')
            )
            ->where('f.deleted', 0)
            ->get(['u.id as id_usuario', 'u.nombre as usuario', 'f.fecha_fichaje as fecha', 'f.id as fichaje_id']);

        // Caso 2: fichaje + ausencia
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

        // Agrupar por (id_usuario, fecha)
        $conflictosMap = [];
        foreach ($rawDescanso as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            if (!isset($conflictosMap[$key])) {
                $conflictosMap[$key] = ['id_usuario' => $r->id_usuario, 'usuario' => $r->usuario, 'fecha' => $r->fecha, 'fichaje_id' => $r->fichaje_id, 'descanso' => false, 'ausencias' => []];
            }
            $conflictosMap[$key]['descanso'] = true;
        }
        foreach ($rawAusencia as $r) {
            $key = $r->id_usuario . '_' . $r->fecha;
            if (!isset($conflictosMap[$key])) {
                $conflictosMap[$key] = ['id_usuario' => $r->id_usuario, 'usuario' => $r->usuario, 'fecha' => $r->fecha, 'fichaje_id' => $r->fichaje_id, 'descanso' => false, 'ausencias' => []];
            }
            $conflictosMap[$key]['ausencias'][] = ['id' => $r->ausencia_id, 'tipo' => $r->ausencia_tipo];
        }

        $conflictosFichaje = collect(array_values($conflictosMap))
            ->sortByDesc('fecha')->values()->take(50);

        // vm_usuarios del usuario web autenticado (para widget de fichaje)
        $vmUsuario = DB::table('vm_usuarios')
            ->where('admin_user_id', auth()->id())
            ->first(['id', 'nombre', 'id_rol']);

        // Visibilidad por rol
        $rolId = (int) ($vmUsuario->id_rol ?? 0);
        $isAdmin = auth()->user()->isProjectAdmin($project);
        $verReservas    = $isAdmin || in_array($rolId, [3, 10, 5, 2]);   // Dir.gral, Dir.Op, Coord.mant, Coord.limp
        $verRRHH        = $isAdmin || in_array($rolId, [10, 5, 2, 11]);  // Dir.Op, Coord.mant, Coord.limp, Dir.RRHH
        $verAusenciasSin= $isAdmin || $rolId === 11;                     // Dir.RRHH
        $verLimpSinImp  = $isAdmin || in_array($rolId, [10, 2]);         // Dir.Op, Coord.limp
        $verMantSinImp  = $isAdmin || in_array($rolId, [10, 5]);         // Dir.Op, Coord.mant

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

        return view('dashboard', compact(
            'project',
            'conciliaciones',
            'tareasLimpieza', 'tareasMantPisc', 'breezewayPendientes',
            'turnoSinFichaje', 'desviaciones',
            'conflictosFichaje',
            'vmUsuario', 'proximasAusencias',
            'verReservas', 'verRRHH', 'verAusenciasSin', 'verLimpSinImp', 'verMantSinImp'
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
                ->first(['horas_semana']);

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
                isFestTrab:   (bool) ($fichaje->festivo ?? false),
                hasFichaje:   true,
                isDescanso:   VmHorasService::esDescansoEfectivo($hoy, $horario, VmHorasService::esDeptoTurno($user->id)),
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

        $hora   = now()->format('H:i:s');
        $nombre = now()->format('Y.m.d') . '_' . $user->nombre;
        DB::table('vm_fichaje')->insert([
            'fecha_fichaje' => $hoy, 'control_user' => $user->id, 'nombre' => $nombre,
            'hora_inicio'   => $hora, 'hora_ini_auto' => $hora,
            'createuser'    => $user->id, 'createdat' => now(),
        ]);

        return response()->json(['ok' => true, 'hora' => now()->format('H:i')]);
    }

    public function fichajePausa(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', now()->toDateString())->where('deleted', 0)->where('control_user', $user->id)->first();

        if (!$fichaje || !$fichaje->hora_inicio) return response()->json(['error' => 'No has fichado entrada'], 404);

        $hora   = now()->format('H:i:s');
        $update = ['updateuser' => $user->id, 'updatedat' => now()];

        if (!$fichaje->pausa_inicio) {
            $update['pausa_inicio'] = $hora; $update['pausa_ini_auto'] = $hora;
        } elseif (!$fichaje->pausa_fin) {
            $update['pausa_fin'] = $hora; $update['pausa_fin_auto'] = $hora;
        } else {
            return response()->json(['error' => 'La pausa ya está registrada'], 409);
        }

        DB::table('vm_fichaje')->where('id', $fichaje->id)->update($update);
        return response()->json(['ok' => true]);
    }

    public function fichajeSalida(Request $request, Project $project)
    {
        $user = $this->vmUsuarioActual();
        if (!$user) return response()->json(['error' => 'Sin perfil de empleado'], 403);

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', now()->toDateString())->where('deleted', 0)->where('control_user', $user->id)->first();

        if (!$fichaje) return response()->json(['error' => 'No has fichado entrada'], 404);
        if ($fichaje->hora_fin) return response()->json(['error' => 'Ya has fichado salida'], 409);

        $hora = now()->format('H:i:s');
        DB::table('vm_fichaje')->where('id', $fichaje->id)
            ->update(['hora_fin' => $hora, 'hora_fin_auto' => $hora, 'updateuser' => $user->id, 'updatedat' => now()]);

        return response()->json(['ok' => true, 'hora' => now()->format('H:i')]);
    }
}
