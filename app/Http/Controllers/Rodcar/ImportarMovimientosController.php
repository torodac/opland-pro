<?php

namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Rodcar\Importadores\KutxaCuentaParser;
use App\Services\Rodcar\Importadores\KutxaTarjetaParser;
use App\Services\Rodcar\Importadores\MediolanumCuentaParser;
use App\Services\Rodcar\Importadores\MediolanumTarjetaParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportarMovimientosController extends Controller
{
    private const PARSERS = [
        'kutxa_cuenta'       => KutxaCuentaParser::class,
        'kutxa_tarjeta'      => KutxaTarjetaParser::class,
        'mediolanum_cuenta'  => MediolanumCuentaParser::class,
        'mediolanum_tarjeta' => MediolanumTarjetaParser::class,
    ];

    private const ES_CUENTA = [
        'kutxa_cuenta'       => true,
        'kutxa_tarjeta'      => false,
        'mediolanum_cuenta'  => true,
        'mediolanum_tarjeta' => false,
    ];

    public function index(Request $request, Project $project)
    {
        $cuentas = DB::table('rodcar_movs_cuenta')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);

        $lotes = DB::table('rodcar_movs_import_lote as l')
            ->leftJoin('rodcar_movs_cuenta as c', 'c.id', '=', 'l.id_movs_cuenta')
            ->orderByDesc('l.createdat')
            ->limit(20)
            ->get(['l.id', 'l.tipo_origen', 'l.nombre_archivo', 'l.total_nuevas', 'l.total_duplicadas', 'l.total_dudosas_importadas', 'l.total_importe', 'l.createdat', 'c.nombre as cuenta_nombre']);

        $huerfanos = DB::table('rodcar_movs_import_lote as l')
            ->whereIn('l.tipo_origen', ['kutxa_tarjeta', 'mediolanum_tarjeta'])
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('rodcar_movs_detalle as d')
                ->whereColumn('d.id_lote', 'l.id')->whereNull('d.id_movs')->where('d.deleted', false))
            ->leftJoin('rodcar_movs_cuenta as c', 'c.id', '=', 'l.id_movs_cuenta')
            ->orderByDesc('l.createdat')
            ->get(['l.id', 'l.nombre_archivo', 'l.createdat', 'c.nombre as cuenta_nombre']);

        return view('rodcar.importar', compact('project', 'cuentas', 'lotes', 'huerfanos'));
    }

    public function subir(Request $request, Project $project)
    {
        $data = $request->validate([
            'tipo'           => 'required|in:kutxa_cuenta,kutxa_tarjeta,mediolanum_cuenta,mediolanum_tarjeta',
            'id_movs_cuenta' => 'required|integer|exists:rodcar_movs_cuenta,id',
            'fichero'        => 'required|file',
        ]);

        $tipo        = $data['tipo'];
        $idCuenta    = (int) $data['id_movs_cuenta'];
        $parserClass = self::PARSERS[$tipo];
        $esCuenta    = self::ES_CUENTA[$tipo];

        $path          = $request->file('fichero')->getRealPath();
        $nombreArchivo = $request->file('fichero')->getClientOriginalName();

        try {
            $resultado = (new $parserClass())->parse($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['fichero' => 'No se pudo leer el fichero: ' . $e->getMessage()]);
        }

        $filas = [];
        foreach ($resultado['hojas'] as $hoja) {
            foreach ($hoja['filas'] as $f) {
                $filas[] = $f;
            }
        }

        if (empty($filas)) {
            return back()->withErrors(['fichero' => 'No se ha detectado ningún movimiento en el fichero.']);
        }

        $nuevos          = [];
        $dudosos         = [];
        $duplicadosCount = 0;

        foreach ($filas as $f) {
            $estado = $esCuenta ? $this->clasificarFilaCuenta($f, $idCuenta) : $this->clasificarFilaTarjeta($f);
            if ($estado === 'duplicado') {
                $duplicadosCount++;
            } elseif ($estado === 'dudoso') {
                $dudosos[] = $f;
            } else {
                $nuevos[] = $f;
            }
        }

        $userId = Auth::id();

        $idLote = DB::table('rodcar_movs_import_lote')->insertGetId([
            'tipo_origen'               => $tipo,
            'id_movs_cuenta'            => $idCuenta,
            'nombre_archivo'            => $nombreArchivo,
            'total_nuevas'              => count($nuevos),
            'total_duplicadas'          => $duplicadosCount,
            'total_dudosas_importadas'  => 0,
            'total_importe'             => round(array_sum(array_column($nuevos, 'importe')), 2),
            'createuser'                => $userId,
            'createdat'                 => now(),
        ]);

        $idsInsertados = $esCuenta
            ? $this->insertarCuenta($nuevos, $idCuenta, $idLote, $userId)
            : $this->insertarDetalle($nuevos, $idLote, $userId);

        $mensajeEnlace = '';
        if (!$esCuenta && !empty($idsInsertados)) {
            $mensajeEnlace = $this->intentarEnlazar($nuevos, $idsInsertados, $idCuenta, $resultado['cargo_conocido'] ?? null);
        }

        if (empty($dudosos)) {
            return redirect()->route('rodcar.importar', $project->slug)->with('success',
                count($nuevos) . " movimientos nuevos importados, {$duplicadosCount} duplicados descartados." . ($mensajeEnlace ? ' ' . $mensajeEnlace : '')
            );
        }

        $token = (string) Str::uuid();
        Cache::put('rodcar_import_dudosos_' . $token, [
            'tipo'           => $tipo,
            'id_movs_cuenta' => $idCuenta,
            'id_lote'        => $idLote,
            'filas'          => $dudosos,
        ], now()->addHours(2));

        return view('rodcar.importar-dudosos', [
            'project' => $project,
            'token'   => $token,
            'dudosos' => $dudosos,
            'resumen' => [
                'nuevos'     => count($nuevos),
                'duplicados' => $duplicadosCount,
                'dudosos'    => count($dudosos),
            ],
            'mensajeEnlace' => $mensajeEnlace,
        ]);
    }

    public function confirmarDudosos(Request $request, Project $project)
    {
        $data = $request->validate([
            'token'     => 'required|string',
            'indices'   => 'array',
            'indices.*' => 'integer',
        ]);

        $cached = Cache::pull('rodcar_import_dudosos_' . $data['token']);
        abort_unless($cached, 404);

        $indices   = $data['indices'] ?? [];
        $seleccion = array_values(array_intersect_key($cached['filas'], array_flip($indices)));

        $userId   = Auth::id();
        $esCuenta = self::ES_CUENTA[$cached['tipo']];

        $idsInsertados = [];
        if (!empty($seleccion)) {
            $idsInsertados = $esCuenta
                ? $this->insertarCuenta($seleccion, $cached['id_movs_cuenta'], $cached['id_lote'], $userId)
                : $this->insertarDetalle($seleccion, $cached['id_lote'], $userId);

            DB::table('rodcar_movs_import_lote')->where('id', $cached['id_lote'])->update([
                'total_dudosas_importadas' => count($seleccion),
                'total_importe'            => DB::raw('total_importe + (' . round(array_sum(array_column($seleccion, 'importe')), 2) . ')'),
            ]);
        }

        $mensajeEnlace = '';
        if (!$esCuenta && !empty($idsInsertados)) {
            $mensajeEnlace = $this->intentarEnlazar($seleccion, $idsInsertados, $cached['id_movs_cuenta'], null);
        }

        return redirect()->route('rodcar.importar', $project->slug)->with('success',
            count($seleccion) . ' movimientos dudosos importados tras revisión.' . ($mensajeEnlace ? ' ' . $mensajeEnlace : '')
        );
    }

    public function huerfanosLote(Request $request, Project $project, int $lote)
    {
        $loteRow = DB::table('rodcar_movs_import_lote')->where('id', $lote)->first();
        abort_unless($loteRow, 404);

        $huerfanos = DB::table('rodcar_movs_detalle')
            ->where('id_lote', $lote)->whereNull('id_movs')->where('deleted', false)
            ->orderBy('fecha_operacion')
            ->get(['id', 'fecha_operacion', 'nombre', 'importe']);

        $candidatos = DB::table('rodcar_movs')
            ->where('id_movs_cuenta', $loteRow->id_movs_cuenta)->where('deleted', false)
            ->orderByDesc('fecha_operacion')
            ->limit(150)
            ->get(['id', 'fecha_operacion', 'nombre', 'importe']);

        return view('rodcar.importar-huerfanos', compact('project', 'loteRow', 'huerfanos', 'candidatos'));
    }

    public function vincular(Request $request, Project $project, int $lote)
    {
        $data = $request->validate(['id_movs' => 'required|integer|exists:rodcar_movs,id']);

        $updated = DB::table('rodcar_movs_detalle')
            ->where('id_lote', $lote)->whereNull('id_movs')->where('deleted', false)
            ->update(['id_movs' => $data['id_movs'], 'updateuser' => Auth::id(), 'updatedat' => now()]);

        return redirect()->route('rodcar.importar', $project->slug)->with('success', "{$updated} movimientos enlazados correctamente.");
    }

    private function clasificarFilaCuenta(array $f, int $idCuenta): string
    {
        $existentes = DB::table('rodcar_movs')
            ->where('id_movs_cuenta', $idCuenta)
            ->where('fecha_operacion', $f['fecha'])
            ->whereBetween('importe', [$f['importe'] - 0.005, $f['importe'] + 0.005])
            ->where('deleted', false)
            ->pluck('nombre');

        return $this->clasificarPorConcepto($existentes, $f['concepto']);
    }

    private function clasificarFilaTarjeta(array $f): string
    {
        $existentes = DB::table('rodcar_movs_detalle')
            ->where('fecha_operacion', $f['fecha'])
            ->whereBetween('importe', [$f['importe'] - 0.005, $f['importe'] + 0.005])
            ->where('deleted', false)
            ->pluck('nombre');

        return $this->clasificarPorConcepto($existentes, $f['concepto']);
    }

    private function clasificarPorConcepto($existentes, string $concepto): string
    {
        if ($existentes->isEmpty()) {
            return 'nuevo';
        }
        foreach ($existentes as $e) {
            if (mb_strtolower(trim($e)) === mb_strtolower(trim($concepto))) {
                return 'duplicado';
            }
        }
        return 'dudoso';
    }

    private function insertarCuenta(array $filas, int $idCuenta, int $idLote, ?int $userId): array
    {
        $anyoMap = DB::table('rodcar_movs_anyo')->pluck('id', 'nombre');
        $ids = [];
        foreach ($filas as $f) {
            $anio = date('Y', strtotime($f['fecha']));
            $ids[] = DB::table('rodcar_movs')->insertGetId([
                'fecha_operacion' => $f['fecha'],
                'nombre'          => $f['concepto'],
                'nombre_banco'    => $f['concepto'],
                'saldo'           => $f['saldo'],
                'importe'         => $f['importe'],
                'i_g'             => $f['importe'] >= 0 ? 'I' : 'G',
                'fecha_calculo'   => $f['fecha'],
                'id_movs_cuenta'  => $idCuenta,
                'id_movs_mes'     => (int) date('n', strtotime($f['fecha'])),
                'id_movs_anyo'    => $anyoMap[$anio] ?? null,
                'id_lote'         => $idLote,
                'createuser'      => $userId,
                'createdat'       => now(),
                'updatedat'       => now(),
                'hidden'          => 0,
                'deleted'         => false,
            ]);
        }
        return $ids;
    }

    private function insertarDetalle(array $filas, int $idLote, ?int $userId): array
    {
        $ids = [];
        foreach ($filas as $f) {
            $ids[] = DB::table('rodcar_movs_detalle')->insertGetId([
                'fecha_operacion' => $f['fecha'],
                'nombre'          => $f['concepto'],
                'nombre_banco'    => $f['concepto'],
                'importe'         => $f['importe'],
                'i_g'             => $f['importe'] >= 0 ? 'I' : 'G',
                'id_movs'         => null,
                'id_lote'         => $idLote,
                'createuser'      => $userId,
                'createdat'       => now(),
                'updatedat'       => now(),
                'hidden'          => 0,
                'deleted'         => false,
            ]);
        }
        return $ids;
    }

    // Intenta enlazar automáticamente el lote de detalle de tarjeta con la fila de cargo en rodcar_movs.
    // Si el PDF trae fecha/importe de cargo exactos (Mediolanum) los usa; si no (Kutxa), busca por
    // importe = -suma de las filas importadas dentro de la misma cuenta. Solo enlaza si hay un único candidato.
    private function intentarEnlazar(array $filasImportadas, array $idsDetalle, int $idCuenta, ?array $cargoConocido): string
    {
        if (empty($idsDetalle)) {
            return '';
        }

        if ($cargoConocido) {
            $candidatos = DB::table('rodcar_movs')
                ->where('id_movs_cuenta', $idCuenta)->where('deleted', false)
                ->where('fecha_operacion', $cargoConocido['fecha'])
                ->whereBetween('importe', [-$cargoConocido['importe_total'] - 0.01, -$cargoConocido['importe_total'] + 0.01])
                ->pluck('id');
        } else {
            $suma = round(array_sum(array_column($filasImportadas, 'importe')), 2);
            $candidatos = DB::table('rodcar_movs')
                ->where('id_movs_cuenta', $idCuenta)->where('deleted', false)
                ->whereBetween('importe', [-$suma - 0.01, -$suma + 0.01])
                ->pluck('id');
        }

        if ($candidatos->count() !== 1) {
            return $candidatos->count() > 1
                ? 'No se pudo enlazar automáticamente con la cuenta (varios cargos candidatos) — quedan pendientes de vincular manualmente.'
                : 'No se ha encontrado el cargo de la tarjeta en la cuenta todavía — quedan pendientes de vincular manualmente.';
        }

        DB::table('rodcar_movs_detalle')->whereIn('id', $idsDetalle)->update(['id_movs' => $candidatos->first()]);

        return 'Enlazados automáticamente con el cargo de la cuenta.';
    }
}
