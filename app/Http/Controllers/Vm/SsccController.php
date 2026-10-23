<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Ficha de una tarea SSCC. Vista propia, como la de fichaje: se ve todo, pero el estado no se
// cambia desde aquí. Marcar una tarea como hecha desencadena otras cosas (la fianza pasa a Devuelta
// y se avisa al huésped), así que solo se hace desde el dashboard, por marcarSsccHecho. Lo único que
// se escribe desde esta ficha es el comentario.
class SsccController extends Controller
{
    public function show(Project $project, int $id)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'tareas_sscc'), 403);

        $tarea = DB::table('vm_tareas_sscc')->where('id', $id)->where('deleted', 0)->first();
        abort_unless($tarea, 404);

        $propiedad    = $tarea->id_propiedades ? DB::table('vm_propiedades')->where('id', $tarea->id_propiedades)->value('nombre') : null;
        $departamento = $tarea->id_departamento ? DB::table('vm_departamentos')->where('id', $tarea->id_departamento)->value('nombre') : null;
        $rol          = $tarea->id_rol ? DB::table('vm_roles')->where('id', $tarea->id_rol)->value('nombre') : null;

        // Accesos directos: solo los que corresponden al tipo de tarea. Una fianza enlaza con su
        // ficha; una revisión de novación, con su novación (propiedad y mes).
        $enlaces = [];
        if (in_array($tarea->Tipo, [DevFianzasController::SSCC_DEVOLUCION, DevFianzasController::SSCC_RETENCION], true) && $tarea->id_reservas) {
            $booking = DB::table('vm_reservas')->where('id', $tarea->id_reservas)->value('booking_id');
            $enlaces[] = [
                'etiqueta' => 'Ficha de la fianza' . ($booking ? ' · reserva ' . $booking : ''),
                'url'      => route('vm.dev-fianza', [$project->slug, $tarea->id_reservas]),
                'icono'    => 'ti-shield-dollar',
            ];
        }
        // Las revisiones creadas antes de existir novacion_periodo no lo tienen: su mes es el de la
        // fecha planificada, que es el último día del mes de la novación.
        $periodo = $tarea->novacion_periodo ?: ($tarea->fecha_planificada ? substr((string) $tarea->fecha_planificada, 0, 7) : null);
        if ($tarea->Tipo === 'Revisión Novación' && $tarea->id_propiedades && $periodo) {
            [$anio, $mes] = array_map('intval', explode('-', $periodo));
            $enlaces[] = [
                'etiqueta' => 'Novación de ' . ($propiedad ?: 'la propiedad') . ' · ' . $periodo,
                'url'      => route('novaciones', ['project' => $project->slug, 'year' => $anio, 'month' => $mes, 'prop_id' => $tarea->id_propiedades]),
                'icono'    => 'ti-file-invoice',
            ];
        }

        return view('vm.sscc', [
            'project'      => $project,
            'tarea'        => $tarea,
            'propiedad'    => $propiedad,
            'departamento' => $departamento,
            'rol'          => $rol,
            'enlaces'      => $enlaces,
            'puedeComentar' => Auth::user()?->canViewTable($project, 'tareas_sscc') ?? false,
            // Mismo botón Hecho que el dashboard: aparece mientras la tarea no esté completada. Si quien
            // lo pulsa no tiene usuario de VM, el servidor lo rechaza con el mismo aviso que el dashboard.
            'puedeHecho'    => $tarea->estado !== 'Completada',
        ]);
    }

    /**
     * Guarda el comentario. Es lo único que esta ficha escribe; el estado no pasa por aquí. Lo puede
     * escribir quien puede ver la ficha: el comentario es una nota, no un cambio sobre la tarea.
     */
    public function guardarComentario(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'tareas_sscc'), 403);

        $tarea = DB::table('vm_tareas_sscc')->where('id', $id)->where('deleted', 0)->first(['id', 'blocked', 'estado']);
        abort_unless($tarea, 404);
        abort_if($tarea->blocked, 403, 'Esta tarea está bloqueada y no puede editarse.');
        // Una vez marcada como hecha, el comentario queda cerrado: es parte de lo que se dejó constancia.
        abort_if($tarea->estado === 'Completada', 403, 'Esta tarea ya está hecha: el comentario no se puede editar.');

        $datos = $request->validate(['comentario' => 'nullable|string|max:5000']);

        DB::table('vm_tareas_sscc')->where('id', $id)->update([
            'comentario' => trim((string) ($datos['comentario'] ?? '')) ?: null,
            'updateuser' => Auth::id(),
            'updatedat'  => now(),
        ]);

        // La ficha lo guarda sola, en segundo plano, y espera JSON. Un envío de formulario normal vuelve atrás.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Comentario guardado.');
    }
}
