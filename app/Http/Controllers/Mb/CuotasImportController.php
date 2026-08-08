<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CuotasReportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Carga del informe "Listado de recibos" (mb) -- FASE DE PRUEBA: escribe en mb_cuotas_provisional,
// no en mb_cuotas real, para poder validar el pipeline completo con ficheros reales sin tocar
// producción. El recorte de "solo últimos 6 años" (pedido por el cliente) todavía NO está activo;
// se activará explícitamente más adelante.
class CuotasImportController extends Controller
{
    private const TIPOS_SELECCIONABLES = ['C-I', 'C-II', 'C-I_Derrama', 'G.dev.', 'Dudoso', 'Entrega a cuenta'];
    private const FECHA_CORTE_RUIDO_HISTORICO = '2010-01-01'; // por debajo de esto, solo se procesa si reconcilia con mb_cuotas real

    public function index(Project $project)
    {
        $ultimasCargas = DB::table('mb_cuotas_provisional')->exists()
            ? DB::table('mb_cuotas_provisional')->selectRaw('COUNT(*) as n, MAX(updatedat) as ultima')->first()
            : null;

        return view('mb.cuotas-import', [
            'project'      => $project,
            'totalProvisional' => $ultimasCargas->n ?? 0,
            'ultimaCarga'  => $ultimasCargas->ultima ?? null,
            'breadcrumb'   => [
                ['label' => 'Cuotas', 'url' => route('listado', [$project->slug, 'cuotas'])],
                ['label' => 'Carga de recibos (prueba)', 'url' => ''],
            ],
        ]);
    }

