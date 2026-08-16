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
        $anios = DB::table('vm_pyg_valores')
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

        // La ventana interanual siempre se ancla al último período con datos REAL (vm_pyg_valores), no
        // al último dato del subgrupo filtrado — si no, "Bajas" (que por definición no tiene el período
        // más reciente) desplazaría la ventana hacia atrás en el tiempo, perdiendo el sentido de "ahora".
        $ultimoPeriodoActivo = DB::table('vm_pyg_valores')->max('periodo');

        // La clasificación Constante/Alta/Baja también es dinámica en la vista interanual: se recalcula
        // sobre la propia ventana móvil de 12 meses (que puede pisar dos ejercicios), no sobre el
        // Ejercicio seleccionado en el desplegable — ese selector queda desactivado en modo interanual.
        $gruposInteranual = null;
        $idsFiltroInteranual = $idsFiltro;
        if ($ultimoPeriodoActivo) {
            $ventanaInteranual   = $this->ventanaInteranual($ultimoPeriodoActivo);
            $periodosVentana     = collect($ventanaInteranual)
                ->map(fn($v) => sprintf('%04d-%02d-01', $v['anio'], $v['mes']));
            $periodosActivosVentana = DB::table('vm_pyg_valores')
                ->whereIn('periodo', $periodosVentana)
                ->distinct()
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
    // Igual que resumenAnio(): usa importe_acumulado del ÚLTIMO período cargado del año (lo que trae el
    // propio fichero), no la suma de importe (delta) mes a mes -- así el total de este puente ("Resultado
    // del ejercicio") siempre coincide con el beneficio mostrado en los KPI de arriba.
    private function waterfallPyg(int $anio, ?array $idsPropiedades): array
    {
        $ultimoPeriodo = DB::table('vm_pyg_valores')
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->max('periodo');

        if (!$ultimoPeriodo) {
            $porEpigrafe = collect();
        } else {
            $q = DB::table('vm_pyg_valores as v')
                ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
                ->where('v.periodo', $ultimoPeriodo);

            if ($idsPropiedades !== null) {
                $q->whereIn('v.id_propiedades', $idsPropiedades);
            }

            $porEpigrafe = $q->selectRaw('c.epigrafe_codigo, SUM(v.importe_acumulado) as importe')
                ->groupBy('c.epigrafe_codigo')
                ->pluck('importe', 'epigrafe_codigo');
        }

        $valor = fn(string ...$codigos) => array_sum(array_map(fn($cod) => (float) ($porEpigrafe[$cod] ?? 0), $codigos));
        $codigosConocidos = ['1', '2', '4', '6', '7', '8', '11', '12', '13', '15', '17', '19'];

        $ingresos     = $valor('1');
        $variacionExis = $valor('2');
        $aprovisiona  = $valor('4');
        $personal     = $valor('6');
        $otrosGastos  = $valor('7');
        $amortizacion = $valor('8');
        $otrosResult  = $valor('11', '12');
        $resultExplot = $ingresos + $variacionExis + $aprovisiona + $personal + $otrosGastos + $amortizacion + $otrosResult;

        $financiero      = $valor('13', '15', '17');
        $impuestos       = $valor('19');
        $resultEjercicio = $resultExplot + $financiero + $impuestos;

        // Salvaguarda: si vm_pyg_cuentas trae algún epígrafe nuevo que no está en la lista de arriba
        // (como pasó con el 2, "Variación de existencias", que faltaba y descuadraba este puente
        // frente al beneficio de los KPI), se agrupa aquí en vez de desaparecer en silencio.
        $sinClasificar = round($porEpigrafe->reject(fn($v, $cod) => in_array((string) $cod, $codigosConocidos, true))->sum(), 2);

        $filas = [
            ['label' => 'Ingresos',                   'valor' => round($ingresos, 2),      'tipo' => 'total'],
            ['label' => 'Variación de existencias',   'valor' => round($variacionExis, 2), 'tipo' => 'delta'],
            ['label' => 'Aprovisionamientos',          'valor' => round($aprovisiona, 2),  'tipo' => 'delta'],
            ['label' => 'Gastos de personal',          'valor' => round($personal, 2),     'tipo' => 'delta'],
            ['label' => 'Otros gastos de explotación', 'valor' => round($otrosGastos, 2),  'tipo' => 'delta'],
            ['label' => 'Amortización',                'valor' => round($amortizacion, 2), 'tipo' => 'delta'],
            ['label' => 'Otros resultados',            'valor' => round($otrosResult, 2),  'tipo' => 'delta'],
            ['label' => 'Resultado de explotación',    'valor' => round($resultExplot, 2), 'tipo' => 'subtotal'],
            ['label' => 'Resultado financiero',        'valor' => round($financiero, 2),   'tipo' => 'delta'],
            ['label' => 'Impuestos',                   'valor' => round($impuestos, 2),    'tipo' => 'delta'],
        ];
        if ($sinClasificar !== 0.0) {
            $filas[] = ['label' => 'Otros epígrafes sin clasificar', 'valor' => $sinClasificar, 'tipo' => 'delta'];
            $resultEjercicio += $sinClasificar;
        }
        $filas[] = ['label' => 'Resultado del ejercicio', 'valor' => round($resultEjercicio, 2), 'tipo' => 'final'];

        return $filas;
    }

    // ───────────────────────── Filtro de propiedades: Todas / Constantes / Altas / Bajas ─────────────────────────
    // Clasificación calculada sobre los períodos con datos en vm_pyg_valores DENTRO del ejercicio
    // seleccionado — dinámica por año, no una foto fija a fecha de hoy.
    private function clasificarPropiedades(int $anio): array
    {
        $periodosActivos = DB::table('vm_pyg_valores')
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->distinct()
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

    // $idsPropiedades = null → "Todas": último período cargado del año (incluye cecos).
    // $idsPropiedades = [...] → solo esas propiedades (sin cecos).
    //
    // Los KPI (ingresos/gastos/beneficio) se leen del importe_acumulado del ÚLTIMO período cargado del
    // año, no de resumar el importe (delta) mes a mes -- importe_acumulado es el valor literal que trae
    // el propio fichero para ese período (acumulado desde enero), así que esto es justo lo que se ve al
    // abrir el Excel de ese mes. Sumar deltas puede divergir si el cliente reclasifica cuentas entre
    // ficheros de meses distintos (visto realmente: mismo total neto, pero ingresos/gastos repartidos
    // de otra forma entre epígrafes).
    private function resumenAnio(int $anio, ?array $idsPropiedades): array
    {
        $ultimoPeriodo = DB::table('vm_pyg_valores')
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->max('periodo');

        $r = $ultimoPeriodo
            ? $this->sumarAcumulado($idsPropiedades, $ultimoPeriodo)
            : (object) ['ingresos' => 0.0, 'gastos' => 0.0];

        return [
            'ingresos'  => (float) $r->ingresos,
            'gastos'    => (float) $r->gastos,
            'beneficio' => (float) $r->ingresos + (float) $r->gastos,
            'meses'     => $this->mesesConDatos($anio),
        ];
    }

    // Ingresos/gastos "tal cual el fichero" de un período concreto: importe_acumulado (no importe),
    // clasificado por el prefijo del código contable (7xx = ingresos, 6xx = gastos). $idsPropiedades =
    // null → sin filtrar por propiedad (incluye también los cecos); [] → ningún resultado; [...] → solo
    // esas propiedades.
    private function sumarAcumulado(?array $idsPropiedades, string $periodo): object
    {
        if ($idsPropiedades !== null && empty($idsPropiedades)) {
            return (object) ['ingresos' => 0.0, 'gastos' => 0.0];
        }

        $q = DB::table('vm_pyg_valores as v')
            ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
            ->where('v.periodo', $periodo);

        if ($idsPropiedades !== null) {
            $q->whereIn('v.id_propiedades', $idsPropiedades);
        }

        return $q->selectRaw("
                COALESCE(SUM(v.importe_acumulado) FILTER (WHERE c.codigo LIKE '7%'), 0) as ingresos,
                COALESCE(SUM(v.importe_acumulado) FILTER (WHERE c.codigo LIKE '6%'), 0) as gastos
            ")
            ->first();
    }

    private function mesesConDatos(int $anio): array
    {
        return DB::table('vm_pyg_valores')
            ->whereRaw('EXTRACT(year FROM periodo) = ?', [$anio])
            ->selectRaw('DISTINCT EXTRACT(month FROM periodo)::int as mes')
            ->pluck('mes')
            ->all();
    }

    // Comparación año a año en igualdad de condiciones: acumulado del ÚLTIMO mes común entre ambos años
    // (p.ej. si 2026 solo tiene hasta junio, compara acumulado-a-junio-2026 vs acumulado-a-junio-2025),
    // no la suma de los meses comunes por separado -- mismo criterio que resumenAnio().
    private function resumenAnioMeses(int $anio, array $meses, ?array $idsPropiedades): array
    {
        if (empty($meses)) return ['ingresos' => 0.0, 'gastos' => 0.0, 'beneficio' => 0.0];

        $periodo = sprintf('%04d-%02d-01', $anio, max($meses));
        $r = $this->sumarAcumulado($idsPropiedades, $periodo);

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

    // $idsPropiedades = null → "Todas": vm_pyg_valores agrupado por periodo (incluye cecos).
    // $idsPropiedades = [...] → solo esas propiedades, vm_pyg_valores agrupado por periodo (sin cecos).
    // Un período sin ninguna fila para el grupo simplemente no aparece (no se rellena con 0).
    private function periodosParaGrafico(?array $idsPropiedades)
    {
        if ($idsPropiedades !== null && empty($idsPropiedades)) {
            $rows = collect();
        } else {
            $q = DB::table('vm_pyg_valores as v')
                ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta');
            if ($idsPropiedades !== null) {
                $q->whereIn('v.id_propiedades', $idsPropiedades);
            }
            $rows = $q->groupBy('v.periodo')
                ->orderBy('v.periodo')
                ->selectRaw("
                    v.periodo,
                    COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '7%'), 0) as ingresos,
                    COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '6%'), 0) as gastos,
                    COALESCE(SUM(v.importe_acumulado), 0) as acumulado,
                    COUNT(DISTINCT v.id_propiedades) as num_propiedades
                ")
                ->get();
        }

        return $rows->map(fn($r) => (object) [
            'anio'        => (int) substr($r->periodo, 0, 4),
            'mes'         => (int) substr($r->periodo, 5, 2),
            'ingresos'    => (float) $r->ingresos,
            'gastos'      => (float) $r->gastos,
            // Suma de importe_acumulado sin distinguir 7xx/6xx: cada cuenta ya trae su propio signo
            // (ingresos positivos, gastos negativos), así que la suma total ya es el neto/beneficio
            // acumulado tal cual lo reporta el fichero de ese período -- no una resta que podamos
            // descuadrar nosotros mismos.
            'acumulado'   => (float) $r->acumulado,
            'propiedades' => (int) $r->num_propiedades,
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
                'anterior' => $anterior->has($m) ? ['ingresos' => $anterior[$m]->ingresos, 'gastos' => $anterior[$m]->gastos, 'propiedades' => $anterior[$m]->propiedades] : null,
                'actual'   => $actual->has($m)   ? ['ingresos' => $actual[$m]->ingresos,     'gastos' => $actual[$m]->gastos,   'propiedades' => $actual[$m]->propiedades]   : null,
            ];
        }

        $lineaActual   = $this->lineaAcumulado($actual);
        $lineaAnterior = $this->lineaAcumulado($anterior);

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

    // Línea de acumulado mes a mes hasta el último mes con dato real: usa el importe_acumulado que
    // trae el propio fichero de cada mes (no una resuma de deltas), para que el punto final de la
    // línea coincida siempre con el beneficio de los KPI de arriba. Un mes intermedio sin dato
    // mantiene el último valor conocido (no corta la línea ni la hace bajar a 0).
    private function lineaAcumulado($mesesKeyed): array
    {
        if ($mesesKeyed->isEmpty()) return [];
        $ultimoMes = max($mesesKeyed->keys()->all());

        $linea = [];
        $run = 0.0;
        foreach (range(1, $ultimoMes) as $m) {
            if ($mesesKeyed->has($m)) {
                $run = $mesesKeyed[$m]->acumulado;
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
                'anterior' => $anteriorP ? ['ingresos' => $anteriorP->ingresos, 'gastos' => $anteriorP->gastos, 'propiedades' => $anteriorP->propiedades] : null,
                'actual'   => $actualP   ? ['ingresos' => $actualP->ingresos,   'gastos' => $actualP->gastos,   'propiedades' => $actualP->propiedades]   : null,
            ];

            if ($actualP) {
                $run = $actualP->acumulado;
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
            // No es una serie del gráfico (no se dibuja barra ni línea) — solo viaja con los datos
            // para que el tooltip pueda mostrar "Propiedades activas: N" junto a Ingresos/Gastos.
            'propiedades' => [
                'actual'   => array_map(fn($g) => $g['actual']['propiedades'] ?? null, $grupos),
                'anterior' => array_map(fn($g) => $g['anterior']['propiedades'] ?? null, $grupos),
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
