<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Importador de extractos bancarios (.xls) de mb: registra cada movimiento con su cuenta y una
// clasificación instantánea por reglas de texto (mb_movs_mapeo), sin IA -- a diferencia del
// clasificador de Rodcar (movimientos:clasificar), que sí usa similitud + IA de respaldo.
class MovimientosBancariosController extends Controller
{
    public function index(Project $project)
    {
        $cuentas = DB::table('mb_movs_cuenta')->where('deleted', false)->orderBy('nombre')->get();
        $lotes = DB::table('mb_movs_import_lote as l')
            ->join('mb_movs_cuenta as c', 'c.id', '=', 'l.id_movs_cuenta')
            ->orderByDesc('l.fecha_importacion')
            ->limit(20)
            ->get(['l.*', 'c.nombre as cuenta_nombre']);

        return view('mb.movimientos-import', [
            'project' => $project,
            'cuentas' => $cuentas,
            'lotes'   => $lotes,
            'breadcrumb' => [
                ['label' => 'Movimientos bancarios', 'url' => ''],
            ],
        ]);
    }

    public function import(Request $request, Project $project)
    {
        $request->validate(['file' => 'required|file']);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if (!isset($rows[2][0]) || trim((string) $rows[2][0]) !== 'Fecha') {
            return response()->json(['error' => 'El formato del fichero no es el esperado (cabecera "Fecha" no encontrada en la fila 3).'], 422);
        }

        $cuenta = $this->resolverCuenta($request->input('id_movs_cuenta'), (string) ($rows[0][0] ?? ''));
        if (!$cuenta) {
            return response()->json(['error' => 'No se ha podido determinar la cuenta del extracto. Selecciónala manualmente.'], 422);
        }

        $reglas = DB::table('mb_movs_mapeo')
            ->where('deleted', false)
            ->orderBy('orden')
            ->get(['id', 'patron', 'tipo_match', 'id_gastos_cuentas']);

        $importadas = 0;
        $duplicadas = 0;
        $sinClasificar = 0;
        $now = now();
        $nombreFichero = $request->file('file')->getClientOriginalName();

        DB::transaction(function () use ($rows, $cuenta, $reglas, $nombreFichero, &$importadas, &$duplicadas, &$sinClasificar, $now) {
            $loteId = DB::table('mb_movs_import_lote')->insertGetId([
                'id_movs_cuenta' => $cuenta->id,
                'fichero'        => $nombreFichero,
                'fecha_importacion' => $now,
                'filas_importadas'  => 0,
                'filas_duplicadas'  => 0,
                'createuser'        => Auth::id(),
                'createdat'         => $now,
            ]);

            for ($i = 3; $i < count($rows); $i++) {
                $row = $rows[$i];
                $fecha = $this->parseFecha($row[0] ?? null);
                if (!$fecha) continue;

                $fechaValor = $this->parseFecha($row[1] ?? null);
                $movimiento = trim((string) ($row[2] ?? ''));
                $masDatos   = trim((string) ($row[3] ?? '')) ?: null;
                $importe    = is_numeric($row[4] ?? null) ? (float) $row[4] : null;
                $saldo      = is_numeric($row[5] ?? null) ? (float) $row[5] : null;
                if ($movimiento === '' || $importe === null) continue;

                $yaExiste = DB::table('mb_movs_bancarios')
                    ->where('id_movs_cuenta', $cuenta->id)
                    ->where('fecha', $fecha)
                    ->where('movimiento', $movimiento)
                    ->where(fn($q) => $masDatos === null ? $q->whereNull('mas_datos') : $q->where('mas_datos', $masDatos))
                    ->where('importe', $importe)
                    ->where(fn($q) => $saldo === null ? $q->whereNull('saldo') : $q->where('saldo', $saldo))
                    ->exists();
                if ($yaExiste) {
                    $duplicadas++;
                    continue;
                }

                $regla = $this->matchRegla($movimiento, $reglas);
                if (!$regla) $sinClasificar++;

                DB::table('mb_movs_bancarios')->insert([
                    'id_movs_cuenta'    => $cuenta->id,
                    'id_lote'           => $loteId,
                    'fecha'             => $fecha,
                    'fecha_valor'       => $fechaValor,
                    'movimiento'        => $movimiento,
                    'mas_datos'         => $masDatos,
                    'importe'           => $importe,
                    'saldo'             => $saldo,
                    'id_movs_mapeo'     => $regla->id ?? null,
                    'id_gastos_cuentas' => $regla->id_gastos_cuentas ?? null,
                    'createuser'        => Auth::id(),
                    'createdat'         => $now,
                    'updatedat'         => $now,
                ]);
                $importadas++;
            }

            DB::table('mb_movs_import_lote')->where('id', $loteId)->update([
                'filas_importadas' => $importadas,
                'filas_duplicadas' => $duplicadas,
            ]);
        });

        return response()->json([
            'ok' => true,
            'cuenta' => $cuenta->nombre,
            'importadas' => $importadas,
            'duplicadas' => $duplicadas,
            'sin_clasificar' => $sinClasificar,
        ]);
    }

