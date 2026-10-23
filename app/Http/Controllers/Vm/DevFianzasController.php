<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Mail\VmFianzaAvisoMail;
use App\Services\FianzaAuditoria;
use App\Services\SsccVisibilidad;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    // el cierre de la tarea SSCC que las remata. "Vigente" y "Pte. revisión" se DERIVAN al
    // pintar, de la fecha de checkout y de si hay decisión. Guardarlos obligaría a un proceso
    // nocturno que los fuera cambiando, y el día que fallara habría estados mintiendo sobre el
    // dinero de un huésped.
    // Lo que se muestra. Vigente, Pte. revisión, Pte. IBAN, Pte. devolución, Conforme, Reclamada y
    // Devuelta se calculan en estadoFianza(), no se guardan.
    public const ESTADO_VIGENTE         = 'Vigente';
    public const ESTADO_REVISION        = 'Pte. revisión';
    public const ESTADO_PTE_IBAN        = 'Pte. IBAN';
    public const ESTADO_PTE_DEVOLUCION  = 'Pte. devolución';
    public const ESTADO_CONFORME        = 'Conforme';
    public const ESTADO_RECLAMADA       = 'Reclamada';
    public const ESTADO_DEVUELTA        = 'Devuelta';

    // Estados de una devolución que todavía no está cerrada. Son los que se cuentan en el listado.
    public const ESTADOS_NO_FINALES = [self::ESTADO_REVISION, self::ESTADO_PTE_IBAN, self::ESTADO_PTE_DEVOLUCION];

    // Decisión guardada en vm_reservas.estado_fianza: conformidad (valor de Pte. devolución) o
    // retención. Son marcas de la decisión, no el estado que se enseña.
    public const ESTADO_APROBADA = 'Pte. devolución';
    public const ESTADO_RETENER  = 'Pte. retención';

    // Tipos de la tarea SSCC que se crea al decidir, junto a los que ya existen en esa tabla
    // ("Validación informes RRHH", "Revisión Novación").
    public const SSCC_DEVOLUCION = 'Devolución fianza';
    public const SSCC_RETENCION  = 'Retención fianza';

    // Cómo se devuelve la fianza. Icnea no da el método de pago, así que lo elige quien decide.
    public const CANALES_COBRO = ['TPV virtual', 'TPV físico', 'Transferencia'];

    // Las reservas de Airbnb no tienen fianza que devolver desde Opland: su canal es fijo, no crean
    // tareas, y la retención avisa solo a Borja para que reclame.
    public const CANAL_AIRBNB = 'Airbnb';

    // Marca que queda en el motivo interno de una retención sin evidencias. Es para el equipo, no
    // para el huésped: el correo al huésped la quita.
    public const SUFIJO_SIN_EVIDENCIAS = ' · sin evidencias';

    // Quién recibe la tarea según el canal. Carol es el rol Contabilidad (7); en TPV físico, las dos
    // tareas van a Coordinador recepción (14) y Empleado Recepción (16). La transferencia no crea
    // tarea al decidir: la de Carol nace cuando el IBAN ya está en Opland.
    public const TAREAS_POR_CANAL = [
        'TPV virtual'   => [['rol' => 7]],
        'TPV físico'    => [['rol' => 14], ['rol' => 16]],
        'Transferencia' => [],
        'Airbnb'        => [],
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

        // Recuento de las devoluciones que aún no están cerradas, sobre todo el listado.
        $stats = array_fill_keys(self::ESTADOS_NO_FINALES, 0);
        foreach ($filas as $fila) {
            $fila->estado_calc = self::estadoFianza($fila);
            if (array_key_exists($fila->estado_calc, $stats)) {
                $stats[$fila->estado_calc]++;
            }
        }

        // Las reservas sin limpieza de salida asociada tienen su propio contador, aparte de los estados.
        $sinLimpieza = $filas->filter(fn($f) => (int) $f->n_tareas === 0)->count();

        // Un stat por canal, con su logo. Cuenta sobre el listado completo, como los de estado.
        $porCanal = $filas->groupBy(fn($f) => (string) ($f->channel ?? ''))->map->count()->sortDesc();
        $canalActivo = in_array($request->input('canal'), $porCanal->keys()->all(), true) ? (string) $request->input('canal') : null;

        // Un stat pulsado filtra la lista; volver a pulsarlo lo quita.
        $statActiva = in_array($request->input('stat'), array_merge(self::ESTADOS_NO_FINALES, ['sin_limpieza']), true) ? $request->input('stat') : null;
        if ($statActiva === 'sin_limpieza') {
            $filas = $filas->filter(fn($f) => (int) $f->n_tareas === 0)->values();
        } elseif ($statActiva) {
            $filas = $filas->filter(fn($f) => $f->estado_calc === $statActiva)->values();
        }

        if ($canalActivo !== null) {
            $filas = $filas->filter(fn($f) => (string) ($f->channel ?? '') === $canalActivo)->values();
        }

        return view('vm.dev-fianzas', [
            'project'   => $project,
            'filas'     => $filas,
            'stats'     => $stats,
            'sinLimpieza' => $sinLimpieza,
            'porCanal'    => $porCanal,
            'canalActivo' => $canalActivo,
            'statActiva' => $statActiva,
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

        // Las tareas SSCC de la reserva, para el bloque Gestión. Las borradas (la gemela de TPV físico
        // o las del reinicio) no se enseñan.
        $sscc = DB::table('vm_tareas_sscc as s')
            ->leftJoin('vm_roles as ro', 'ro.id', '=', 's.id_rol')
            ->leftJoin('vm_departamentos as de', 'de.id', '=', 's.id_departamento')
            ->where('s.id_reservas', $id)
            ->where(fn($x) => $x->where('s.deleted', 0)->orWhereNull('s.deleted'))
            ->orderBy('s.id')
            ->get(['s.id', 's.Tipo', 's.estado', 's.updatedat', 's.control_user', 's.id_departamento', 's.id_rol',
                   'ro.nombre as rol', 'de.nombre as departamento']);

        // Una tarea que no es suya se lista, pero no se abre: la vista lo avisa en lugar de enlazarla.
        foreach ($sscc as $t) {
            $t->puedeVer = SsccVisibilidad::puedeVer($project, $t);
        }

        return view('vm.dev-fianza', [
            'project'       => $project,
            'reserva'       => $reserva,
            'tareas'        => $tareas,
            'comentarios'   => $comentarios,
            'incidencias'   => $incidencias,
            'fotos'         => $fotos,
            'sscc'          => $sscc,
            'estado'        => self::estadoFianza($reserva),
            'esAirbnb'      => self::esAirbnb($reserva->channel),
            'canales'       => self::esAirbnb($reserva->channel) ? [self::CANAL_AIRBNB] : self::CANALES_COBRO,
            'iban'          => FianzaIbanController::ibanParaVer($id, Auth::user()),
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

            if ($reservaDeFoto = $this->reservaDeFoto($id)) {
                FianzaAuditoria::registrar($reservaDeFoto, 'foto_quitada', ['foto' => $id]);
            }
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

        if ($reservaId) {
            FianzaAuditoria::registrar($reservaId, 'foto_marcada', [
                'foto' => $id, 'concepto' => $importe > 0 ? $concepto : null, 'importe' => $importe > 0 ? $importe : null,
            ]);
        }

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

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza', 'fianza', 'channel']);
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

        $canal = $this->validarCanal($request, $reserva);
        if ($canal instanceof \Illuminate\Http\JsonResponse) {
            return $canal;
        }

        DB::transaction(function () use ($id, $canal, $reserva) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'       => self::ESTADO_APROBADA,
                'fianza_canal_cobro'  => $canal,
                'fianza_usuario'      => Auth::id(),
                'fianza_fecha'        => now(),
                'updatedat'           => now(),
            ]);

            $this->crearTareasDeCobro($id, $canal, self::SSCC_DEVOLUCION,
                'Devolver la fianza íntegra (' . number_format((float) ($reserva->fianza ?? 0), 2, ',', '.') . ' €) por ' . $canal . '.');
        });

        $this->avisarDecision($id, $canal, (float) ($reserva->fianza ?? 0), 0.0, '');

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

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza', 'fianza', 'channel']);
        abort_unless($reserva, 404);

        if (!empty($reserva->estado_fianza)) {
            return response()->json([
                'error' => 'Esta reserva ya tiene decisión: "' . $reserva->estado_fianza . '". '
                         . 'Para rehacerla, deja el estado en blanco desde la ficha de la reserva.',
            ], 422);
        }

        $canal = $this->validarCanal($request, $reserva);
        if ($canal instanceof \Illuminate\Http\JsonResponse) {
            return $canal;
        }

        $lineas = $this->retencionesDeReserva($id);
        if ($lineas->isEmpty()) {
            return response()->json([
                'error' => 'No hay ninguna foto con retención. Pulsa el escudo de una foto para indicar concepto e importe.',
            ], 422);
        }

        $total  = (float) $lineas->sum('retencion_importe');
        $fianza = (float) ($reserva->fianza ?? 0);

        // En Airbnb no hay fianza que topar: la retención es una reclamación, y puede ser mayor
        // que cero aunque la reserva no tenga fianza registrada.
        if (!self::esAirbnb($reserva->channel)) {
            if ($fianza <= 0) {
                return response()->json(['error' => 'Esta reserva no tiene fianza, así que no hay nada que retener.'], 422);
            }
            if ($total > $fianza + 0.005) {
                return response()->json([
                    'error' => 'La retención (' . number_format($total, 2, ',', '.')
                             . ' €) no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).',
                ], 422);
            }
        }

        // El motivo se compone de los conceptos de las fotos: ese campo lo siguen leyendo el
        // listado y la ficha de la reserva, así que se mantiene útil en vez de dejarlo vacío.
        $motivo = $lineas->map(fn($l) => trim((string) $l->retencion_concepto)
                . ' (' . number_format((float) $l->retencion_importe, 2, ',', '.') . ' €)')
            ->implode('; ');

        DB::transaction(function () use ($id, $total, $motivo, $canal, $lineas) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'           => self::ESTADO_RETENER,
                'fianza_retencion'        => $total,
                'fianza_motivo_retencion' => $motivo,
                'fianza_canal_cobro'      => $canal,
                'fianza_usuario'          => Auth::id(),
                'fianza_fecha'            => now(),
                'updatedat'               => now(),
            ]);

            $this->crearTareasDeCobro($id, $canal, self::SSCC_RETENCION,
                'Retener ' . number_format($total, 2, ',', '.') . ' € de la fianza, por '
                . $lineas->count() . ' concepto(s) (' . $canal . '): ' . $motivo);
        });

        $this->avisarDecision($id, $canal, $fianza, $total, $motivo);

        return response()->json(['ok' => true, 'estado' => self::ESTADO_RETENER, 'total' => $total]);
    }

    /**
     * Correo bilingüe al huésped con la devolución. Lo llama DashboardController::marcarSsccHecho
     * cuando todas las tareas SSCC de la fianza están completadas: ahí es cuando el dinero se ha
     * movido de verdad. Sin fianza (0), no hay correo; con fianza lo hay siempre, también si se retiene todo.
     */
    public function avisarHuespedDevolucion(int $reservaId): void
    {
        $aviso = $this->datosAvisoHuesped($reservaId);
        if ($aviso['motivo'] === 'sin_fianza') {
            return;
        }
        if ($aviso['motivo'] === 'sin_correo') {
            Log::warning('Fianza: sin correo del huésped, no se avisa de la devolución', ['reserva' => $reservaId]);
            return;
        }

        FianzaAuditoria::registrar($reservaId, 'correo_enviado', ['tipo' => 'devolucion_huesped', 'destinatario' => $aviso['to']]);
        Mail::to($this->destinatario($aviso['to']))->send(new VmFianzaAvisoMail($aviso['asunto'], 'emails.vm.fianza-huesped', $aviso['datos']));
    }

    /**
     * Lo que diría el correo al huésped y a quién iría. Lo usan el envío y la previsualización del
     * botón Hecho, para que lo que se ve sea lo que sale. 'motivo' es null cuando sí hay correo.
     *
     * @return array{motivo: ?string, to: ?string, asunto: ?string, datos: array}
     */
    public function datosAvisoHuesped(int $reservaId): array
    {
        $r = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $reservaId)
            ->first(['r.booking_id', 'r.guest_name', 'r.guest_email', 'r.check_in_date', 'r.check_out_date',
                     'r.fianza', 'r.fianza_retencion', 'r.fianza_motivo_retencion', 'p.nombre as propiedad']);

        // Sin fianza no hay nada que devolver ni que retener, y no se avisa. Con fianza se avisa siempre,
        // también si se retiene todo: el huésped tiene que saber qué se queda y por qué.
        if (!$r || (float) ($r->fianza ?? 0) <= 0) {
            return ['motivo' => 'sin_fianza', 'to' => null, 'asunto' => null, 'datos' => []];
        }
        if (!$r->guest_email) {
            return ['motivo' => 'sin_correo', 'to' => null, 'asunto' => null, 'datos' => []];
        }

        $retencion = (float) ($r->fianza_retencion ?? 0);
        $importe   = max(0.0, (float) ($r->fianza ?? 0) - $retencion);

        return [
            'motivo'  => null,
            'to'      => $r->guest_email,
            'asunto'  => 'Devolución de su fianza · Deposit refund · reserva ' . $r->booking_id,
            'datos'   => [
                'nombre'     => $r->guest_name,
                'booking_id' => $r->booking_id,
                'propiedad'  => $r->propiedad ?: 'sin propiedad',
                'entrada'    => Carbon::parse($r->check_in_date)->format('d/m/Y'),
                'salida'     => Carbon::parse($r->check_out_date)->format('d/m/Y'),
                'importe'    => $importe,
                'retencion'  => $retencion,
                // El motivo interno marca las retenciones sin evidencias; al huésped no se le enseña esa marca.
                'motivo'     => str_replace(self::SUFIJO_SIN_EVIDENCIAS, '', (string) ($r->fianza_motivo_retencion ?? '')),
                'adjuntos'   => $this->rutasFotosRetencion($reservaId),
            ],
        ];
    }

    /**
     * Lo que provocaría el botón Hecho de esta tarea, para la modal de confirmación: si manda correo
     * al huésped y a quién, el correo en castellano y las miniaturas de las fotos que van adjuntas.
     */
    public function previaHecho(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'tareas_sscc'), 403);

        $tarea = DB::table('vm_tareas_sscc')->where('id', $id)->where('deleted', 0)
            ->first(['id', 'estado', 'Tipo', 'id_reservas', 'id_tarea_hermana', 'control_user', 'id_departamento', 'id_rol']);
        abort_unless($tarea, 404);
        abort_unless(SsccVisibilidad::puedeVer($project, $tarea), 403, 'Esta tarea no está asignada a ti, a tu rol ni a tu departamento.');

        $vacia = ['envia' => false, 'motivo' => null, 'destinatario' => null, 'redirigido_a' => null, 'html' => null, 'fotos' => []];

        if ($tarea->estado === 'Completada') {
            return response()->json(['motivo' => 'La tarea ya está hecha.'] + $vacia);
        }
        if (!$tarea->id_reservas || !in_array($tarea->Tipo, [self::SSCC_DEVOLUCION, self::SSCC_RETENCION], true)) {
            return response()->json(['motivo' => 'Esta tarea no es de fianza: no envía correo.'] + $vacia);
        }

        // Mismo criterio que DashboardController::marcarSsccHecho: la gemela se borra al cerrar esta,
        // así que el correo sale si no quedan más tareas abiertas aparte de ellas dos.
        $otras = DB::table('vm_tareas_sscc')
            ->where('id_reservas', $tarea->id_reservas)
            ->where('deleted', 0)
            ->where('estado', '!=', 'Completada')
            ->where('id', '!=', $id)
            ->when($tarea->id_tarea_hermana, fn($q) => $q->where('id', '!=', $tarea->id_tarea_hermana))
            ->exists();
        if ($otras) {
            return response()->json(['motivo' => 'Quedan otras tareas de la fianza abiertas: el correo sale al cerrar la última.'] + $vacia);
        }

        $aviso = $this->datosAvisoHuesped((int) $tarea->id_reservas);
        if ($aviso['motivo'] === 'sin_fianza') {
            return response()->json(['motivo' => 'La reserva no tiene fianza: no hay correo al huésped.'] + $vacia);
        }
        if ($aviso['motivo'] === 'sin_correo') {
            return response()->json(['motivo' => 'La reserva no tiene correo del huésped: no se enviará.'] + $vacia);
        }

        $fotos = [];
        foreach ($this->retencionesDeReserva((int) $tarea->id_reservas) as $foto) {
            if (is_file(storage_path('app/public/' . $foto->file_foto))) {
                $fotos[] = [
                    'url'      => route('vm.sscc.foto', [$project->slug, $id, $foto->id]),
                    'concepto' => $foto->retencion_concepto,
                    'importe'  => (float) $foto->retencion_importe,
                ];
            }
        }

        $override = (string) config('services.vm_fianzas.mail_override_to');

        return response()->json([
            'envia'         => true,
            'motivo'        => null,
            'destinatario'  => $aviso['to'],
            'redirigido_a'  => $override !== '' ? $override : null,
            'html'          => view('emails.vm.partials.fianza-huesped-es', $aviso['datos'] + ['idioma' => 'es'])->render(),
            'fotos'         => $fotos,
        ]);
    }

    /** Una foto de retención de la tarea, para la miniatura de la modal. Solo las que van adjuntas al correo. */
    public function fotoRetencionSscc(Request $request, Project $project, int $id, int $foto)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'tareas_sscc'), 403);

        $tarea = DB::table('vm_tareas_sscc')->where('id', $id)->where('deleted', 0)
            ->first(['id_reservas', 'control_user', 'id_departamento', 'id_rol']);
        abort_unless($tarea && SsccVisibilidad::puedeVer($project, $tarea), 403);
        $reservaId = $tarea->id_reservas;
        abort_unless($reservaId, 404);

        $fila = collect($this->retencionesDeReserva((int) $reservaId))->firstWhere('id', $foto);
        abort_unless($fila, 404);

        $ruta = storage_path('app/public/' . $fila->file_foto);
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta);
    }

    /**
     * Las fotos que justifican la retención, como rutas en disco, para adjuntarlas al correo al
     * huésped. Sin retención con foto (p. ej. una retención sin evidencias), no hay nada que adjuntar.
     *
     * @return array<int, string>
     */
    private function rutasFotosRetencion(int $reservaId): array
    {
        $rutas = [];
        foreach ($this->retencionesDeReserva($reservaId) as $foto) {
            $ruta = storage_path('app/public/' . $foto->file_foto);
            if (is_file($ruta)) {
                $rutas[] = $ruta;
            } else {
                Log::warning('Fianza: foto de retención no encontrada, no se adjunta', ['reserva' => $reservaId, 'foto' => $foto->file_foto]);
            }
        }

        return $rutas;
    }

    /**
     * A quién va realmente un correo de fianzas. Con VM_FIANZAS_MAIL_OVERRIDE_TO definida (ahora,
     * temporalmente, para pruebas) todos van a esa dirección, y el destinatario real queda en el log.
     */
    private function destinatario(string $real): string
    {
        $override = (string) config('services.vm_fianzas.mail_override_to');
        if ($override === '') {
            return $real;
        }

        Log::info('Fianza: correo redirigido por prueba', ['destinatario_real' => $real, 'enviado_a' => $override]);

        return $override;
    }

    /**
     * Los correos que salen al decidir. Ninguno bloquea la decisión: si uno falla, queda en el log
     * y la fianza sigue decidida.
     *
     * - Huésped: solo si hay devolución monetaria (fianza menos retención, mayor que 0).
     * - Borja: solo retenciones de reservas de Airbnb.
     * - Filipinas: solo por transferencia, con el enlace para el número de cuenta.
     */
    private function avisarDecision(int $reservaId, string $canal, float $fianza, float $retencion, string $motivo): void
    {
        $r = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $reservaId)
            ->first(['r.booking_id', 'r.guest_name', 'r.guest_email', 'r.check_in_date',
                     'r.check_out_date', 'r.channel', 'p.nombre as propiedad']);
        if (!$r) {
            return;
        }

        // Un paso de la decisión, para la auditoría de la reserva. La marca de sin evidencias viaja en el motivo.
        FianzaAuditoria::registrar($reservaId, $retencion > 0 ? 'decision_retencion' : 'decision_conforme', [
            'canal'          => $canal,
            'fianza'         => $fianza,
            'retencion'      => $retencion,
            'motivo'         => str_replace(self::SUFIJO_SIN_EVIDENCIAS, '', $motivo),
            'sin_evidencias' => str_contains($motivo, self::SUFIJO_SIN_EVIDENCIAS),
        ]);

        $datosBase = [
            'booking_id' => $r->booking_id,
            'propiedad'  => $r->propiedad ?: 'sin propiedad',
            'entrada'    => Carbon::parse($r->check_in_date)->format('d/m/Y'),
            'salida'     => Carbon::parse($r->check_out_date)->format('d/m/Y'),
        ];

        // El correo al huésped no sale aquí: sale cuando Contabilidad cierra la SSCC (ver
        // avisarHuespedDevolucion), que es cuando el dinero se ha movido de verdad.

        if ($retencion > 0 && stripos((string) $r->channel, 'airbnb') !== false) {
            FianzaAuditoria::registrar($reservaId, 'correo_enviado', ['tipo' => 'retencion_airbnb', 'destinatario' => config('services.vm_fianzas.borja_to')]);
            Mail::to($this->destinatario(config('services.vm_fianzas.borja_to')))->send(new VmFianzaAvisoMail(
                'Retención de fianza Airbnb · reserva ' . $r->booking_id,
                'emails.vm.fianza-retencion-airbnb',
                $datosBase + ['huesped' => $r->guest_name, 'retencion' => $retencion, 'motivo' => $motivo,
                              'enlace' => url('vm/dev-fianzas/' . $reservaId)],
            ));
        }

        if ($canal === 'Transferencia') {
            $filipinas = config('services.vm_fianzas.filipinas_to');
            if ($filipinas) {
                FianzaAuditoria::registrar($reservaId, 'enlace_iban', ['caduca_dias' => (int) config('services.vm_fianzas.iban_enlace_dias', 7)]);
                FianzaAuditoria::registrar($reservaId, 'correo_enviado', ['tipo' => 'transferencia_iban', 'destinatario' => $filipinas]);
                Mail::to($this->destinatario($filipinas))->send(new VmFianzaAvisoMail(
                    'Cierre devolución de depósitos · reserva ' . $r->booking_id,
                    'emails.vm.fianza-iban-filipinas',
                    $datosBase + [
                        'enlace' => FianzaIbanController::enlaceParaFilipinas($reservaId),
                        'caduca' => now()->addDays((int) config('services.vm_fianzas.iban_enlace_dias', 7))->format('d/m/Y'),
                    ],
                ));
            } else {
                Log::warning('Fianza por transferencia: falta VM_FIANZAS_FILIPINAS_TO, no se envía el enlace', ['reserva' => $reservaId]);
            }
        }
    }

    /**
     * Retención sin evidencias: concepto e importe escritos a mano, sin foto ni incidencia que los
     * respalde. Es para cuando no hay nada que enseñar (por ejemplo, reservas de Airbnb sin fianza).
     * Las mismas reglas de fianza y de canal que la retención con evidencias, y el mismo registro:
     * el total va a fianza_retencion y el motivo, marcado como sin evidencias, a fianza_motivo_retencion.
     */
    public function retenerSinEvidencias(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'estado_fianza', 'fianza', 'channel']);
        abort_unless($reserva, 404);

        if (!empty($reserva->estado_fianza)) {
            return response()->json([
                'error' => 'Esta reserva ya tiene decisión: "' . $reserva->estado_fianza . '". '
                         . 'Para rehacerla, deja el estado en blanco desde la ficha de la reserva.',
            ], 422);
        }

        $datos = $request->validate([
            'concepto' => 'required|string|max:255',
            'importe'  => 'required|numeric|min:0.01',
        ], [], ['concepto' => 'concepto de la retención', 'importe' => 'importe a retener']);

        $canal = $this->validarCanal($request, $reserva);
        if ($canal instanceof \Illuminate\Http\JsonResponse) {
            return $canal;
        }

        $total    = round((float) $datos['importe'], 2);
        $concepto = trim($datos['concepto']);
        $fianza   = (float) ($reserva->fianza ?? 0);

        if (!self::esAirbnb($reserva->channel)) {
            if ($fianza <= 0) {
                return response()->json(['error' => 'Esta reserva no tiene fianza, así que no hay nada que retener.'], 422);
            }
            if ($total > $fianza + 0.005) {
                return response()->json([
                    'error' => 'La retención (' . number_format($total, 2, ',', '.')
                             . ' €) no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).',
                ], 422);
            }
        }

        $motivo = $concepto . ' (' . number_format($total, 2, ',', '.') . ' €)' . self::SUFIJO_SIN_EVIDENCIAS;

        DB::transaction(function () use ($id, $total, $motivo, $canal) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'           => self::ESTADO_RETENER,
                'fianza_retencion'        => $total,
                'fianza_motivo_retencion' => $motivo,
                'fianza_canal_cobro'      => $canal,
                'fianza_usuario'          => Auth::id(),
                'fianza_fecha'            => now(),
                'updatedat'               => now(),
            ]);

            $this->crearTareasDeCobro($id, $canal, self::SSCC_RETENCION,
                'Retener ' . number_format($total, 2, ',', '.') . ' € de la fianza, sin evidencias (' . $canal . '): ' . $motivo);
        });

        $this->avisarDecision($id, $canal, $fianza, $total, $motivo);

        return response()->json(['ok' => true, 'estado' => self::ESTADO_RETENER, 'total' => $total]);
    }

    /**
     * Devuelve el proceso de devolución de la reserva al punto de partida: quita la decisión, el
     * canal, el IBAN y el enlace del IBAN, las retenciones de las fotos y las tareas SSCC de
     * devolución y retención (borrado lógico). La auditoría deja constancia de lo que había, sin
     * registrar el número de cuenta.
     */
    public function reiniciar(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)
            ->first(['id', 'estado_fianza', 'fianza_retencion', 'fianza_canal_cobro']);
        abort_unless($reserva, 404);

        // Las fotos cuelgan de la limpieza o de una incidencia de mantenimiento salida de ella: hay que
        // mirar los dos caminos, igual que retencionesDeReserva, o quedan marcas sin borrar.
        $limpiezas   = DB::table('vm_tareas_limpieza')->where('id_reservas', $id)->pluck('id')->all();
        $incidencias = $limpiezas ? $this->incidenciasDeTareas($limpiezas)->pluck('id')->all() : [];

        $fotosIds = DB::table('vm_fotos')
            ->where(function ($q) use ($limpiezas, $incidencias) {
                $q->whereIn('id_tareas_limpieza', $limpiezas);
                if ($incidencias) {
                    $q->orWhereIn('id_tareas_mantenimiento', $incidencias);
                }
            })
            ->where(fn($x) => $x->where('informe_estancia', 1)
                ->orWhereNotNull('retencion_concepto')
                ->orWhereNotNull('retencion_importe'))
            ->pluck('id')->all();

        $tareasIds = DB::table('vm_tareas_sscc')
            ->where('id_reservas', $id)
            ->whereIn('Tipo', [self::SSCC_DEVOLUCION, self::SSCC_RETENCION])
            ->where(fn($x) => $x->where('deleted', 0)->orWhereNull('deleted'))
            ->pluck('id')->all();

        DB::transaction(function () use ($id, $fotosIds, $tareasIds) {
            DB::table('vm_reservas')->where('id', $id)->update([
                'estado_fianza'           => null,
                'fianza_retencion'        => null,
                'fianza_motivo_retencion' => null,
                'fianza_usuario'          => null,
                'fianza_fecha'            => null,
                'fianza_canal_cobro'      => null,
                'fianza_iban_cifrado'     => null,
                'fianza_iban_guardado_at' => null,
                'fianza_token_iban'       => null,
                'fianza_departamento'     => null,
                'updatedat'               => now(),
            ]);

            if ($fotosIds) {
                DB::table('vm_fotos')->whereIn('id', $fotosIds)->update([
                    'informe_estancia'      => 0,
                    'informe_estancia_user' => null,
                    'informe_estancia_at'   => null,
                    'retencion_concepto'    => null,
                    'retencion_importe'     => null,
                ]);
            }

            if ($tareasIds) {
                DB::table('vm_tareas_sscc')->whereIn('id', $tareasIds)->update(['deleted' => 1]);
            }
        });

        \App\Services\FianzaAuditoria::registrar($id, 'reinicio_fianza', [
            'estado_anterior'    => $reserva->estado_fianza,
            'retencion_anterior' => $reserva->fianza_retencion,
            'canal_anterior'     => $reserva->fianza_canal_cobro,
            'fotos_desmarcadas'  => count($fotosIds),
            'tareas_borradas'    => count($tareasIds),
        ]);

        return response()->json(['ok' => true]);
    }

    // ── Piezas compartidas por las dos decisiones ────────────────────────────

    /** Una reserva de Airbnb, por el canal que manda Icnea (sin distinguir mayúsculas). */
    public static function esAirbnb(?string $channel): bool
    {
        return stripos((string) $channel, 'airbnb') !== false;
    }

    /**
     * El canal de cobro de la decisión. En Airbnb es fijo y no se lee del formulario: el desplegable
     * se muestra deshabilitado, y aunque llegara otro valor el servidor manda. Devuelve el texto, o
     * la respuesta de error.
     */
    private function validarCanal(Request $request, object $reserva)
    {
        if (self::esAirbnb($reserva->channel)) {
            return self::CANAL_AIRBNB;
        }

        $datos = $request->validate([
            'canal' => 'required|string',
        ], [], ['canal' => 'canal de cobro']);

        if (!in_array($datos['canal'], self::CANALES_COBRO, true)) {
            return response()->json(['error' => 'Elige el canal de cobro: TPV virtual, TPV físico o transferencia.'], 422);
        }

        return $datos['canal'];
    }

    /**
     * Las tareas que hacen que la decisión llegue a quien mueve el dinero, según el canal.
     *
     * Cada una lleva id_reservas porque de ahí sale el paso a "Devuelta": la fianza solo se da por
     * devuelta cuando están completadas todas sus tareas.
     */
    private function crearTareasDeCobro(int $reservaId, string $canal, string $tipo, string $descripcion): void
    {
        $ids = [];
        foreach (self::TAREAS_POR_CANAL[$canal] ?? [] as $destino) {
            $ids[] = $this->crearTareaSscc($reservaId, $destino['departamento'] ?? null, $destino['rol'] ?? null, $tipo, $descripcion);
        }

        // Dos tareas para lo mismo (TPV físico): cada una apunta a su gemela. Al cerrar una, la otra se borra.
        if (count($ids) === 2) {
            DB::table('vm_tareas_sscc')->where('id', $ids[0])->update(['id_tarea_hermana' => $ids[1]]);
            DB::table('vm_tareas_sscc')->where('id', $ids[1])->update(['id_tarea_hermana' => $ids[0]]);
        }
    }

    public function crearTareaSscc(int $reservaId, ?int $departamento, ?int $rol, string $tipo, string $descripcion): int
    {
        $reserva = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $reservaId)
            ->first(['r.booking_id', 'r.id_propiedades', 'r.check_out_date', 'p.nombre as propiedad']);

        $tareaId = DB::table('vm_tareas_sscc')->insertGetId([
            // La novación a la que pertenece la fianza es la del mes de su salida.
            'novacion_periodo'  => $reserva->check_out_date ? Carbon::parse($reserva->check_out_date)->format('Y-m') : null,
            'nombre'            => $tipo . ' · reserva ' . ($reserva->booking_id ?? $reservaId)
                                 . ' (' . ($reserva->propiedad ?: 'sin propiedad') . ')',
            'descripcion'       => $descripcion,
            'Tipo'              => $tipo,
            'estado'            => 'Nueva',
            'id_departamento'   => $departamento,
            'id_rol'            => $rol,
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

        FianzaAuditoria::registrar($reservaId, 'tarea_creada', [
            'id' => $tareaId, 'tipo' => $tipo, 'departamento' => $departamento, 'rol' => $rol,
        ]);

        return $tareaId;
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
     * "Vigente" y "Pte. revisión" se derivan aquí en vez de almacenarse: dependen solo de la
     * fecha y de si hay decisión, y guardarlos obligaría a un proceso nocturno que los fuera
     * cambiando. El día que ese proceso fallara, la pantalla mentiría sobre dinero de un huésped.
     *
     * Con fianza 0 no hay nada que decidir y no se muestra estado, que es lo que ya pasaba antes.
     */
    public static function estadoFianza(?object $reserva): ?string
    {
        if (!$reserva) {
            return null;
        }

        $decision = $reserva->estado_fianza ?? null;


        // Sin decisión: depende solo de si la salida ya ha pasado.
        if (empty($decision)) {
            $checkout = $reserva->check_out_date ?? null;

            return ($checkout && $checkout >= now()->toDateString())
                ? self::ESTADO_VIGENTE
                : self::ESTADO_REVISION;
        }

        // Airbnb: no hay devolución desde Opland, así que la decisión es el estado final.
        if (self::esAirbnb($reserva->channel ?? null)) {
            return $decision === self::ESTADO_RETENER ? self::ESTADO_RECLAMADA : self::ESTADO_CONFORME;
        }

        // Resto de canales, con decisión (conformidad o retención).
        if (!empty($reserva->sscc_finalizada)) {
            return self::ESTADO_DEVUELTA;
        }
        if (($reserva->fianza_canal_cobro ?? null) === 'Transferencia' && empty($reserva->fianza_iban_guardado_at)) {
            return self::ESTADO_PTE_IBAN;
        }

        return self::ESTADO_PTE_DEVOLUCION;
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
                r.fianza_canal_cobro, r.fianza_iban_guardado_at,
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
                -- Con varias tareas (TPV físico tiene dos) la fianza solo está devuelta cuando
                -- todas están completadas: basta una abierta para que siga pendiente.
                (SELECT CASE WHEN count(*) > 0
                             AND count(*) FILTER (WHERE s.estado = 'Completada') = count(*)
                        THEN 1 ELSE 0 END
                   FROM vm_tareas_sscc s
                  WHERE s.id_reservas = r.id AND COALESCE(s.deleted, 0) = 0) AS sscc_finalizada
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
