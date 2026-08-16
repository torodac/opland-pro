<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CuotasReportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Carga del informe "Listado de recibos" (mb) -- escribe directamente en mb_cuotas (producción).
// El recorte de "solo últimos 6 años" (pedido por el cliente) todavía NO está activo; se activará
// explícitamente más adelante.
//
// Flujo en 3 pasos:
//   1. Mapeo:  si hay conceptos sin clasificar, se pide ejercicio+tipo y se guardan en mb_cuotas_mapeo.
//   2. Evaluar: se calcula TODO lo que cambiaría (cuotas, importe recién cobrado, cambios de
//      propietario, avisos de demandas) sin escribir nada en la base de datos.
//   3. Confirmar: solo si el usuario lo confirma explícitamente se aplica de verdad. Si se
//      descarta, no se ha tocado ni una fila.
//
// Cada fichero cargado es una "foto" (fecha_exportacion, tomada del nombre del fichero) del estado
// de todas las cuotas en esa fecha -- no de la fecha en que se sube. Por eso el dato en bruto se
// guarda siempre en mb_cuotas_exportaciones (una fila por cuota+fecha_exportacion, nunca se pierde
// ni se sobreescribe salvo recarga del mismo fichero), y tanto el estado actual (mb_cuotas) como el
// histórico de cambios (mb_cuotas_estado_historico) se recalculan a partir de ahí cada vez que una
// cuota recibe una foto nueva. Esto permite cargar ficheros en cualquier orden -- incluido hacer
// backfill de años antiguos después de tener ya cargado el año actual -- sin que el orden de carga
// corrompa ni el estado actual ni el histórico, y registra también los retrocesos reales (p.ej. un
// recibo devuelto por el banco: Pagada -> Pendiente).
//
// mb_cuotas tiene estados administrativos/legales que el fichero nunca trae (Demandada, Incobrable,
// Anulada) y un enlace a su expediente (id_demandas). Si una cuota ya existente tiene uno de esos
// estados, la carga actualiza importe/pendiente/forma_pago/propietario pero NUNCA pisa el estado ni
// id_demandas -- si además el fichero implicaría un cambio de estado, se avisa aparte.
//
// El propietario de cada vivienda (mb_propietarios_historico) se actualiza siempre que cambie,
// independientemente de si alguna cuota de esa vivienda tiene un estado protegido -- es un dato a
// nivel de vivienda, no de cuota. Nunca se fusiona automáticamente con un propietario existente por
// similitud de nombre (solo por coincidencia EXACTA tras normalizar): si no hay coincidencia exacta
// se crea uno nuevo, y los duplicados por variantes de escritura se revisan aparte en
// /mb/propietarios ("posibles duplicados").
class CuotasImportController extends Controller
{
    private const TIPOS_SELECCIONABLES = ['C-I', 'C-II', 'C-I_Derrama', 'G.dev.', 'Dudoso', 'Entrega a cuenta'];
    private const FECHA_CORTE_RUIDO_HISTORICO = '2010-01-01'; // por debajo de esto, solo se procesa si reconcilia con mb_cuotas real
    private const ESTADOS_PROTEGIDOS = ['Demandada', 'Incobrable', 'Anulada'];
    private const TIPOS_CUOTA_INFORMATIVOS = ['G.dev.', 'Dudoso', 'Entrega a cuenta'];

    public function index(Project $project)
    {
        $ultimasCargas = DB::table('mb_cuotas')->selectRaw('COUNT(*) as n, MAX(updatedat) as ultima')->first();
        $demandasDetalle = $this->demandasEjercicioActualDetalle();

        return view('mb.cuotas-import', [
            'project'      => $project,
            'totalProvisional' => $ultimasCargas->n ?? 0,
            'ultimaCarga'  => $ultimasCargas->ultima ?? null,
            'demandasEjercicioActual' => $demandasDetalle->count(),
            'demandasEjercicioActualDetalle' => $demandasDetalle,
            'breadcrumb'   => [
                ['label' => 'Cuotas', 'url' => route('listado', [$project->slug, 'cuotas'])],
                ['label' => 'Carga de recibos', 'url' => ''],
            ],
        ]);
    }

    // Ejercicio actual de cuotas (formato "YYYY-YYYY", julio a junio) -- misma regla que
    // ListadoController/ViviendasController/EntregasCuentaController (duplicación aceptada).
    private function ejercicioActualCuotas(): string
    {
        $hoy  = now();
        $anio = (int) $hoy->format('n') >= 7 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
        return $anio . '-' . ($anio + 1);
    }

    private function ejercicioRango(string $ejercicio): array
    {
        [$y1, $y2] = explode('-', $ejercicio);
        return [$y1 . '-07-01', $y2 . '-06-30'];
    }