    private function resolverCuenta(?string $idManual, string $tituloFichero): ?object
    {
        if ($idManual) {
            return DB::table('mb_movs_cuenta')->where('id', $idManual)->where('deleted', false)->first();
        }

        $soloDigitos = preg_replace('/\D/', '', $tituloFichero);
        return DB::table('mb_movs_cuenta')
            ->where('deleted', false)
            ->whereNotNull('numero_cuenta')
            ->get()
            ->first(fn($c) => $soloDigitos !== '' && str_contains($soloDigitos, $c->numero_cuenta));
    }

    // Primera regla (por orden) que coincide con el texto del movimiento.
    // Botón "Ejecutar" de /mb/movs_mapeo: recalcula la clasificación de TODOS los movimientos ya
    // importados con las reglas actuales. Vista previa (sin escribir nada) y aplicación reutilizan
    // el mismo cálculo -- es determinista y barato, no hace falta guardar un estado intermedio
    // entre previsualizar y confirmar.
    public function previsualizarClasificacion(Project $project)
    {
        $resultado = $this->recalcularClasificacion($project, aplicar: false);
        return response()->json(['ok' => true] + $resultado);
    }

    public function aplicarClasificacion(Project $project)
    {
        $resultado = $this->recalcularClasificacion($project, aplicar: true);
        return response()->json(['ok' => true] + $resultado);
    }

    private function recalcularClasificacion(Project $project, bool $aplicar): array
    {
        $reglas = DB::table('mb_movs_mapeo')
            ->where('deleted', false)
            ->orderBy('orden')
            ->get(['id', 'patron', 'tipo_match', 'id_gastos_cuentas']);

        $movimientos = DB::table('mb_movs_bancarios')
            ->where('deleted', false)
            ->get(['id', 'movimiento', 'id_movs_mapeo', 'id_gastos_cuentas']);

        $aClasificar   = 0; // no tenían categoría y ahora sí
        $aReclasificar = 0; // ya tenían categoría y cambia
        $sinCambios    = 0;

        foreach ($movimientos as $mov) {
            $regla = $this->matchRegla($mov->movimiento, $reglas);
            $nuevoIdMapeo  = $regla->id ?? null;
            $nuevoIdCuenta = $regla->id_gastos_cuentas ?? null;

            if ($nuevoIdCuenta === $mov->id_gastos_cuentas) {
                $sinCambios++;
                continue;
            }

            if ($mov->id_gastos_cuentas === null) {
                $aClasificar++;
            } else {
                $aReclasificar++;
            }

            if ($aplicar) {
                DB::table('mb_movs_bancarios')->where('id', $mov->id)->update([
                    'id_movs_mapeo'     => $nuevoIdMapeo,
                    'id_gastos_cuentas' => $nuevoIdCuenta,
                    'updatedat'         => now(),
                ]);
            }
        }

        return [
            'total'          => $movimientos->count(),
            'a_clasificar'   => $aClasificar,
            'a_reclasificar' => $aReclasificar,
            'sin_cambios'    => $sinCambios,
        ];
    }

    // Siempre sensible a mayusculas (decision del cliente: no tiene sentido que no lo sea, p.ej.
    // "WEB" al principio del movimiento es un patron consistente en mayusculas en los extractos
    // reales, no se necesita variante insensible).
    private function matchRegla(string $movimiento, \Illuminate\Support\Collection $reglas): ?object
    {
        foreach ($reglas as $regla) {
            $match = match ($regla->tipo_match) {
                'comienza' => str_starts_with($movimiento, $regla->patron),
                'es_igual' => $movimiento === $regla->patron,
                default    => str_contains($movimiento, $regla->patron), // 'contiene'
            };

            if ($match) return $regla;
        }
        return null;
    }

    private function parseFecha($valor): ?string
    {
        if ($valor === null || $valor === '') return null;
        try {
            if ($valor instanceof \DateTimeInterface) {
                return \Illuminate\Support\Carbon::instance($valor)->toDateString();
            }
            if (is_numeric($valor)) {
                return \Illuminate\Support\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor))->toDateString();
            }
            return \Illuminate\Support\Carbon::parse((string) $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
