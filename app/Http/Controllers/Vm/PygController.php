<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PygController extends Controller
{
    public function index(Project $project)
    {
        $periodos = DB::select("
            SELECT
                v.periodo,
                COUNT(*) AS num_registros,
                COUNT(DISTINCT v.id_cuenta) AS num_cuentas,
                COUNT(DISTINCT v.id_propiedades) AS num_propiedades,
                COUNT(DISTINCT v.ceco) AS num_cecos,
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '7%'), 0) AS importe_ingresos,
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '6%'), 0) AS importe_gastos
            FROM vm_pyg_valores v
            JOIN vm_pyg_cuentas c ON c.id = v.id_cuenta
            WHERE EXISTS (
                SELECT 1 FROM vm_pyg g WHERE g.periodo = v.periodo AND g.deleted = 0
            )
            GROUP BY v.periodo
            ORDER BY v.periodo DESC
        ");

        return view('pyg', compact('project', 'periodos'));
    }

    public function import(Request $request, Project $project)
    {
        // Paso 1: primera subida con fichero
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:20480']);
            $file  = $request->file('file');
            $tmpId = uniqid('pyg_', true);
            $stored = $file->storeAs('pyg_tmp', "{$tmpId}.xlsx");
            if (!$stored) {
                return response()->json(['ok' => false, 'error' => 'Error al guardar el fichero en el servidor (storeAs failed).']);
            }
            Storage::disk('local')->put("pyg_tmp/{$tmpId}.name", $file->getClientOriginalName());
            $filePath = Storage::disk('local')->path("pyg_tmp/{$tmpId}.xlsx");
            if (!file_exists($filePath)) {
                return response()->json(['ok' => false, 'error' => 'Fichero guardado pero no encontrado en: '.$filePath]);
            }
        } else {
            // Paso 2: confirmación de mappings (JSON sin fichero)
            $request->validate(['tmp_id' => ['required', 'string', 'regex:/^pyg_[a-zA-Z0-9_.]+$/']]);
            $tmpId    = $request->input('tmp_id');
            $filePath = Storage::disk('local')->path("pyg_tmp/{$tmpId}.xlsx");
            if (!file_exists($filePath)) {
                return response()->json(['ok' => false, 'error' => 'Fichero temporal no encontrado. Vuelve a subir el Excel.']);
            }
        }

        $originalName = Storage::disk('local')->exists("pyg_tmp/{$tmpId}.name")
            ? Storage::disk('local')->get("pyg_tmp/{$tmpId}.name")
            : 'informe.xlsx';

        // Aplicar mappings recibidos: el código se AÑADE al historial (nunca se pierde)
        foreach ($request->input('mappings', []) as $mapping) {
            $code = $mapping['code'] ?? null;
            $type = $mapping['type'] ?? 'omitir';
            if (!$code) continue;

            if ($type === 'propiedad' && !empty($mapping['id'])) {
                $propId = (int) $mapping['id'];
                $prop   = DB::table('vm_propiedades')->where('id', $propId)->first(['a3_code_historial']);
                $partes = $this->splitHistorial($prop->a3_code_historial ?? null);
                if (!in_array($code, $partes, true)) $partes[] = $code;

                DB::table('vm_propiedades')->where('id', $propId)->update([
                    'a3_code'           => $code,
                    'a3_code_historial' => implode(',', $partes),
                ]);
            } elseif ($type === 'ceco') {
                if (!empty($mapping['id'])) {
                    $cecoId = (int) $mapping['id'];
                    $ceco   = DB::table('vm_pyg_ceco')->where('id', $cecoId)->first(['nombre_historico']);
                    $partes = $this->splitHistorial($ceco->nombre_historico ?? null);
                    if (!in_array($code, $partes, true)) $partes[] = $code;

                    DB::table('vm_pyg_ceco')->where('id', $cecoId)->update([
                        'nombre_historico' => implode(',', $partes),
                    ]);
                } else {
                    $this->resolveOrCreateCeco($code);
                }
            }
            // 'omitir': no hacer nada
        }

        // Si sólo guardar mapeos (sin importar), devolver ok
        if ($request->boolean('save_only')) {
            return response()->json(['ok' => true, 'saved_only' => true]);
        }

        // Parsear fichero
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se puede leer el fichero: ' . $e->getMessage()]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, false, false);

        // Período (fila índice 3)
        $periodoRaw = (string)($rows[3][0] ?? '');
        if (!preg_match('/a\s+(\d{2})\/(\d{2})\/(\d{4})/', $periodoRaw, $m)) {
            return response()->json(['ok' => false, 'error' => 'No se puede detectar el período en el fichero.']);
        }
        $periodo = "{$m[3]}-{$m[2]}-01";

        // Columnas (fila índice 6) — excluir columna "Total" (sin distinción de mayúsculas)
        $propCodes = [];
        foreach ($rows[6] ?? [] as $colIdx => $val) {
            if ($colIdx === 0) continue;
            $v = trim((string)$val);
            if ($v === '' || strtolower($v) === 'total') continue;
            $propCodes[$colIdx] = $v;
        }

        if (empty($propCodes)) {
            return response()->json(['ok' => false, 'error' => 'No se detectaron columnas de centros de coste.']);
        }

        // Mapas código → id, construidos a partir del historial completo (fuente única de verdad)
        $propHistorialMap = $this->buildHistorialMap('vm_propiedades', 'a3_code_historial');
        $cecoHistorialMap = $this->buildHistorialMap('vm_pyg_ceco', 'nombre_historico');

        // Resolver cada columna: propiedad, ceco, o desconocida
        $resolved     = []; // colIdx => ['id_propiedades' => x] | ['ceco' => y]
        $unknownCodes = [];
        foreach ($propCodes as $colIdx => $code) {
            if (isset($propHistorialMap[$code])) {
                $resolved[$colIdx] = ['id_propiedades' => $propHistorialMap[$code]];
                // Refrescar el "código de la última carga" (solo caché de visualización)
                DB::table('vm_propiedades')->where('id', $propHistorialMap[$code])
                    ->where('a3_code', '!=', $code)
                    ->update(['a3_code' => $code]);
            } elseif (isset($cecoHistorialMap[$code])) {
                $resolved[$colIdx] = ['ceco' => $cecoHistorialMap[$code]];
            } else {
                $unknownCodes[] = $code;
            }
        }
        $unknownCodes = array_values(array_unique($unknownCodes));

        if (!empty($unknownCodes)) {
            $propiedades = DB::table('vm_propiedades')
                ->select('id', 'nombre', 'a3_code', 'deleted')
                ->orderBy('nombre')
                ->get();

            $cecos = DB::table('vm_pyg_ceco')
                ->select('id', 'nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'needs_mapping' => true,
                'tmp_id'        => $tmpId,
                'periodo'       => $periodo,
                'unknown_codes' => $unknownCodes,
                'propiedades'   => $propiedades,
                'cecos'         => $cecos,
            ]);
        }

        // Comprobar que dos columnas distintas del Excel no resuelvan a la misma propiedad/ceco
        // (puede pasar si el historial acumuló variantes de codigo que hoy aparecen como columnas separadas)
        $conflicto = $this->detectarConflictos($propCodes, $resolved);
        if ($conflicto) {
            return response()->json(['ok' => false, 'error' => $conflicto]);
        }

        // Todo mapeado: ejecutar importación
        $result = $this->runImport($rows, $periodo, $resolved, $filePath, $originalName);

        Storage::disk('local')->delete(["pyg_tmp/{$tmpId}.xlsx", "pyg_tmp/{$tmpId}.name"]);

        return response()->json(array_merge(['ok' => true], $result));
    }

    // Detecta si dos codigos distintos del Excel resuelven a la misma propiedad/ceco
    private function detectarConflictos(array $propCodes, array $resolved): ?string
    {
        $porEntidad = [];
        foreach ($resolved as $colIdx => $ref) {
            $entidad = isset($ref['id_propiedades']) ? 'prop_' . $ref['id_propiedades'] : 'ceco_' . $ref['ceco'];
            $porEntidad[$entidad][] = $propCodes[$colIdx];
        }

        $mensajes = [];
        foreach ($porEntidad as $entidad => $codigos) {
            if (count($codigos) < 2) continue;
            if (str_starts_with($entidad, 'prop_')) {
                $id = (int) substr($entidad, 5);
                $nombre = DB::table('vm_propiedades')->where('id', $id)->value('nombre');
                $mensajes[] = "Los códigos " . implode(', ', array_map(fn($c) => "\"{$c}\"", $codigos)) . " del Excel apuntan a la misma propiedad \"{$nombre}\" (id {$id})";
            } else {
                $id = (int) substr($entidad, 5);
                $nombre = DB::table('vm_pyg_ceco')->where('id', $id)->value('nombre');
                $mensajes[] = "Los códigos " . implode(', ', array_map(fn($c) => "\"{$c}\"", $codigos)) . " del Excel apuntan al mismo centro de coste \"{$nombre}\" (id {$id})";
            }
        }

        if (empty($mensajes)) return null;

        return "El Excel tiene columnas duplicadas para la misma propiedad/ceco, no se puede importar así: " . implode('; ', $mensajes) . ". Revisa el Excel o el histórico de códigos antes de reintentar.";
    }

    private function splitHistorial(?string $historial): array
    {
        if (!$historial) return [];
        return array_values(array_filter(array_map('trim', explode(',', $historial)), fn($p) => $p !== ''));
    }

    // Mapa codigo => id a partir de una columna "historial" separada por comas (fuente unica de verdad)
    private function buildHistorialMap(string $table, string $column): array
    {
        $map = [];
        DB::table($table)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select('id', $column)
            ->get()
            ->each(function ($row) use (&$map, $column) {
                foreach ($this->splitHistorial($row->{$column}) as $h) {
                    $map[$h] = $row->id;
                }
            });
        return $map;
    }

    // Busca un ceco cuyo historial ya contenga este codigo; si no existe, lo crea
    private function resolveOrCreateCeco(string $code): int
    {
        $map = $this->buildHistorialMap('vm_pyg_ceco', 'nombre_historico');
        if (isset($map[$code])) {
            return $map[$code];
        }

        return DB::table('vm_pyg_ceco')->insertGetId([
            'nombre'           => $code,
            'nombre_historico' => $code,
            'createdat'        => now(),
            'updatedat'        => now(),
        ]);
    }

    private function runImport(array $rows, string $periodo, array $resolved, string $filePath, string $originalName): array
    {
        return DB::transaction(function () use ($rows, $periodo, $resolved, $filePath, $originalName) {
            return $this->runImportInTransaction($rows, $periodo, $resolved, $filePath, $originalName);
        });
    }

    private function runImportInTransaction(array $rows, string $periodo, array $resolved, string $filePath, string $originalName): array
    {
        $deleted = DB::table('vm_pyg_valores')->where('periodo', $periodo)->delete();

        $currentEpigrafe    = ['codigo' => null, 'nombre' => null];
        $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
        $orden = 0;
        $cuentasNuevas = 0;
        $valoresInsertados = 0;

        // Cuentas leidas desde el ultimo bloque, pendientes de que aparezca
        // la linea de bloque que las cierra (el bloque es un subtotal que
        // aparece DESPUES de los epigrafes que agrupa, no antes)
        $pendientes = [];

        for ($i = 7; $i < count($rows); $i++) {
            $row   = $rows[$i];
            $label = (string)($row[0] ?? '');
            if (trim($label) === '') continue;

            if (preg_match('/^\s*([A-Z])\)\s+(.+)$/', $label, $m)) {
                $bloque = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                foreach ($pendientes as $acc) {
                    [$cid, $nueva] = $this->upsertCuenta($acc, $bloque);
                    if ($nueva) $cuentasNuevas++;
                    $valoresInsertados += $this->insertarValores($periodo, $cid, $acc['row'], $resolved);
                }
                $pendientes = [];
                continue;
            }
            if (preg_match('/^\s+(\d+)\.\s+(.+)$/', $label, $m)) {
                $currentEpigrafe    = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
                continue;
            }
            if (preg_match('/^\s+([a-z])\)\s+(.+)$/', $label, $m)) {
                $currentSubepigrafe = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                continue;
            }
            if (preg_match('/^\s+(\d{8})\s+(.+)$/', $label, $m)) {
                $orden++;
                $pendientes[] = [
                    'codigo'      => $m[1],
                    'nombre'      => trim($m[2]),
                    'epigrafe'    => $currentEpigrafe,
                    'subepigrafe' => $currentSubepigrafe,
                    'orden'       => $orden,
                    'row'         => $row,
                ];
            }
        }

        // Cuentas que quedaran sin bloque si el fichero terminase sin una linea de bloque que las cierre
        foreach ($pendientes as $acc) {
            [$cid, $nueva] = $this->upsertCuenta($acc, ['codigo' => null, 'nombre' => null]);
            if ($nueva) $cuentasNuevas++;
            $valoresInsertados += $this->insertarValores($periodo, $cid, $acc['row'], $resolved);
        }

        $this->guardarResumenPeriodo($periodo, $filePath, $originalName);

        return [
            'periodo'    => $periodo,
            'cuentas'    => $cuentasNuevas,
            'valores'    => $valoresInsertados,
            'sustituido' => $deleted > 0,
        ];
    }

    // Guarda el fichero de forma permanente y calcula/actualiza el resumen del periodo en vm_pyg.
    // Si ya habia un resumen activo para este periodo, lo marca como deleted=1 (se conserva el historico).
    private function guardarResumenPeriodo(string $periodo, string $filePath, string $originalName): void
    {
        DB::table('vm_pyg')
            ->where('periodo', $periodo)
            ->where('deleted', 0)
            ->update(['deleted' => 1, 'updatedat' => now()]);

        $destino = 'vm/pyg/' . $periodo . '_' . uniqid() . '_' . $originalName;
        Storage::disk('public')->put($destino, file_get_contents($filePath));

        $agregado = DB::table('vm_pyg_valores as v')
            ->join('vm_pyg_cuentas as c', 'c.id', '=', 'v.id_cuenta')
            ->where('v.periodo', $periodo)
            ->selectRaw("
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '7%'), 0) AS importe_ingresos,
                COALESCE(SUM(v.importe) FILTER (WHERE c.codigo LIKE '6%'), 0) AS importe_gastos,
                COUNT(DISTINCT v.id_propiedades) AS num_propiedades,
                COUNT(DISTINCT v.ceco) AS num_cecos,
                COUNT(DISTINCT v.id_cuenta) AS num_cuentas,
                COUNT(*) AS num_registros
            ")
            ->first();

        DB::table('vm_pyg')->insert([
            'periodo'          => $periodo,
            'fichero'          => $destino,
            'fichero_nombre'   => $originalName,
            'importe_ingresos' => $agregado->importe_ingresos ?? 0,
            'importe_gastos'   => $agregado->importe_gastos ?? 0,
            'num_propiedades'  => $agregado->num_propiedades ?? 0,
            'num_cecos'        => $agregado->num_cecos ?? 0,
            'num_cuentas'      => $agregado->num_cuentas ?? 0,
            'num_registros'    => $agregado->num_registros ?? 0,
            'createdat'        => now(),
            'updatedat'        => now(),
        ]);
    }

    // Da de alta o actualiza una cuenta en vm_pyg_cuentas. Devuelve [id, esNueva]
    private function upsertCuenta(array $acc, array $bloque): array
    {
        $cuentaId = DB::table('vm_pyg_cuentas')->where('codigo', $acc['codigo'])->value('id');
        $datos = [
            'nombre'             => $acc['nombre'],
            'bloque_codigo'      => $bloque['codigo'],
            'bloque_nombre'      => $bloque['nombre'],
            'epigrafe_codigo'    => $acc['epigrafe']['codigo'],
            'epigrafe_nombre'    => $acc['epigrafe']['nombre'],
            'subepigrafe_codigo' => $acc['subepigrafe']['codigo'],
            'subepigrafe_nombre' => $acc['subepigrafe']['nombre'],
            'orden'              => $acc['orden'],
        ];

        if (!$cuentaId) {
            $cuentaId = DB::table('vm_pyg_cuentas')->insertGetId(array_merge($datos, [
                'codigo'    => $acc['codigo'],
                'createdat' => now(),
            ]));
            return [$cuentaId, true];
        }

        DB::table('vm_pyg_cuentas')->where('id', $cuentaId)->update($datos);
        return [$cuentaId, false];
    }

    // Inserta los valores de una fila de cuenta para las columnas resueltas. Devuelve cuantos valores inserto
    private function insertarValores(string $periodo, int $cuentaId, array $row, array $resolved): int
    {
        $insertados = 0;
        foreach ($resolved as $colIdx => $ref) {
            $val = $row[$colIdx] ?? null;
            if ($val === null || $val === '') continue;
            $importe = (float) $val;
            if ($importe === 0.0) continue;

            DB::table('vm_pyg_valores')->insert([
                'periodo'        => $periodo,
                'id_cuenta'      => $cuentaId,
                'id_propiedades' => $ref['id_propiedades'] ?? null,
                'ceco'           => $ref['ceco'] ?? null,
                'importe'        => $importe,
                'createdat'      => now(),
            ]);
            $insertados++;
        }
        return $insertados;
    }

    public function deletePeriodo(Request $request, Project $project, string $periodo)
    {
        DB::table('vm_pyg_valores')->where('periodo', $periodo)->delete();
        return response()->json(['ok' => true]);
    }
}
