<?php

namespace App\Http\Controllers\Mb;

use App\Exports\ViviendasExport;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ViviendasController extends Controller
{
    // "Deuda" = cuotas todavía reclamables: Pendiente (aún sin acción legal) o Demandada (en curso).
    // Incobrable/Anulada/Pagada no cuentan como deuda viva.
    private const ESTADOS_DEUDA = ['Pendiente', 'Demandada'];

    // Movimientos informativos en mb_cuotas: se insertan/visualizan, pero no cuentan como
    // deuda, emisión ni cobro en ningún cálculo (confirmado por el cliente 2026-08-08).
    private const TIPOS_CUOTA_INFORMATIVOS = ['G.dev.', 'Dudoso', 'Entrega a cuenta'];

    private function idAsambleaActual(): ?int
    {
        return DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->value('id');
    }

    private function baseQuery(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $idAsamblea = (int) ($this->idAsambleaActual() ?? 0);

        $query = DB::table('mb_viviendas as v')
            ->where('v.deleted', 0)
            ->when($q !== '', fn ($qq) => $qq->where(fn ($sub) => $sub
                ->where('v.nombre', 'ilike', '%' . $q . '%')
                ->orWhereExists(fn ($ex) => $ex->selectRaw('1')->from('mb_propietarios_historico as ph')
                    ->join('mb_propietarios as p', 'p.id', '=', 'ph.id_propietarios')
                    ->whereColumn('ph.id_viviendas', 'v.id')
                    ->where('ph.deleted', 0)
                    ->where('p.nombre', 'ilike', '%' . $q . '%'))
            ))
            ->select([
                'v.id', 'v.nombre',
                DB::raw("(SELECT p.nombre FROM mb_propietarios_historico ph
                          JOIN mb_propietarios p ON p.id = ph.id_propietarios
                          WHERE ph.id_viviendas = v.id AND ph.deleted = 0
                          ORDER BY (ph.fecha_hasta IS NULL) DESC, ph.fecha_desde DESC LIMIT 1) as ultimo_propietario"),
                DB::raw("COALESCE((SELECT SUM(c.pendiente) FROM mb_cuotas c
                          WHERE c.id_viviendas = v.id AND c.estado NOT IN ('Anulada','Incobrable') AND c.pendiente > 0
                          AND c.tipo_cuota NOT IN ('G.dev.','Dudoso','Entrega a cuenta')), 0) as deuda_acumulada"),
                DB::raw("COALESCE((SELECT COUNT(*) FROM mb_cuotas c
                          WHERE c.id_viviendas = v.id AND c.estado = 'Pendiente'
                          AND c.tipo_cuota NOT IN ('G.dev.','Dudoso','Entrega a cuenta')), 0) as cuotas_ptes"),
                DB::raw("COALESCE((SELECT COUNT(*) FROM mb_cuotas c
                          WHERE c.id_viviendas = v.id AND c.estado = 'Demandada'
                          AND c.tipo_cuota NOT IN ('G.dev.','Dudoso','Entrega a cuenta')), 0) as cuotas_demandadas"),
                DB::raw("(SELECT ah.numero_hoja FROM mb_asambleas_hojas ah
                          WHERE ah.id_viviendas = v.id AND ah.id_asambleas = {$idAsamblea} AND ah.deleted = 0
                          LIMIT 1) as voto"),
            ]);

        if ($request->boolean('stat_a_demandar')) {
            $query->whereIn('v.id', $this->viviendasProximasAPrescribir());
        }

        if ($request->boolean('stat_adheridas')) {
            $query->where('v.id_edificios', $this->edificioAdheridosId());
        }

        return $query;
    }

    public function index(Request $request, Project $project)
    {
        $viviendas = $this->baseQuery($request)->orderByDesc('deuda_acumulada')->get();

        $viviendasADemandar = $this->viviendasProximasAPrescribir();
        $viviendas->each(function ($v) use ($viviendasADemandar) {
            $v->a_demandar = in_array($v->id, $viviendasADemandar);
        });

        $sortField = $request->input('sort');
        $sortDir   = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        if ($sortField && in_array($sortField, ['nombre', 'ultimo_propietario', 'deuda_acumulada', 'cuotas_ptes', 'cuotas_demandadas', 'a_demandar', 'voto'])) {
            $viviendas = $sortDir === 'asc'
                ? $viviendas->sortBy($sortField)->values()
                : $viviendas->sortByDesc($sortField)->values();
        }

        $cuotasPorVivienda = DB::table('mb_cuotas')
            ->whereIn('id_viviendas', $viviendas->pluck('id'))
            ->whereNotIn('estado', ['Anulada', 'Incobrable'])
            ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->where('pendiente', '>', 0)
            ->orderByDesc('fecha_emision')
            ->get(['id', 'id_viviendas', 'ejercicio', 'tipo_cuota', 'fecha_emision', 'estado', 'importe', 'pendiente'])
            ->groupBy('id_viviendas');

        $entregasPorVivienda = DB::table('mb_entregas_cuenta')
            ->whereIn('id_viviendas', $viviendas->pluck('id'))
            ->where('deleted', 0)
            ->whereRaw('importe > importe_aplicado')
            ->orderByDesc('fecha')
            ->get(['id', 'id_viviendas', 'fecha', 'concepto', 'importe', 'importe_aplicado'])
            ->groupBy('id_viviendas');

        $totalADemandar = DB::table('mb_viviendas as v')
            ->whereIn('v.id', $viviendasADemandar)
            ->where('v.deleted', 0)
            ->count();
        $importeADemandar = (float) DB::table('mb_cuotas')
            ->whereIn('id_viviendas', $viviendasADemandar)
            ->whereIn('estado', self::ESTADOS_DEUDA)
            ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->sum('pendiente');

        $totalAdheridas = DB::table('mb_viviendas')
            ->where('id_edificios', $this->edificioAdheridosId())
            ->where('deleted', 0)
            ->count();

        return view('mb.viviendas-listado', [
            'project' => $project,
            'viviendas' => $viviendas,
            'cuotasPorVivienda' => $cuotasPorVivienda,
            'entregasPorVivienda' => $entregasPorVivienda,
            'q' => $request->input('q', ''),
            'sortField' => $sortField,
            'sortDir' => $sortDir,
            'statADemandarActivo' => $request->boolean('stat_a_demandar'),
            'totalADemandar' => $totalADemandar,
            'importeADemandar' => $importeADemandar,
            'statAdheridasActivo' => $request->boolean('stat_adheridas'),
            'totalAdheridas' => $totalAdheridas,
        ]);
    }

    public function export(Request $request, Project $project)
    {
        $viviendas = $this->baseQuery($request)->orderByDesc('deuda_acumulada')->get();
        $conMovimientos = $request->boolean('movimientos');

        $cuotasPorVivienda = collect();
        if ($conMovimientos) {
            $cuotasPorVivienda = DB::table('mb_cuotas')
                ->whereIn('id_viviendas', $viviendas->pluck('id'))
                ->whereNotIn('estado', ['Anulada', 'Incobrable'])
                ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
                ->where('pendiente', '>', 0)
                ->orderByDesc('fecha_emision')
                ->get(['id_viviendas', 'ejercicio', 'tipo_cuota', 'fecha_emision', 'estado', 'importe', 'pendiente'])
                ->groupBy('id_viviendas');
        }

        $filename = 'viviendas_' . ($conMovimientos ? 'con_movimientos' : 'listado') . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new ViviendasExport($viviendas, $cuotasPorVivienda, $conMovimientos), $filename);
    }

    // Misma regla que la tarjeta "A demandar" del listado de cuotas: viviendas con alguna cuota
    // pendiente (no demandada/anulada) cuya antigüedad desde fecha_emision cumple 5 años en el
    // PRÓXIMO ejercicio -- último momento para reclamarla antes de que prescriba.
    private function viviendasProximasAPrescribir(): array
    {
        $hoy  = now();
        $anio = (int) $hoy->format('n') >= 7 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
        $nextStart = ($anio + 1) . '-07-01';
        $nextEnd   = ($anio + 2) . '-06-30';

        return DB::table('mb_cuotas')
            ->where('estado', 'Pendiente')
            ->where('pendiente', '>', 0)
            ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->whereRaw("(fecha_emision + INTERVAL '5 years') BETWEEN ? AND ?", [$nextStart, $nextEnd])
            ->distinct()
            ->pluck('id_viviendas')
            ->filter()
            ->values()
            ->all();
    }

    private function edificioAdheridosId(): ?int
    {
        return DB::table('mb_edificios')->where('nombre', 'ADHERIDOS')->where('deleted', 0)->value('id');
    }
}
