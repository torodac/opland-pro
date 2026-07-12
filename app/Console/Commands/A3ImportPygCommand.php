<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class A3ImportPygCommand extends Command
{
    protected $signature = 'a3:import-pyg {file : Ruta al fichero Excel (.xlsx)}';
    protected $description = 'Importa un informe mensual de Pérdidas y Ganancias de A3 (Excel)';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Fichero no encontrado: {$filePath}");
            return 1;
        }

        $this->info("Cargando {$filePath}...");
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        // 0-indexed rows and columns
        $rows = $sheet->toArray(null, true, true, false);

        // Row index 3 (4th row): "Período: de DD/MM/YYYY a DD/MM/YYYY"
        $periodoRaw = (string)($rows[3][0] ?? '');
        if (!preg_match('/a\s+(\d{2})\/(\d{2})\/(\d{4})/', $periodoRaw, $m)) {
            $this->error("No se puede extraer el período de: {$periodoRaw}");
            return 1;
        }
        $periodo = "{$m[3]}-{$m[2]}-01";
        $this->info("Período: {$periodo}");

        // Row index 6 (7th row): column headers = a3_codes
        $headerRow = $rows[6] ?? [];
        $propCodes = [];
        foreach ($headerRow as $colIdx => $val) {
            if ($colIdx === 0) continue;
            $v = trim((string)$val);
            if ($v !== '') {
                $propCodes[$colIdx] = $v;
            }
        }
        $this->info("Columnas: " . implode(', ', $propCodes));

        // Delete existing valores for this period to allow re-import
        $deleted = DB::table('vm_pyg_valores')->where('periodo', $periodo)->delete();
        if ($deleted > 0) {
            $this->warn("Eliminados {$deleted} registros previos del período {$periodo}");
        }

        $currentBloque      = ['codigo' => null, 'nombre' => null];
        $currentEpigrafe    = ['codigo' => null, 'nombre' => null];
        $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
        $orden = 0;
        $cuentasNuevas = 0;
        $valoresInsertados = 0;

        $totalRows = count($rows);
        for ($i = 7; $i < $totalRows; $i++) {
            $row   = $rows[$i];
            $label = (string)($row[0] ?? '');
            if (trim($label) === '') continue;

            // BLOQUE: " A) RESULTADO DE EXPLOTACIÓN"
            if (preg_match('/^\s+([A-Z])\)\s+(.+)$/', $label, $m)) {
                $currentBloque      = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                $currentEpigrafe    = ['codigo' => null, 'nombre' => null];
                $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
                continue;
            }

            // EPÍGRAFE: "      1. Importe neto..."
            if (preg_match('/^\s+(\d+)\.\s+(.+)$/', $label, $m)) {
                $currentEpigrafe    = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                $currentSubepigrafe = ['codigo' => null, 'nombre' => null];
                continue;
            }

            // SUBEPÍGRAFE: "      b) Otros ingresos..."
            if (preg_match('/^\s+([a-z])\)\s+(.+)$/', $label, $m)) {
                $currentSubepigrafe = ['codigo' => $m[1], 'nombre' => trim($m[2])];
                continue;
            }

            // CUENTA CONTABLE: "          70500020    INGRESOS ALOJAMIENTO"
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

        $this->info("Cuentas nuevas: {$cuentasNuevas}");
        $this->info("Valores insertados: {$valoresInsertados}");
        $this->info("Importación completada OK.");

        return 0;
    }
}
