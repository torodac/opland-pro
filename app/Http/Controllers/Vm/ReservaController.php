<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Ficha de una reserva, con vista propia como la de fichaje. Los datos de la reserva vienen de Icnea
// y se muestran en solo lectura. Lo único que se edita aquí es la decisión de fianza (estado,
// retención, motivo, quién decidió y cuándo), y se hace con el permiso de Dev. fianzas.
//
// Ojo: cambiar la decisión a mano no crea tareas SSCC ni envía correos. Eso solo ocurre al decidir
// desde Dev. fianzas. Aquí queda para corregir o rehacer, no como vía normal de decisión.
class ReservaController extends Controller
{
    public const ESTADOS_EDITABLES = ['Pte. devolución', 'Pte. retención'];

    public function show(Project $project, int $id)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'reservas'), 403);

        $reserva = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $id)
            ->where(fn($q) => $q->where('r.deleted', 0)->orWhereNull('r.deleted'))
            ->first(['r.*', 'p.nombre as propiedad']);
        abort_unless($reserva, 404);

        $limpiezas = DB::table('vm_tareas_limpieza')
            ->where('id_reservas', $id)
            ->whereIn('Tipo', DevFianzasController::TIPOS_SALIDA)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('fecha_planificada')
            ->get(['id', 'nombre', 'fecha_planificada', 'estado']);

        $sscc = DB::table('vm_tareas_sscc')
            ->where('id_reservas', $id)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('id')
            ->get(['id', 'nombre', 'Tipo', 'estado']);

        // El estado que se enseña es el derivado, igual que en Dev. fianzas.
        $reserva->sscc_finalizada = ($sscc->count() > 0 && $sscc->every(fn($t) => $t->estado === 'Completada')) ? 1 : 0;

        return view('vm.reserva', [
            'project'     => $project,
            'reserva'     => $reserva,
            'estado'      => DevFianzasController::estadoFianza($reserva),
            'limpiezas'   => $limpiezas,
            'sscc'        => $sscc,
            'usuarios'    => DB::table('admin_users')->orderBy('name')->get(['id', 'name']),
            'estados'     => self::ESTADOS_EDITABLES,
            'puedeEditar' => Auth::user()?->canEditTable($project, 'dev_fianzas_default') ?? false,
        ]);
    }

    /** Guarda la decisión de fianza tal como se edita en la ficha. Solo cambia los cinco campos de fianza. */
    public function guardarFianza(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'fianza']);
        abort_unless($reserva, 404);

        $datos = $request->validate([
            'estado_fianza'           => 'nullable|in:' . implode(',', self::ESTADOS_EDITABLES),
            'fianza_retencion'        => 'nullable|numeric|min:0',
            'fianza_motivo_retencion' => 'nullable|string|max:500',
            'fianza_usuario'          => 'nullable|integer|exists:admin_users,id',
            'fianza_fecha'            => 'nullable|date',
        ], [], [
            'estado_fianza' => 'estado de la fianza', 'fianza_retencion' => 'retención',
            'fianza_motivo_retencion' => 'motivo', 'fianza_usuario' => 'decidido por', 'fianza_fecha' => 'fecha de decisión',
        ]);

        $fianza = (float) ($reserva->fianza ?? 0);
        $retencion = round((float) ($datos['fianza_retencion'] ?? 0), 2);

        if ($fianza > 0 && $retencion > $fianza + 0.005) {
            return back()->withErrors(['fianza_retencion' => 'La retención no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).'])->withInput();
        }
        if (($datos['estado_fianza'] ?? null) === 'Pte. retención' && $retencion <= 0) {
            return back()->withErrors(['fianza_retencion' => 'Una retención necesita un importe mayor que cero.'])->withInput();
        }

        // Un campo vacío del formulario llega como cadena vacía: se guarda como nulo, no como ''.
        $vacioANulo = fn($valor) => ($valor === '' || $valor === null) ? null : $valor;

        DB::table('vm_reservas')->where('id', $id)->update([
            'estado_fianza'           => $vacioANulo($datos['estado_fianza'] ?? null),
            'fianza_retencion'        => $retencion > 0 ? $retencion : null,
            'fianza_motivo_retencion' => $vacioANulo($datos['fianza_motivo_retencion'] ?? null),
            'fianza_usuario'          => $vacioANulo($datos['fianza_usuario'] ?? null),
            'fianza_fecha'            => $vacioANulo($datos['fianza_fecha'] ?? null),
            'updatedat'               => now(),
        ]);

        return redirect()->route('vm.reserva_form', [$project->slug, $id])->with('ok', 'Fianza guardada.');
    }
}
