<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidacionController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes',  now()->month);

        $anio = max(2020, min(2030, $anio));
        $mes  = max(1,    min(12,   $mes));

        $reservas = DB::table('vm_reservas as r')
            ->join('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('p.deleted', 0)
            ->where('p.tipo_renta', 'Variable')
            ->whereYear('r.check_out_date', $anio)
            ->whereMonth('r.check_out_date', $mes)
            ->whereNotIn('r.booking_status', ['cancelled', 'canceled'])
            ->orderBy('p.id')
            ->orderBy('r.check_out_date')
            ->get([
                'r.id', 'r.booking_id', 'r.guest_name',
                'r.check_in_date', 'r.check_out_date',
                'r.liquidado',
                'p.id as propiedad_id',
                'p.nombre as propiedad',
            ]);

        $bookingIds = $reservas->pluck('booking_id');

        // Suma de importes marcados como propietario por reserva
        $importesProp = DB::table('vm_reservas_importes')
            ->whereIn('booking_id', $bookingIds)
            ->where('propietario', 1)
            ->groupBy('booking_id')
            ->selectRaw('booking_id, SUM(importe) as total')
            ->pluck('total', 'booking_id');

        // Comisión canal desde vm_reservas_importes
        $comisionCanal = DB::table('vm_reservas_importes')
            ->whereIn('booking_id', $bookingIds)
            ->where('texto', 'Comisión canal')
            ->pluck('importe', 'booking_id');

        $byPropiedad = $reservas->groupBy('propiedad');

        $meses = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre',
        ];

        return view('liquidacion', [
            'project'       => $project,
            'byPropiedad'   => $byPropiedad,
            'importesProp'  => $importesProp,
            'comisionCanal' => $comisionCanal,
            'anio'          => $anio,
            'mes'           => $mes,
            'meses'         => $meses,
            'breadcrumb'    => [
                ['label' => 'Planilla de liquidación', 'url' => ''],
            ],
        ]);
    }

    public function pdf(Request $request, Project $project)
    {
        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes',  now()->month);
        $anio = max(2020, min(2030, $anio));
        $mes  = max(1,    min(12,   $mes));

        $reservas = DB::table('vm_reservas as r')
            ->join('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('p.deleted', 0)
            ->where('p.tipo_renta', 'Variable')
            ->whereYear('r.check_out_date', $anio)
            ->whereMonth('r.check_out_date', $mes)
            ->whereNotIn('r.booking_status', ['cancelled', 'canceled'])
            ->orderBy('p.id')
            ->orderBy('r.check_out_date')
            ->get(['r.id', 'r.booking_id', 'r.guest_name', 'r.check_in_date', 'r.check_out_date', 'r.liquidado', 'p.id as propiedad_id', 'p.nombre as propiedad']);

        $bookingIds    = $reservas->pluck('booking_id');
        $importesProp  = DB::table('vm_reservas_importes')->whereIn('booking_id', $bookingIds)->where('propietario', 1)->groupBy('booking_id')->selectRaw('booking_id, SUM(importe) as total')->pluck('total', 'booking_id');
        $comisionCanal = DB::table('vm_reservas_importes')->whereIn('booking_id', $bookingIds)->where('texto', 'Comisión canal')->pluck('importe', 'booking_id');
        $byPropiedad   = $reservas->groupBy('propiedad');

        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('liquidacion-pdf', compact('byPropiedad', 'importesProp', 'comisionCanal', 'anio', 'mes', 'meses'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("liquidacion_{$anio}_{$mes}.pdf");
    }

    public function toggleLiquidado(Request $request, Project $project, int $reservaId)
    {
        $reserva = DB::table('vm_reservas')->where('id', $reservaId)->first(['id', 'liquidado']);
        if (!$reserva) {
            return response()->json(['ok' => false], 404);
        }
        $nuevo = $reserva->liquidado ? 0 : 1;
        DB::table('vm_reservas')->where('id', $reservaId)->update(['liquidado' => $nuevo]);
        return response()->json(['ok' => true, 'liquidado' => $nuevo]);
    }
}