    public function import(Request $request, Project $project)
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
            return response()->json(['ok' => false, 'error' => 'Fichero temporal no encontrado. Vuelve a subirlo.']);
        }
        $originalName = Storage::disk('local')->exists("cuotas_tmp/{$tmpId}.name")
            ? Storage::disk('local')->get("cuotas_tmp/{$tmpId}.name")
            : 'recibos.xls';

        // Aplicar las clasificaciones que haya enviado el usuario para conceptos nuevos.
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

        try {
            $records = (new CuotasReportParser())->parse($filePath);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se puede leer el fichero: ' . $e->getMessage()]);
        }

        if (empty($records)) {
            return response()->json(['ok' => false, 'error' => 'No se ha reconocido ninguna fila de cuota en el fichero.']);
        }

        $mapeo = DB::table('mb_cuotas_mapeo')->get()->keyBy('concepto');

        $conceptosDelFichero = collect($records)->pluck('concepto')->unique();

        // Clasificación automática: cualquier concepto que contenga "cuenta" (entregas a cuenta)
        // se mapea directo a "Entrega a cuenta", sin pedir clasificación manual.
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

        if ($desconocidos->isNotEmpty()) {
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

            return response()->json([
                'needs_mapping' => true,
                'tmp_id'        => $tmpId,
                'conceptos'     => $ejemplos,
                'tipos'         => self::TIPOS_SELECCIONABLES,
            ]);
        }

        $resultado = $this->runImport($records, $mapeo, $originalName, $filePath);

        Storage::disk('local')->delete(["cuotas_tmp/{$tmpId}.xls", "cuotas_tmp/{$tmpId}.name"]);

        return response()->json(array_merge(['ok' => true], $resultado));
    }

    private function runImport(array $records, $mapeo, string $originalName, string $filePath): array
    {
        return DB::transaction(function () use ($records, $mapeo, $originalName, $filePath) {
            return $this->runImportInTransaction($records, $mapeo, $originalName, $filePath);
        });
    }

    private function runImportInTransaction(array $records, $mapeo, string $originalName, string $filePath): array
    {
        $viviendaMap = DB::table('mb_viviendas')->pluck('id', 'cuota_name')
            ->mapWithKeys(fn($id, $nombre) => [$this->normalize($nombre) => $id]);

        // Existentes en mb_cuotas_provisional, para upsert.
        $provisionalExistentes = DB::table('mb_cuotas_provisional')->get()->keyBy(
            fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision
        );

        // mb_cuotas REAL, solo para reconciliar el ruido histórico anterior a 2010.
        $realCuotas = DB::table('mb_cuotas')->get()->keyBy(
            fn($c) => $c->id_viviendas . '|' . $this->normalize($c->concepto) . '|' . $c->fecha_emision
        );

        $nuevas = 0; $actualizadas = 0; $sinCambios = 0;
        $sinVivienda = []; $omitidasRuido = []; $pendienteHistorico = 0;
        $ultimoPorVivienda = []; // id_viviendas => ultima fila procesada (por fecha)

        $now = now();
        $hoy = $now->toDateString();

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

            // Ruido histórico anterior a 2010: solo se procesa si reconcilia con mb_cuotas real
            // (misma vivienda+concepto+fecha, mismo ejercicio derivado, mismo tipo_cuota mapeado).
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
            $nombreCuota = $r['concepto'] . ' - ' . \Carbon\Carbon::parse($r['fecha_emision'])->format('d/m/Y');

            $existente = $provisionalExistentes[$key] ?? null;

            if (!$existente) {
                DB::table('mb_cuotas_provisional')->insert([
                    'id_viviendas'     => $idViv,
                    'nombre'           => $nombreCuota,
                    'fecha_emision'    => $r['fecha_emision'],
                    'concepto'         => $r['concepto'],
                    'ejercicio'        => $ejercicio,
                    'tipo_cuota'       => $tipoCuota,
                    'propietario'      => $r['propietario'],
                    'forma_pago'       => $r['forma_pago'],
                    'importe'          => $r['importe'],
                    'pendiente'        => $r['pendiente'],
                    'importe_cobrado'  => $importeCobrado,
                    'estado'           => $estado,
                    'createdat'        => $now,
                    'updatedat'        => $now,
                ]);
                $nuevas++;
            } else {
                $cambia = abs((float) $existente->importe - $r['importe']) > 0.005
                    || abs((float) $existente->pendiente - $r['pendiente']) > 0.005
                    || trim((string) $existente->forma_pago) !== $r['forma_pago']
                    || $this->normalize($existente->propietario) !== $this->normalize($r['propietario']);

                if ($cambia) {
                    if (abs((float) $existente->pendiente - $r['pendiente']) > 0.005) {
                        DB::table('mb_cuotas_pendiente_historico')->insert([
                            'id_cuota'           => $existente->id,
                            'pendiente_anterior' => $existente->pendiente,
                            'pendiente_nuevo'    => $r['pendiente'],
                            'fecha_carga'        => $hoy,
                            'createdat'          => $now,
                        ]);
                        $pendienteHistorico++;
                    }

                    DB::table('mb_cuotas_provisional')->where('id', $existente->id)->update([
                        'importe'         => $r['importe'],
                        'pendiente'       => $r['pendiente'],
                        'forma_pago'      => $r['forma_pago'],
                        'propietario'     => $r['propietario'],
                        'importe_cobrado' => $importeCobrado,
                        'estado'          => $estado,
                        'updatedat'       => $now,
                    ]);
                    $actualizadas++;
                } else {
                    $sinCambios++;
                }
            }

            if (!isset($ultimoPorVivienda[$idViv]) || $r['fecha_emision'] >= $ultimoPorVivienda[$idViv]['fecha_emision']) {
                $ultimoPorVivienda[$idViv] = ['fecha_emision' => $r['fecha_emision'], 'propietario' => $r['propietario']];
            }
        }

        // Detección (informativa, sin escritura) de cambios de propietario respecto al histórico real.
        $cambiosPropietario = [];
        if (!empty($ultimoPorVivienda)) {
            $activos = DB::table('mb_propietarios_historico')
                ->whereIn('id_viviendas', array_keys($ultimoPorVivienda))
                ->whereNull('fecha_hasta')
                ->get()->keyBy('id_viviendas');

            foreach ($ultimoPorVivienda as $idViv => $info) {
                $activo = $activos[$idViv] ?? null;
                if ($activo && $this->normalize($activo->propietario) !== $this->normalize($info['propietario'])) {
                    $cambiosPropietario[] = [
                        'id_viviendas'         => $idViv,
                        'propietario_historico'=> $activo->propietario,
                        'propietario_fichero'  => $info['propietario'],
                    ];
                }
            }
        }

        // Guardar el fichero original para auditoría.
        $destino = 'mb/cuotas_imports/' . now()->format('Y-m-d_His') . '_' . $originalName;
        Storage::disk('public')->put($destino, file_get_contents($filePath));

        return [
            'nuevas'                    => $nuevas,
            'actualizadas'              => $actualizadas,
            'sin_cambios'               => $sinCambios,
            'pendiente_historico'       => $pendienteHistorico,
            'sin_vivienda'              => array_keys($sinVivienda),
            'omitidas_ruido_historico'  => $omitidasRuido,
            'cambios_propietario_detectados' => $cambiosPropietario,
            'fichero_guardado'          => $destino,
        ];
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
