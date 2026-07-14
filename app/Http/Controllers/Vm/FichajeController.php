<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\VmHorasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FichajeController extends Controller
{
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

        $heMin = VmHorasService::calcularHeDia(
            $tfMin, $pMin, null, $contrato,
            $isFestivo,
            (bool) ($fichaje->fuera_de_turno ?? 0),
            (bool) ($fichaje->festivo ?? 0),
            $tfMin !== null,
            $horario && $horario->tipo === 'descanso',
            (int) ($fichaje->ajuste_he ?? 0)
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

        return view('vm.fichaje', compact(
            'project', 'fichaje', 'usuario', 'usuarios',
            'imputaciones', 'totalImputado',
            'fichadoMin', 'esperadoMin', 'heMin', 'efectivasMin',
            'puedeAjustar', 'puedeSinLimiteFecha'
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

        $data['ajuste_he']        = (int) ($data['ajuste_he'] ?? 0);
        $data['festivo']        = (int) ($data['festivo'] ?? 0);
        $data['fuera_de_turno'] = (int) ($data['fuera_de_turno'] ?? 0);
        $data['validado']       = (bool) ($data['validado'] ?? false);
        $data['updatedat']      = now();

        DB::table('vm_fichaje')->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }
}
