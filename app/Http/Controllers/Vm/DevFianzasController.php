<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Devolución de fianzas: revisar lo que dejó cada huésped al salir y decidir si se le devuelve la
// fianza entera o se le retiene parte.
//
// El acceso de lectura lo resuelve el middleware menu.access desde la entrada del menú (tabla
// virtual dev_fianzas_default), igual que jerarquias o permisos-roles. Las dos acciones que
// deciden sobre dinero exigen además permiso de EDICIÓN sobre esa tabla, que es cosa aparte de
// poder mirar la pantalla.
class DevFianzasController extends Controller
{
    // Solo entran las salidas de octubre en adelante y anteriores a hoy. Lo segundo no es un
    // detalle: la fianza se captura en la pasada de icnea:sync-importes de las 06:00 y los
    // comentarios llegan a las 22:00, así que el día del checkout la información está a medias.
    // Exclusivo: la primera salida que entra es la del 1 de octubre.
    private const ANTES_DE = '2026-09-30';

    // "Las de salida": son las limpiezas que ven cómo queda la casa. Las de mantenimiento quedan
    // fuera de esta pantalla por decisión expresa -- además, vm_tareas_mantenimiento no tiene
    // id_reservas, así que no habría forma fiable de atarlas a la reserva.
    private const TIPOS_SALIDA = ['Checkout', 'Limpieza salida'];

    // Una limpieza descartada no se hizo, así que no dice nada del estado en que quedó la casa:
    // ni ella ni sus comentarios ni sus fotos cuentan aquí. "Cancelada" es otro valor posible del
    // campo, pero hoy no hay ninguna tarea con él.
    private const ESTADOS_EXCLUIDOS = ['Descartada'];

    public const ESTADO_APROBADA = 'Aprobada devolución';
    public const ESTADO_RETENER  = 'Aplicar retención';

    public function index(Request $request, Project $project)
    {
        $q = trim((string) $request->input('q', ''));

        $filas = $this->baseQuery()
            ->when($q !== '', fn($query) => $query->where(function ($sub) use ($q) {
                $sub->where('r.booking_id', 'ilike', "%{$q}%")
                    ->orWhere('r.nombre', 'ilike', "%{$q}%")
                    ->orWhere('r.guest_name', 'ilike', "%{$q}%");
            }))
            ->orderByDesc('r.check_out_date')
            ->orderByDesc('r.booking_id')
            ->get();

        return view('vm.dev-fianzas', [
            'project'   => $project,
            'filas'     => $filas,
            'q'         => $q,
            'puedeEditar' => Auth::user()?->canEditTable($project, 'dev_fianzas_default') ?? false,
        ]);
    }

    public function show(Request $request, Project $project, int $id)
    {
        $reserva = $this->baseQuery()->where('r.id', $id)->first();
        abort_unless($reserva, 404);

        $tareas = $this->tareasDeSalida($id);
        $ids    = $tareas->pluck('id')->all();

        $comentarios = $ids
            ? DB::table('vm_tareas_comentarios as c')
                ->join('vm_tareas_limpieza as t', 't.id', '=', 'c.id_tarea')
                ->where('c.tipo', 'limpieza')
                ->whereIn('c.id_tarea', $ids)
                ->where(fn($x) => $x->where('c.deleted', 0)->orWhereNull('c.deleted'))
                ->orderByDesc('c.fecha')
                ->get(['c.id', 'c.comentario', 'c.fecha', 't.id as tarea_id', 't.nombre as tarea_nombre'])
            : collect();

        $fotos = $ids
            ? DB::table('vm_fotos as f')
                ->join('vm_tareas_limpieza as t', 't.id', '=', 'f.id_tareas_limpieza')
                ->whereIn('f.id_tareas_limpieza', $ids)
                ->where(fn($x) => $x->where('f.deleted', 0)->orWhereNull('f.deleted'))
                ->orderBy('f.createdat')
                ->get(['f.id', 'f.file_foto', 'f.nombre', 'f.createdat', 't.id as tarea_id', 't.nombre as tarea_nombre'])
            : collect();

        return view('vm.dev-fianza', [
            'project'     => $project,
            'reserva'     => $reserva,
            'tareas'      => $tareas,
            'comentarios' => $comentarios,
            'fotos'       => $fotos,
            'puedeEditar' => Auth::user()?->canEditTable($project, 'dev_fianzas_default') ?? false,
        ]);
    }

    /** Aprobar la devolución completa. */
    public function conforme(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza']);
        abort_unless($reserva, 404);

        // Decidir sobre una reserva ya decidida no es un reintento inocente: el estado se cambia
        // de vuelta a blanco desde la ficha de la reserva, a propósito, para que quede claro que
        // es una corrección.
        if (!empty($reserva->estado_fianza)) {
            return response()->json([
                'error' => 'Esta reserva ya tiene decisión: "' . $reserva->estado_fianza . '". '
                         . 'Para rehacerla, deja el estado en blanco desde la ficha de la reserva.',
            ], 422);
        }

        DB::table('vm_reservas')->where('id', $id)->update([
            'estado_fianza' => self::ESTADO_APROBADA,
            'fianza_usuario' => Auth::id(),
            'fianza_fecha'   => now(),
            'updatedat'      => now(),
        ]);

        return response()->json(['ok' => true, 'estado' => self::ESTADO_APROBADA]);
    }

