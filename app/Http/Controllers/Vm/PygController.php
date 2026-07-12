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
                COUNT(DISTINCT v.id_cuenta) AS num_cuentas,
                array_agg(DISTINCT v.a3_code ORDER BY v.a3_code) AS cecos
            FROM vm_pyg_valores v
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

        // Aplicar mappings recibidos
        foreach ($request->input('mappings', []) as $mapping) {
            $code = $mapping['code'] ?? null;
            $type = $mapping['type'] ?? 'omitir';
            if (!$code) continue;

            if ($type === 'propiedad' && !empty($mapping['id'])) {
                $prop = DB::table('vm_propiedades')->where('id', (int)$mapping['id'])->first();
                $updateData = ['a3_code' => $code];
                if ($prop && $prop->a3_code && $prop->a3_code !== $code) {
                    $partes = $prop->a3_code_historial ? explode(',', $prop->a3_code_historial) : [];
                    if (!in_array($prop->a3_code, $partes)) $partes[] = $prop->a3_code;
                    $updateData['a3_code_historial'] = implode(',', $partes);
                }
                DB::table('vm_propiedades')->where('id', (int)$mapping['id'])->update($updateData);
            } elseif ($type === 'ceco') {
                DB::table('vm_pyg_ceco')->upsert(
                    ['a3_code' => $code, 'nombre' => $code, 'es_propiedad' => false],
                    ['a3_code'],
                    ['nombre']
                );
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
        $rows  = $sheet->toArray(null, true, true, false);

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

        // Comprobar códigos desconocidos (tras aplicar mappings)
        $knownProp = DB::table('vm_propiedades')
            ->whereNotNull('a3_code')->where('a3_code', '!=', '')->pluck('a3_code')->toArray();
        $knownCeco = DB::table('vm_pyg_ceco')->pluck('a3_code')->toArray();

        // Construir mapa historial → id para resolución automática
        $historialMap = [];
        DB::table('vm_propiedades')
            ->whereNotNull('a3_code_historial')->where('a3_code_historial', '!=', '')
            ->select('id', 'a3_code_historial')
            ->get()
            ->each(function ($p) use (&$historialMap) {
                foreach (explode(',', $p->a3_code_historial) as $h) {
                    $h = trim($h);
                    if ($h !== '') $historialMap[$h] = $p->id;
                }
            });

        $allKnown = array_merge($knownProp, $knownCeco, array_keys($historialMap));

        // Separar: resolubles por historial vs realmente desconocidos
        $autoResolved = [];
        $unknownCodes = [];
        foreach (array_values($propCodes) as $c) {
            if (in_array($c, $knownProp) || in_array($c, $knownCeco)) continue;
            if (isset($historialMap[$c])) {
                $autoResolved[$c] = $historialMap[$c];
            } else {
                $unknownCodes[] = $c;
            }
        }
        $unknownCodes = array_values(array_unique($unknownCodes));

        // Aplicar resoluciones automáticas por historial: actualizar a3_code
        foreach ($autoResolved as $code => $propId) {
            $prop = DB::table('vm_propiedades')->where('id', $propId)->first(['a3_code', 'a3_code_historial']);
            if ($prop && $prop->a3_code !== $code) {
                $partes = $prop->a3_code_historial ? explode(',', $prop->a3_code_historial) : [];
                if ($prop->a3_code && !in_array($prop->a3_code, $partes)) $partes[] = $prop->a3_code;
                $partes = array_values(array_filter($partes, fn($p) => trim($p) !== $code));
                DB::table('vm_propiedades')->where('id', $propId)->update([
                    'a3_code'           => $code,
                    'a3_code_historial' => implode(',', array_filter($partes)),
                ]);
            }
            // Redirigir propCodes al a3_code actualizado para que runImport lo encuentre
            foreach ($propCodes as $idx => $v) {
                if ($v === $code) $propCodes[$idx] = $code;
            }
        }

        if (!empty($unknownCodes)) {
            $sinCodigo = DB::table('vm_propiedades')
                ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                ->select('id', 'nombre', 'a3_code')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'needs_mapping' => true,
                'tmp_id'        => $tmpId,
                'periodo'       => $periodo,
                'unknown_codes' => $unknownCodes,
                'propiedades'   => $sinCodigo,
            ]);
        }

        // Todo mapeado: ejecutar importación
        $result = $this->runImport($rows, $periodo, $propCodes);
        @unlink($filePath);

        return response()->json(array_merge(['ok' => true], $result));
    }

    private function runImport(array $rows, string $periodo, array $propCodes): array
    {
        $deleted = DB::table('vm_pyg_valores')->where('periodo', $periodo)->delete();

        $currentBloque      = ['codigo' => null, 'nombre' => null];
        $currentEpigrafe    = ['codigo' => null, 'nombre' => null];
        $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
        $orden = 0;
        $cuentasNuevas = 0;
        $valoresInsertados = 0;

        for ($i = 7; $i < count($rows); $i++) {
            $row   = $rows[$i];
            $label = (string)($row[0] ?? '');
            if (trim($label) === '') continue;

            if (preg_match('/^\s+([A-Z])\)\s+(.+)$/', $label, $m)) {
                $currentBloque      = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                $currentEpigrafe    = ['codigo' => null, 'nombre' => null];
                $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
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
                $codigo = $m[1];
                $nombre = trim($m[2]);
                $orden++;

                $cuentaId = DB::table('vm_pyg_cuentas')->where('codigo', $codigo)->value('id');
                if (!$cuentaId) {
                    $cuentaId = DB::table('vm_pyg_cuentas')->insertGetId([
                        'codigo'             => $codigo,
                        'nombre'             => $nombre,
                        'bloque_codigo'      => $currentBloque['codigo'],
                        'bloque_nombre'      => $currentBloque['nombre'],
                        'epigrafe_codigo'    => $currentEpigrafe['codigo'],
                        'epigrafe_nombre'    => $currentEpigrafe['nombre'],
                        'subepigrafe_codigo' => $currentSubepigrafe['codigo'],
                        'subepigrafe_nombre' => $currentSubepigrafe['nombre'],
                        'orden'              => $orden,
                        'createdat'          => now(),
                    ]);
                    $cuentasNuevas++;
                } else {
                    DB::table('vm_pyg_cuentas')->where('id', $cuentaId)->update([
                        'nombre'             => $nombre,
                        'bloque_codigo'      => $currentBloque['codigo'],
                        'bloque_nombre'      => $currentBloque['nombre'],
                        'epigrafe_codigo'    => $currentEpigrafe['codigo'],
                        'epigrafe_nombre'    => $currentEpigrafe['nombre'],
                        'subepigrafe_codigo' => $currentSubepigrafe['codigo'],
                        'subepigrafe_nombre' => $currentSubepigrafe['nombre'],
                        'orden'              => $orden,
                    ]);
                }

                foreach ($propCodes as $colIdx => $a3Code) {
                    $val     = $row[$colIdx] ?? null;
                    $importe = ($val === null || $val === '') ? 0.00 : (float)$val;
                    DB::table('vm_pyg_valores')->insert([
                        'periodo'   => $periodo,
                        'id_cuenta' => $cuentaId,
                        'a3_code'   => $a3Code,
                        'importe'   => $importe,
                        'createdat' => now(),
                    ]);
                    $valoresInsertados++;
                }
            }
        }

        return [
            'periodo'    => $periodo,
            'cuentas'    => $cuentasNuevas,
            'valores'    => $valoresInsertados,
            'sustituido' => $deleted > 0,
        ];
    }

    public function deletePeriodo(Request $request, Project $project, string $periodo)
    {
        DB::table('vm_pyg_valores')->where('periodo', $periodo)->delete();
        return response()->json(['ok' => true]);
    }
}
