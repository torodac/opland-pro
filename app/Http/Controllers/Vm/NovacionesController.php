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
        }

        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return view('novaciones', [
            'project'     => $project,
            'propiedades' => $propiedades,
            'prop_id'     => $propId,
            'propiedad'   => $propiedad,
            'year'        => $year,
            'month'       => $month,
            'reservas'    => $reservas,
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
            ->where('deleted', 0)
            ->orderBy('id')
            ->get(['id', 'texto', 'importe', 'propietario']);

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

        $nombre = 'novacion_' . ($propiedad->nombre ?? 'propiedad') . "_{$year}_{$mp}.pdf";
        return $pdf->download(str_replace(' ', '_', $nombre));
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