    /** Retener parte de la fianza: importe + motivo, los dos obligatorios. */
    public function retener(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza', 'fianza']);
        abort_unless($reserva, 404);

        if (!empty($reserva->estado_fianza)) {
            return response()->json([
                'error' => 'Esta reserva ya tiene decisión: "' . $reserva->estado_fianza . '". '
                         . 'Para rehacerla, deja el estado en blanco desde la ficha de la reserva.',
            ], 422);
        }

        $datos = $request->validate([
            'retencion' => 'required|numeric|min:0.01',
            'motivo'    => 'required|string|max:2000',
        ], [], [
            'retencion' => 'importe de la retención',
            'motivo'    => 'motivo de la retención',
        ]);

        $fianza = (float) ($reserva->fianza ?? 0);
        if ($fianza <= 0) {
            return response()->json(['error' => 'Esta reserva no tiene fianza, así que no hay nada que retener.'], 422);
        }
        if ((float) $datos['retencion'] > $fianza + 0.005) {
            return response()->json([
                'error' => 'La retención (' . number_format((float) $datos['retencion'], 2, ',', '.')
                         . ' €) no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).',
            ], 422);
        }

        DB::table('vm_reservas')->where('id', $id)->update([
            'estado_fianza'           => self::ESTADO_RETENER,
            'fianza_retencion'        => (float) $datos['retencion'],
            'fianza_motivo_retencion' => trim($datos['motivo']),
            'fianza_usuario'          => Auth::id(),
            'fianza_fecha'            => now(),
            'updatedat'               => now(),
        ]);

        return response()->json(['ok' => true, 'estado' => self::ESTADO_RETENER]);
    }

    // ── Consultas compartidas ────────────────────────────────────────────────

    /**
     * Las reservas del periodo con sus contadores. Los dos recuentos se hacen con subconsultas
     * correlacionadas en vez de JOIN + GROUP BY: con dos tablas hijas a la vez, el JOIN multiplica
     * filas y los contadores saldrían inflados uno por el otro.
     */
    private function baseQuery()
    {
        $tipos     = "'" . implode("','", self::TIPOS_SALIDA) . "'";
        $excluidos = "'" . implode("','", self::ESTADOS_EXCLUIDOS) . "'";

        return DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->leftJoin('admin_users as au', 'au.id', '=', 'r.fianza_usuario')
            ->where(fn($x) => $x->where('r.deleted', 0)->orWhereNull('r.deleted'))
            // Las canceladas no tienen nada que decidir: no hubo estancia, ni limpieza de salida,
            // ni fianza. Y de hecho Icnea devuelve pending_deposit vacío para ellas, así que
            // tampoco se les pregunta nunca (icnea:sync-importes ya las excluye).
            ->where('r.booking_status', '<>', 'cancelled')
            ->where('r.check_out_date', '>', self::ANTES_DE)
            ->whereRaw('r.check_out_date < CURRENT_DATE')
            ->selectRaw("
                r.id, r.booking_id, r.nombre, r.guest_name, r.check_out_date, r.check_in_date,
                r.fianza, r.estado_fianza, r.fianza_retencion, r.fianza_motivo_retencion,
                r.fianza_fecha, r.booking_status,
                p.nombre AS propiedad,
                au.name  AS decidido_por,
                (SELECT count(*) FROM vm_tareas_limpieza t
                  WHERE t.id_reservas = r.id AND t.\"Tipo\" IN ({$tipos})
                    AND COALESCE(t.estado, '') NOT IN ({$excluidos})
                    AND COALESCE(t.deleted, 0) = 0) AS n_tareas,
                (SELECT count(*) FROM vm_tareas_comentarios c
                   JOIN vm_tareas_limpieza t ON t.id = c.id_tarea
                  WHERE c.tipo = 'limpieza' AND COALESCE(c.deleted, 0) = 0
                    AND t.id_reservas = r.id AND t.\"Tipo\" IN ({$tipos})
                    AND COALESCE(t.estado, '') NOT IN ({$excluidos})
                    AND COALESCE(t.deleted, 0) = 0) AS n_comentarios,
                (SELECT count(*) FROM vm_fotos f
                   JOIN vm_tareas_limpieza t ON t.id = f.id_tareas_limpieza
                  WHERE COALESCE(f.deleted, 0) = 0
                    AND t.id_reservas = r.id AND t.\"Tipo\" IN ({$tipos})
                    AND COALESCE(t.estado, '') NOT IN ({$excluidos})
                    AND COALESCE(t.deleted, 0) = 0) AS n_fotos
            ");
    }

    /** Limpiezas de salida de una reserva. */
    private function tareasDeSalida(int $reservaId)
    {
        return DB::table('vm_tareas_limpieza')
            ->where('id_reservas', $reservaId)
            ->whereIn('Tipo', self::TIPOS_SALIDA)
            ->whereNotIn(DB::raw("COALESCE(estado, '')"), self::ESTADOS_EXCLUIDOS)
            ->where(fn($x) => $x->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('fecha_planificada')
            ->get(['id', 'nombre', 'fecha_planificada', 'estado', 'breezeway_task_id']);
    }
}
