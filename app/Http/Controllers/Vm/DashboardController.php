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
        DB::table('vm_fichaje')->where('id', $fichajeId)->update(['validado' => true]);
        return response()->json(['ok' => true]);
    }

    public function index(Request $request, Project $project)
    {
        $hoy    = Carbon::today()->toDateString();
        $en7    = Carbon::today()->addDays(7)->toDateString();
        $manana = Carbon::today()->addDay()->toDateString();

        // ── Reservas hoy ────────────────────────────────────────────────────
        $checkinHoy = DB::table('vm_reservas')
            ->whereDate('check_in_date', $hoy)
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('check_in_date')
            ->get(['id', 'guest_name', 'vm_propiedades_nombre', 'check_in_date', 'checkin_status', 'booking_status']);

        $checkoutHoy = DB::table('vm_reservas')
            ->whereDate('check_out_date', $hoy)
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('check_out_date')
            ->get(['id', 'guest_name', 'vm_propiedades_nombre', 'check_out_date', 'checkin_status', 'booking_status']);

        // ── Próximos 7 días — tabla columnas por día ─────────────────────────
        // Checkins: count + nombres por día
        $checkinRaw = DB::table('vm_reservas')
            ->whereBetween('check_in_date', [$manana, $en7])
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('check_in_date')
            ->get(['check_in_date', 'vm_propiedades_nombre'])
            ->groupBy(fn($r) => Carbon::parse($r->check_in_date)->toDateString());

        // Checkouts: count + nombres + tiempo_limpieza por día (orden desc tiempo)
        $checkoutRaw = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.nombre', '=', 'r.vm_propiedades_nombre')
            ->whereBetween('r.check_out_date', [$manana, $en7])
            ->whereNotIn('r.booking_status', ['cancelled'])
            ->orderByDesc('p.tiempo_limpieza')
            ->orderBy('r.check_out_date')
            ->get(['r.check_out_date', 'r.vm_propiedades_nombre', 'p.tiempo_limpieza'])
            ->groupBy(fn($r) => Carbon::parse($r->check_out_date)->toDateString());

        // Construir array de 7 días
        $dias7 = [];
        for ($i = 1; $i <= 7; $i++) {
            $dias7[] = Carbon::today()->addDays($i)->toDateString();
        }

        $checkinProximos  = $checkinRaw;
        $checkoutProximos = $checkoutRaw;

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

        // ── Tareas limpieza vencidas sin imputación ──────────────────────────
        $allUsuarios    = DB::table('vm_usuarios')->where('deleted', 0)->pluck('nombre', 'id');
        $tareasLimpieza = DB::table('vm_tareas_limpieza as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', '<', $hoy)
            ->where(fn($q) => $q->whereNull('t.tiempo')->orWhere('t.tiempo', '00:00:00'))
            ->orderBy('t.fecha_planificada')
            ->limit(50)
            ->get(['t.id', 't.control_user', 't.fecha_planificada', 'p.nombre as propiedad'])
            ->map(function ($t) use ($allUsuarios) {
                $ids = json_decode($t->control_user ?? '[]', true) ?? [];
                $t->control_user_nombre = collect($ids)
                    ->map(fn($id) => $allUsuarios[$id] ?? "#{$id}")
                    ->implode(', ') ?: '—';
                return $t;
            });

        // ── Tareas mantenimiento + piscinas vencidas ─────────────────────────
        $tareasMantenimiento = DB::table('vm_tareas_mantenimiento as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', '<', $hoy)
            ->whereNull('t.master_duraciones')
            ->orderBy('t.fecha_planificada')
            ->limit(25)
            ->get(['t.id', 't.nombre', 't.fecha_planificada', 'p.nombre as propiedad']);

        $tareasPiscinas = DB::table('vm_tareas_piscinas as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', '<', $hoy)
            ->whereNull('t.master_duraciones')
            ->orderBy('t.fecha_planificada')
            ->limit(25)
            ->get(['t.id', 't.nombre', 't.fecha_planificada', 'p.nombre as propiedad']);

        $tareasMantPisc = $tareasMantenimiento->concat($tareasPiscinas)->sortBy('fecha_planificada')->values();

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
        $usuarios     = DB::table('vm_usuarios')->where('deleted', 0)->pluck('id', 'nombre');
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
        $verAusenciasSin= $isAdmin || in_array($rolId, [10, 11]);        // Dir.Op, Dir.RRHH
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
            'checkinHoy', 'checkoutHoy',
            'checkinProximos', 'checkoutProximos', 'dias7',
            'conciliaciones',
            'tareasLimpieza', 'tareasMantPisc',
            'turnoSinFichaje', 'desviaciones',
            'conflictosFichaje',
            'vmUsuario', 'proximasAusencias',
            'verReservas', 'verRRHH', 'verAusenciasSin', 'verLimpSinImp', 'verMantSinImp'
        ));

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
                isRotatorio:  (bool) ($fichaje->fuera_de_turno ?? false),
                isFestTrab:   (bool) ($fichaje->festivo ?? false),
                hasFichaje:   true,
                isDescanso:   $horario === 'descanso',
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
