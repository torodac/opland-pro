<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\InformeAprobacionGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AusenciaController extends Controller
{
    private function authorize(Project $project): void
    {
        if (!Auth::user()->canViewTable($project, 'ausencias')) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }
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

    public function index(Request $request, Project $project)
    {
        $this->authorize($project);

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $query = DB::table('vm_ausencias as a')
            ->join('vm_usuarios as u', 'u.id', '=', 'a.id_usuarios')
            ->where('a.deleted', 0);

        if ($request->filled('f_id_usuarios')) {
            $query->where('a.id_usuarios', $request->f_id_usuarios);
        }
        if ($request->filled('f_tipo')) {
            $query->where('a.tipo', $request->f_tipo);
        }
        if ($request->filled('f_anyo_devengo')) {
            $query->where('a.anyo_devengo', $request->f_anyo_devengo);
        }

        $ausencias = $query->orderByDesc('a.fecha_inicio')
            ->paginate(50, [
                'a.id', 'a.tipo', 'a.fecha_inicio', 'a.fecha_fin', 'a.anyo_devengo',
                'a.comentario', 'a.file_fichero', 'a.id_usuarios', 'u.nombre as empleado',
            ])
            ->withQueryString();

        return view('vm.ausencias-form', [
            'project'       => $project,
            'usuarios'      => $usuarios,
            'ausencias'     => $ausencias,
            'tiposAusencia' => $this->getTiposAusencia(),
            'filtros'       => $request->only(['f_id_usuarios', 'f_tipo', 'f_anyo_devengo']),
            'canEdit'       => Auth::user()->canEditTable($project, 'ausencias'),
            'isAdmin'       => Auth::user()->isProjectAdmin($project),
            'breadcrumb'    => [
                ['label' => 'Ausencias', 'url' => ''],
            ],
        ]);
    }

    private function validarFechas(Request $request, ?int $excludeId = null): ?array
    {
        if (empty($request->id_usuarios)) return ['error' => 'El empleado es obligatorio.'];
        if (empty($request->tipo))         return ['error' => 'El tipo es obligatorio.'];
        if (empty($request->fecha_inicio)) return ['error' => 'La fecha de inicio es obligatoria.'];
        if (empty($request->fecha_fin))    return ['error' => 'La fecha de fin es obligatoria.'];

        $inicio = Carbon::parse($request->fecha_inicio);
        $fin    = Carbon::parse($request->fecha_fin);

        if ($inicio->gt($fin)) {
            return ['error' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.'];
        }

        $anyoDevengo = $request->anyo_devengo ? (int) $request->anyo_devengo : $inicio->year;
        $anyoActual  = (int) now()->year;
        if ($anyoDevengo < 2020 || $anyoDevengo > $anyoActual + 1) {
            return ['error' => 'El año de devengo debe estar entre 2020 y ' . ($anyoActual + 1) . '.'];
        }

        $solapeQuery = DB::table('vm_ausencias')
            ->where('id_usuarios', $request->id_usuarios)
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $request->fecha_fin)
            ->where('fecha_fin', '>=', $request->fecha_inicio);
        if ($excludeId) {
            $solapeQuery->where('id', '!=', $excludeId);
        }
        $solape = $solapeQuery->first();

        if ($solape) {
            $desde = Carbon::parse($solape->fecha_inicio)->format('d/m/Y');
            $hasta = Carbon::parse($solape->fecha_fin)->format('d/m/Y');
            return ['error' => 'Las fechas se solapan con una ausencia existente de este empleado (' . $solape->tipo . ': ' . $desde . ' – ' . $hasta . ').'];
        }

        return ['anyo_devengo' => $anyoDevengo];
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize($project);

        $check = $this->validarFechas($request);
        if (isset($check['error'])) {
            return response()->json(['error' => $check['error']], 422);
        }

        if (InformeAprobacionGuard::estaCompletado((int) $request->id_usuarios, $request->fecha_inicio)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $request->id_usuarios, $request->fecha_inicio)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        $fichero = null;
        if ($request->hasFile('fichero') && $request->file('fichero')->isValid()) {
            $fichero = $request->file('fichero')->store('vm/ausencias/' . $request->id_usuarios, 'public');
        }

        $id = DB::table('vm_ausencias')->insertGetId([
            'id_usuarios'  => $request->id_usuarios,
            'tipo'         => $request->tipo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'comentario'   => $request->comentario ?: null,
            'anyo_devengo' => $check['anyo_devengo'],
            'file_fichero' => $fichero,
            'deleted'      => 0,
            'createuser'   => Auth::id(),
            'updateuser'   => Auth::id(),
            'createdat'    => now(),
            'updatedat'    => now(),
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog(
            (int) $request->id_usuarios, $request->fecha_inicio, 'vm_ausencias', 'insert', $id, $request
        );

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function update(Request $request, Project $project, int $ausId)
    {
        $this->authorize($project);

        $ausencia = DB::table('vm_ausencias')->where('id', $ausId)->where('deleted', 0)->first();
        if (!$ausencia) return response()->json(['error' => 'Ausencia no encontrada.'], 404);

        $check = $this->validarFechas($request, $ausId);
        if (isset($check['error'])) {
            return response()->json(['error' => $check['error']], 422);
        }

        // Cualquiera de los dos rangos (el que tenía antes o el que se le quiere asignar ahora)
        // puede caer en un mes ya aprobado -- se comprueban los dos.
        if (InformeAprobacionGuard::estaCompletado((int) $ausencia->id_usuarios, $ausencia->fecha_inicio)
            || InformeAprobacionGuard::estaCompletado((int) $request->id_usuarios, $request->fecha_inicio)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset')) {
            $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $ausencia->id_usuarios, $ausencia->fecha_inicio)
                ?? InformeAprobacionGuard::mensajeSiEnAprobacion((int) $request->id_usuarios, $request->fecha_inicio);
            if ($aviso) {
                return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
            }
        }

        $data = [
            'id_usuarios'  => $request->id_usuarios,
            'tipo'         => $request->tipo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'comentario'   => $request->comentario ?: null,
            'anyo_devengo' => $check['anyo_devengo'],
            'updateuser'   => Auth::id(),
            'updatedat'    => now(),
        ];

        if ($request->hasFile('fichero') && $request->file('fichero')->isValid()) {
            $data['file_fichero'] = $request->file('fichero')->store('vm/ausencias/' . $request->id_usuarios, 'public');
        }

        DB::table('vm_ausencias')->where('id', $ausId)->update($data);

        $aviso = InformeAprobacionGuard::checkAndLog(
            (int) $request->id_usuarios, $request->fecha_inicio, 'vm_ausencias', 'update', $ausId, $request
        );

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }

    public function destroy(Request $request, Project $project, int $ausId)
    {
        $this->authorize($project);

        $ausencia = DB::table('vm_ausencias')->where('id', $ausId)->where('deleted', 0)->first();
        if (!$ausencia) return response()->json(['error' => 'Ausencia no encontrada.'], 404);

        if (InformeAprobacionGuard::estaCompletado((int) $ausencia->id_usuarios, $ausencia->fecha_inicio)) {
            return response()->json(['error' => 'Este informe ya está aprobado y bloqueado. No se puede modificar.'], 423);
        }
        if (!$request->boolean('confirmar_reset') && $aviso = InformeAprobacionGuard::mensajeSiEnAprobacion((int) $ausencia->id_usuarios, $ausencia->fecha_inicio)) {
            return response()->json(['requiere_confirmacion' => true, 'mensaje' => $aviso], 409);
        }

        DB::table('vm_ausencias')->where('id', $ausId)->update([
            'deleted'    => 1,
            'updateuser' => Auth::id(),
            'updatedat'  => now(),
        ]);

        $aviso = InformeAprobacionGuard::checkAndLog(
            (int) $ausencia->id_usuarios, $ausencia->fecha_inicio, 'vm_ausencias', 'delete', $ausId, $request
        );

        return response()->json(['ok' => true, 'aviso_aprobacion' => $aviso]);
    }
}