    // Viviendas con alguna cuota Pendiente cuya fecha_emision es anterior al corte de 5 años desde
    // el FIN del ejercicio en curso (fin_ejercicio - 5 años) -- es decir, ya está o entra en plazo
    // de prescribir dentro de este ejercicio, hay que demandarla ya. El importe mostrado es TODO lo
    // pendiente de esa vivienda (no solo la parte que cruza el corte).
    //
    // Si se pasa $simulado (el resultado de calcularResultado() sobre un fichero aún no confirmado),
    // se superpone sobre el estado real de mb_cuotas antes de aplicar el filtro/agrupación -- así el
    // listado que se ve durante la evaluación (antes de confirmar) refleja lo que el fichero va a
    // dejar, no solo el estado actual de Opland (p.ej. si el fichero trae el cobro de una de estas
    // cuotas, esa vivienda deja de aparecer o reduce su importe pendiente en la vista previa).
    private function demandasEjercicioActualDetalle(array $simulado = []): \Illuminate\Support\Collection
    {
        [, $finActual] = $this->ejercicioRango($this->ejercicioActualCuotas());
        $cutoff = \Illuminate\Support\Carbon::parse($finActual)->subYears(5)->toDateString();

        $filtroBase = fn ($q, $alias) => $q
            ->where("{$alias}.estado", 'Pendiente')
            ->where("{$alias}.pendiente", '>', 0)
            ->whereNotIn("{$alias}.tipo_cuota", self::TIPOS_CUOTA_INFORMATIVOS);

        if (empty($simulado)) {
            return $filtroBase(DB::table('mb_cuotas as c'), 'c')
                ->join('mb_viviendas as v', 'v.id', '=', 'c.id_viviendas')
                ->where('v.deleted', 0)
                ->whereIn('c.id_viviendas', function ($q) use ($cutoff, $filtroBase) {
                    $filtroBase($q->select('c2.id_viviendas')->from('mb_cuotas as c2'), 'c2')
                        ->join('mb_viviendas as v2', 'v2.id', '=', 'c2.id_viviendas')
                        ->where('v2.deleted', 0)
                        ->where('c2.fecha_emision', '<', $cutoff);
                })
                ->groupBy('v.id', 'v.nombre')
                ->orderBy('v.nombre')
                ->get(['v.nombre', DB::raw('SUM(c.pendiente) as pendiente')]);
        }

        // Con simulación: trae las cuotas ya pendientes en BD más todas las de las viviendas tocadas
        // por el fichero (para poder detectar tanto las que dejan de estar pendientes como las que
        // pasan a estarlo), superpone encima el resultado simulado y repite en PHP la misma lógica
        // de filtro/agrupación/corte que la consulta SQL de arriba.
        $idViviendasSimulado = collect($simulado)->pluck('id_viviendas')->unique()->values()->all();

        $cuotas = DB::table('mb_cuotas as c')
            ->join('mb_viviendas as v', 'v.id', '=', 'c.id_viviendas')
            ->where('v.deleted', 0)
            ->where(function ($q) use ($idViviendasSimulado) {
                $q->where(function ($q2) {
                    $q2->where('c.estado', 'Pendiente')
                        ->where('c.pendiente', '>', 0)
                        ->whereNotIn('c.tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS);
                });
                if (!empty($idViviendasSimulado)) $q->orWhereIn('c.id_viviendas', $idViviendasSimulado);
            })
            ->get(['c.id_viviendas', 'v.nombre', 'c.concepto', 'c.fecha_emision', 'c.tipo_cuota', 'c.estado', 'c.pendiente']);

        $porClave = $cuotas->keyBy(fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision);

        $nombresPorVivienda = $cuotas->pluck('nombre', 'id_viviendas');
        $viviendasFaltantes = collect($idViviendasSimulado)->diff($nombresPorVivienda->keys())->values();
        if ($viviendasFaltantes->isNotEmpty()) {
            DB::table('mb_viviendas')->whereIn('id', $viviendasFaltantes)->pluck('nombre', 'id')
                ->each(function ($nombre, $id) use (&$nombresPorVivienda) { $nombresPorVivienda[$id] = $nombre; });
        }

        foreach ($simulado as $key => $sim) {
            if (isset($porClave[$key])) {
                $c = $porClave[$key];
                $c->estado = $sim['estado'];
                $c->pendiente = $sim['pendiente'];
                $c->tipo_cuota = $sim['tipo_cuota'];
            } else {
                $porClave[$key] = (object) [
                    'id_viviendas'  => $sim['id_viviendas'],
                    'nombre'        => $nombresPorVivienda[$sim['id_viviendas']] ?? null,
                    'concepto'      => $sim['concepto'],
                    'fecha_emision' => $sim['fecha_emision'],
                    'tipo_cuota'    => $sim['tipo_cuota'],
                    'estado'        => $sim['estado'],
                    'pendiente'     => $sim['pendiente'],
                ];
            }
        }

        $filtroPhp = fn ($c) => trim((string) $c->estado) === 'Pendiente'
            && (float) $c->pendiente > 0
            && !in_array($c->tipo_cuota, self::TIPOS_CUOTA_INFORMATIVOS, true);

        $pendientes = $porClave->filter($filtroPhp);
        $idViviendasIncluir = $pendientes->filter(fn($c) => $c->fecha_emision < $cutoff)
            ->pluck('id_viviendas')->unique();

        return $pendientes
            ->whereIn('id_viviendas', $idViviendasIncluir)
            ->groupBy('id_viviendas')
            ->map(fn($grupo) => (object) [
                'nombre'    => $grupo->first()->nombre,
                'pendiente' => round($grupo->sum('pendiente'), 2),
            ])
            ->sortBy('nombre')
            ->values();
    }

    private function demandasEjercicioActual(): int
    {
        return $this->demandasEjercicioActualDetalle()->count();
    }

    // Paso 1: recibe el fichero (o continúa uno ya subido vía tmp_id tras resolver el mapeo) y
    // devuelve needs_mapping si hay conceptos sin clasificar. Si todo está mapeado, evalúa
    // directamente (paso 2) sin escribir nada.
    public function evaluar(Request $request, Project $project)
    {
        [$tmpId, $filePath, $originalName, $error] = $this->resolverTmp($request);
        if ($error) return response()->json(['ok' => false, 'error' => $error]);

        $this->guardarMappingsEnviados($request);

        try {
            $records = (new CuotasReportParser())->parse($filePath);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se puede leer el fichero: ' . $e->getMessage()]);
        }

        if (empty($records)) {
            return response()->json(['ok' => false, 'error' => 'No se ha reconocido ninguna fila de cuota en el fichero.']);
        }

        $mapeo = DB::table('mb_cuotas_mapeo')->get()->keyBy('concepto');
        $necesitaMapeo = $this->resolverConceptosNuevos($records, $mapeo);
        if ($necesitaMapeo !== null) {
            return response()->json(array_merge(['needs_mapping' => true, 'tmp_id' => $tmpId], $necesitaMapeo));
        }

        $fechaExportacion = $this->fechaExportacionDesdeNombre($originalName) ?? now()->toDateString();
        $now = now();

        [$clavesTocadas, $snapshotsNuevos, $sinVivienda, $omitidasRuido, $ultimoPorVivienda] =
            $this->procesarRegistros($records, $mapeo, $fechaExportacion, $originalName, $now);

        $resultado = $this->calcularResultado($clavesTocadas, $snapshotsNuevos, $fechaExportacion, $now, escribir: false);
        $cambiosPropietario = $this->resolverPropietarios($ultimoPorVivienda, $fechaExportacion, $now, escribir: false);
        $demandasDetalle = $this->demandasEjercicioActualDetalle($resultado['simulado']);
        unset($resultado['simulado']); // solo interno, no hace falta mandarlo al frontend

        return response()->json(array_merge(['ok' => true, 'tmp_id' => $tmpId], $resultado, [
            'sin_vivienda'                     => array_keys($sinVivienda),
            'omitidas_ruido_historico'         => $omitidasRuido,
            'cambios_propietario'              => $cambiosPropietario,
            'demandas_ejercicio_actual'        => $demandasDetalle->count(),
            'demandas_ejercicio_actual_detalle'=> $demandasDetalle->values(),
        ]));
        // OJO: no se borra el fichero temporal aquí -- se necesita para confirmar() o cancelar().
    }

    // Paso 3: aplica de verdad lo evaluado. Vuelve a parsear el MISMO fichero (por tmp_id) y esta
    // vez sí escribe: mb_cuotas_exportaciones, mb_cuotas + histórico de estado, y el histórico de
    // propietarios.
    public function confirmar(Request $request, Project $project)
    {
        $request->validate(['tmp_id' => ['required', 'string', 'regex:/^cuotas_[a-zA-Z0-9_.]+$/']]);
        $tmpId = $request->input('tmp_id');
        $filePath = Storage::disk('local')->path("cuotas_tmp/{$tmpId}.xls");
        if (!file_exists($filePath)) {
            return response()->json(['ok' => false, 'error' => 'Fichero temporal no encontrado. Vuelve a subirlo.']);
        }
        $originalName = Storage::disk('local')->exists("cuotas_tmp/{$tmpId}.name")
            ? Storage::disk('local')->get("cuotas_tmp/{$tmpId}.name")
            : 'recibos.xls';

        try {
            $records = (new CuotasReportParser())->parse($filePath);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se puede leer el fichero: ' . $e->getMessage()]);
        }

        $mapeo = DB::table('mb_cuotas_mapeo')->get()->keyBy('concepto');
        $fechaExportacion = $this->fechaExportacionDesdeNombre($originalName) ?? now()->toDateString();
        $now = now();

        $resultado = DB::transaction(function () use ($records, $mapeo, $fechaExportacion, $originalName, $filePath, $now) {
            [$clavesTocadas, $snapshotsNuevos, $sinVivienda, $omitidasRuido, $ultimoPorVivienda] =
                $this->procesarRegistros($records, $mapeo, $fechaExportacion, $originalName, $now);

            $this->persistirExportaciones($snapshotsNuevos, $fechaExportacion, $now);

            $resultado = $this->calcularResultado($clavesTocadas, $snapshotsNuevos, $fechaExportacion, $now, escribir: true);
            $cambiosPropietario = $this->resolverPropietarios($ultimoPorVivienda, $fechaExportacion, $now, escribir: true);

            $destino = 'mb/cuotas_imports/' . now()->format('Y-m-d_His') . '_' . $originalName;
            Storage::disk('public')->put($destino, file_get_contents($filePath));

            return array_merge($resultado, [
                'sin_vivienda'             => array_keys($sinVivienda),
                'omitidas_ruido_historico' => $omitidasRuido,
                'cambios_propietario'      => $cambiosPropietario,
                'fichero_guardado'         => $destino,
            ]);
        });

        Storage::disk('local')->delete(["cuotas_tmp/{$tmpId}.xls", "cuotas_tmp/{$tmpId}.name"]);

        // Se recalcula fuera de la transaccion, ya con los estados de mb_cuotas actualizados.
        $demandasDetalle = $this->demandasEjercicioActualDetalle();
        $resultado['demandas_ejercicio_actual'] = $demandasDetalle->count();
        $resultado['demandas_ejercicio_actual_detalle'] = $demandasDetalle->values();
        unset($resultado['simulado']); // solo interno, no hace falta mandarlo al frontend

        return response()->json(array_merge(['ok' => true], $resultado));
    }

    // Descarta la evaluación sin aplicar nada -- borra el fichero temporal.
    public function cancelar(Request $request, Project $project)
    {
        $request->validate(['tmp_id' => ['required', 'string', 'regex:/^cuotas_[a-zA-Z0-9_.]+$/']]);
        $tmpId = $request->input('tmp_id');
        Storage::disk('local')->delete(["cuotas_tmp/{$tmpId}.xls", "cuotas_tmp/{$tmpId}.name"]);

        return response()->json(['ok' => true]);
    }

    // Resuelve el fichero temporal a partir del upload o de un tmp_id ya existente.
    private function resolverTmp(Request $request): array
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:51200']);
            $file  = $request->file('file');
            $tmpId = uniqid('cuotas_', true);
            $file->storeAs('cuotas_tmp', "{$tmpId}.xls");
            Storage::disk('local')->put("cuotas_tmp/{$tmpId}.name", $file->getClientOriginalName());
        } else {
            $request->validate(['tmp_id' => ['required', 'string', 'regex:/^cuotas_[a-zA-Z0-9_.]+$/']]);
            $tmpId = $request->input('tmp_id');
        }

