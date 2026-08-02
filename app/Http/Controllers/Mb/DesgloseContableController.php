<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesgloseContableController extends Controller
{
    // Orden de las agrupaciones tal como aparecen en el informe de gestión real (asamblea).
    private const ORDEN_GASTOS = [10, 1, 2, 7, 8, 3, 5, 12, 6, 11];
    private const ORDEN_INGRESOS = [9, 4];
    private const ESTADOS_EXCLUIDOS = ['Anulada', 'Incobrable'];
    // mb_gastos_cuentas.id de "Ingresos cuotas" (77800001), "Ingresos cuotas ejercicios anteriores" (77800099)
    // e "Ingresos derrama" (sin código, id 110): sustituidas por el cálculo real desde mb_cuotas
    // (confirmado 2026-07-28: son asientos de consolidación periódica, no la fuente primaria) — no sumar también desde mb_gastos.
    private const CUENTAS_CUOTAS_SUSTITUIDAS = [52, 104, 110];

    public function index(Request $request, Project $project)
    {
        $ejercicioSel = $request->input('ejercicio') ?: $this->ejercicioActual();
        $modo = $request->input('modo', 'ordinario') === 'extraordinario' ? 'extraordinario' : 'ordinario';

        $anioInicio = (int) substr($ejercicioSel, 0, 4);
        $ejercicios = [
            $this->labelEjercicio($anioInicio),
            $this->labelEjercicio($anioInicio - 1),
            $this->labelEjercicio($anioInicio - 2),
        ];

        $matrices = [
            'ordinario' => [],
            'extraordinario' => [],
        ];
        foreach ($ejercicios as $ej) {
            $matrices['ordinario'][$ej] = $this->matrizOrdinaria($ej);
            $matrices['extraordinario'][$ej] = $this->matrizExtraordinaria($ej);
        }

        $stats = $this->stats($ejercicioSel);
        $chart = $this->datosGrafico($ejercicioSel);

        return view('mb.desglose-contable', compact(
            'project', 'ejercicioSel', 'ejercicios', 'modo', 'matrices', 'stats', 'chart'
        ));
    }

    public function movimientos(Request $request, Project $project)
    {
        $tipo = $request->input('tipo');
        $ejercicio = $request->input('ejercicio');
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        if ($tipo === 'cuenta') {
            $cuentaId = (int) $request->input('id');
            $conProyecto = $request->input('modo') === 'extraordinario';

            $query = DB::table('mb_gastos as g')
                ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
                ->where('g.id_gastos_cuentas', $cuentaId)
                ->where('g.deleted', 0)
                ->when($conProyecto, fn ($q) => $q->whereNotNull('g.id_proyectos'), fn ($q) => $q->whereNull('g.id_proyectos'));
            $rows = $this->whereDevengoEntre($query, $desde, $hasta, 'g')
                ->orderByRaw('COALESCE(g.fecha_devengo, g.fecha_emision)')
                ->get(['g.id', 'g.nombre', 'g.fecha_emision', 'g.fecha_devengo', 'g.importe_neto', 'g.cuenta_aig', 'c.cuenta as cuenta_codigo']);

            return response()->json(['rows' => $this->marcarAigMismatch($rows)]);
        }

        if ($tipo === 'proyecto') {
            $proyectoId = (int) $request->input('id');
            $query = DB::table('mb_gastos as g')
                ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
                ->where('g.id_proyectos', $proyectoId)
                ->where('g.deleted', 0);
            $rows = $this->whereDevengoEntre($query, $desde, $hasta, 'g')
                ->orderByRaw('COALESCE(g.fecha_devengo, g.fecha_emision)')
                ->get(['g.id', 'g.nombre', 'g.fecha_emision', 'g.fecha_devengo', 'g.importe_neto', 'g.cuenta_aig', 'c.cuenta as cuenta_codigo']);

            return response()->json(['rows' => $this->marcarAigMismatch($rows)]);
        }

        if ($tipo === 'cuotas') {
            $subtipo = $request->input('subtipo');
            $query = DB::table('mb_cuotas as c')
                ->leftJoin('mb_viviendas as v', 'v.id', '=', 'c.id_viviendas')
                ->whereBetween('c.fecha_pago', [$desde, $hasta])
                ->whereNotIn('c.estado', self::ESTADOS_EXCLUIDOS);

            if ($subtipo === 'ordinario_ci') {
                $query->where('c.tipo_cuota', 'C-I')->where('c.ejercicio', $ejercicio);
            } elseif ($subtipo === 'ordinario_cii') {
                $query->where('c.tipo_cuota', 'C-II')->where('c.ejercicio', $ejercicio);
            } elseif ($subtipo === 'derrama') {
                $query->where('c.tipo_cuota', 'C-I_Derrama')->where('c.ejercicio', $ejercicio);
            } elseif ($subtipo === 'anteriores') {
                $query->whereIn('c.tipo_cuota', ['C-I', 'C-II', 'C-I_Derrama'])->where('c.ejercicio', '!=', $ejercicio);
            }

            $rows = $query->orderBy('c.fecha_pago')
                ->get(['c.id', 'v.nombre as vivienda', 'c.tipo_cuota', 'c.ejercicio', 'c.fecha_pago', 'c.importe_cobrado']);

            return response()->json(['rows' => $rows]);
        }

        abort(404);
    }

    // "Cuenta AIG" es una referencia contable heredada del sistema antiguo (formato "62000006-NOMBRE");
    // solo sus 8 primeros dígitos son comparables con el código real de la cuenta asignada hoy.
    private function marcarAigMismatch($rows)
    {
        return $rows->map(function ($r) {
            $aigCodigo = $r->cuenta_aig ? substr($r->cuenta_aig, 0, 8) : null;
            $r->aig_codigo = $aigCodigo;
            $r->aig_mismatch = $aigCodigo !== null && $aigCodigo !== $r->cuenta_codigo;
            return $r;
        });
    }

    private function ejercicioActual(): string
    {
        $hoy = now();
        $anio = (int) $hoy->format('n') >= 7 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
        return $this->labelEjercicio($anio);
    }

    private function labelEjercicio(int $anioInicio): string
    {
        return $anioInicio . '-' . ($anioInicio + 1);
    }

    private function limitesEjercicio(string $ejercicio): array
    {
        [$y1, $y2] = explode('-', $ejercicio);
        return [$y1 . '-07-01', $y2 . '-06-30'];
    }

    // El ejercicio de un gasto se determina por su fecha de devengo (económica), no por la fecha
    // de emisión del documento -- salvo las ~4 filas sin devengo informado, que caen a fecha_emision.
    private function whereDevengoEntre($query, string $desde, string $hasta, string $prefijo = '')
    {
        $col = $prefijo !== '' ? "{$prefijo}." : '';
        return $query->whereRaw("COALESCE({$col}fecha_devengo, {$col}fecha_emision) BETWEEN ? AND ?", [$desde, $hasta]);
    }

    // Algunas cuentas contables (p.ej. "Lubina - I.B.I.", "PTE REVISIÓN") no tienen código asignado
    // en el sistema origen: se clasifican por el signo del importe (igual que el resto: 7xx/negativo = ingreso).
    private function esIngresoRow($r): bool
    {
        if ($r->cuenta !== null && $r->cuenta !== '') {
            return str_starts_with((string) $r->cuenta, '7');
        }
        return (float) $r->importe_neto < 0;
    }

    private function esGastoRow($r): bool
    {
        if ($r->cuenta !== null && $r->cuenta !== '') {
            return str_starts_with((string) $r->cuenta, '6');
        }
        return (float) $r->importe_neto >= 0;
    }

    private function cuotasImporte(string $ejercicio, array $tipos, bool $mismoEjercicio): float
    {
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        return (float) DB::table('mb_cuotas')
            ->whereIn('tipo_cuota', $tipos)
            ->whereNotIn('estado', self::ESTADOS_EXCLUIDOS)
            ->when($mismoEjercicio, fn ($q) => $q->where('ejercicio', $ejercicio), fn ($q) => $q->where('ejercicio', '!=', $ejercicio))
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->sum('importe_cobrado');
    }

    private function matrizOrdinaria(string $ejercicio): array
    {
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        $cuotasCI = $this->cuotasImporte($ejercicio, ['C-I'], true);
        $cuotasCII = $this->cuotasImporte($ejercicio, ['C-II'], true);

        $gastosQuery = DB::table('mb_gastos as g')
            ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
            ->leftJoin('mb_gastos_agrupacion as a', 'a.id', '=', 'c.id_gastos_agrupacion')
            ->where('g.deleted', 0)
            ->whereNull('g.id_proyectos');
        $gastosRows = $this->whereDevengoEntre($gastosQuery, $desde, $hasta, 'g')
            ->get(['c.id as cuenta_id', 'c.nombre as cuenta_nombre', 'c.cuenta', 'a.id as agrupacion_id', 'a.nombre as agrupacion_nombre', 'g.importe_neto']);

        $ingresoGroups = $this->agruparCuentas(
            $gastosRows->filter(fn ($r) => $this->esIngresoRow($r) && !in_array($r->cuenta_id, self::CUENTAS_CUOTAS_SUSTITUIDAS)),
            self::ORDEN_INGRESOS,
            signoIngreso: true,
        );

        // La agrupación "Ingresos cuotas" sustituye el cálculo por cuenta contable por el cálculo real desde mb_cuotas.
        array_unshift($ingresoGroups, [
            'label' => 'Ingresos cuotas',
            'total' => $cuotasCI + $cuotasCII,
            'clickKey' => null,
            'accounts' => [
                ['label' => 'Cuotas C-I', 'total' => $cuotasCI, 'clickKey' => ['tipo' => 'cuotas', 'subtipo' => 'ordinario_ci']],
                ['label' => 'Cuotas C-II', 'total' => $cuotasCII, 'clickKey' => ['tipo' => 'cuotas', 'subtipo' => 'ordinario_cii']],
            ],
        ]);

        $gastoGroups = $this->agruparCuentas(
            $gastosRows->filter(fn ($r) => $this->esGastoRow($r)),
            self::ORDEN_GASTOS,
            signoIngreso: false,
        );

        return [
            'ingresos' => ['total' => array_sum(array_column($ingresoGroups, 'total')), 'groups' => $ingresoGroups],
            'gastos'   => ['total' => array_sum(array_column($gastoGroups, 'total')), 'groups' => $gastoGroups],
        ];
    }

    private function matrizExtraordinaria(string $ejercicio): array
    {
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        $derrama = $this->cuotasImporte($ejercicio, ['C-I_Derrama'], true);
        $anteriores = (float) DB::table('mb_cuotas')
            ->whereIn('tipo_cuota', ['C-I', 'C-II', 'C-I_Derrama'])
            ->whereNotIn('estado', self::ESTADOS_EXCLUIDOS)
            ->where('ejercicio', '!=', $ejercicio)
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->sum('importe_cobrado');

        $gastosQuery = DB::table('mb_gastos as g')
            ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
            ->where('g.deleted', 0)
            ->whereNotNull('g.id_proyectos');
        $gastosRows = $this->whereDevengoEntre($gastosQuery, $desde, $hasta, 'g')
            ->get(['c.id as cuenta_id', 'c.nombre as cuenta_nombre', 'c.cuenta', 'g.importe_neto']);

        $porCuenta = $gastosRows
            ->filter(fn ($r) => $this->esIngresoRow($r) && !in_array($r->cuenta_id, self::CUENTAS_CUOTAS_SUSTITUIDAS))
            ->groupBy('cuenta_id');

        $ingresoAccounts = [
            ['label' => 'Ingresos derrama', 'total' => $derrama, 'clickKey' => ['tipo' => 'cuotas', 'subtipo' => 'derrama']],
            ['label' => 'Ingresos cuotas ejercicios anteriores', 'total' => $anteriores, 'clickKey' => ['tipo' => 'cuotas', 'subtipo' => 'anteriores']],
        ];
        foreach ($porCuenta as $cuentaId => $rowsCuenta) {
            $first = $rowsCuenta->first();
            $ingresoAccounts[] = ['label' => $first->cuenta_nombre, 'total' => -$rowsCuenta->sum('importe_neto'), 'clickKey' => ['tipo' => 'cuenta', 'id' => $cuentaId, 'modo' => 'extraordinario']];
        }
        $totalIngresos = array_sum(array_column($ingresoAccounts, 'total'));

        $gastosPorProyectoQuery = DB::table('mb_gastos as g')
            ->join('mb_proyectos as p', 'p.id', '=', 'g.id_proyectos')
            ->where('g.deleted', 0);
        $gastosPorProyecto = $this->whereDevengoEntre($gastosPorProyectoQuery, $desde, $hasta, 'g')
            ->select('p.id as proyecto_id', 'p.nombre as proyecto_nombre', DB::raw('SUM(g.importe_neto) as total'))
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('total')
            ->get();

        $gastoGroups = $gastosPorProyecto->map(fn ($r) => [
            'label' => $r->proyecto_nombre,
            'total' => (float) $r->total,
            'clickKey' => ['tipo' => 'proyecto', 'id' => $r->proyecto_id],
            'accounts' => [],
        ])->values()->all();

        return [
            'ingresos' => ['total' => $totalIngresos, 'groups' => [['label' => 'Ingresos extraordinarios', 'total' => $totalIngresos, 'clickKey' => null, 'accounts' => $ingresoAccounts]]],
            'gastos'   => ['total' => array_sum(array_column($gastoGroups, 'total')), 'groups' => $gastoGroups],
        ];
    }

    private function agruparCuentas($rows, array $orden, bool $signoIngreso): array
    {
        $porAgrupacion = $rows->groupBy(fn ($r) => $r->agrupacion_id ?? 0);

        $ids = $porAgrupacion->keys()->all();
        usort($ids, function ($a, $b) use ($orden) {
            $posA = array_search($a, $orden);
            $posB = array_search($b, $orden);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;
            return $posA <=> $posB;
        });

        $groups = [];
        foreach ($ids as $id) {
            $rowsGrupo = $porAgrupacion->get($id);
            $porCuenta = $rowsGrupo->groupBy('cuenta_id');
            $accounts = $porCuenta->map(function ($rowsCuenta) use ($signoIngreso) {
                $first = $rowsCuenta->first();
                $suma = $rowsCuenta->sum('importe_neto');
                return [
                    'label' => $first->cuenta_nombre,
                    'total' => $signoIngreso ? -$suma : $suma,
                    'clickKey' => ['tipo' => 'cuenta', 'id' => $first->cuenta_id, 'modo' => 'ordinario'],
                ];
            })->sortByDesc('total')->values()->all();

            $groups[] = [
                'label' => $rowsGrupo->first()->agrupacion_nombre ?? 'Sin agrupación',
                'total' => array_sum(array_column($accounts, 'total')),
                'clickKey' => null,
                'accounts' => $accounts,
            ];
        }

        return $groups;
    }

    private function stats(string $ejercicio): array
    {
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        $ordinaria = $this->matrizOrdinaria($ejercicio);
        $extraordinaria = $this->matrizExtraordinaria($ejercicio);

        $totalIngresos = $ordinaria['ingresos']['total'] + $extraordinaria['ingresos']['total'];
        $totalGastos = $ordinaria['gastos']['total'] + $extraordinaria['gastos']['total'];
        $neto = $totalIngresos - $totalGastos;
        $margen = $totalIngresos > 0 ? ($neto / $totalIngresos) * 100 : 0;

        $cuotasAnio = DB::table('mb_cuotas')
            ->where('ejercicio', $ejercicio)
            ->whereNotIn('estado', self::ESTADOS_EXCLUIDOS)
            ->selectRaw('SUM(importe) as total_importe, SUM(pendiente) as total_pendiente')
            ->first();

        $pctImpago = ($cuotasAnio && $cuotasAnio->total_importe > 0)
            ? ((float) $cuotasAnio->total_pendiente / (float) $cuotasAnio->total_importe) * 100
            : 0;

        return [
            'ingresos' => $totalIngresos,
            'gastos' => $totalGastos,
            'neto' => $neto,
            'margen' => $margen,
            'pctImpago' => $pctImpago,
        ];
    }

    private function datosGrafico(string $ejercicio): array
    {
        [$desde, $hasta] = $this->limitesEjercicio($ejercicio);

        $ingresosGastosPorMesQuery = DB::table('mb_gastos as g')
            ->join('mb_gastos_cuentas as c', 'c.id', '=', 'g.id_gastos_cuentas')
            ->where('g.deleted', 0)
            ->where(fn ($q) => $q->where('c.cuenta', 'like', '6%')->orWhere(fn ($q2) => $q2->where('c.cuenta', 'like', '7%')->whereNotIn('c.id', self::CUENTAS_CUOTAS_SUSTITUIDAS)));
        $ingresosGastosPorMes = $this->whereDevengoEntre($ingresosGastosPorMesQuery, $desde, $hasta, 'g')
            ->selectRaw("to_char(COALESCE(g.fecha_devengo, g.fecha_emision), 'YYYY-MM') as mes, c.cuenta, SUM(g.importe_neto) as total")
            ->groupBy('mes', 'c.cuenta')
            ->get();

        $cuotasPorMes = DB::table('mb_cuotas')
            ->whereIn('tipo_cuota', ['C-I', 'C-II', 'C-I_Derrama'])
            ->whereNotIn('estado', self::ESTADOS_EXCLUIDOS)
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->selectRaw("to_char(fecha_pago, 'YYYY-MM') as mes, SUM(importe_cobrado) as total")
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $meses = [];
        $cursor = \Carbon\Carbon::parse($desde);
        for ($i = 0; $i < 12; $i++) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $ingresos = [];
        $gastos = [];
        foreach ($meses as $mes) {
            $ingresoMes = (float) ($cuotasPorMes[$mes]->total ?? 0);
            $gastoMes = 0.0;
            foreach ($ingresosGastosPorMes->where('mes', $mes) as $r) {
                if (str_starts_with((string) $r->cuenta, '7')) {
                    $ingresoMes += -(float) $r->total;
                } else {
                    $gastoMes += (float) $r->total;
                }
            }
            $ingresos[] = round($ingresoMes, 2);
            $gastos[] = round($gastoMes, 2);
        }

        return ['meses' => $meses, 'ingresos' => $ingresos, 'gastos' => $gastos];
    }
}
