<?php

namespace App\Http\Controllers\Alegre;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarioReservasController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $dias        = max(14, min(60, (int) $request->input('dias', 30)));
        $desdeRaw    = $request->input('desde', now()->toDateString());
        try { $desdeCarbon = \Carbon\Carbon::parse($desdeRaw); }
        catch (\Exception $e) { $desdeCarbon = now(); }
        $desde = $desdeCarbon->toDateString();
        $hasta = $desdeCarbon->copy()->addDays($dias)->toDateString();
        $salidas = $request->input('salidas'); // 'hoy' | 'manana' | null

        $baseQuery = DB::table('alegre_reservas as r')
            ->join('alegre_propiedades as p', 'p.id', '=', 'r.propiedad')
            ->leftJoin('alegre_clientes as c', 'c.id', '=', 'r.cliente')
            ->where('p.deleted', 0)
            ->where('r.deleted', 0);

        // Conteos para los stats
        $salidasHoy    = (clone $baseQuery)->whereDate('r.fecha_checkout', now()->toDateString())->count();
        $salidasManana = (clone $baseQuery)->whereDate('r.fecha_checkout', now()->addDay()->toDateString())->count();

        // Filtro por salidas si se activa el stat
        $reservasQuery = (clone $baseQuery)
            ->where('r.fecha_checkout', '>=', $desde)
            ->where('r.fecha_checkin', '<=', $hasta)
            ->orderBy('p.nombre')
            ->orderBy('r.fecha_checkin');

        if ($salidas === 'hoy') {
            $propsFiltradas = (clone $baseQuery)
                ->whereDate('r.fecha_checkout', now()->toDateString())
                ->pluck('p.nombre')->unique();
            $reservasQuery->whereIn('p.nombre', $propsFiltradas);
        } elseif ($salidas === 'manana') {
            $propsFiltradas = (clone $baseQuery)
                ->whereDate('r.fecha_checkout', now()->addDay()->toDateString())
                ->pluck('p.nombre')->unique();
            $reservasQuery->whereIn('p.nombre', $propsFiltradas);
        }

        $reservas = $reservasQuery->get([
            'p.nombre as propiedad',
            'r.id',
            'r.fecha_checkin as check_in_date',
            'r.fecha_checkout as check_out_date',
            'r.importe',
            'c.nombre as guest_name',
        ]);

        // Importe cobrado por reserva (suma de alegre_reserva_importes)
        $cobrosPorReserva = DB::table('alegre_reserva_importes')
            ->where('deleted', 0)
            ->whereIn('reserva', $reservas->pluck('id'))
            ->groupBy('reserva')
            ->select('reserva', DB::raw('SUM(importe) as total_cobrado'))
            ->pluck('total_cobrado', 'reserva');

        $reservas = $reservas->map(function ($r) use ($cobrosPorReserva) {
            $cobrado = (float) ($cobrosPorReserva[$r->id] ?? 0);
            $total   = (float) ($r->importe ?? 0);

            $r->booking_status = match(true) {
                $total > 0 && $cobrado >= $total => 'arrived',    // pagada
                $cobrado > 0                      => 'confirmed', // confirmada (parcial)
                default                           => 'requested', // provisional
            };
            $r->guest_name = $r->guest_name ?? 'Sin cliente';

            return $r;
        });

        $propiedades = $reservas->pluck('propiedad')->unique()->sort()->values();

        $reservasPorPropiedad = $reservas->groupBy('propiedad');

        // No hay tareas de limpieza/mantenimiento en el proyecto alegre
        $tareasPorPropiedad = collect();

        return view('calendario-reservas', [
            'project'                => $project,
            'desde'                  => $desde,
            'propiedades'            => $propiedades,
            'reservasPorPropiedad'   => $reservasPorPropiedad,
            'tareasPorPropiedad'     => $tareasPorPropiedad,
            'dias'                   => $dias,
            'salidasHoy'             => $salidasHoy,
            'salidasManana'          => $salidasManana,
            'salidasFiltro'          => $salidas,
            'legend'                 => [
                ['color' => '#86efac', 'label' => 'Pagada'],
                ['color' => '#93c5fd', 'label' => 'Confirmada (pago parcial)'],
                ['color' => '#fde68a', 'label' => 'Provisional (sin cobro)'],
            ],
            'breadcrumb'             => [
                ['label' => 'Calendario de reservas', 'url' => ''],
            ],
        ]);
    }
}
