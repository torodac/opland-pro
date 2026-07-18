<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeFinancieroController extends Controller
{
    private const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    public function index(Request $request, Project $project)
    {
        $anios = DB::table('vm_pyg')
            ->where('deleted', 0)
            ->selectRaw('DISTINCT EXTRACT(year FROM periodo)::int as anio')
            ->orderByDesc('anio')
            ->pluck('anio');

        $anioActual = (int) ($request->input('anio') ?: ($anios->first() ?? now()->year));

        $grupos = $this->clasificarPropiedades($anioActual);
        $filtro = $request->input('filtro', 'todas');
        if (!isset($grupos[$filtro])) $filtro = 'todas';
        $idsFiltro = $filtro === 'todas' ? null : $grupos[$filtro]['ids'];

        $actual         = $this->resumenAnio($anioActual, $idsFiltro);
        $mesesAnterior  = $this->mesesConDatos($anioActual - 1);
        $mesesComunes   = array_values(array_intersect($actual['meses'], $mesesAnterior));

        $delta = null;
        if (!empty($mesesComunes)) {
            $actualComun   = $this->resumenAnioMeses($anioActual, $mesesComunes, $idsFiltro);
            $anteriorComun = $this->resumenAnioMeses($anioActual - 1, $mesesComunes, $idsFiltro);
            $delta = [
                'ingresos'  => $this->pct($actualComun['ingresos'], $anteriorComun['ingresos']),
                'gastos'    => $this->pct(abs($actualComun['gastos']), abs($anteriorComun['gastos'])),
                'beneficio' => $this->pct($actualComun['beneficio'], $anteriorComun['beneficio']),
            ];
        }

        $propiedadesEnCartera = DB::table('vm_propiedades')->where('deleted', 0)->count();

        $periodosEjercicio = $this->periodosParaGrafico($idsFiltro);

        // La ventana interanual siempre se ancla al último período activo REAL (vm_pyg), no al último
        // dato del subgrupo filtrado — si no, "Bajas" (que por definición no tiene el período más
        // reciente) desplazaría la ventana hacia atrás en el tiempo, perdiendo el sentido de "ahora".
        $ultimoPeriodoActivo = DB::table('vm_pyg')->where('deleted', 0)->max('periodo');

        // La clasificación Constante/Alta/Baja también es dinámica en la vista interanual: se recalcula
        // sobre la propia ventana móvil de 12 meses (que puede pisar dos ejercicios), no sobre el
        // Ejercicio seleccionado en el desplegable — ese selector queda desactivado en modo interanual.
        $gruposInteranual = null;
        $idsFiltroInteranual = $idsFiltro;
        if ($ultimoPeriodoActivo) {
            $ventanaInteranual   = $this->ventanaInteranual($ultimoPeriodoActivo);
            $periodosVentana     = collect($ventanaInteranual)
                ->map(fn($v) => sprintf('%04d-%02d-01', $v['anio'], $v['mes']));
            $periodosActivosVentana = DB::table('vm_pyg')
                ->where('deleted', 0)
                ->whereIn('periodo', $periodosVentana)
                ->orderBy('periodo')
                ->pluck('periodo');
            $gruposInteranual = $this->clasificarPropiedadesEnPeriodos($periodosActivosVentana);
            $idsFiltroInteranual = $filtro === 'todas' ? null : ($gruposInteranual[$filtro]['ids'] ?? []);
        }
        $periodosInteranual = $this->periodosParaGrafico($idsFiltroInteranual);

        $graficoEjercicio  = $this->graficoPorEjercicio($anioActual, $periodosEjercicio);
        $graficoInteranual = $this->graficoInteranual($periodosInteranual, $ultimoPeriodoActivo);

        return view('vm.informe-financiero', [
            'project'               => $project,
            'anios'                 => $anios,
            'anioActual'            => $anioActual,
            'ingresos'              => $actual['ingresos'],
            'gastos'                => $actual['gastos'],
            'beneficio'             => $actual['beneficio'],
            'propiedadesEnCartera'  => $propiedadesEnCartera,
            'delta'                 => $delta,
            'graficoEjercicio'      => $graficoEjercicio,
            'graficoInteranual'     => $graficoInteranual,
            'grupos'                => $grupos,
            'gruposInteranual'      => $gruposInteranual,
            'filtro'                => $filtro,
            'waterfall'             => $this->waterfallPyg($anioActual, $idsFiltro),
        ]);
    }

    // ───────────────────────── Puente de rentabilidad (waterfall): Ingresos → Resultado del ejercicio ─────────────────────────
    // Usa la jerarquía contable real de vm_pyg_cuentas (bloque/epígrafe del PGC), no categorías
    // inventadas: epígrafe 1=ingresos, 4=aprovisionamientos, 6=personal, 7=otros gastos explotación,
    // 8=amortización, 11+12=otros resultados (bloque A: Resultado de explotación); 13+15+17=resultado
    // financiero (bloque B); 19=impuestos (bloque D: Resultado del ejercicio).
    private function waterfallPyg(int $anio, ?array $idsPropiedades): array
    {
        $q = DB::table('vm_pyg_valores as v')
            ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
            ->whereRaw('EXTRACT(year FROM v.periodo) = ?', [$anio])
            ->whereIn('v.periodo', function ($sub) {
                $sub->select('periodo')->from('vm_pyg')->where('deleted', 0);
            });

        if ($idsPropiedades !== null) {
            $q->whereIn('v.id_propiedades', $idsPropiedades);
        }

        $porEpigrafe = $q->selectRaw('c.epigrafe_codigo, SUM(v.importe) as importe')
            ->groupBy('c.epigrafe_codigo')
            ->pluck('importe', 'epigrafe_codigo');

        $valor = fn(string ...$codigos) => array_sum(array_map(fn($cod) => (float) ($porEpigrafe[$cod] ?? 0), $codigos));

        $ingresos     = $valor('1');
        $aprovisiona  = $valor('4');
        $personal     = $valor('6');
        $otrosGastos  = $valor('7');
        $amortizacion = $valor('8');
        $otrosResult  = $valor('11', '12');
        $resultExplot = $ingresos + $aprovisiona + $personal + $otrosGastos + $amortizacion + $otrosResult;

        $financiero      = $valor('13', '15', '17');
        $impuestos       = $valor('19');
        $resultEjercicio = $resultExplot + $financiero + $impuestos;

        return [
            ['label' => 'Ingresos',                   'valor' => round($ingresos, 2),     'tipo' => 'total'],
            ['label' => 'Aprovisionamientos',          'valor' => round($aprovisiona, 2),  'tipo' => 'delta'],
            ['label' => 'Gastos de personal',          'valor' => round($personal, 2),     'tipo' => 'delta'],
            ['label' => 'Otros gastos de explotación', 'valor' => round($otrosGastos, 2),  'tipo' => 'delta'],
            ['label' => 'Amortización',                'valor' => round($amortizacion, 2), 'tipo' => 'delta'],
            ['label' => 'Otros resultados',            'valor' => round($otrosResult, 2),  'tipo' => 'delta'],
            ['label' => 'Resultado de explotación',    'valor' => round($resultExplot, 2), 'tipo' => 'subtotal'],
            ['label' => 'Resultado financiero',        'valor' => round($financiero, 2),   'tipo' => 'delta'],
            ['label' => 'Impuestos',                   'valor' => round($impuestos, 2),    'tipo' => 'delta'],
            ['label' => 'Resultado del ejercicio',      'valor' => round($resultEjercicio, 2), 'tipo' => 'final'],
        ];
    }

    // ───────────────────────── Filtro de propiedades: Todas / Constantes / Altas / Bajas ─────────────────────────
    // Clasificación calculada sobre los períodos activos de vm_pyg (deleted=0) DENTRO del ejercicio
    // seleccionado — dinámica por año, no una foto fija a fecha de hoy.
    private function clasificarPropiedades(int $anio): array
    {
        $periodosActivos = DB::table('vm_pyg')
            ->where('deleted', 0)
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->orderBy('periodo')
            ->pluck('periodo');

        return $this->clasificarPropiedadesEnPeriodos($periodosActivos);
    }

    // Misma clasificación pero sobre una lista arbitraria de períodos — la usa tanto la vista por
    // ejercicio (períodos de un año) como la interanual (ventana móvil de 12 meses, que puede pisar
    // dos años naturales). "Constante/Alta/Baja" siempre es relativo a la ventana de datos que se
    // esté mostrando en cada momento, nunca una foto fija.
    private function clasificarPropiedadesEnPeriodos($periodosActivos): array
    {
        $nPeriodos = $periodosActivos->count();
        $ultimoPeriodo = $periodosActivos->last();

        $constantes = []; $altas = []; $bajas = [];

        if ($nPeriodos > 0) {
            $rows = DB::table('vm_pyg_valores')
                ->whereNotNull('id_propiedades')
                ->whereIn('periodo', $periodosActivos)
                ->selectRaw('id_propiedades, COUNT(DISTINCT periodo) as n_periodos, MIN(periodo) as primer, MAX(periodo) as ultimo')
                ->groupBy('id_propiedades')
                ->get();

            foreach ($rows as $r) {
                $id = (int) $r->id_propiedades;
                if ((int) $r->n_periodos === $nPeriodos) {
                    $constantes[] = $id;
                    continue;
                }
                // Regla de fallback (también cubre Alta/Baja "puras"): si el último período con
                // movimiento es el más reciente del ejercicio, Alta; si no, Baja.
                if ($r->ultimo === $ultimoPeriodo) $altas[] = $id;
                else $bajas[] = $id;
            }
        }

        $todas = array_merge($constantes, $altas, $bajas);

        return [
            'todas'      => ['ids' => $todas,      'label' => 'Todas',      'count' => count($todas)],
            'constantes' => ['ids' => $constantes, 'label' => 'Constantes', 'count' => count($constantes)],
            'altas'      => ['ids' => $altas,      'label' => 'Altas',      'count' => count($altas)],
            'bajas'      => ['ids' => $bajas,      'label' => 'Bajas',      'count' => count($bajas)],
        ];
    }

    // $idsPropiedades = null → "Todas" tal cual: totales de vm_pyg (incluye cecos).
    // $idsPropiedades = [...] → solo esas propiedades, sumando vm_pyg_valores.importe (sin cecos).
    private function resumenAnio(int $anio, ?array $idsPropiedades): array
    {
        if ($idsPropiedades === null) {
            $r = DB::table('vm_pyg')
                ->where('deleted', 0)
                ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
                ->selectRaw('COALESCE(SUM(importe_ingresos),0) as ingresos, COALESCE(SUM(importe_gastos),0) as gastos')
                ->first();
        } else {
            $r = $this->sumarValoresPropiedades($idsPropiedades, $anio, null);
        }

        return [
            'ingresos'  => (float) $r->ingresos,
            'gastos'    => (float) $r->gastos,
            'beneficio' => (float) $r->ingresos + (float) $r->gastos,
            'meses'     => $this->mesesConDatos($anio),
        ];
    }

    private function sumarValoresPropiedades(array $ids, int $anio, ?array $meses): object
    {
        if (empty($ids)) return (object) ['ingresos' => 0.0, 'gastos' => 0.0];

        $q = DB::table('vm_pyg_valores as v')
            ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
            ->whereIn('v.id_propiedades', $ids)
            ->whereRaw('EXTRACT(year FROM v.periodo) = ?', [$anio])
            // Solo períodos activos de vm_pyg — vm_pyg_valores puede tener filas huérfanas
            // de períodos ya marcados deleted=1 (reimportaciones) que no deben sumar aquí.
            ->whereIn('v.periodo', function ($sub) {
                $sub->select('periodo')->from('vm_pyg')->where('deleted', 0);
            });

        if ($meses !== null) {
            $q->whereRaw('EXTRACT(month FROM v.periodo) IN (' . implode(',', array_fill(0, count($meses), '?')) . ')', $meses);
        }

        return $q->selectRaw("
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '7%'), 0) as ingresos,
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '6%'), 0) as gastos
            ")
            ->first();
    }

    private function mesesConDatos(int $anio): array
    {
        return DB::table('vm_pyg')
            ->where('deleted', 0)
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->selectRaw('DISTINCT EXTRACT(month FROM periodo)::int as mes')
            ->pluck('mes')
            ->all();
    }

    // Agregado de un año restringido a un conjunto concreto de meses (para comparar año a año en igualdad de condiciones)
    private function resumenAnioMeses(int $anio, array $meses, ?array $idsPropiedades): array
    {
        if (empty($meses)) return ['ingresos' => 0.0, 'gastos' => 0.0, 'beneficio' => 0.0];

        if ($idsPropiedades === null) {
            $r = DB::table('vm_pyg')
                ->where('deleted', 0)
                ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
                ->whereRaw('EXTRACT(month FROM periodo) IN (' . implode(',', array_fill(0, count($meses), '?')) . ')', $meses)
                ->selectRaw('COALESCE(SUM(importe_ingresos),0) as ingresos, COALESCE(SUM(importe_gastos),0) as gastos')
                ->first();
        } else {
            $r = $this->sumarValoresPropiedades($idsPropiedades, $anio, $meses);
        }

        return [
            'ingresos'  => (float) $r->ingresos,
            'gastos'    => (float) $r->gastos,
            'beneficio' => (float) $r->ingresos + (float) $r->gastos,
        ];
    }

    private function pct(float $actual, float $anterior): float
    {
        if ($anterior == 0.0) return 0.0;
        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }

    // $idsPropiedades = null → "Todas": vm_pyg tal cual (incluye cecos).
    // $idsPropiedades = [...] → solo esas propiedades, vm_pyg_valores agrupado por periodo (sin cecos).
    // Un período sin ninguna fila para el grupo simplemente no aparece (no se rellena con 0).
    private function periodosParaGrafico(?array $idsPropiedades)
    {
        if ($idsPropiedades === null) {
            $rows = DB::table('vm_pyg')
                ->where('deleted', 0)
                ->orderBy('periodo')
                ->get(['periodo', 'importe_ingresos as ingresos', 'importe_gastos as gastos']);
        } elseif (empty($idsPropiedades)) {
            $rows = collect();
        } else {
            $rows = DB::table('vm_pyg_valores as v')
                ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
                ->whereIn('v.id_propiedades', $idsPropiedades)
                ->whereIn('v.periodo', function ($sub) {
                    $sub->select('periodo')->from('vm_pyg')->where('deleted', 0);
                })
                ->groupBy('v.periodo')
                ->orderBy('v.periodo')
                ->selectRaw("
                    v.periodo,
                    COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '7%'), 0) as ingresos,
                    COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '6%'), 0) as gastos
                ")
                ->get();
        }

        return $rows->map(fn($r) => (object) [
            'anio'     => (int) substr($r->periodo, 0, 4),
            'mes'      => (int) substr($r->periodo, 5, 2),
            'ingresos' => (float) $r->ingresos,
            'gastos'   => (float) $r->gastos,
        ]);
    }

    // ───────────────────────── Gráfico: vista por ejercicio (Ene..Dic, año seleccionado vs anterior) ─────────────────────────

    private function graficoPorEjercicio(int $anioActual, $periodos): array
    {
        $anioAnterior = $anioActual - 1;
        $porMes = fn(int $anio) => $periodos->where('anio', $anio)->keyBy('mes');

        $actual   = $porMes($anioActual);
        $anterior = $porMes($anioAnterior);

        $grupos = [];
        foreach (range(1, 12) as $m) {
            $grupos[] = [
                'anterior' => $anterior->has($m) ? ['ingresos' => $anterior[$m]->ingresos, 'gastos' => $anterior[$m]->gastos] : null,
                'actual'   => $actual->has($m)   ? ['ingresos' => $actual[$m]->ingresos,     'gastos' => $actual[$m]->gastos]   : null,
            ];
        }

        $lineaActual   = $this->acumuladoHastaUltimoDato($actual);
        $lineaAnterior = $this->acumuladoHastaUltimoDato($anterior);

        return $this->renderizarGrafico(
            categorias: self::MESES,
            grupos: $grupos,
            // array_values(): array_filter() no reindexa las claves — si no hay datos del año
            // anterior (p.ej. 2024 vacío), el elemento superviviente se queda con la clave 1 y
            // json_encode() lo serializa como objeto {"1":...} en vez de array, rompiendo el
            // g.lineas.forEach() del lado JS.
            lineas: array_values(array_filter([
                !empty($lineaAnterior) ? ['valores' => $lineaAnterior, 'color' => '#9ca3af', 'dashed' => true,  'label' => "Acum. {$anioAnterior}"] : null,
                !empty($lineaActual)   ? ['valores' => $lineaActual,   'color' => '#1d4ed8', 'dashed' => false, 'label' => "Acum. {$anioActual}", 'destacarUltimo' => true, 'etiquetaUltimo' => "{$anioActual} →"] : null,
            ])),
        );
    }

    // Acumula ingresos+gastos mes a mes hasta el último mes con dato real; los meses intermedios sin
    // dato suman 0 pero NO cortan la acumulación (un subgrupo de propiedades sí puede tener huecos,
    // a diferencia de "Todas" donde nunca los había).
    private function acumuladoHastaUltimoDato($mesesKeyed): array
    {
        if ($mesesKeyed->isEmpty()) return [];
        $ultimoMes = max($mesesKeyed->keys()->all());

        $linea = [];
        $run = 0.0;
        foreach (range(1, $ultimoMes) as $m) {
            if ($mesesKeyed->has($m)) {
                $run += $mesesKeyed[$m]->ingresos + $mesesKeyed[$m]->gastos;
            }
            $linea[$m - 1] = $run;
        }
        return $linea;
    }

    // ───────────────────────── Gráfico: vista interanual (ventana móvil de 12 meses, siempre termina en el último mes cargado) ─────────────────────────

    // Los 12 [anio,mes] de la ventana móvil, terminando en $ultimoPeriodoActivo. Se usa tanto para
    // pintar el gráfico interanual como para clasificar propiedades dentro de esa misma ventana.
    private function ventanaInteranual(string $ultimoPeriodoActivo): array
    {
        $a = (int) substr($ultimoPeriodoActivo, 0, 4);
        $m = (int) substr($ultimoPeriodoActivo, 5, 2) - 11;
        while ($m < 1) { $m += 12; $a--; }

        $ventana = [];
        for ($i = 0; $i < 12; $i++) {
            $ventana[] = ['anio' => $a, 'mes' => $m];
            $m++;
            if ($m > 12) { $m = 1; $a++; }
        }
        return $ventana;
    }

    private function graficoInteranual($periodos, ?string $ultimoPeriodoActivo): array
    {
        if (!$ultimoPeriodoActivo) {
            return $this->renderizarGrafico(categorias: [], grupos: [], lineas: []);
        }

        $ventana = $this->ventanaInteranual($ultimoPeriodoActivo);

        $valorEn = fn(int $anio, int $mes) => $periodos->first(fn($p) => $p->anio === $anio && $p->mes === $mes);

        $categorias = [];
        $grupos = [];
        $lineaValores = [];
        $run = 0.0;

        foreach ($ventana as $i => $v) {
            $actualP   = $valorEn($v['anio'], $v['mes']);
            $anteriorP = $valorEn($v['anio'] - 1, $v['mes']); // mismo mes, un año antes de ESE mes concreto

            $sufijo = $v['mes'] === 1 || $i === 0 ? substr((string) $v['anio'], -2) : '';
            $categorias[] = self::MESES[$v['mes'] - 1] . $sufijo;

            $grupos[] = [
                'anterior' => $anteriorP ? ['ingresos' => $anteriorP->ingresos, 'gastos' => $anteriorP->gastos] : null,
                'actual'   => $actualP   ? ['ingresos' => $actualP->ingresos,   'gastos' => $actualP->gastos]   : null,
            ];

            if ($actualP) {
                $run += $actualP->ingresos + $actualP->gastos;
            }
            $lineaValores[$i] = $run; // aunque no haya dato ese mes, se marca el punto (sin cambio) para no romper la línea
        }

        return $this->renderizarGrafico(
            categorias: $categorias,
            grupos: $grupos,
            lineas: [
                ['valores' => $lineaValores, 'color' => '#1d4ed8', 'dashed' => false, 'label' => 'Beneficio acumulado de la ventana', 'destacarUltimo' => true],
            ],
            mesActualIndex: count($ventana) - 1,
        );
    }

    // ───────────────────────── Datos para Chart.js (barras agrupadas + línea(s) de acumulado) ─────────────────────────

    private function renderizarGrafico(array $categorias, array $grupos, array $lineas, ?int $mesActualIndex = null): array
    {
        if (empty($categorias)) {
            return ['vacio' => true];
        }

        $serie = fn(string $clave, string $concepto) => array_map(
            fn($g) => $g[$clave] ? round(abs($g[$clave][$concepto]), 2) : null,
            $grupos
        );

        // Dos escalas independientes (barras a la izquierda, líneas a la derecha) pero con el
        // mismo cero: las barras nunca son negativas, pero si la línea de acumulado baja de
        // cero necesita hueco visual por debajo — se lo damos también al eje de barras (aunque
        // ahí nunca haya dato) para que ambos ceros caigan en el mismo píxel del eje horizontal.
        $maxBarras = 0.0;
        foreach ($grupos as $grupo) {
            foreach (['anterior', 'actual'] as $k) {
                if ($grupo[$k]) {
                    $maxBarras = max($maxBarras, abs($grupo[$k]['ingresos']), abs($grupo[$k]['gastos']));
                }
            }
        }
        if ($maxBarras <= 0) $maxBarras = 1.0;

        $maxLinea = 0.0;
        $minLinea = 0.0;
        foreach ($lineas as $l) {
            foreach ($l['valores'] as $v) {
                $maxLinea = max($maxLinea, $v);
                $minLinea = min($minLinea, $v);
            }
        }
        if ($maxLinea <= 0) $maxLinea = 1.0;

        // Redondeamos a "pasos bonitos" (200k, 500k, 1M...) en vez de cortar justo en el dato
        // real: si no, la última línea de referencia del eje sale con un número feo (el máximo
        // exacto de los datos) en lugar de la siguiente marca redonda por encima.
        $pasoLinea    = $this->pasoBonito($maxLinea - $minLinea);
        $maxLineaNice = ceil($maxLinea / $pasoLinea) * $pasoLinea;
        $minLineaNice = $minLinea < 0 ? floor($minLinea / $pasoLinea) * $pasoLinea : 0.0;

        // Misma proporción de hueco negativo que en el eje de líneas (ya redondeado), aplicada
        // al eje de barras — así ambos ceros siguen cayendo en el mismo píxel del eje horizontal.
        $ratio = ($maxLineaNice - $minLineaNice) > 0 ? (-$minLineaNice) / ($maxLineaNice - $minLineaNice) : 0.0;

        $pasoBarras    = $this->pasoBonito($maxBarras);
        $maxBarrasNice = ceil($maxBarras / $pasoBarras) * $pasoBarras;
        $minBarrasNice = $ratio > 0 ? -($ratio / (1 - $ratio)) * $maxBarrasNice : 0.0;

        return [
            'vacio'          => false,
            'categorias'     => $categorias,
            'mesActualIndex' => $mesActualIndex,
            'escalaBarras'   => ['min' => round($minBarrasNice, 2), 'max' => round($maxBarrasNice, 2), 'paso' => $pasoBarras],
            'escalaLineas'   => ['min' => round($minLineaNice, 2), 'max' => round($maxLineaNice, 2), 'paso' => $pasoLinea],
            'barras' => [
                'actualIngresos'   => $serie('actual', 'ingresos'),
                'actualGastos'     => $serie('actual', 'gastos'),
                'anteriorIngresos' => $serie('anterior', 'ingresos'),
                'anteriorGastos'   => $serie('anterior', 'gastos'),
            ],
            // array_values() defensivo: si $lineas llega con huecos en sus claves (p.ej. tras un
            // array_filter en el llamador), json_encode() lo serializaría como objeto en vez de
            // array y rompería el .forEach() del lado JS.
            'lineas' => array_values(array_map(fn($l) => [
                'label'          => $l['label'],
                'color'          => $l['color'],
                'dashed'         => $l['dashed'],
                'valores'        => array_map(fn($v) => round($v, 2), array_values($l['valores'])),
                'destacarUltimo' => $l['destacarUltimo'] ?? false,
                'etiquetaUltimo' => $l['etiquetaUltimo'] ?? null,
            ], $lineas)),
        ];
    }

    // Paso de eje "bonito" (1/2/5 × potencia de 10) para un rango dado, apuntando a ~6 marcas.
    private function pasoBonito(float $rango): float
    {
        if ($rango <= 0) return 1.0;
        $pasoBruto = $rango / 6;
        $magnitud  = 10 ** floor(log10($pasoBruto));
        $residuo   = $pasoBruto / $magnitud;
        $residuoBonito = match (true) {
            $residuo <= 1   => 1,
            $residuo <= 2   => 2,
            $residuo <= 5   => 5,
            default         => 10,
        };
        return $residuoBonito * $magnitud;
    }
}
