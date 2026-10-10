<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NovacionesController extends Controller
{
    /**
     * Líneas que no vienen de Icnea: las crea y las gobierna esta pantalla. No admiten importe
     * corregido porque no hay original que preservar, y el cron las deja en paz (mismo listado en
     * IcneaSyncImportesCommand::$textosProtegidos).
     */
    public const TEXTOS_PROPIOS = ['Management Fee', 'Comisión Bancos'];

    /** Dos importes se consideran iguales por debajo de este céntimo, igual que en el snapshot. */
    private const TOLERANCIA = 0.01;

    public const MESES_ES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function index(Request $request, Project $project)
    {
        $propiedades = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->where('tipo_renta', 'Cesión uso')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'porc_honorarios']);

        $year   = (int) $request->input('year',  now()->year);
        $month  = (int) $request->input('month', now()->month);
        $propId = (int) $request->input('prop_id', $propiedades->first()->id ?? 0);

        $reservas  = collect();
        $propiedad = null;
        $ultimaSincronizacion = null;

        if ($propId) {
            $propiedad = $propiedades->firstWhere('id', $propId);
            $mp    = str_pad($month, 2, '0', STR_PAD_LEFT);
            $desde = "{$year}-{$mp}-01";
            $hasta = Carbon::parse($desde)->endOfMonth()->toDateString();

            $reservas = DB::table('vm_reservas')
                ->where('id_propiedades', $propId)
                ->whereBetween('check_out_date', [$desde, $hasta])
                ->whereNotIn('booking_status', ['cancelled'])
                ->orderBy('check_out_date')
                ->get(['id', 'booking_id', 'guest_name', 'check_in_date', 'check_out_date', 'booking_status', 'novacion', 'base_propietario']);

            $ultimaSincronizacion = DB::table('vm_novaciones_gastos')
                ->where('id_propiedades', $propId)
                ->where('fecha_novacion', $hasta)
                ->value('ultima_sincronizacion');
        }

        $historial = $propId ? $this->historialDocumentos($propId, $year, $month) : collect();

        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        // Tareas de revisión pendientes en cualquiera de los últimos 6 meses (no solo el
        // mes inmediatamente anterior), ya que sincronizar() ahora revisa ese rango completo.
        $tareasRevision = collect();
        if ($propId) {
            $yAct = $year;
            $mAct = $month;
            for ($i = 0; $i < 6; $i++) {
                [$yAct, $mAct] = $this->mesAnterior($yAct, $mAct);
                $tarea = $this->tareaRevisionPendiente($propId, $yAct, $mAct);
                if ($tarea) {
                    $tarea->mes_label = $meses_es[$mAct] . ' ' . $yAct;
                    $tareasRevision->push($tarea);
                }
            }
        }

        return view('novaciones', [
            'project'     => $project,
            'propiedades' => $propiedades,
            'prop_id'     => $propId,
            'propiedad'   => $propiedad,
            'ultima_sincronizacion' => $ultimaSincronizacion,
            'year'        => $year,
            'month'       => $month,
            'reservas'    => $reservas,
            'historial'   => $historial,
            'tareas_revision' => $tareasRevision,
            'meses_es'    => $meses_es,
            'breadcrumb'  => [['label' => 'Novaciones', 'url' => '']],
        ]);
    }

    public function importes(Request $request, Project $project)
    {
        $bookingId = $request->input('booking_id');
        $reserva   = DB::table('vm_reservas')->where('booking_id', $bookingId)->first();

        if (!$reserva) {
            return response()->json(['error' => 'Reserva no encontrada'], 404);
        }

        // Management Fee solo si no hay Comisión canal
        $tieneCC = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->where('deleted', 0)
            ->where('texto', 'Comisión canal')
            // El efectivo, no el de Icnea: si alguien corrige la comisión de canal a 0, esta
            // reserva pasa a necesitar Management Fee igual que si Icnea no la hubiera mandado.
            ->whereRaw('COALESCE(importe_novacion, importe) > 0')
            ->exists();

        if (!$tieneCC) {
            $totalReserva = (float) DB::table('vm_reservas_importes')
                ->where('booking_id', $bookingId)
                ->where('deleted', 0)
                ->whereNotIn('texto', ['Management Fee', 'Comisión Bancos', 'Comisión canal'])
                ->selectRaw('COALESCE(SUM(COALESCE(importe_novacion, importe)), 0) as t')
                ->value('t');

            $mgmtFee = round($totalReserva * 0.05, 2);
            $existsMgmt = DB::table('vm_reservas_importes')
                ->where('booking_id', $bookingId)
                ->where('texto', 'Management Fee')
                ->exists();

            if (!$existsMgmt) {
                DB::table('vm_reservas_importes')->insert([
                    'id_reserva'  => $reserva->id,
                    'booking_id'  => $bookingId,
                    'texto'       => 'Management Fee',
                    'importe'     => $mgmtFee,
                    'propietario' => 1,
                    'createdat'   => now(),
                    'updatedat'   => now(),
                ]);
            }
        }

        // Comisión Bancos: igual que Management Fee, se autocrea siempre para que su
        // toggle sea activable desde el primer momento, sin necesidad de modificar antes el importe.
        $existsCb = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->where('texto', 'Comisión Bancos')
            ->exists();

        if (!$existsCb) {
            DB::table('vm_reservas_importes')->insert([
                'id_reserva'  => $reserva->id,
                'booking_id'  => $bookingId,
                'texto'       => 'Comisión Bancos',
                'importe'     => 0,
                'propietario' => 1,
                'createdat'   => now(),
                'updatedat'   => now(),
            ]);
        }

        $importes = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->orderBy('id')
            ->get([
                'id', 'texto', 'importe', 'propietario', 'deleted',
                // La corrección manual y la foto del momento de novar. Sin esto el JS no las ve.
                'importe_novacion', 'importe_novacion_at', 'importe_novado', 'propietario_novado',
            ]);

        $propiedad = DB::table('vm_propiedades')
            ->where('id', $reserva->id_propiedades ?? 0)
            ->first(['porc_honorarios']);

        return response()->json([
            'reserva'         => $reserva,
            'importes'        => $importes,
            'porc_honorarios' => $propiedad ? (float) $propiedad->porc_honorarios : 0,
        ]);
    }

    public function toggleImporte(Request $request, Project $project)
    {
        $id  = (int) $request->input('id');
        $row = DB::table('vm_reservas_importes')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'No encontrado'], 404);

        $newVal = $row->propietario ? 0 : 1;
        DB::table('vm_reservas_importes')->where('id', $id)->update([
            'propietario' => $newVal,
            'updatedat'   => now(),
        ]);

        return response()->json(['propietario' => $newVal]);
    }

    public function updateImporte(Request $request, Project $project)
    {
        $id      = (int) $request->input('id');
        $importe = (float) $request->input('importe', 0);

        $row = DB::table('vm_reservas_importes')->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'No encontrado'], 404);

        DB::table('vm_reservas_importes')->where('id', $id)->update([
            'importe'   => $importe,
            'updatedat' => now(),
        ]);

        return response()->json(['id' => $id, 'importe' => $importe]);
    }

    /**
     * Corrige el importe de una línea SIN tocar el que vino de Icnea.
     *
     * Endpoint aparte de updateImporte() a propósito: aquel escribe "importe" de forma
     * destructiva y lo usa el Management Fee. Compartirlos abriría la puerta a machacar por error
     * el valor de Icnea, que es justo lo que esto existe para impedir.
     */
    public function updateImporteNovacion(Request $request, Project $project)
    {
        $id  = (int) $request->input('id');
        $fila = DB::table('vm_reservas_importes')->where('id', $id)->first(['id', 'texto', 'importe']);

        if (!$fila) {
            return response()->json(['error' => 'Línea no encontrada'], 404);
        }

        // Management Fee y Comisión Bancos son líneas propias de VM: no vienen de Icnea, no hay
        // original que preservar y su importe ya es el que teclea el usuario.
        if (in_array($fila->texto, self::TEXTOS_PROPIOS, true)) {
            return response()->json([
                'error' => 'Esta línea es propia de VM y no viene de Icnea: edita su importe directamente.',
            ], 422);
        }

        // Vacío y cero son cosas distintas: vacío retira la corrección, cero anula la línea.
        // (float) sobre '' daría 0.0 y convertiría "sin corregir" en "corregido a cero".
        $bruto = $request->input('importe_novacion');
        $valor = ($bruto === null || $bruto === '') ? null : round((float) $bruto, 2);

        DB::table('vm_reservas_importes')->where('id', $id)->update([
            'importe_novacion'      => $valor,
            'importe_novacion_user' => $valor === null ? null : auth()->id(),
            'importe_novacion_at'   => $valor === null ? null : now(),
            'updatedat'             => now(),
        ]);

        return response()->json([
            'ok'               => true,
            'id'               => $id,
            'importe'          => (float) $fila->importe,
            'importe_novacion' => $valor,
        ]);
    }

    public function guardar(Request $request, Project $project)
    {
        $bookingId       = $request->input('booking_id');
        $basePropietario = $request->input('base_propietario');
        $baseCalculo     = $request->input('base_calculo');
        $ivaPct          = $request->input('iva_pct');
        $reserva         = DB::table('vm_reservas')->where('booking_id', $bookingId)->first();
        if (!$reserva) return response()->json(['error' => 'No encontrada'], 404);

        $porcHonorarios = DB::table('vm_propiedades')
            ->where('id', $reserva->id_propiedades ?? 0)
            ->value('porc_honorarios');

        // Se guarda CON QUÉ se calculó, no solo el resultado. Hasta ahora solo quedaban las dos
        // cifras finales: ni las líneas, ni los toggles, ni el IVA, de modo que una novación no
        // era reproducible y tampoco se podía detectar que Icnea cambiara algo después.
        DB::transaction(function () use ($bookingId, $basePropietario, $baseCalculo, $ivaPct, $porcHonorarios) {
            DB::table('vm_reservas')->where('booking_id', $bookingId)->update([
                'novacion'                 => 1,
                'base_propietario'         => $basePropietario,
                'base_calculo'             => $baseCalculo,
                'novacion_iva_pct'         => $ivaPct === null || $ivaPct === '' ? null : (float) $ivaPct,
                'novacion_porc_honorarios' => $porcHonorarios === null ? null : (float) $porcHonorarios,
                'novacion_at'              => now(),
                'novacion_user'            => auth()->id(),
                'updatedat'                => now(),
            ]);

            // La foto de cada línea: cuánto valía el efectivo y cómo estaba su toggle. Las ya
            // borradas se dejan fuera: no entraron en el cálculo.
            DB::table('vm_reservas_importes')
                ->where('booking_id', $bookingId)
                ->where('deleted', 0)
                ->update([
                    'importe_novado'     => DB::raw('COALESCE(importe_novacion, importe)'),
                    'propietario_novado' => DB::raw('propietario'),
                    'updatedat'          => now(),
                ]);
        });

        return response()->json(['ok' => true, 'base_propietario' => $basePropietario]);
    }

    // ── SINCRONIZACIÓN CON ICNEA ────────────────────────────────────────────
    // Re-lee de Icnea el detalle de importes de las reservas de una propiedad+mes
    // y reconcilia vm_reservas_importes: actualiza lo que cambió, añade lo nuevo,
    // y marca como deleted lo que ya no viene en la respuesta de Icnea (sin tocar
    // las líneas que gestiona la propia Novaciones a mano: Management Fee y
    // Comisión Bancos, que no existen en Icnea).
    public function sincronizar(Request $request, Project $project)
    {
        $propId = (int) $request->input('prop_id');
        $year   = (int) $request->input('year');
        $month  = (int) $request->input('month');

        $hasta = Carbon::create($year, $month)->endOfMonth()->toDateString();

        // Mes seleccionado
        $resultadoMesActual = $this->reconciliarMes($propId, $year, $month);
        $this->marcarSincronizado($propId, $hasta);

        $tareasCreadas = [];

        // El mes en curso también se comprueba: una reserva ya novada de este mes puede haber
        // cambiado en Icnea aunque el mes no esté documentado todavía.
        $tareaActual = $this->comprobarCambiosNovadas($propId, $year, $month);
        if ($tareaActual && ($tareaActual['creada'] ?? false)) {
            $tareasCreadas[] = ['year' => $year, 'month' => $month] + $tareaActual;
        }

        // 6 meses anteriores — se resincronizan siempre de paso, y por cada uno cuyos
        // subtotales ya documentados (vm_novaciones_documentos) dejen de coincidir con los
        // recién recalculados, se abre una tarea de revisión para Contabilidad.
        $erroresAnteriores = 0;
        $yAct = $year;
        $mAct = $month;
        for ($i = 0; $i < 6; $i++) {
            [$yAct, $mAct] = $this->mesAnterior($yAct, $mAct);
            $resultadoMes = $this->reconciliarMes($propId, $yAct, $mAct);
            $this->marcarSincronizado($propId, Carbon::create($yAct, $mAct)->endOfMonth()->toDateString());
            $erroresAnteriores += $resultadoMes['errores'];

            // Dos avisos distintos: uno mira si los totales ya documentados dejaron de cuadrar;
            // el otro, si alguna reserva novada ha cambiado desde que se novó, que es lo que al
            // primero se le escapa (compara cifras materializadas, que no se mueven).
            foreach ([$this->comprobarDiferenciaYCrearTarea($propId, $yAct, $mAct),
                      $this->comprobarCambiosNovadas($propId, $yAct, $mAct)] as $tarea) {
                if ($tarea && ($tarea['creada'] ?? false)) {
                    $tareasCreadas[] = ['year' => $yAct, 'month' => $mAct] + $tarea;
                }
            }
        }

        $ahora = now();

        return response()->json([
            'ok'                    => true,
            'procesadas'            => $resultadoMesActual['procesadas'],
            'errores'               => $resultadoMesActual['errores'] + $erroresAnteriores,
            'ultima_sincronizacion' => $ahora->format('d/m/Y H:i'),
            'tareas_revision'       => $tareasCreadas,
        ]);
    }

    // Reconcilia vm_reservas_importes de todas las reservas de una propiedad+mes
    // contra la respuesta actual de Icnea. Nunca borra: inserta lo nuevo, actualiza
    // lo que cambió, y marca deleted=1 lo que ya no devuelve Icnea (salvo las líneas
    // que gestiona a mano la propia Novaciones: Management Fee y Comisión Bancos).
    private function reconciliarMes(int $propId, int $year, int $month): array
    {
        $mp    = str_pad($month, 2, '0', STR_PAD_LEFT);
        $desde = "{$year}-{$mp}-01";
        $hasta = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $reservas = DB::table('vm_reservas')
            ->where('id_propiedades', $propId)
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->whereNotIn('booking_status', ['cancelled'])
            ->get(['id', 'booking_id']);

        $apiKey           = (string) config('services.icnea.api_key');
        $ownerId          = (string) config('services.icnea.owner_id');
        $textosProtegidos = ['Management Fee', 'Comisión Bancos'];

        $procesadas = 0;
        $errores    = 0;

        foreach ($reservas as $reserva) {
            $respuesta = $this->fetchReservationIcnea($apiKey, $ownerId, $reserva->booking_id);

            if ($respuesta === null) {
                $errores++;
                continue;
            }

            $detail = $respuesta['detail'] ?? null;
            $lineas = [];
            if (!empty($detail)) {
                if (isset($detail['text'])) {
                    $detail = [$detail];
                }
                foreach ($detail as $linea) {
                    $texto = trim($linea['text'] ?? '');
                    // Icnea devuelve el catalan "allotjament" para el concepto de alojamiento.
                    if (strcasecmp($texto, 'allotjament') === 0) {
                        $texto = 'alojamiento';
                    }
                    if ($texto !== '') {
                        $lineas[$texto] = (float) ($linea['import'] ?? 0);
                    }
                }
            }
            $comisionCanal = isset($respuesta['channel_commission']) && (float) $respuesta['channel_commission'] > 0
                ? (float) $respuesta['channel_commission']
                : null;
            if ($comisionCanal !== null) {
                $lineas['Comisión canal'] = $comisionCanal;
            }

            $actuales = DB::table('vm_reservas_importes')
                ->where('booking_id', $reserva->booking_id)
                ->where('deleted', 0)
                ->get(['id', 'texto']);

            foreach ($actuales as $fila) {
                if (in_array($fila->texto, $textosProtegidos, true)) {
                    continue;
                }
                if (!array_key_exists($fila->texto, $lineas)) {
                    DB::table('vm_reservas_importes')->where('id', $fila->id)->update([
                        'deleted'   => 1,
                        'updatedat' => now(),
                    ]);
                }
            }

            foreach ($lineas as $texto => $importe) {
                $existente = DB::table('vm_reservas_importes')
                    ->where('booking_id', $reserva->booking_id)
                    ->where('texto', $texto)
                    ->first();

                if ($existente) {
                    if ((float) $existente->importe !== $importe || $existente->deleted) {
                        DB::table('vm_reservas_importes')->where('id', $existente->id)->update([
                            'importe'   => $importe,
                            'deleted'   => 0,
                            'updatedat' => now(),
                        ]);
                    }
                } else {
                    DB::table('vm_reservas_importes')->insert([
                        'id_reserva' => $reserva->id,
                        'booking_id' => $reserva->booking_id,
                        'texto'      => $texto,
                        'importe'    => $importe,
                        'createdat'  => now(),
                        'updatedat'  => now(),
                    ]);
                }
            }

            $procesadas++;
        }

        return ['procesadas' => $procesadas, 'errores' => $errores];
    }

    private function marcarSincronizado(int $propId, string $fechaNovacion): void
    {
        $ahora = now();
        $existingGasto = DB::table('vm_novaciones_gastos')
            ->where('id_propiedades', $propId)
            ->where('fecha_novacion', $fechaNovacion)
            ->first();

        if ($existingGasto) {
            DB::table('vm_novaciones_gastos')->where('id', $existingGasto->id)->update([
                'ultima_sincronizacion' => $ahora,
                'updatedat'             => $ahora,
            ]);
        } else {
            DB::table('vm_novaciones_gastos')->insert([
                'id_propiedades'        => $propId,
                'fecha_novacion'        => $fechaNovacion,
                'ultima_sincronizacion' => $ahora,
                'createdat'             => $ahora,
                'updatedat'             => $ahora,
            ]);
        }
    }

    private function mesAnterior(int $year, int $month): array
    {
        $anterior = Carbon::create($year, $month, 1)->subMonth();
        return [(int) $anterior->year, (int) $anterior->month];
    }

    // Recalcula los 3 totales de una propiedad+mes con la MISMA fórmula que novacion-pdf.blade.php
    // (totalPropietario = sum(base_propietario), totalVM = sum(base_calculo) - sum(base_propietario),
    // totalGastos = suministros + mantenimiento + piscinas). Solo cuenta reservas ya novadas (novacion=1),
    // igual que hace el PDF.
    private function calcularTotalesNovacion(int $propId, int $year, int $month): array
    {
        $mp    = str_pad($month, 2, '0', STR_PAD_LEFT);
        $desde = "{$year}-{$mp}-01";
        $hasta = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $reservas = DB::table('vm_reservas')
            ->where('id_propiedades', $propId)
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->where('novacion', 1)
            ->whereNotIn('booking_status', ['cancelled'])
            ->get(['base_propietario', 'base_calculo']);

        $totalPropietario = (float) $reservas->sum('base_propietario');
        $totalCalculo     = (float) $reservas->sum('base_calculo');
        $totalVm          = $totalCalculo - $totalPropietario;

        $gastos = DB::table('vm_novaciones_gastos')
            ->where('id_propiedades', $propId)
            ->where('fecha_novacion', $hasta)
            ->first();

        $totalSumi = 0;
        foreach (['electricidad', 'agua', 'internet', 'alarma', 'jardineria'] as $k) {
            $totalSumi += $gastos ? (float) ($gastos->$k ?? 0) : 0;
        }

        $totalMant = (float) DB::table('vm_tareas_mantenimiento')
            ->where('id_propiedades', $propId)->where('deleted', 0)
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')->sum('importe_novacion');

        $totalPiscinas = (float) DB::table('vm_tareas_piscinas')
            ->where('id_propiedades', $propId)->where('deleted', 0)
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')->sum('importe_novacion');

        $totalGastos = $totalSumi + $totalMant + $totalPiscinas;

        return [
            'importe_propietario' => round($totalPropietario, 2),
            'importe_vm'          => round($totalVm, 2),
            'total_gastos'        => round($totalGastos, 2),
        ];
    }

    private function historialDocumentos(int $propId, int $year, int $month)
    {
        return DB::table('vm_novaciones_documentos as d')
            ->leftJoin('admin_users as u', 'u.id', '=', 'd.createuser')
            ->where('d.id_propiedades', $propId)
            ->where('d.year', $year)
            ->where('d.month', $month)
            ->where('d.deleted', 0)
            ->orderByDesc('d.createdat')
            ->select('d.id', 'd.createdat', 'd.importe_propietario', 'd.importe_vm', 'd.total_gastos', 'u.name as createuser_nombre')
            ->get();
    }

    // Compara los totales ya documentados del mes indicado contra los recién recalculados.
    // Si difieren y no hay ya una tarea de revisión abierta para esa propiedad+mes, crea una.
    // Publico (no private): IcneaSyncImportesCommand (cron) reutiliza esta misma comprobacion
    // tras su propia reconciliacion, para que la tarea "Revisión Novación" se genere igual haya
    // venido de pulsar "Sincronizar" a mano o del cron automatico.
    public function comprobarDiferenciaYCrearTarea(int $propId, int $year, int $month): ?array
    {
        $ultimoDoc = DB::table('vm_novaciones_documentos')
            ->where('id_propiedades', $propId)
            ->where('year', $year)->where('month', $month)
            ->where('deleted', 0)
            ->orderByDesc('createdat')
            ->first();

        if (!$ultimoDoc) {
            return null; // nada documentado todavía para ese mes, no hay nada que comparar
        }

        $actual = $this->calcularTotalesNovacion($propId, $year, $month);

        $difiere = abs((float) $ultimoDoc->importe_propietario - $actual['importe_propietario']) > 0.01
            || abs((float) $ultimoDoc->importe_vm - $actual['importe_vm']) > 0.01
            || abs((float) $ultimoDoc->total_gastos - $actual['total_gastos']) > 0.01;

        if (!$difiere) {
            return null;
        }

        $propiedad = DB::table('vm_propiedades')->where('id', $propId)->value('nombre');
        $mesLabel  = self::MESES_ES[$month] . '-' . $year;

        return $this->crearTareaRevision(
            $propId, $year, $month,
            "Revisar novación {$mesLabel} de {$propiedad}",
            "Los importes ya documentados de la novación de {$mesLabel} para {$propiedad} han "
            . "cambiado tras sincronizar con Icnea. Revisar antes de dar por buena la novación anterior."
        );
    }

    /**
     * Abre una tarea "Revisión Novación" para Contabilidad, si no hay ya una viva de esa
     * propiedad+mes. Compartido por los dos avisos: el de totales documentados y el de importes
     * cambiados desde la novación -- una misma revisión cubre el mes entero.
     *
     * Desde el cron no hay sesión, así que createuser queda a NULL.
     */
    private function crearTareaRevision(int $propId, int $year, int $month, string $nombre, string $descripcion): ?array
    {
        $fechaFin = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $yaExiste = DB::table('vm_tareas_sscc')
            ->where('id_propiedades', $propId)
            ->where('Tipo', 'Revisión Novación')
            ->where('fecha_planificada', $fechaFin)
            ->where('deleted', 0)
            ->where('estado', '!=', 'Completada')
            ->exists();

        if ($yaExiste) {
            return ['creada' => false, 'motivo' => 'ya_existe'];
        }

        $idTarea = DB::table('vm_tareas_sscc')->insertGetId([
            'nombre'            => $nombre,
            'descripcion'       => $descripcion,
            'Tipo'              => 'Revisión Novación',
            'estado'            => 'Nueva',
            'fecha_planificada' => $fechaFin,
            'id_propiedades'    => $propId,
            'id_departamento'   => DB::table('vm_departamentos')->where('nombre', 'Adm/Finanzas')->value('id'),
            'hidden'            => 0,
            'deleted'           => 0,
            'blocked'           => 0,
            'createuser'        => auth()->id(),
            'createdat'         => now(),
            'updatedat'         => now(),
        ]);

        return ['creada' => true, 'id' => $idTarea];
    }

    /**
     * Qué ha cambiado en una reserva desde que se novó.
     *
     * Compara el estado actual de sus líneas contra la foto que dejó guardar() en *_novado. No
     * recalcula la base del propietario: eso exigiría portar a PHP la fórmula que vive en el
     * JavaScript del panel y mantener dos copias de la misma cuenta. Comparar las ENTRADAS
     * detecta lo mismo -- que algo se movió respecto a lo novado -- sin duplicar nada.
     *
     * @return array<int, string> Descripción de cada diferencia; vacío si no hay ninguna.
     */
    private function cambiosDesdeLaNovacion(string $bookingId): array
    {
        $lineas = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->get(['texto', 'importe', 'importe_novacion', 'importe_novado',
                   'propietario', 'propietario_novado', 'deleted']);

        $euros  = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $cambios = [];

        foreach ($lineas as $l) {
            $estabaAlNovar = $l->importe_novado !== null;
            $efectivo      = (float) ($l->importe_novacion ?? $l->importe);

            if ($estabaAlNovar && $l->deleted) {
                $cambios[] = "«{$l->texto}»: Icnea ya no la devuelve (valía " . $euros($l->importe_novado) . ').';
                continue;
            }

            if ($l->deleted) {
                continue;   // ya estaba borrada al novar: nada que contar
            }

            if (!$estabaAlNovar) {
                if (!in_array($l->texto, self::TEXTOS_PROPIOS, true)) {
                    $cambios[] = "«{$l->texto}»: línea nueva de " . $euros($efectivo) . ', no estaba al novar.';
                }
                continue;
            }

            if (abs($efectivo - (float) $l->importe_novado) > self::TOLERANCIA) {
                $cambios[] = "«{$l->texto}»: " . $euros($l->importe_novado) . ' → ' . $euros($efectivo) . '.';
            }

            if ($l->propietario_novado !== null && (int) $l->propietario !== (int) $l->propietario_novado) {
                $cambios[] = "«{$l->texto}»: " . ((int) $l->propietario ? 'se ha incluido en' : 'se ha excluido de')
                           . ' el cálculo.';
            }
        }

        return $cambios;
    }

    /**
     * Abre una tarea de revisión si alguna reserva ya novada de esta propiedad+mes ha cambiado
     * desde que se novó.
     *
     * Esto es lo que comprobarDiferenciaYCrearTarea() NO ve: aquella compara totales ya
     * materializados (base_propietario), que no se mueven aunque Icnea cambie un importe.
     */
    public function comprobarCambiosNovadas(int $propId, int $year, int $month): ?array
    {
        $mp    = str_pad($month, 2, '0', STR_PAD_LEFT);
        $desde = "{$year}-{$mp}-01";
        $hasta = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $reservas = DB::table('vm_reservas')
            ->where('id_propiedades', $propId)
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->where('novacion', 1)
            // Solo las que tienen foto. Las novadas antes de que esto existiera no la tienen, y
            // sin ella TODAS sus lineas pareceran "nuevas": se avisaria de un cambio que no ha
            // habido. Volveran a vigilarse la proxima vez que se guarde su novacion.
            ->whereNotNull('novacion_at')
            ->whereNotIn('booking_status', ['cancelled'])
            ->get(['booking_id', 'guest_name']);

        $detalle = [];
        foreach ($reservas as $r) {
            foreach ($this->cambiosDesdeLaNovacion((string) $r->booking_id) as $cambio) {
                $detalle[] = "Reserva {$r->booking_id}" . ($r->guest_name ? " ({$r->guest_name})" : '') . " — {$cambio}";
            }
        }

        if (!$detalle) {
            return null;
        }

        $propiedad = DB::table('vm_propiedades')->where('id', $propId)->value('nombre');
        $mesLabel  = self::MESES_ES[$month] . '-' . $year;

        return $this->crearTareaRevision(
            $propId, $year, $month,
            "Revisar novación {$mesLabel} de {$propiedad}: han cambiado importes",
            "Estas reservas ya novadas de {$mesLabel} para {$propiedad} han cambiado desde que se "
            . "novaron:\n\n- " . implode("\n- ", $detalle)
            . "\n\nRevisar si procede rehacer la novación."
        );
    }

    private function tareaRevisionPendiente(int $propId, int $year, int $month): ?object
    {
        $fechaFin = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $tarea = DB::table('vm_tareas_sscc')
            ->where('id_propiedades', $propId)
            ->where('Tipo', 'Revisión Novación')
            ->where('fecha_planificada', $fechaFin)
            ->where('deleted', 0)
            ->where('estado', '!=', 'Completada')
            ->orderByDesc('id')
            ->first(['id', 'nombre', 'estado']);

        return $tarea ?: null;
    }

    private function fetchReservationIcnea(string $apiKey, string $ownerId, string $bookingId): ?array
    {
        $url = 'https://ws.icnea.net/services_get_reservation.aspx?' . http_build_query([
            'api_key'    => $apiKey,
            'owner_id'   => $ownerId,
            'booking_id' => $bookingId,
        ]);

        $ctx = stream_context_create(['http' => [
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        $resp = $data['services_get_reservation_response'] ?? null;
        if (!is_array($resp)) {
            return null;
        }

        // Icnea dejó de anidar la reserva bajo 'reservations' en algún momento antes del
        // 2026-08-20: ahora los campos (detail, channel_commission...) vienen directamente en el
        // primer nivel. Se aceptan las dos formas para no depender de cuál sirva hoy.
        return $resp['reservations'] ?? $resp;
    }

    public function saveComisionBancos(Request $request, Project $project)
    {
        $bookingId = $request->input('booking_id');
        $importe   = (float) $request->input('importe', 0);
        $reserva   = DB::table('vm_reservas')->where('booking_id', $bookingId)->first();
        if (!$reserva) return response()->json(['error' => 'No encontrada'], 404);

        $existing = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->where('texto', 'Comisión Bancos')
            ->first();

        if ($existing) {
            DB::table('vm_reservas_importes')->where('id', $existing->id)->update([
                'importe'   => $importe,
                'updatedat' => now(),
            ]);
            return response()->json(['id' => $existing->id, 'importe' => $importe, 'propietario' => $existing->propietario]);
        }

        $id = DB::table('vm_reservas_importes')->insertGetId([
            'id_reserva'  => $reserva->id,
            'booking_id'  => $bookingId,
            'texto'       => 'Comisión Bancos',
            'importe'     => $importe,
            'propietario' => 1,
            'createdat'   => now(),
            'updatedat'   => now(),
        ]);

        return response()->json(['id' => $id, 'importe' => $importe, 'propietario' => 1]);
    }

    // ── PDF ─────────────────────────────────────────────────────────────────

    public function pdf(Request $request, Project $project)
    {
        $propId = (int) $request->input('prop_id');
        $year   = (int) $request->input('year');
        $month  = (int) $request->input('month');

        $mp           = str_pad($month, 2, '0', STR_PAD_LEFT);
        $desde        = "{$year}-{$mp}-01";
        $hasta        = Carbon::create($year, $month)->endOfMonth()->toDateString();
        $fechaNovacion = $hasta;

        $propiedad = DB::table('vm_propiedades')->where('id', $propId)->first();

        $reservas = DB::table('vm_reservas')
            ->where('id_propiedades', $propId)
            ->whereBetween('check_out_date', [$desde, $hasta])
            ->where('novacion', 1)
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('check_out_date')
            ->get(['booking_id','guest_name','check_in_date','check_out_date','base_propietario','base_calculo']);

        // Gastos
        $gastos = DB::table('vm_novaciones_gastos')
            ->where('id_propiedades', $propId)
            ->where('fecha_novacion', $fechaNovacion)
            ->first();

        $tareas = DB::table('vm_tareas_mantenimiento')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['Descartada', 'Cancelada']))
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')
            ->orderBy('fecha_finalizacion')
            ->get(['fecha_finalizacion','nombre','nombre_novacion','importe_novacion']);

        $piscinas = DB::table('vm_tareas_piscinas')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['Descartada', 'Cancelada']))
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')
            ->orderBy('fecha_finalizacion')
            ->get(['fecha_finalizacion','nombre','nombre_novacion','importe_novacion']);

        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $logoPath  = public_path('projects/vm/logo.png');
        $logoB64   = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $SUMI = [
            'electricidad' => 'Electricidad',
            'agua'         => 'Agua',
            'internet'     => 'Internet',
            'alarma'       => 'Alarma',
            'jardineria'   => 'Jardinería',
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('novacion-pdf', compact(
            'propiedad','reservas','gastos','tareas','piscinas',
            'meses_es','year','month','logoB64','SUMI','fechaNovacion'
        ))->setPaper('a4', 'portrait');

        $nombre  = 'novacion_' . ($propiedad->nombre ?? 'propiedad') . "_{$year}_{$mp}.pdf";
        $nombre  = str_replace(' ', '_', $nombre);
        $binario = $pdf->output();

        // Histórico: snapshot de los totales + copia del PDF, para poder detectar
        // más adelante si algo cambió respecto a lo que se documentó aquí.
        $totales    = $this->calcularTotalesNovacion($propId, $year, $month);
        $rutaRel    = "novaciones/{$propId}/{$year}-{$mp}/" . now()->format('YmdHis') . '_' . $nombre;
        \Illuminate\Support\Facades\Storage::disk('local')->put($rutaRel, $binario);

        DB::table('vm_novaciones_documentos')->insert([
            'id_propiedades'      => $propId,
            'year'                => $year,
            'month'               => $month,
            'fecha_novacion'      => $fechaNovacion,
            'createuser'          => auth()->id(),
            'createdat'           => now(),
            'importe_propietario' => $totales['importe_propietario'],
            'importe_vm'          => $totales['importe_vm'],
            'total_gastos'        => $totales['total_gastos'],
            'pdf_path'            => $rutaRel,
            'deleted'             => 0,
        ]);

        return response($binario, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    public function verDocumento(Request $request, Project $project, int $id)
    {
        $doc = DB::table('vm_novaciones_documentos')->where('id', $id)->where('deleted', 0)->first();
        if (!$doc || !$doc->pdf_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($doc->pdf_path)) {
            abort(404, 'Documento no encontrado');
        }

        return response(\Illuminate\Support\Facades\Storage::disk('local')->get($doc->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="novacion_' . $id . '.pdf"',
        ]);
    }

    // ── GASTOS ──────────────────────────────────────────────────────────────

    public function gastos(Request $request, Project $project)
    {
        $propId = (int) $request->input('prop_id');
        $year   = (int) $request->input('year');
        $month  = (int) $request->input('month');

        $mp           = str_pad($month, 2, '0', STR_PAD_LEFT);
        $fechaNovacion = Carbon::create($year, $month)->endOfMonth()->toDateString();
        $desde        = "{$year}-{$mp}-01";
        $hasta        = $fechaNovacion;

        $gastos = DB::table('vm_novaciones_gastos')
            ->where('id_propiedades', $propId)
            ->where('fecha_novacion', $fechaNovacion)
            ->first();

        $mantenimiento = DB::table('vm_tareas_mantenimiento')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['Descartada', 'Cancelada']))
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->orderBy('fecha_finalizacion')
            ->get(['id', 'nombre', 'fecha_finalizacion', 'nombre_novacion', 'importe_novacion']);

        $piscinas = DB::table('vm_tareas_piscinas')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
            ->where(fn($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['Descartada', 'Cancelada']))
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->orderBy('fecha_finalizacion')
            ->get(['id', 'nombre', 'fecha_finalizacion', 'nombre_novacion', 'importe_novacion']);

        return response()->json([
            'gastos'        => $gastos,
            'mantenimiento' => $mantenimiento,
            'piscinas'      => $piscinas,
            'fecha_novacion'=> $fechaNovacion,
        ]);
    }

    public function saveGastos(Request $request, Project $project)
    {
        $propId        = (int) $request->input('prop_id');
        $fechaNovacion = $request->input('fecha_novacion');
        $importes = ['electricidad', 'agua', 'internet', 'alarma', 'jardineria'];
        $fechas   = ['fecha_electricidad', 'fecha_agua', 'fecha_internet', 'fecha_alarma', 'fecha_jardineria'];

        $data = ['updatedat' => now()];
        foreach ($importes as $c) {
            $v = $request->input($c);
            $data[$c] = ($v !== null && $v !== '') ? (float) $v : null;
        }
        foreach ($fechas as $c) {
            $v = $request->input($c);
            $data[$c] = ($v !== null && $v !== '') ? $v : null;
        }

        $existing = DB::table('vm_novaciones_gastos')
            ->where('id_propiedades', $propId)
            ->where('fecha_novacion', $fechaNovacion)
            ->first();

        if ($existing) {
            DB::table('vm_novaciones_gastos')->where('id', $existing->id)->update($data);
            return response()->json(['id' => $existing->id]);
        }

        $data['id_propiedades'] = $propId;
        $data['fecha_novacion'] = $fechaNovacion;
        $data['createdat']      = now();
        $id = DB::table('vm_novaciones_gastos')->insertGetId($data);
        return response()->json(['id' => $id]);
    }

    public function createTarea(Request $request, Project $project)
    {
        $propId        = (int) $request->input('prop_id');
        $year          = (int) $request->input('year');
        $month         = (int) $request->input('month');
        $nombreNov     = $request->input('nombre_novacion') ?: null;
        $importeNov    = $request->input('importe_novacion');
        $importeNov    = ($importeNov !== null && $importeNov !== '') ? (float) $importeNov : null;

        $fechaFin      = Carbon::create($year, $month)->endOfMonth()->toDateString();
        $userId        = auth()->id();

        $id = DB::table('vm_tareas_mantenimiento')->insertGetId([
            'nombre'          => 'Tarea ficticia para novación',
            'fecha_planificada'  => $fechaFin,
            'fecha_finalizacion' => $fechaFin,
            'control_user'    => json_encode([(string) $userId]),
            'Tipo'            => 'Novación',
            'hidden'          => 1,
            'deleted'         => 0,
            'blocked'         => 0,
            'id_propiedades'  => $propId,
            'descripcion'     => 'Tarea ficticia creada para reflejar en la novación los gastos de varias actuaciones de mantenimiento.',
            'nombre_novacion' => $nombreNov,
            'importe_novacion'=> $importeNov,
            'createdat'       => now(),
            'updatedat'       => now(),
        ]);

        $tarea = DB::table('vm_tareas_mantenimiento')
            ->where('id', $id)
            ->first(['id', 'nombre', 'fecha_finalizacion', 'nombre_novacion', 'importe_novacion']);

        return response()->json(['ok' => true, 'tarea' => $tarea]);
    }

    public function updateTarea(Request $request, Project $project)
    {
        $id    = (int) $request->input('id');
        $tabla = $request->input('tabla'); // mantenimiento | piscinas
        $table = $tabla === 'piscinas' ? 'vm_tareas_piscinas' : 'vm_tareas_mantenimiento';

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) return response()->json(['error' => 'No encontrado'], 404);

        $data = ['updatedat' => now()];

        if ($request->has('nombre_novacion')) {
            $data['nombre_novacion'] = $request->input('nombre_novacion') ?: null;
        }
        if ($request->has('importe_novacion')) {
            $v = $request->input('importe_novacion');
            $data['importe_novacion'] = ($v !== null && $v !== '') ? (float) $v : null;
        }

        DB::table($table)->where('id', $id)->update($data);
        return response()->json(['ok' => true]);
    }
}
