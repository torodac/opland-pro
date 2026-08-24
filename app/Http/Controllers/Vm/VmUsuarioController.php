<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use App\Services\InformeAprobacionGuard;
use App\Services\VmHorasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VmUsuarioController extends Controller
{
    // Horas de convenio para un contrato a 40h/semana; se prorratea linealmente por horas_semana
    // y por los días naturales que el contrato estuvo activo dentro de cada año.
    private const HORAS_CONVENIO_ANUAL_40H = 1772;
    // Días de vacaciones para un año completo -- a diferencia de las horas, NO se prorratea por
    // horas_semana (un contrato a tiempo parcial no reduce los días, solo las horas por día),
    // solo por el tiempo que el contrato estuvo activo dentro del año.
    private const DIAS_VACACIONES_ANUAL = 22;

    private function authorize(Project $project): void
    {
        if (!auth()->user()->canViewTable($project, 'usuarios')) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }
    }

    public function show(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        $usuario = DB::table('vm_usuarios as u')
            ->leftJoin('vm_departamentos as d', 'd.id', '=', 'u.id_departamento')
            ->where('u.id', $id)
            ->select('u.*', 'd.nombre as departamento')
            ->firstOrFail();

        $contratos = DB::table('vm_contratos')
            ->where('id_usuarios', $id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->get();

        $ausencias = DB::table('vm_ausencias')
            ->where('id_usuarios', $id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_inicio')
            ->get();

        $festivos = DB::table('vm_festivos')
            ->where('deleted', 0)
            ->where(function ($q) use ($usuario) {
                $q->whereNull('sede')
                  ->orWhere('sede', '')
                  ->orWhere('sede', $usuario->sede ?? '');
            })
            ->pluck('fecha_fecha')
            ->map(fn($f) => substr($f, 0, 10))
            ->values()
            ->toJson();

        $nominas = DB::table('vm_nominas')
            ->where('id_usuario', $id)
            ->where('deleted', 0)
            ->orderByDesc('mes')
            ->get();

        $bonus = DB::table('vm_bonus')
            ->where('deleted', 0)
            ->where(function ($q) use ($usuario) {
                $q->where(function ($q2) use ($usuario) {
                    $q2->where('alcance', 'usuario')->where('id_referencia', $usuario->nombre);
                })->orWhere(function ($q2) use ($usuario) {
                    $q2->where('alcance', 'cargo')->where('id_referencia', $usuario->cargo);
                })->orWhere(function ($q2) use ($usuario) {
                    $q2->where('alcance', 'departamento')->where('id_referencia', $usuario->departamento);
                });
            })
            ->orderBy('alcance')
            ->get();

        $ausenciasPorTipo = $ausencias->groupBy('tipo')->map(function ($grupo) {
            return $grupo->sum(function ($a) {
                $ini = Carbon::parse($a->fecha_inicio);
                $fin = Carbon::parse($a->fecha_fin);
                return $ini->diffInDays($fin) + 1;
            });
        });

        $roles = DB::table('vm_roles')->orderBy('nombre')->get();

        $horarios = DB::table('vm_horarios')
            ->where('id_usuario', $id)
            ->get(['fecha', 'tipo', 'hora_inicio', 'hora_fin']);

        $fichajes = DB::table('vm_fichaje')
            ->where('control_user', $id)
            ->where('deleted', 0)
            ->whereNotNull('hora_fin')
            ->get(['fecha_fichaje', 'hora_inicio', 'hora_fin', 'pausa_inicio', 'pausa_fin', 'fuera_de_turno', 'festivo']);

        $departamentos = DB::table('vm_departamentos')->where('deleted', 0)->orderBy('nombre')->get(['id', 'nombre']);
        $cargos = ['Responsable propietarios','Jefe de mantenimiento','Ayte. Diseño','Sub Gobernanta','Mozo/Mantenimiento','Captador Clientes','Jf. Diseño Arquitecto','Gobernanta','Limpiadora','Ayte. Mantenimiento','Oficial Mantenimiento','Contable','Jf. Recepción','RRHH','Jefe Operaciones','Reviu Manager','Jefe Finanzas','Recepcionista'];

        $tiposAusencia = $this->getTiposAusencia();

        $tieneAccesoApp = in_array($usuario->acceso, ['APP', 'APP y web']);
        $pushActivo = DB::table('vm_push_subscriptions')->where('id_usuario', $id)->exists();
        $pushInactivo = $tieneAccesoApp && !$pushActivo;

        $distinctYears = DB::table('vm_fichaje')
            ->where('control_user', $id)
            ->where('deleted', 0)
            ->selectRaw("EXTRACT(YEAR FROM fecha_fichaje::date)::int as yr")
            ->distinct()
            ->pluck('yr')
            ->toArray();

        $diasHe = [];
        foreach ($distinctYears as $yr) {
            $diasHe = array_merge($diasHe, VmHorasService::calcularAnio($id, (int) $yr));
        }

        $imputacionesPorFecha = DB::table('vm_imputaciones')
            ->where('id_usuario', $id)
            ->whereNotNull('fecha_imputacion')
            ->selectRaw("fecha_imputacion::date::text as fecha, SUM(duracion) as minutos")
            ->groupBy('fecha_imputacion')
            ->pluck('minutos', 'fecha');

        $contratosOrdenados = DB::table('vm_contratos')
            ->where('id_usuarios', $id)
            ->where(function ($q) { $q->where('deleted', 0)->orWhereNull('deleted'); })
            ->orderBy('fecha_alta')
            ->get(['fecha_alta', 'fecha_baja', 'horas_semana']);

        $fichadosPorFecha = [];
        foreach ($fichajes as $f) {
            $fecha = substr($f->fecha_fichaje, 0, 10);
            if (!($f->hora_inicio && $f->hora_fin)) continue;
            $tfMin = VmHorasService::hmsToMinutes($f->hora_fin) - VmHorasService::hmsToMinutes($f->hora_inicio);
            $pMin = null;
            if ($f->pausa_inicio && $f->pausa_fin) {
                $pMin = VmHorasService::hmsToMinutes($f->pausa_fin) - VmHorasService::hmsToMinutes($f->pausa_inicio);
            }
            $horasSemanales = null;
            foreach ($contratosOrdenados as $c) {
                if ($c->fecha_alta <= $fecha && (is_null($c->fecha_baja) || $c->fecha_baja >= $fecha)) {
                    $horasSemanales = $c->horas_semana;
                    break;
                }
            }
            $ded = $horasSemanales !== null
                ? VmHorasService::pausaDeducible($pMin, $horasSemanales)
                : ($pMin ?? 0);
            $fichadosPorFecha[$fecha] = $tfMin - $ded;
        }

        $ajustesHe = DB::table('vm_fichaje')
            ->where('control_user', $id)
            ->where('deleted', 0)
            ->where('ajuste_he', '!=', 0)
            ->orderBy('fecha_fichaje', 'desc')
            ->get(['id', 'fecha_fichaje', 'ajuste_he', 'ajuste_he_motivo']);

        [$horasConvenioAnio, $horasConvenioHoyAnio, $diasVacacionesCorrespondenAnio] =
            $this->calcularConvenioPorAnio($contratosOrdenados, $distinctYears);

        return view('vm.usuario', compact(
            'project','usuario','contratos','ausencias','nominas',
            'bonus','ausenciasPorTipo','roles','departamentos','cargos','horarios','fichajes','tiposAusencia','festivos',
            'pushInactivo','diasHe','imputacionesPorFecha','fichadosPorFecha','ajustesHe',
            'horasConvenioAnio','horasConvenioHoyAnio','diasVacacionesCorrespondenAnio'
        ));
    }

    // Horas de convenio y días de vacaciones que corresponden a cada año, prorrateados por
    // los contratos del usuario. Devuelve 3 mapas [año => valor]:
    // - horasConvenioAnio: objetivo del año completo.
    // - horasConvenioHoyAnio: mismo objetivo pero recortado a "hasta hoy" (para años pasados
    //   coincide con el anterior; para el año en curso es menor; para años futuros da 0).
    // - diasVacacionesCorrespondenAnio: días de vacaciones del año completo (sin recorte a hoy).
    private function calcularConvenioPorAnio($contratos, array $aniosConFichaje): array
    {
        $hoy = now()->toDateString();

        $anios = $aniosConFichaje;
        foreach ($contratos as $c) {
            $anioIni = (int) substr($c->fecha_alta, 0, 4);
            $anioFin = $c->fecha_baja ? (int) substr($c->fecha_baja, 0, 4) : (int) now()->year;
            for ($y = $anioIni; $y <= $anioFin; $y++) $anios[] = $y;
        }
        $anios = array_unique(array_merge($anios, [(int) now()->year]));

        $horasConvenioAnio = [];
        $horasConvenioHoyAnio = [];
        $diasVacacionesCorrespondenAnio = [];

        foreach ($anios as $y) {
            $inicioAnio = "{$y}-01-01";
            $finAnio    = "{$y}-12-31";
            $diasAnio   = Carbon::parse($inicioAnio)->isLeapYear() ? 366 : 365;

            $horas = 0.0;
            $horasHoy = 0.0;
            $diasVac = 0.0;

            foreach ($contratos as $c) {
                $desde = max($c->fecha_alta, $inicioAnio);
                $hasta = min($c->fecha_baja ?? $finAnio, $finAnio);
                if ($desde > $hasta || !$c->horas_semana) continue;

                $diasSolapados = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
                $factorHoras   = ((float) $c->horas_semana) / 40;

                $horas    += self::HORAS_CONVENIO_ANUAL_40H * $factorHoras * ($diasSolapados / $diasAnio);
                $diasVac  += self::DIAS_VACACIONES_ANUAL * ($diasSolapados / $diasAnio);

                $hastaHoy = min($hasta, $hoy);
                if ($desde <= $hastaHoy) {
                    $diasSolapadosHoy = Carbon::parse($desde)->diffInDays(Carbon::parse($hastaHoy)) + 1;
                    $horasHoy += self::HORAS_CONVENIO_ANUAL_40H * $factorHoras * ($diasSolapadosHoy / $diasAnio);
                }
            }

            $horasConvenioAnio[$y]              = round($horas, 1);
            $horasConvenioHoyAnio[$y]           = round($horasHoy, 1);
            $diasVacacionesCorrespondenAnio[$y] = round($diasVac, 1);
        }

        return [$horasConvenioAnio, $horasConvenioHoyAnio, $diasVacacionesCorrespondenAnio];
    }

    private function getTiposAusencia(): array
    {
        $field = DB::table('admin_table_fields as tf')
            ->join('admin_project_tables as pt', 'tf.project_table_id', '=', 'pt.id')
            ->where('pt.name', 'ausencias')
            ->where('tf.name', 'tipo')
            ->value('tf.extras');

        if (!$field) return [];

        $opts = str_replace('opt:', '', $field);
        return array_map('trim', explode(',', $opts));
    }

    public function update(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        DB::table('vm_usuarios')->where('id', $id)->update([
            'nombre'       => $request->nombre,
            'dni'          => $request->dni,
            'mail'         => $request->mail,
            'telefono'       => $request->telefono,
            'id_departamento'=> $request->id_departamento ?: null,
            'cargo'          => $request->cargo ?: null,
            'id_rol'       => $request->id_rol ?: null,
            'acceso'       => $request->acceso,
            'updatedat'    => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function storeContrato(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        $nombreUsuario = DB::table('vm_usuarios')->where('id', $id)->value('nombre');
        DB::table('vm_contratos')->insert([
            'id_usuarios'  => $id,
            'nombre'       => "{$nombreUsuario}_{$request->fecha_alta}",
            'fecha_alta'   => $request->fecha_alta,
            'fecha_baja'   => $request->fecha_baja ?: null,
            'salario_base' => $request->salario_base,
            'horas_semana' => $request->horas_semana ?: null,
            'deleted'      => 0,
            'createdat'    => now(),
            'updatedat'    => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function updateContrato(Request $request, Project $project, int $id, int $contratoId)
    {
        $this->authorize($project);
        DB::table('vm_contratos')->where('id', $contratoId)->where('id_usuarios', $id)->update([
            'fecha_alta'   => $request->fecha_alta,
            'fecha_baja'   => $request->fecha_baja ?: null,
            'salario_base' => $request->salario_base,
            'horas_semana' => $request->horas_semana ?: null,
            'updatedat'    => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function storeBonus(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        $usuario = DB::table('vm_usuarios')->where('id', $id)->first();
        DB::table('vm_bonus')->insert([
            'alcance'       => 'usuario',
            'id_referencia' => $usuario->nombre,
            'meses'         => implode(',', $request->meses),
            'importe'       => $request->importe,
            'fecha_inicio'  => $request->fecha_inicio,
            'fecha_fin'     => $request->fecha_fin ?: null,
            'descripcion'   => $request->descripcion ?: null,
            'deleted'       => 0,
            'createdat'     => now(),
            'updatedat'     => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function updateBonus(Request $request, Project $project, int $id, int $bonusId)
    {
        $this->authorize($project);
        DB::table('vm_bonus')->where('id', $bonusId)->update([
            'meses'        => implode(',', $request->meses),
            'importe'      => $request->importe,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin ?: null,
            'descripcion'  => $request->descripcion ?: null,
            'updatedat'    => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function deleteBonus(Request $request, Project $project, int $id, int $bonusId)
    {
        $this->authorize($project);
        DB::table('vm_bonus')->where('id', $bonusId)->update(['deleted' => 1]);
        return response()->json(['ok' => true]);
    }

    public function storeAusencia(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        if (empty($request->tipo))         return response()->json(['error' => 'El tipo es obligatorio.'], 422);
        if (empty($request->fecha_inicio)) return response()->json(['error' => 'La fecha de inicio es obligatoria.'], 422);
        if (empty($request->fecha_fin))    return response()->json(['error' => 'La fecha de fin es obligatoria.'], 422);

        $inicio = Carbon::parse($request->fecha_inicio);
        $fin    = Carbon::parse($request->fecha_fin);

        if ($inicio->gt($fin)) {
            return response()->json(['error' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.'], 422);
        }

        $anyoDevengo = $request->anyo_devengo ? (int) $request->anyo_devengo : $inicio->year;
        $anyoActual  = (int) now()->year;
        if ($anyoDevengo < 2020 || $anyoDevengo > $anyoActual + 1) {
            return response()->json(['error' => 'El año de devengo debe estar entre 2020 y ' . ($anyoActual + 1) . '.'], 422);
        }

        $solape = DB::table('vm_ausencias')
            ->where('id_usuarios', $id)
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $request->fecha_fin)
            ->where('fecha_fin', '>=', $request->fecha_inicio)
            ->first();

        if ($solape) {
            $desde = Carbon::parse($solape->fecha_inicio)->format('d/m/Y');
            $hasta = Carbon::parse($solape->fecha_fin)->format('d/m/Y');
            return response()->json([
                'error' => 'Las fechas se solapan con una ausencia existente (' . $solape->tipo . ': ' . $desde . ' – ' . $hasta . ').'
            ], 422);
        }

        // Subida de fichero (multipart)
        $fichero = null;
        if ($request->hasFile('fichero') && $request->file('fichero')->isValid()) {
            $fichero = $request->file('fichero')->store('vm/ausencias/' . $id, 'public');
        }

        $ausId = DB::table('vm_ausencias')->insertGetId([
            'id_usuarios'  => $id,
            'tipo'         => $request->tipo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'comentario'   => $request->comentario ?: null,
            'anyo_devengo' => $anyoDevengo,
            'file_fichero' => $fichero,
            'deleted'      => 0,
            'createdat'    => now(),
            'updatedat'    => now(),
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog($id, $request->fecha_inicio, 'vm_ausencias', 'insert', $ausId, $request);

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function updateAusencia(Request $request, Project $project, int $id, int $ausId)
    {
        $this->authorize($project);
        $ausencia = DB::table('vm_ausencias')->where('id', $ausId)->where('id_usuarios', $id)->where('deleted', 0)->first();
        if (!$ausencia) return response()->json(['error' => 'Ausencia no encontrada.'], 404);

        if (empty($request->tipo))         return response()->json(['error' => 'El tipo es obligatorio.'], 422);
        if (empty($request->fecha_inicio)) return response()->json(['error' => 'La fecha de inicio es obligatoria.'], 422);
        if (empty($request->fecha_fin))    return response()->json(['error' => 'La fecha de fin es obligatoria.'], 422);

        $inicio = Carbon::parse($request->fecha_inicio);
        $fin    = Carbon::parse($request->fecha_fin);

        if ($inicio->gt($fin)) {
            return response()->json(['error' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.'], 422);
        }

        $anyoDevengo = $request->anyo_devengo ? (int) $request->anyo_devengo : $inicio->year;
        $anyoActual  = (int) now()->year;
        if ($anyoDevengo < 2020 || $anyoDevengo > $anyoActual + 1) {
            return response()->json(['error' => 'El año de devengo debe estar entre 2020 y ' . ($anyoActual + 1) . '.'], 422);
        }

        $solape = DB::table('vm_ausencias')
            ->where('id_usuarios', $id)
            ->where('deleted', 0)
            ->where('id', '!=', $ausId)
            ->where('fecha_inicio', '<=', $request->fecha_fin)
            ->where('fecha_fin', '>=', $request->fecha_inicio)
            ->first();

        if ($solape) {
            $desde = Carbon::parse($solape->fecha_inicio)->format('d/m/Y');
            $hasta = Carbon::parse($solape->fecha_fin)->format('d/m/Y');
            return response()->json([
                'error' => 'Las fechas se solapan con una ausencia existente (' . $solape->tipo . ': ' . $desde . ' – ' . $hasta . ').'
            ], 422);
        }

        $data = [
            'tipo'         => $request->tipo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'comentario'   => $request->comentario ?: null,
            'anyo_devengo' => $anyoDevengo,
            'updatedat'    => now(),
        ];

        if ($request->hasFile('fichero') && $request->file('fichero')->isValid()) {
            $data['file_fichero'] = $request->file('fichero')->store('vm/ausencias/' . $id, 'public');
        }

        DB::table('vm_ausencias')->where('id', $ausId)->update($data);

        $aviso = InformeAprobacionGuard::checkAndLog($id, $request->fecha_inicio, 'vm_ausencias', 'update', $ausId, $request);

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function deleteAusencia(Request $request, Project $project, int $id, int $ausId)
    {
        $this->authorize($project);
        $ausencia = DB::table('vm_ausencias')->where('id', $ausId)->where('id_usuarios', $id)->where('deleted', 0)->first();
        if (!$ausencia) return response()->json(['error' => 'Ausencia no encontrada.'], 404);

        DB::table('vm_ausencias')->where('id', $ausId)->update(['deleted' => 1, 'updatedat' => now()]);

        $aviso = InformeAprobacionGuard::checkAndLog($id, $ausencia->fecha_inicio, 'vm_ausencias', 'delete', $ausId, $request);

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function storeNomina(Request $request, Project $project, int $id)
    {
        $this->authorize($project);
        DB::table('vm_nominas')->upsert([
            'id_usuario'  => $id,
            'mes'         => Carbon::parse($request->mes)->startOfMonth()->toDateString(),
            'devengado'   => $request->devengado,
            'liquido'     => $request->liquido,
            'coste_total' => $request->coste_total,
            'deleted'     => 0,
            'createdat'   => now(),
            'updatedat'   => now(),
        ], ['id_usuario', 'mes'], ['devengado', 'liquido', 'coste_total', 'updatedat']);
        return response()->json(['ok' => true]);
    }
}
