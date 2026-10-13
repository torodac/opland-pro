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
    // Pública por lo mismo que TIPOS_SALIDA: el dashboard aplica este mismo corte a las
    // limpiezas sin reserva, y dos fechas sueltas acabarían separándose.
    public const ANTES_DE = '2026-09-30';

    // "Las de salida": son las limpiezas que ven cómo queda la casa. Las de mantenimiento quedan
    // fuera de esta pantalla por decisión expresa -- además, vm_tareas_mantenimiento no tiene
    // id_reservas, así que no habría forma fiable de atarlas a la reserva.
    // Pública porque el dashboard lista justo estas mismas tareas cuando les falta la reserva:
    // si cada sitio mantuviera su propia lista, acabarían diciendo cosas distintas.
    public const TIPOS_SALIDA = ['Checkout', 'Limpieza salida'];

    // Una limpieza descartada no se hizo, así que no dice nada del estado en que quedó la casa:
    // ni ella ni sus comentarios ni sus fotos cuentan aquí. "Cancelada" es otro valor posible del
    // campo, pero hoy no hay ninguna tarea con él.
    public const ESTADOS_EXCLUIDOS = ['Descartada'];

    // ── El ciclo de vida de la fianza ────────────────────────────────────────
    // Solo se ALMACENAN los tres que nacen de una acción: dar conformidad, aplicar retención, y
    // el cierre de la tarea SSCC que las remata. "Vigente" y "Pte. evaluación" se DERIVAN al
    // pintar, de la fecha de checkout y de si hay decisión. Guardarlos obligaría a un proceso
    // nocturno que los fuera cambiando, y el día que fallara habría estados mintiendo sobre el
    // dinero de un huésped.
    public const ESTADO_VIGENTE    = 'Vigente';
    public const ESTADO_EVALUACION = 'Pte. evaluación';
    public const ESTADO_APROBADA   = 'Pte. devolución';
    public const ESTADO_RETENER    = 'Pte. retención';
    public const ESTADO_DEVUELTA   = 'Devuelta';

    // Tipos de la tarea SSCC que se crea al decidir, junto a los que ya existen en esa tabla
    // ("Validación informes RRHH", "Revisión Novación").
    public const SSCC_DEVOLUCION = 'Devolución fianza';
    public const SSCC_RETENCION  = 'Retención fianza';

    // A dónde va la tarea. "Contabilidad" es un ROL en este proyecto (id 7), no un departamento,
    // así que se manda a Adm/Finanzas (6), que es donde ya van las SSCC de "Revisión Novación".
    public const DEPARTAMENTOS = [
        3 => 'Recepción',
        6 => 'Contabilidad',
    ];

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

        $incidencias = $this->incidenciasDeTareas($ids);

        // Las columnas que se piden a las dos consultas son las mismas y en el mismo orden,
        // porque luego se concatenan: la vista no tiene por qué saber de dónde vino cada foto,
        // solo pintar la etiqueta de procedencia que ya lleva cada una.
        $columnas = fn(string $alias) => [
            'f.id', 'f.file_foto', 'f.nombre', 'f.createdat',
            'f.informe_estancia', 'f.informe_estancia_at',
            'f.retencion_concepto', 'f.retencion_importe',
            "{$alias}.id as tarea_id", "{$alias}.nombre as tarea_nombre",
        ];

        $fotosLimpieza = $ids
            ? DB::table('vm_fotos as f')
                ->join('vm_tareas_limpieza as t', 't.id', '=', 'f.id_tareas_limpieza')
                ->whereIn('f.id_tareas_limpieza', $ids)
                ->where(fn($x) => $x->where('f.deleted', 0)->orWhereNull('f.deleted'))
                ->orderBy('f.createdat')
                ->get($columnas('t'))
                ->each(fn($f) => $f->origen = 'limpieza')
            : collect();

        // Las fotos de las incidencias son la prueba del desperfecto, así que van en la misma
        // rejilla y son marcables igual: es justo lo que hay que enseñar para justificar una
        // retención.
        $idsIncidencias = $incidencias->pluck('id')->all();
        $fotosIncidencias = $idsIncidencias
            ? DB::table('vm_fotos as f')
                ->join('vm_tareas_mantenimiento as m', 'm.id', '=', 'f.id_tareas_mantenimiento')
                ->whereIn('f.id_tareas_mantenimiento', $idsIncidencias)
                ->where(fn($x) => $x->where('f.deleted', 0)->orWhereNull('f.deleted'))
                ->orderBy('f.createdat')
                ->get($columnas('m'))
                ->each(fn($f) => $f->origen = 'incidencia')
            : collect();

        $fotos = $fotosLimpieza->concat($fotosIncidencias)->values();

        return view('vm.dev-fianza', [
            'project'       => $project,
            'reserva'       => $reserva,
            'tareas'        => $tareas,
            'comentarios'   => $comentarios,
            'incidencias'   => $incidencias,
            'fotos'         => $fotos,
            'estado'        => self::estadoFianza($reserva),
            'departamentos' => self::DEPARTAMENTOS,
            'puedeEditar'   => Auth::user()?->canEditTable($project, 'dev_fianzas_default') ?? false,
        ]);
    }

    /**
     * Marca o desmarca una foto para el informe de la estancia que se entrega al propietario.
     *
     * Exige permiso de EDICIÓN como las otras dos acciones de esta pantalla: aunque no mueva
     * dinero, decide qué acaba en un documento que ve el propietario.
     */
    /**
     * Marca una foto para el informe de la estancia y, si procede, le pone retención.
     *
     * Un solo badge con dos sentidos, por decisión expresa: marcar una retención implica que la
     * foto va al informe, y para llevarla al informe SIN retener se deja el importe a cero. El
     * pop-up tiene que decirlo, porque no se adivina.
     *
     * Desmarcar (quitar) borra también la retención: una retención sobre una foto que no va al
     * informe no se sostendría ante el huésped, que es a quien hay que poder enseñársela.
     */
    public function marcarFoto(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $foto = DB::table('vm_fotos')->where('id', $id)->first(['id', 'informe_estancia']);
        abort_unless($foto, 404);

        $datos = $request->validate([
            'quitar'   => 'sometimes|boolean',
            'concepto' => 'nullable|string|max:255',
            'importe'  => 'nullable|numeric|min:0',
        ], [], ['concepto' => 'concepto de la retención', 'importe' => 'importe a retener']);

        if (!empty($datos['quitar'])) {
            DB::table('vm_fotos')->where('id', $id)->update([
                'informe_estancia'      => 0,
                'informe_estancia_user' => null,
                'informe_estancia_at'   => null,
                'retencion_concepto'    => null,
                'retencion_importe'     => null,
                'updatedat'             => now(),
            ]);

            return response()->json(['ok' => true, 'marcada' => false, 'concepto' => null, 'importe' => null]);
        }

        $importe  = isset($datos['importe']) && $datos['importe'] !== '' ? (float) $datos['importe'] : 0.0;
        $concepto = trim((string) ($datos['concepto'] ?? ''));

        // Un importe sin concepto deja una línea que nadie sabrá justificar cuando el huésped
        // pregunte por qué se le ha descontado.
        if ($importe > 0 && $concepto === '') {
            return response()->json(['error' => 'Indica el concepto de la retención.'], 422);
        }

        // La reserva de esta foto, para validar contra su fianza antes de dejar escribir.
        $reservaId = $this->reservaDeFoto($id);
        if ($importe > 0 && $reservaId) {
            $fianza = (float) (DB::table('vm_reservas')->where('id', $reservaId)->value('fianza') ?? 0);
            // La suma de TODAS las fotos de la reserva, con esta ya cambiada, es lo que no puede
            // pasarse: validar foto a foto dejaría colar el exceso repartido entre varias.
            $otras = $this->retencionesDeReserva($reservaId)->where('id', '<>', $id)->sum('retencion_importe');
            if ($fianza > 0 && $otras + $importe > $fianza + 0.005) {
                return response()->json([
                    'error' => 'La retención total (' . number_format($otras + $importe, 2, ',', '.')
                             . ' €) no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).',
                ], 422);
            }
        }

        DB::table('vm_fotos')->where('id', $id)->update([
            'informe_estancia'      => 1,
            'informe_estancia_user' => Auth::id(),
            'informe_estancia_at'   => now(),
            'retencion_concepto'    => $importe > 0 ? $concepto : null,
            'retencion_importe'     => $importe > 0 ? $importe : null,
            'updatedat'             => now(),
        ]);

        return response()->json([
            'ok'       => true,
            'marcada'  => true,
            'concepto' => $importe > 0 ? $concepto : null,
            'importe'  => $importe > 0 ? $importe : null,
        ]);
    }

    /** Aprobar la devolución completa. */
    public function conforme(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza', 'fianza']);
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

        $departamento = $this->validarDepartamento($request);
        if ($departamento instanceof \Illuminate\Http\JsonResponse) {
            return $departamento;
        }

        DB::transaction(function () use ($id, $departamento, $reserva) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'       => self::ESTADO_APROBADA,
                'fianza_departamento' => $departamento,
                'fianza_usuario'      => Auth::id(),
                'fianza_fecha'        => now(),
                'updatedat'           => now(),
            ]);

            $this->crearTareaSscc($id, $departamento, self::SSCC_DEVOLUCION,
                'Devolver la fianza íntegra (' . number_format((float) ($reserva->fianza ?? 0), 2, ',', '.') . ' €).');
        });

        return response()->json(['ok' => true, 'estado' => self::ESTADO_APROBADA]);
    }

    /**
     * Confirmar la retención que se ha ido construyendo foto a foto.
     *
     * Ya no llega un importe suelto desde una modal: las líneas están en las fotos, y aquí solo
     * se suman y se congela el total en fianza_retencion, que es lo que lee el listado. Así el
     * importe que se le cobra al huésped siempre tiene detrás una foto que enseñarle.
     */
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

        $departamento = $this->validarDepartamento($request);
        if ($departamento instanceof \Illuminate\Http\JsonResponse) {
            return $departamento;
        }

        $lineas = $this->retencionesDeReserva($id);
        if ($lineas->isEmpty()) {
            return response()->json([
                'error' => 'No hay ninguna foto con retención. Pulsa el escudo de una foto para indicar concepto e importe.',
            ], 422);
        }

        $total  = (float) $lineas->sum('retencion_importe');
        $fianza = (float) ($reserva->fianza ?? 0);

        if ($fianza <= 0) {
            return response()->json(['error' => 'Esta reserva no tiene fianza, así que no hay nada que retener.'], 422);
        }
        if ($total > $fianza + 0.005) {
            return response()->json([
                'error' => 'La retención (' . number_format($total, 2, ',', '.')
                         . ' €) no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).',
            ], 422);
        }

        // El motivo se compone de los conceptos de las fotos: ese campo lo siguen leyendo el
        // listado y la ficha de la reserva, así que se mantiene útil en vez de dejarlo vacío.
        $motivo = $lineas->map(fn($l) => trim((string) $l->retencion_concepto)
                . ' (' . number_format((float) $l->retencion_importe, 2, ',', '.') . ' €)')
            ->implode('; ');

        DB::transaction(function () use ($id, $total, $motivo, $departamento, $lineas) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'           => self::ESTADO_RETENER,
                'fianza_retencion'        => $total,
                'fianza_motivo_retencion' => $motivo,
                'fianza_departamento'     => $departamento,
                'fianza_usuario'          => Auth::id(),
                'fianza_fecha'            => now(),
                'updatedat'               => now(),
            ]);

            $this->crearTareaSscc($id, $departamento, self::SSCC_RETENCION,
                'Retener ' . number_format($total, 2, ',', '.') . ' € de la fianza, por '
                . $lineas->count() . ' concepto(s): ' . $motivo);
        });

        return response()->json(['ok' => true, 'estado' => self::ESTADO_RETENER, 'total' => $total]);
    }

    // ── Piezas compartidas por las dos decisiones ────────────────────────────

    /** El departamento al que va la tarea SSCC. Devuelve el id, o la respuesta de error. */
    private function validarDepartamento(Request $request)
    {
        $datos = $request->validate([
            'departamento' => 'required|integer',
        ], [], ['departamento' => 'departamento']);

        $dep = (int) $datos['departamento'];
        if (!array_key_exists($dep, self::DEPARTAMENTOS)) {
            return response()->json(['error' => 'Elige a qué departamento se asigna la tarea.'], 422);
        }

        return $dep;
    }

    /**
     * La tarea que hace que la decisión llegue a quien mueve el dinero.
     *
     * Lleva id_reservas porque de ahí sale el paso a "Devuelta": sin esa columna no habría forma
     * de saber qué fianza cierra cada tarea.
     */
    private function crearTareaSscc(int $reservaId, int $departamento, string $tipo, string $descripcion): void
    {
        $reserva = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $reservaId)
            ->first(['r.booking_id', 'r.id_propiedades', 'p.nombre as propiedad']);

        DB::table('vm_tareas_sscc')->insert([
            'nombre'            => $tipo . ' · reserva ' . ($reserva->booking_id ?? $reservaId)
                                 . ' (' . ($reserva->propiedad ?: 'sin propiedad') . ')',
            'descripcion'       => $descripcion,
            'Tipo'              => $tipo,
            'estado'            => 'Nueva',
            'id_departamento'   => $departamento,
            'id_reservas'       => $reservaId,
            'id_propiedades'    => $reserva->id_propiedades ?? null,
            'fecha_planificada' => now()->toDateString(),
            'deleted'           => 0,
            'hidden'            => 0,
            'blocked'           => 0,
            'createuser'        => Auth::id(),
            'createdat'         => now(),
            'updatedat'         => now(),
        ]);
    }

    /** Las fotos de una reserva que llevan retención, en el orden en que se marcaron. */
    private function retencionesDeReserva(int $reservaId)
    {
        $ids = $this->tareasDeSalida($reservaId)->pluck('id')->all();
        if (!$ids) {
            return collect();
        }

        $incidencias = $this->incidenciasDeTareas($ids)->pluck('id')->all();

        return DB::table('vm_fotos')
            ->where(function ($q) use ($ids, $incidencias) {
                $q->whereIn('id_tareas_limpieza', $ids);
                if ($incidencias) {
                    $q->orWhereIn('id_tareas_mantenimiento', $incidencias);
                }
            })
            ->whereNotNull('retencion_importe')
            ->where('retencion_importe', '>', 0)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('informe_estancia_at')
            ->get(['id', 'file_foto', 'retencion_concepto', 'retencion_importe']);
    }

    /** A qué reserva pertenece una foto, por su tarea (de limpieza o de la incidencia). */
    private function reservaDeFoto(int $fotoId): ?int
    {
        $foto = DB::table('vm_fotos')->where('id', $fotoId)
            ->first(['id_tareas_limpieza', 'id_tareas_mantenimiento']);
        if (!$foto) {
            return null;
        }

        if ($foto->id_tareas_limpieza) {
            return DB::table('vm_tareas_limpieza')->where('id', $foto->id_tareas_limpieza)->value('id_reservas');
        }

        if ($foto->id_tareas_mantenimiento) {
            // La incidencia no referencia la reserva: se llega por la limpieza de la que salió.
            $limpieza = DB::table('vm_tareas_mantenimiento')->where('id', $foto->id_tareas_mantenimiento)
                ->value('id_tareas_limpieza');

            return $limpieza
                ? DB::table('vm_tareas_limpieza')->where('id', $limpieza)->value('id_reservas')
                : null;
        }

        return null;
    }

    // ── Consultas compartidas ────────────────────────────────────────────────

    /** Las incidencias reportadas que cuelgan de estas tareas de limpieza. */
    private function incidenciasDeTareas(array $idsTareas)
    {
        if (!$idsTareas) {
            return collect();
        }

        return DB::table('vm_tareas_mantenimiento as m')
            ->whereIn('m.id_tareas_limpieza', $idsTareas)
            ->where('m.reported_issue', 1)
            ->where(fn($q) => $q->where('m.deleted', 0)->orWhereNull('m.deleted'))
            ->orderBy('m.breezeway_createdat')
            ->get(['m.id', 'm.nombre', 'm.descripcion', 'm.estado', 'm.Tipo',
                   'm.departamento_breezeway', 'm.breezeway_createdat', 'm.breezeway_task_id',
                   'm.vinculo_deducido', 'm.id_tareas_limpieza']);
    }

    /**
     * El estado que se le enseña a la gente, que no siempre es el guardado.
     *
     * "Vigente" y "Pte. evaluación" se derivan aquí en vez de almacenarse: dependen solo de la
     * fecha y de si hay decisión, y guardarlos obligaría a un proceso nocturno que los fuera
     * cambiando. El día que ese proceso fallara, la pantalla mentiría sobre dinero de un huésped.
     *
     * Con fianza 0 no hay nada que decidir y no se muestra estado, que es lo que ya pasaba antes.
     */
    public static function estadoFianza(?object $reserva): ?string
    {
        if (!$reserva || (float) ($reserva->fianza ?? 0) <= 0) {
            return null;
        }

        // Una decisión tomada manda sobre cualquier cálculo.
        if (!empty($reserva->estado_fianza)) {
            return !empty($reserva->sscc_finalizada) ? self::ESTADO_DEVUELTA : $reserva->estado_fianza;
        }

        $checkout = $reserva->check_out_date ?? null;

        return ($checkout && $checkout >= now()->toDateString())
            ? self::ESTADO_VIGENTE
            : self::ESTADO_EVALUACION;
    }

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
                r.channel,
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
                    AND COALESCE(t.deleted, 0) = 0) AS n_fotos,
                (SELECT count(*) FROM vm_tareas_mantenimiento m
                   JOIN vm_tareas_limpieza t ON t.id = m.id_tareas_limpieza
                  WHERE m.reported_issue = 1 AND COALESCE(m.deleted, 0) = 0
                    AND t.id_reservas = r.id AND t.\"Tipo\" IN ({$tipos})
                    AND COALESCE(t.estado, '') NOT IN ({$excluidos})
                    AND COALESCE(t.deleted, 0) = 0) AS n_incidencias,
                -- Si la tarea SSCC de esta fianza ya está cerrada, el estado pasa a Devuelta.
                -- Se mira al pintar en vez de copiarse a la reserva: así no hay dos sitios
                -- diciendo cosas distintas sobre si el dinero se ha movido o no.
                --
                -- 'Completada' es el cierre, igual que en limpieza y mantenimiento. Hubo un
                -- segundo valor, 'Finalizada', que solo existía en esta tabla y que se retiró el
                -- 2026-10-13: tener dos formas de decir lo mismo obligaba a que cada consulta se
                -- acordara de aceptar las dos, y la que no se acordara se equivocaría en
                -- silencio sobre si una fianza ya está devuelta.
                --
                -- Cancelada y Descartada no cuentan: ahí no se ha movido ningún dinero.
                (SELECT count(*) FROM vm_tareas_sscc s
                  WHERE s.id_reservas = r.id AND COALESCE(s.deleted, 0) = 0
                    AND s.estado = 'Completada') AS sscc_finalizada
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
