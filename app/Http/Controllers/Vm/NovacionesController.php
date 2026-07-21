<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NovacionesController extends Controller
{
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

        [$yAnterior, $mAnterior] = $this->mesAnterior($year, $month);
        $tareaRevision = $propId ? $this->tareaRevisionPendiente($propId, $yAnterior, $mAnterior) : null;

        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

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
            'tarea_revision' => $tareaRevision,
            'mes_anterior_label' => $meses_es[$mAnterior] . ' ' . $yAnterior,
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
            ->where('importe', '>', 0)
            ->exists();

        if (!$tieneCC) {
            $totalReserva = (float) DB::table('vm_reservas_importes')
                ->where('booking_id', $bookingId)
                ->where('deleted', 0)
                ->whereNotIn('texto', ['Management Fee', 'Comisión Bancos', 'Comisión canal'])
                ->sum('importe');

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

        $importes = DB::table('vm_reservas_importes')
            ->where('booking_id', $bookingId)
            ->orderBy('id')
            ->get(['id', 'texto', 'importe', 'propietario', 'deleted']);

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

    public function guardar(Request $request, Project $project)
    {
        $bookingId       = $request->input('booking_id');
        $basePropietario = $request->input('base_propietario');
        $baseCalculo     = $request->input('base_calculo');
        $reserva         = DB::table('vm_reservas')->where('booking_id', $bookingId)->first();
        if (!$reserva) return response()->json(['error' => 'No encontrada'], 404);

        DB::table('vm_reservas')->where('booking_id', $bookingId)->update([
            'novacion'         => 1,
            'base_propietario' => $basePropietario,
            'base_calculo'     => $baseCalculo,
            'updatedat'        => now(),
        ]);

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

        // Mes anterior — se re-sincroniza siempre de paso, y si los subtotales que
        // ya se documentaron (vm_novaciones_documentos) dejan de coincidir con los
        // recién recalculados, se abre una tarea de revisión para Contabilidad.
        [$yAnt, $mAnt] = $this->mesAnterior($year, $month);
        $resultadoMesAnterior = $this->reconciliarMes($propId, $yAnt, $mAnt);
        $this->marcarSincronizado($propId, Carbon::create($yAnt, $mAnt)->endOfMonth()->toDateString());

        $tareaCreada = $this->comprobarDiferenciaYCrearTarea($propId, $yAnt, $mAnt);

        $ahora = now();

        return response()->json([
            'ok'                    => true,
            'procesadas'            => $resultadoMesActual['procesadas'],
            'errores'               => $resultadoMesActual['errores'] + $resultadoMesAnterior['errores'],
            'ultima_sincronizacion' => $ahora->format('d/m/Y H:i'),
            'mes_anterior'          => ['year' => $yAnt, 'month' => $mAnt],
            'tarea_revision'        => $tareaCreada,
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

        $apiKey           = 'v$c$t$321$m$r$b';
        $ownerId          = '1540';
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
            ->leftJoin('vm_usuarios as u', 'u.id', '=', 'd.createuser')
            ->where('d.id_propiedades', $propId)
            ->where('d.year', $year)
            ->where('d.month', $month)
            ->where('d.deleted', 0)
            ->orderByDesc('d.createdat')
            ->select('d.id', 'd.createdat', 'd.importe_propietario', 'd.importe_vm', 'd.total_gastos', 'u.nombre as createuser_nombre')
            ->get();
    }

    // Compara los totales ya documentados del mes indicado contra los recién recalculados.
    // Si difieren y no hay ya una tarea de revisión abierta para esa propiedad+mes, crea una.
    private function comprobarDiferenciaYCrearTarea(int $propId, int $year, int $month): ?array
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

        $propiedad = DB::table('vm_propiedades')->where('id', $propId)->value('nombre');
        $deptContabilidad = DB::table('vm_departamentos')->where('nombre', 'Adm/Finanzas')->value('id');
        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $mesLabel = $meses_es[$month] . '-' . $year;

        $idTarea = DB::table('vm_tareas_sscc')->insertGetId([
            'nombre'            => "Revisar novación {$mesLabel} de {$propiedad}",
            'descripcion'       => "Los importes ya documentados de la novación de {$mesLabel} para {$propiedad} han cambiado tras sincronizar con Icnea. Revisar antes de dar por buena la novación anterior.",
            'Tipo'              => 'Revisión Novación',
            'estado'            => 'Nueva',
            'fecha_planificada' => $fechaFin,
            'id_propiedades'    => $propId,
            'id_departamento'   => $deptContabilidad,
            'hidden'            => 0,
            'deleted'           => 0,
            'blocked'           => 0,
            'createuser'        => auth()->id(),
            'createdat'         => now(),
            'updatedat'         => now(),
        ]);

        return ['creada' => true, 'id' => $idTarea];
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
        return $data['services_get_reservation_response']['reservations'] ?? null;
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
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')
            ->orderBy('fecha_finalizacion')
            ->get(['fecha_finalizacion','nombre_novacion','importe_novacion']);

        $piscinas = DB::table('vm_tareas_piscinas')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->whereNotNull('importe_novacion')
            ->orderBy('fecha_finalizacion')
            ->get(['fecha_finalizacion','nombre_novacion','importe_novacion']);

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
            ->whereBetween('fecha_finalizacion', [$desde, $hasta])
            ->orderBy('fecha_finalizacion')
            ->get(['id', 'nombre', 'fecha_finalizacion', 'nombre_novacion', 'importe_novacion']);

        $piscinas = DB::table('vm_tareas_piscinas')
            ->where('id_propiedades', $propId)
            ->where('deleted', 0)
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