        $filePath = Storage::disk('local')->path("cuotas_tmp/{$tmpId}.xls");
        if (!file_exists($filePath)) {
            return [null, null, null, 'Fichero temporal no encontrado. Vuelve a subirlo.'];
        }
        $originalName = Storage::disk('local')->exists("cuotas_tmp/{$tmpId}.name")
            ? Storage::disk('local')->get("cuotas_tmp/{$tmpId}.name")
            : 'recibos.xls';

        return [$tmpId, $filePath, $originalName, null];
    }

    private function guardarMappingsEnviados(Request $request): void
    {
        foreach ($request->input('mappings', []) as $mapping) {
            $concepto  = trim((string) ($mapping['concepto'] ?? ''));
            $ejercicio = trim((string) ($mapping['ejercicio'] ?? ''));
            $tipo      = trim((string) ($mapping['tipo_cuota'] ?? ''));
            if ($concepto === '' || $ejercicio === '' || !in_array($tipo, self::TIPOS_SELECCIONABLES, true)) continue;

            DB::table('mb_cuotas_mapeo')->updateOrInsert(
                ['concepto' => $concepto],
                ['ejercicio' => $ejercicio, 'tipo_cuota' => $tipo, 'updatedat' => now(), 'createdat' => now()]
            );
        }
    }

    // Clasificación automática ("cuenta" -> Entrega a cuenta) + detección de conceptos que aún
    // necesitan que el usuario indique ejercicio+tipo. Devuelve null si todo está ya mapeado, o el
    // payload a devolver (needs_mapping) si falta alguno.
    private function resolverConceptosNuevos(array $records, &$mapeo): ?array
    {
        $conceptosDelFichero = collect($records)->pluck('concepto')->unique();

        foreach ($conceptosDelFichero as $concepto) {
            if ($mapeo->has($concepto) || !str_contains(mb_strtolower($concepto), 'cuenta')) continue;

            $fila = collect($records)->firstWhere('concepto', $concepto);
            $fila = [
                'concepto'   => $concepto,
                'ejercicio'  => $this->ejercicioFromFecha($fila['fecha_emision']),
                'tipo_cuota' => 'Entrega a cuenta',
                'updatedat'  => now(),
                'createdat'  => now(),
            ];
            DB::table('mb_cuotas_mapeo')->updateOrInsert(['concepto' => $concepto], $fila);
            $mapeo[$concepto] = (object) $fila;
        }

        $desconocidos = $conceptosDelFichero->filter(fn($c) => !$mapeo->has($c))->values();
        if ($desconocidos->isEmpty()) return null;

        $ejemplos = [];
        foreach ($desconocidos as $concepto) {
            $fila = collect($records)->firstWhere('concepto', $concepto);
            $ejemplos[] = [
                'concepto'           => $concepto,
                'ejercicio_sugerido' => $this->ejercicioFromFecha($fila['fecha_emision']),
                'ejemplo_vivienda'   => $fila['vivienda_cuota_name'],
                'ejemplo_fecha'      => $fila['fecha_emision'],
                'ejemplo_importe'    => $fila['importe'],
            ];
        }

        return ['conceptos' => $ejemplos, 'tipos' => self::TIPOS_SELECCIONABLES];
    }

    // Resuelve cada fila del fichero a vivienda+tipo_cuota, aplica el filtro de ruido histórico, y
    // construye la "foto hipotética" (snapshot) de cada cuota tocada. No escribe nada en la BD.
    // Devuelve [clavesTocadas, snapshotsNuevos, sinVivienda, omitidasRuido, ultimoPorVivienda].
    private function procesarRegistros(array $records, $mapeo, string $fechaExportacion, string $originalName, Carbon $now): array
    {
        $viviendaMap = DB::table('mb_viviendas')->pluck('id', 'cuota_name')
            ->mapWithKeys(fn($id, $nombre) => [$this->normalize($nombre) => $id]);

        $realCuotas = DB::table('mb_cuotas')->get()->keyBy(
            fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision
        );

        $sinVivienda = []; $omitidasRuido = [];
        $ultimoPorVivienda = [];
        $clavesTocadas = [];
        $snapshotsNuevos = [];

        foreach ($records as $r) {
            $idViv = $viviendaMap[$this->normalize($r['vivienda_cuota_name'])] ?? null;
            if (!$idViv) {
                $sinVivienda[$r['vivienda_cuota_name']] = true;
                continue;
            }

            $ejercicio = $this->ejercicioFromFecha($r['fecha_emision']);
            $tipoCuota = $mapeo[$r['concepto']]->tipo_cuota ?? null;
            if (!$tipoCuota) continue; // no debería pasar: ya se exigió resolver todos los conceptos antes de llegar aquí

            $key = $idViv . '|' . $this->normalize($r['concepto']) . '|' . $r['fecha_emision'];

            if ($r['fecha_emision'] < self::FECHA_CORTE_RUIDO_HISTORICO) {
                $real = $realCuotas[$key] ?? null;
                $reconcilia = $real
                    && trim((string) $real->ejercicio) === $ejercicio
                    && trim((string) $real->tipo_cuota) === $tipoCuota;
                if (!$reconcilia) {
                    $omitidasRuido[] = $r['concepto'] . ' (' . $r['vivienda_cuota_name'] . ', ' . $r['fecha_emision'] . ')';
                    continue;
                }
            }

            $importeCobrado = round($r['importe'] - $r['pendiente'], 2);
            $estado = $r['pendiente'] > 0 ? 'Pendiente' : 'Pagada';

            $snapshotsNuevos[$key] = [
                'id_viviendas'      => $idViv,
                'concepto'          => $r['concepto'],
                'fecha_emision'     => $r['fecha_emision'],
                'fecha_exportacion' => $fechaExportacion,
                'ejercicio'         => $ejercicio,
                'tipo_cuota'        => $tipoCuota,
                'propietario'       => $r['propietario'],
                'forma_pago'        => $r['forma_pago'],
                'importe'           => $r['importe'],
                'pendiente'         => $r['pendiente'],
                'importe_cobrado'   => $importeCobrado,
                'estado'            => $estado,
                'fichero_origen'    => $originalName,
                'createdat'         => $now,
                'updatedat'         => $now,
            ];
            $clavesTocadas[$key] = [$idViv, $r['concepto'], $r['fecha_emision']];

            if (!isset($ultimoPorVivienda[$idViv]) || $r['fecha_emision'] >= $ultimoPorVivienda[$idViv]['fecha_emision']) {
                $ultimoPorVivienda[$idViv] = ['fecha_emision' => $r['fecha_emision'], 'propietario' => $r['propietario']];
            }
        }

        return [$clavesTocadas, $snapshotsNuevos, $sinVivienda, $omitidasRuido, $ultimoPorVivienda];
    }

    // Inserta/actualiza en mb_cuotas_exportaciones las fotos de esta carga (solo se llama desde
    // confirmar(), nunca desde evaluar()).
    private function persistirExportaciones(array $snapshotsNuevos, string $fechaExportacion, Carbon $now): void
    {
        $existentes = DB::table('mb_cuotas_exportaciones')
            ->where('fecha_exportacion', $fechaExportacion)
            ->get()->keyBy(fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision);

        foreach ($snapshotsNuevos as $key => $datosSnap) {
            $existenteSnap = $existentes[$key] ?? null;
            if (!$existenteSnap) {
                DB::table('mb_cuotas_exportaciones')->insert($datosSnap);
                continue;
            }
            $cambia = abs((float) $existenteSnap->importe - $datosSnap['importe']) > 0.005
                || abs((float) $existenteSnap->pendiente - $datosSnap['pendiente']) > 0.005
                || trim((string) $existenteSnap->forma_pago) !== $datosSnap['forma_pago']
                || $this->normalize($existenteSnap->propietario) !== $this->normalize($datosSnap['propietario']);
            if ($cambia) {
                $datosSnapSinCreate = $datosSnap;
                unset($datosSnapSinCreate['createdat']);
                DB::table('mb_cuotas_exportaciones')->where('id', $existenteSnap->id)->update($datosSnapSinCreate);
            }
        }
    }

    // Calcula (y si $escribir=true, persiste) el estado actual y el histórico de cada cuota tocada,
    // a partir de TODAS sus fotos conocidas (las que ya hay en mb_cuotas_exportaciones + la
    // hipotética/nueva de esta carga, aunque todavía no esté persistida), ordenadas por
    // fecha_exportacion -- no por orden de carga. Clasifica cada cuota en: nuevas,
    // pendiente_a_pagada, pagada_a_pendiente, actualizadas_otros_datos, sin_cambios. Detecta avisos
    // de demandas (cuota con id_demandas activa cuyo estado protegido difiere del que marcaría el
    // fichero).
    private function calcularResultado(array $clavesTocadas, array $snapshotsNuevos, string $fechaExportacion, Carbon $now, bool $escribir): array
    {
        if (empty($clavesTocadas)) {
            return [
                'fecha_exportacion' => $fechaExportacion,
                'nuevas' => 0, 'pendiente_a_pagada' => 0, 'pagada_a_pendiente' => 0,
                'actualizadas_otros_datos' => 0, 'sin_cambios' => 0,
                'importe_recien_cobrado' => 0.0, 'estado_historico' => 0, 'avisos_demandas' => [],
                'demandas_a_cobradas' => 0,
                'importe_pendiente_fichero' => 0.0, 'importe_incobrable' => 0.0,
                'importe_demandado' => 0.0, 'importe_pendiente_real' => 0.0,
                'simulado' => [],
            ];
        }

        $idViviendasTocadas = collect($clavesTocadas)->pluck(0)->unique()->values();

        // Timeline existente en BD, EXCLUYENDO la foto de esta misma fecha_exportacion para esta
        // clave (si ya existiera de una carga previa del mismo fichero) -- se sustituye siempre por
        // la versión en memoria de $snapshotsNuevos, así el cálculo es idéntico se haya persistido
        // ya o no.
        $timelinesPorClave = DB::table('mb_cuotas_exportaciones')
            ->whereIn('id_viviendas', $idViviendasTocadas)
            ->orderBy('fecha_exportacion')
            ->orderBy('id')
            ->get()
            ->groupBy(fn($row) => $row->id_viviendas . '|' . $this->normalize($row->concepto) . '|' . $row->fecha_emision);

        $cuotasPorClave = DB::table('mb_cuotas')
            ->whereIn('id_viviendas', $idViviendasTocadas)
            ->get()
            ->keyBy(fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision);

        $nuevas = 0; $pendienteAPagada = 0; $pagadaAPendiente = 0; $actualizadasOtros = 0; $sinCambios = 0;
        $eventosHistorico = 0; $importeRecienCobrado = 0.0; $avisosDemandas = [];
        // Importe "pendiente" que trae el fichero para las cuotas tocadas en esta carga, desglosado
        // por lo que ese importe va a QUEDAR en Opland: si la cuota ya está protegida (Demandada/
        // Incobrable/Anulada) su estado no se toca pase lo que pase en el fichero, así que ese
        // importe no pasa a "pendiente real" -- se queda en su cajón correspondiente.
        $importePendienteFichero = 0.0; $importeIncobrable = 0.0; $importeDemandado = 0.0;
        $importeAnulado = 0.0; $importePendienteReal = 0.0;
        // Estado "resultante" (post-fichero) de cada cuota tocada, aunque no se escriba en BD
        // todavía (evaluar() usa esto para simular el efecto del fichero sobre "a demandar").
        $simulado = [];
        $userId = Auth::id();

        foreach ($clavesTocadas as $key => $meta) {
            $timeline = ($timelinesPorClave[$key] ?? collect())
                ->reject(fn($row) => (string) $row->fecha_exportacion === $fechaExportacion)
                ->values();
            $nuevoSnap = $snapshotsNuevos[$key] ?? null;
            if ($nuevoSnap) $timeline->push((object) $nuevoSnap);
            $timeline = $timeline->sortBy('fecha_exportacion')->values();
            if ($timeline->isEmpty()) continue;

            $eventos = [];
            $prev = null;
            $fechaCobroActual = null;
            foreach ($timeline as $snap) {
                if ($prev !== null) {
                    $pendienteCambia = abs((float) $prev->pendiente - (float) $snap->pendiente) > 0.005;
                    $estadoCambia = trim((string) $prev->estado) !== trim((string) $snap->estado);
                    if ($pendienteCambia || $estadoCambia) {
                        $eventos[] = [
                            'estado_anterior'    => $prev->estado,
                            'estado_nuevo'       => $snap->estado,
                            'pendiente_anterior' => $prev->pendiente,
                            'pendiente_nuevo'    => $snap->pendiente,
                            'fecha_exportacion'  => $snap->fecha_exportacion,
                            'fichero_origen'     => $snap->fichero_origen,
                            'createdat'          => $now,
                        ];
                    }
                }
                if ($snap->estado === 'Pagada' && ($prev === null || $prev->estado !== 'Pagada')) {
                    $fechaCobroActual = $snap->fecha_exportacion;
                } elseif ($snap->estado !== 'Pagada') {
                    $fechaCobroActual = null;
                }
                $prev = $snap;
            }

            $ultimo = $timeline->last();
            $nombreCuota = $ultimo->concepto . ' - ' . Carbon::parse($ultimo->fecha_emision)->format('d/m/Y');

            $cuotaExistente = $cuotasPorClave[$key] ?? null;
            $estadoProtegido = $cuotaExistente && in_array(trim((string) $cuotaExistente->estado), self::ESTADOS_PROTEGIDOS, true);

            $hayCambioEnEsteCarga = collect($eventos)->contains(fn($e) => (string) $e['fecha_exportacion'] === $fechaExportacion);

            if ($estadoProtegido && $cuotaExistente->id_demandas !== null && $hayCambioEnEsteCarga) {
                $avisosDemandas[] = [
                    'id_cuota'      => $cuotaExistente->id,
                    'id_viviendas'  => $cuotaExistente->id_viviendas,
                    'concepto'      => $cuotaExistente->concepto,
                    'fecha_emision' => $cuotaExistente->fecha_emision,
                    'estado_actual' => $cuotaExistente->estado,
                    'estado_fichero'=> $ultimo->estado,
                    'id_demandas'   => $cuotaExistente->id_demandas,
                ];
            }

            $pendienteFichero = (float) ($ultimo->pendiente ?? 0);
            if ($pendienteFichero > 0.005) {
                $importePendienteFichero += $pendienteFichero;
                $estadoExistente = $cuotaExistente ? trim((string) $cuotaExistente->estado) : null;
                if ($estadoExistente === 'Incobrable') {
                    $importeIncobrable += $pendienteFichero;
                } elseif ($estadoExistente === 'Demandada') {
                    $importeDemandado += $pendienteFichero;
                } elseif ($estadoExistente === 'Anulada') {
                    $importeAnulado += $pendienteFichero;
                } else {
                    $importePendienteReal += $pendienteFichero;
                }
            }

            $datos = [
                'id_viviendas'    => $ultimo->id_viviendas,
                'nombre'          => $nombreCuota,
                'fecha_emision'   => $ultimo->fecha_emision,
                'concepto'        => $ultimo->concepto,
                'ejercicio'       => $ultimo->ejercicio,
                'tipo_cuota'      => $ultimo->tipo_cuota,
                'propietario'     => $ultimo->propietario,
                'forma_pago'      => $ultimo->forma_pago,
                'importe'         => $ultimo->importe,
                'pendiente'       => $ultimo->pendiente,
                'importe_cobrado' => $ultimo->importe_cobrado,
                'updatedat'       => $now,
            ];
            if (!$estadoProtegido) {
                $datos['estado']     = $ultimo->estado;
                $datos['fecha_pago'] = $fechaCobroActual;
            }

            if ($cuotaExistente) {
                $estadoAntes = trim((string) $cuotaExistente->estado);
                $estadoDespues = $estadoProtegido ? $estadoAntes : trim((string) $datos['estado']);
                $otrosCambios = abs((float) $cuotaExistente->importe - (float) $datos['importe']) > 0.005
                    || trim((string) $cuotaExistente->forma_pago) !== trim((string) $datos['forma_pago'])
                    || $this->normalize($cuotaExistente->propietario) !== $this->normalize($datos['propietario']);
                $pendienteCambioReal = abs((float) $cuotaExistente->pendiente - (float) $datos['pendiente']) > 0.005;

                if (!$estadoProtegido && $estadoAntes !== 'Pagada' && $estadoDespues === 'Pagada') {
                    $pendienteAPagada++;
                    $importeRecienCobrado += (float) $cuotaExistente->pendiente;
                } elseif (!$estadoProtegido && $estadoAntes === 'Pagada' && $estadoDespues !== 'Pagada') {
                    $pagadaAPendiente++;
                } elseif ($otrosCambios || $pendienteCambioReal) {
                    $actualizadasOtros++;
                } else {
                    $sinCambios++;
                }

                if ($escribir) {
                    DB::table('mb_cuotas')->where('id', $cuotaExistente->id)->update(array_merge($datos, ['updateuser' => $userId]));
                }
                $idCuota = $cuotaExistente->id;
                $estadoFinal = $estadoDespues;
            } else {
                $datos['estado']     = $datos['estado'] ?? $ultimo->estado;
                $datos['fecha_pago'] = $datos['fecha_pago'] ?? $fechaCobroActual;
                if ($datos['estado'] === 'Pagada') {
                    $importeRecienCobrado += (float) $ultimo->importe;
                }
                $nuevas++;

                if ($escribir) {
                    $datos['blocked'] = 0; $datos['hidden'] = 0; $datos['deleted'] = 0;
                    $datos['createuser'] = $userId;
                    $datos['createdat'] = $now;
                    $idCuota = DB::table('mb_cuotas')->insertGetId($datos);
                } else {
                    $idCuota = null;
                }
                $estadoFinal = $datos['estado'];
            }

            // Estado "resultante" de esta cuota tras aplicar el fichero -- se usa para simular en
            // memoria (sin escribir) que efecto tendria la carga sobre "viviendas a demandar",
            // incluso durante la evaluacion (escribir:false).
            $simulado[$key] = [
                'id_viviendas'  => $datos['id_viviendas'],
                'concepto'      => $datos['concepto'],
                'fecha_emision' => $datos['fecha_emision'],
                'tipo_cuota'    => $datos['tipo_cuota'],
                'estado'        => $estadoFinal,
                'pendiente'     => (float) $datos['pendiente'],
            ];

            if ($escribir && $idCuota) {
                DB::table('mb_cuotas_estado_historico')->where('id_cuota', $idCuota)->delete();
                if (!empty($eventos)) {
                    foreach ($eventos as &$ev) { $ev['id_cuota'] = $idCuota; }
                    unset($ev);
                    DB::table('mb_cuotas_estado_historico')->insert($eventos);
                }
            }
            $eventosHistorico += count($eventos);
        }

        // De los avisos (cuotas con demanda activa que el fichero tocaría pero que se protegen y
        // NO se escriben), cuántas son concretamente demandas que el fichero marca como cobradas
        // -- informativo, para que se revisen y liquiden a mano si procede.
        $demandasACobradas = collect($avisosDemandas)
            ->where('estado_actual', 'Demandada')
            ->where('estado_fichero', 'Pagada')
            ->count();

        return [
            'fecha_exportacion'         => $fechaExportacion,
            'nuevas'                    => $nuevas,
            'pendiente_a_pagada'        => $pendienteAPagada,
            'pagada_a_pendiente'        => $pagadaAPendiente,
            'actualizadas_otros_datos'  => $actualizadasOtros,
            'sin_cambios'               => $sinCambios,
            'importe_recien_cobrado'    => round($importeRecienCobrado, 2),
            'estado_historico'          => $eventosHistorico,
            'avisos_demandas'           => $avisosDemandas,
            'demandas_a_cobradas'       => $demandasACobradas,
            'importe_pendiente_fichero' => round($importePendienteFichero, 2),
            'importe_incobrable'        => round($importeIncobrable, 2),
            'importe_demandado'         => round($importeDemandado, 2),
            'importe_pendiente_real'    => round($importePendienteReal, 2),
            'simulado'                  => $simulado,
        ];
    }

    // Detecta (y si $escribir=true, aplica) los cambios de propietario respecto al histórico real.
    // Se compara contra quién era el propietario activo EN LA FECHA DE EXPORTACIÓN del fichero, no
    // contra el propietario activo ahora mismo -- si se está cargando un fichero antiguo (backfill),
    // el propietario "actual" puede ser alguien posterior que nada tiene que ver con lo que refleja
    // ese fichero, y compararlo así generaría cambios falsos.
    //
    // La resolución a mb_propietarios es SIEMPRE por coincidencia exacta (tras normalizar) -- nunca
    // se fusiona automáticamente por similitud; si no hay coincidencia exacta se crea un propietario
    // nuevo. Los posibles duplicados por variantes de escritura se revisan aparte en
    // /mb/propietarios.
    private function resolverPropietarios(array $ultimoPorVivienda, string $fechaExportacion, Carbon $now, bool $escribir): array
    {
        if (empty($ultimoPorVivienda)) return [];

        $activos = DB::table('mb_propietarios_historico')
            ->whereIn('id_viviendas', array_keys($ultimoPorVivienda))
            ->where('fecha_desde', '<=', $fechaExportacion)
            ->where(fn($q) => $q->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>=', $fechaExportacion))
            ->orderByDesc('fecha_desde')
            ->get()
            ->groupBy('id_viviendas')
            ->map(fn($rows) => $rows->first());

        $nombresViviendas = DB::table('mb_viviendas')
            ->whereIn('id', array_keys($ultimoPorVivienda))
            ->pluck('nombre', 'id');

        $userId = Auth::id();
        $cambios = [];

        foreach ($ultimoPorVivienda as $idViv => $info) {
            $activo = $activos[$idViv] ?? null;
            if (!$activo || $this->normalize($activo->propietario) === $this->normalize($info['propietario'])) continue;

            // Protección contra ficheros procesados fuera de orden cronológico (o "solo pendientes"
            // cuya última cuota es una deuda antigua que aún arrastra el propietario ANTERIOR al
            // cambio real -- el campo propietario de una cuota refleja quién la emitió, no quién es
            // el dueño ahora). Si ya existe un tramo de historico que empieza DESPUÉS de esta fecha
            // de exportación, o si cerrar el tramo activo aquí generaría un rango invertido
            // (fecha_hasta < fecha_desde), esta carga no es fiable para inferir un cambio de
            // propietario -- se ignora en vez de corromper el historico ya conocido (bug real
            // detectado y corregido en BD el 2026-08-14, ver DOC_TECNICO).
            $hayTramoPosterior = DB::table('mb_propietarios_historico')
                ->where('id_viviendas', $idViv)
                ->where('fecha_desde', '>', $fechaExportacion)
                ->exists();
            $nuevaFechaHasta = Carbon::parse($fechaExportacion)->subDay();
            if ($hayTramoPosterior || $nuevaFechaHasta->lt(Carbon::parse($activo->fecha_desde))) {
                continue;
            }

            // Protección adicional: si el "nuevo" propietario que trae el fichero coincide con un
            // propietario YA SUPERADO de esta misma vivienda (un tramo anterior al activo), es casi
            // seguro que es el mismo dato viejo arrastrado por una cuota antigua sin cobrar, no un
            // cambio de titularidad real -- la propiedad no suele "volver" al dueño anterior.
            $normNuevo = $this->normalize($info['propietario']);
            $esRetrocesoAOwnerAnterior = DB::table('mb_propietarios_historico')
                ->where('id_viviendas', $idViv)
                ->where('fecha_desde', '<', $activo->fecha_desde)
                ->whereRaw('upper(propietario) = ?', [$normNuevo])
                ->exists();
            if ($esRetrocesoAOwnerAnterior) continue;

            $idPropietarios = DB::table('mb_propietarios')->whereRaw('upper(nombre) = ?', [$normNuevo])->value('id');
            $esNuevo = $idPropietarios === null;

            if ($escribir) {
                if ($esNuevo) {
                    $idPropietarios = DB::table('mb_propietarios')->insertGetId([
                        'nombre' => $info['propietario'], 'blocked' => 0, 'hidden' => 0, 'deleted' => 0,
                        'createuser' => $userId, 'createdat' => $now, 'updatedat' => $now,
                    ]);
                }
                DB::table('mb_propietarios_historico')->where('id', $activo->id)->update([
                    'fecha_hasta' => Carbon::parse($fechaExportacion)->subDay()->toDateString(),
                    'updatedat' => $now, 'updateuser' => $userId,
                ]);
                DB::table('mb_propietarios_historico')->insert([
                    'id_viviendas' => $idViv, 'nombre' => $info['propietario'], 'propietario' => $info['propietario'],
                    'id_propietarios' => $idPropietarios, 'fecha_desde' => $fechaExportacion, 'fecha_hasta' => null,
                    'blocked' => 0, 'hidden' => 0, 'deleted' => 0, 'createuser' => $userId,
                    'createdat' => $now, 'updatedat' => $now,
                ]);
            }

            $cambios[] = [
                'id_viviendas'          => $idViv,
                'nombre_vivienda'       => $nombresViviendas[$idViv] ?? null,
                'propietario_anterior'  => $activo->propietario,
                'propietario_nuevo'     => $info['propietario'],
                'resolucion'            => $esNuevo ? 'nuevo' : 'existente',
            ];
        }

        return $cambios;
    }

    public function historico(Request $request, Project $project, int $cuota)
    {
        $eventos = DB::table('mb_cuotas_estado_historico')
            ->where('id_cuota', $cuota)
            ->orderByDesc('fecha_exportacion')
            ->orderByDesc('id')
            ->get();

        return response()->json(['historico' => $eventos]);
    }

    private function fechaExportacionDesdeNombre(string $nombreFichero): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})[ _.-]/', $nombreFichero, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }

    private function ejercicioFromFecha(string $fecha): string
    {
        $y = (int) substr($fecha, 0, 4);
        $m = (int) substr($fecha, 5, 2);
        return $m >= 7 ? "{$y}-" . ($y + 1) : ($y - 1) . "-{$y}";
    }

    private function normalize(?string $s): string
    {
        $s = mb_strtoupper(trim((string) $s));
        return preg_replace('/\s+/', ' ', $s);
    }
}
