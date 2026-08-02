<?php

namespace App\Http\Controllers\Rodcar;

use App\Http\Controllers\Controller;
use App\Jobs\ClasificarMovimientosJob;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidacionMovimientosController extends Controller
{
    public function index(Request $request, Project $project)
    {
        // La clasificación automática con IA solo procesa 2026 en adelante (ver
        // ClasificarMovimientosCommand::FECHA_MINIMA), así que el contador refleja lo mismo.
        $sinClasificar = DB::table('rodcar_movs')->whereNull('estado_clasificacion')->where('deleted', false)->where('fecha_operacion', '>=', '2026-01-01')->count()
            + DB::table('rodcar_movs_detalle')->whereNull('estado_clasificacion')->where('deleted', false)->where('fecha_operacion', '>=', '2026-01-01')->count();

        $q             = trim((string) $request->input('q', ''));
        $fase          = (string) $request->input('fase', '');
        $confianzaMin  = (string) $request->input('confianza_min', '');
        $cuenta        = (string) $request->input('cuenta', '');
        $anyo          = (string) $request->input('anyo', '');
        $mes           = (string) $request->input('mes', '');

        // Primera visita sin ningún filtro en la URL: por defecto, el último año/mes con
        // movimientos pendientes (para no renderizar de golpe todo el histórico, que es lento).
        // Si el usuario ya ha enviado el formulario (aunque sea eligiendo "todos"), se respeta su elección.
        $sinFiltrosEnUrl = !$request->has('q') && !$request->has('fase') && !$request->has('confianza_min')
            && !$request->has('cuenta') && !$request->has('anyo') && !$request->has('mes');

        if ($sinFiltrosEnUrl) {
            $ultimo = DB::table('rodcar_movs')
                ->whereIn('estado_clasificacion', ['pendiente_validacion', 'clasificado_ia_alta_confianza'])
                ->where('deleted', false)
                ->orderByDesc('fecha_operacion')
                ->first(['id_movs_anyo', 'id_movs_mes']);
            if ($ultimo) {
                $anyo = (string) $ultimo->id_movs_anyo;
                $mes  = (string) $ultimo->id_movs_mes;
            }
        }

        // Movimientos (rodcar_movs): pasan por el clasificador automático (fases 1-3), así que
        // solo están "pendientes" los que ese pipeline ha dejado en pendiente_validacion/
        // clasificado_ia_alta_confianza sin tipo1 confirmado todavía.
        $pendientesMovs = DB::table('rodcar_movs as m')
            ->leftJoin('rodcar_movs_cuenta as c', 'c.id', '=', 'm.id_movs_cuenta')
            ->whereIn('m.estado_clasificacion', ['pendiente_validacion', 'clasificado_ia_alta_confianza'])
            ->where('m.deleted', false)
            // Si ya tiene tipo1 confirmado (p.ej. por una actualización masiva desde el listado),
            // ya no está pendiente de validar aunque el estado_clasificacion no se haya actualizado.
            ->whereNull('m.id_movs_tipo1')
            ->when($q !== '', fn ($qq) => $qq->where('m.nombre', 'ilike', '%' . $q . '%'))
            ->when($fase !== '', fn ($qq) => $qq->where('m.fase_clasificacion', (int) $fase))
            ->when($confianzaMin !== '', fn ($qq) => $qq->where('m.confianza_ia', '>=', (int) $confianzaMin))
            ->when($cuenta !== '', fn ($qq) => $qq->where('m.id_movs_cuenta', (int) $cuenta))
            ->when($anyo !== '', fn ($qq) => $qq->where('m.id_movs_anyo', (int) $anyo))
            ->when($mes !== '', fn ($qq) => $qq->where('m.id_movs_mes', (int) $mes))
            ->select([
                DB::raw("'movs' as origen"), 'm.id', 'm.fecha_operacion', 'm.nombre', 'm.importe',
                'm.fase_clasificacion', 'm.confianza_ia', 'm.justificacion_ia',
                'm.id_movs_tipo1_propuesto', 'm.id_movs_tipo2_propuesto',
                'c.nombre as cuenta_nombre',
            ]);

        // Detalle (rodcar_movs_detalle): líneas de tarjeta (cargo agregado -> partidas). No pasan
        // por el clasificador automático todavía, así que "pendiente" es simplemente que no tengan
        // tipo1 asignado. Heredan cuenta/año/mes de su movimiento padre cuando están vinculadas
        // (las huérfanas, sin padre, quedan sin cuenta hasta que se enlacen desde el importador).
        $pendientesDetalle = DB::table('rodcar_movs_detalle as d')
            ->leftJoin('rodcar_movs as pm', 'pm.id', '=', 'd.id_movs')
            ->leftJoin('rodcar_movs_cuenta as c', 'c.id', '=', 'pm.id_movs_cuenta')
            ->where('d.deleted', false)
            ->whereNull('d.id_movs_tipo1')
            ->when($q !== '', fn ($qq) => $qq->where('d.nombre', 'ilike', '%' . $q . '%'))
            ->when($fase !== '', fn ($qq) => $qq->where('d.fase_clasificacion', (int) $fase))
            ->when($confianzaMin !== '', fn ($qq) => $qq->where('d.confianza_ia', '>=', (int) $confianzaMin))
            ->when($cuenta !== '', fn ($qq) => $qq->where('pm.id_movs_cuenta', (int) $cuenta))
            ->when($anyo !== '', fn ($qq) => $qq->where('pm.id_movs_anyo', (int) $anyo))
            ->when($mes !== '', fn ($qq) => $qq->where('pm.id_movs_mes', (int) $mes))
            ->select([
                DB::raw("'detalle' as origen"), 'd.id', 'd.fecha_operacion', 'd.nombre', 'd.importe',
                'd.fase_clasificacion', 'd.confianza_ia', 'd.justificacion_ia',
                'd.id_movs_tipo1_propuesto', 'd.id_movs_tipo2_propuesto',
                'c.nombre as cuenta_nombre',
            ]);

        $pendientes = $pendientesMovs->unionAll($pendientesDetalle)->orderByDesc('fecha_operacion')->get();

        $tipos1  = DB::table('rodcar_movs_tipo1')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);
        $tipos2  = DB::table('rodcar_movs_tipo2')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);
        $cuentas = DB::table('rodcar_movs_cuenta')->where('deleted', false)->orderBy('nombre')->get(['id', 'nombre']);
        $anyos   = DB::table('rodcar_movs_anyo')->where('deleted', false)->orderByDesc('nombre')->get(['id', 'nombre']);
        $meses   = DB::table('rodcar_movs_mes')->where('deleted', false)->orderBy('id')->get(['id', 'nombre']);

        return view('rodcar.validacion', compact(
            'project', 'pendientes', 'tipos1', 'tipos2', 'cuentas', 'anyos', 'meses',
            'sinClasificar', 'q', 'fase', 'confianzaMin', 'cuenta', 'anyo', 'mes', 'sinFiltrosEnUrl'
        ));
    }

    public function clasificar(Request $request, Project $project)
    {
        ClasificarMovimientosJob::dispatch();

        return response()->json(['ok' => true]);
    }

    // $origen distingue entre un movimiento de cuenta (rodcar_movs) y una línea de detalle de
    // tarjeta (rodcar_movs_detalle) -- comparten exactamente las mismas columnas de clasificación,
    // pero son tablas y secuencias de ID independientes, así que hace falta saber en cuál operar.
    public function validar(Request $request, Project $project, string $origen, int $id)
    {
        abort_unless(in_array($origen, ['movs', 'detalle'], true), 404);

        $data = $request->validate([
            'id_movs_tipo1' => 'required|integer|exists:rodcar_movs_tipo1,id',
            'id_movs_tipo2' => 'nullable|integer|exists:rodcar_movs_tipo2,id',
            'crear_mapeo'   => 'nullable|boolean',
        ]);

        $tabla      = $origen === 'movs' ? 'rodcar_movs' : 'rodcar_movs_detalle';
        $columnaLog = $origen === 'movs' ? 'id_movs' : 'id_movs_detalle';

        $fila = DB::table($tabla)->where('id', $id)->first();
        abort_unless($fila, 404);

        DB::table($tabla)->where('id', $id)->update([
            'id_movs_tipo1'        => $data['id_movs_tipo1'],
            'id_movs_tipo2'        => $data['id_movs_tipo2'] ?? null,
            'estado_clasificacion' => 'validado_manual',
            'fase_clasificacion'   => 4,
            'updatedat'            => now(),
            'updateuser'           => auth()->id(),
        ]);

        DB::table('rodcar_movs_clasificacion_log')->insert([
            $columnaLog => $id, 'fase' => 4,
            'id_movs_tipo1' => $data['id_movs_tipo1'], 'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
            'confianza' => 100, 'justificacion' => 'Validado manualmente.',
            'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
        ]);

        // Mapeo: solo se crea/actualiza si el usuario lo pide explícitamente (botón "Confirmar + mapear"),
        // para que la Fase 1 solo reconozca de forma automática los conceptos que se han marcado como
        // reutilizables, no cualquier confirmación puntual.
        // ("nombre_normalizado" es una columna generada, no se puede escribir directamente.)
        $extraClasificados = 0;

        if ($request->boolean('crear_mapeo')) {
            $normalizado  = mb_strtoupper(trim($fila->nombre));
            $mapeoExiste = DB::table('rodcar_movs_mapeo')->where('nombre_normalizado', $normalizado)->value('id');

            if ($mapeoExiste) {
                DB::table('rodcar_movs_mapeo')->where('id', $mapeoExiste)->update([
                    'id_movs_tipo1' => $data['id_movs_tipo1'],
                    'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                    'updateuser'    => auth()->id(),
                    'updatedat'     => now(),
                ]);
            } else {
                DB::table('rodcar_movs_mapeo')->insert([
                    'nombre'        => $fila->nombre,
                    'id_movs_tipo1' => $data['id_movs_tipo1'],
                    'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                    'createuser'    => auth()->id(),
                    'createdat'     => now(),
                    'updatedat'     => now(),
                ]);
            }

            // Aplica de inmediato el mapeo a cualquier otro pendiente con el mismo concepto exacto,
            // en AMBAS tablas (movs y detalle comparten el mismo catálogo de mapeos por nombre
            // normalizado -- un concepto como "MERCADONA" es el mismo tanto si llega como movimiento
            // de cuenta como si llega como línea de detalle de tarjeta).
            $idsOtrosMovs = DB::table('rodcar_movs')
                ->when($origen === 'movs', fn ($qq) => $qq->where('id', '!=', $id))
                ->whereRaw('upper(trim(nombre)) = ?', [$normalizado])
                ->whereIn('estado_clasificacion', ['pendiente_validacion', 'clasificado_ia_alta_confianza'])
                ->whereNull('id_movs_tipo1')
                ->where('deleted', false)
                ->pluck('id');

            $idsOtrosDetalle = DB::table('rodcar_movs_detalle')
                ->when($origen === 'detalle', fn ($qq) => $qq->where('id', '!=', $id))
                ->whereRaw('upper(trim(nombre)) = ?', [$normalizado])
                ->whereNull('id_movs_tipo1')
                ->where('deleted', false)
                ->pluck('id');

            if ($idsOtrosMovs->isNotEmpty()) {
                DB::table('rodcar_movs')->whereIn('id', $idsOtrosMovs)->update([
                    'id_movs_tipo1'        => $data['id_movs_tipo1'],
                    'id_movs_tipo2'        => $data['id_movs_tipo2'] ?? null,
                    'estado_clasificacion' => 'validado_manual',
                    'fase_clasificacion'   => 4,
                    'updatedat'            => now(),
                    'updateuser'           => auth()->id(),
                ]);

                DB::table('rodcar_movs_clasificacion_log')->insert($idsOtrosMovs->map(fn ($otroId) => [
                    'id_movs' => $otroId, 'fase' => 4,
                    'id_movs_tipo1' => $data['id_movs_tipo1'], 'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                    'confianza' => 100, 'justificacion' => "Aplicado automáticamente desde el mapeo (mismo concepto que {$origen} #{$id}).",
                    'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
                ])->all());
            }

            if ($idsOtrosDetalle->isNotEmpty()) {
                DB::table('rodcar_movs_detalle')->whereIn('id', $idsOtrosDetalle)->update([
                    'id_movs_tipo1'        => $data['id_movs_tipo1'],
                    'id_movs_tipo2'        => $data['id_movs_tipo2'] ?? null,
                    'estado_clasificacion' => 'validado_manual',
                    'fase_clasificacion'   => 4,
                    'updatedat'            => now(),
                    'updateuser'           => auth()->id(),
                ]);

                DB::table('rodcar_movs_clasificacion_log')->insert($idsOtrosDetalle->map(fn ($otroId) => [
                    'id_movs_detalle' => $otroId, 'fase' => 4,
                    'id_movs_tipo1' => $data['id_movs_tipo1'], 'id_movs_tipo2' => $data['id_movs_tipo2'] ?? null,
                    'confianza' => 100, 'justificacion' => "Aplicado automáticamente desde el mapeo (mismo concepto que {$origen} #{$id}).",
                    'createuser' => auth()->id(), 'createdat' => now(), 'updatedat' => now(),
                ])->all());
            }

            $extraClasificados = $idsOtrosMovs->count() + $idsOtrosDetalle->count();
        }

        return response()->json(['ok' => true, 'extra_clasificados' => $extraClasificados]);
    }
}
