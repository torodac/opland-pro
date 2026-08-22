<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Version previa simple de control de invitados por tarjeta (mb): NO controla aforo real
// (no hay entrada/salida ni "cuantos hay dentro ahora"), solo lleva la suma acumulada de
// invitados que han entrado con las tarjetas de cada vivienda. Las tarjetas se renuevan cada
// año (mb_tarjetas.anio) y las del año anterior quedan en desuso (deleted=1 al renovar).
class AforoController extends Controller
{
    // Propietario actual de una vivienda (mismo patron que
    // AsambleaRepartoController::propietarioSubquery(), usando la columna denormalizada
    // "propietario" de mb_propietarios_historico en vez de un join a mb_propietarios).
    private function propietarioActual(int $idVivienda): ?string
    {
        return DB::table('mb_propietarios_historico')
            ->where('id_viviendas', $idVivienda)
            ->where('deleted', 0)
            ->orderByRaw('(fecha_hasta IS NULL) DESC, fecha_desde DESC')
            ->value('propietario');
    }

    // Suma de invitados registrados para TODAS las tarjetas (deleted=0) de la vivienda dada.
    private function totalInvitados(int $idVivienda): int
    {
        return (int) DB::table('mb_invitados_registro as r')
            ->join('mb_tarjetas as t', 't.id', '=', 'r.id_tarjetas')
            ->where('t.id_viviendas', $idVivienda)
            ->where('t.deleted', 0)
            ->where('r.deleted', 0)
            ->sum('r.personas');
    }

    public function index(Request $request, Project $project)
    {
        return view('mb.aforo', [
            'project'    => $project,
            'breadcrumb' => [
                ['label' => 'Gestión MB', 'url' => ''],
            ],
        ]);
    }

    public function tarjeta(Request $request, Project $project)
    {
        $codigo = trim((string) $request->input('codigo', ''));
        if ($codigo === '') {
            return response()->json(['error' => 'Falta el código de tarjeta.'], 422);
        }

        $tarjeta = DB::table('mb_tarjetas as t')
            ->join('mb_viviendas as v', 'v.id', '=', 't.id_viviendas')
            ->where('t.codigo', $codigo)
            ->where('t.deleted', 0)
            ->select(['t.id', 't.id_viviendas', 'v.nombre as vivienda'])
            ->first();

        if (!$tarjeta) {
            return response()->json(['error' => 'Tarjeta no reconocida.'], 404);
        }

        return response()->json([
            'id_tarjetas' => $tarjeta->id,
            'vivienda'    => $tarjeta->vivienda,
            'propietario' => $this->propietarioActual($tarjeta->id_viviendas),
            'total_actual'=> $this->totalInvitados($tarjeta->id_viviendas),
        ]);
    }

    public function registrar(Request $request, Project $project)
    {
        $codigo   = trim((string) $request->input('codigo', ''));
        $personas = (int) $request->input('personas', 0);

        if ($codigo === '') {
            return response()->json(['error' => 'Falta el código de tarjeta.'], 422);
        }
        if ($personas < 1) {
            return response()->json(['error' => 'El número de personas debe ser al menos 1.'], 422);
        }

        $tarjeta = DB::table('mb_tarjetas')
            ->where('codigo', $codigo)
            ->where('deleted', 0)
            ->first(['id', 'id_viviendas']);

        if (!$tarjeta) {
            return response()->json(['error' => 'Tarjeta no reconocida.'], 404);
        }

        $now = now();
        DB::table('mb_invitados_registro')->insert([
            'id_tarjetas' => $tarjeta->id,
            'personas'    => $personas,
            'fecha'       => $now->toDateString(),
            'hora'        => $now->format('H:i:s'),
            'createuser'  => Auth::id(),
            'updateuser'  => Auth::id(),
            'createdat'   => $now,
            'updatedat'   => $now,
        ]);

        return response()->json([
            'ok'           => true,
            'total_actual' => $this->totalInvitados($tarjeta->id_viviendas),
        ]);
    }

    // Pantalla de asignacion: todas las viviendas de un ejercicio (año natural), con su
    // codigo de tarjeta editable al lado (vacio si no tiene ninguna ese año).
    public function asignacion(Request $request, Project $project)
    {
        $anio = (int) ($request->input('anio') ?: now()->format('Y'));
        $q    = trim((string) $request->input('q', ''));

        $viviendas = DB::table('mb_viviendas as v')
            ->leftJoin('mb_tarjetas as t', function ($j) use ($anio) {
                $j->on('t.id_viviendas', '=', 'v.id')
                  ->where('t.anio', $anio)
                  ->where('t.deleted', 0);
            })
            ->where('v.deleted', 0)
            ->when($q !== '', fn ($qq) => $qq->where('v.nombre', 'ilike', '%' . $q . '%'))
            ->orderBy('v.nombre')
            // Si por lo que sea hay mas de una tarjeta esa vivienda/año, nos quedamos con la
            // de menor id (la "principal" para esta pantalla) via distinct+order.
            ->select(['v.id', 'v.nombre', DB::raw('MIN(t.id) as id_tarjeta'), DB::raw('MIN(t.codigo) as codigo')])
            ->groupBy('v.id', 'v.nombre')
            ->paginate(50)
            ->withQueryString();

        return view('mb.tarjetas-asignacion', [
            'project'   => $project,
            'anio'      => $anio,
            'q'         => $q,
            'viviendas' => $viviendas,
            'breadcrumb' => [
                ['label' => 'Asignación de tarjetas', 'url' => ''],
            ],
        ]);
    }

    public function guardarTarjeta(Request $request, Project $project)
    {
        $idVivienda = (int) $request->input('id_viviendas');
        $anio       = (int) $request->input('anio');
        $codigo     = trim((string) $request->input('codigo', ''));

        if (!$idVivienda || !$anio) {
            return response()->json(['error' => 'Faltan datos.'], 422);
        }

        $existente = DB::table('mb_tarjetas')
            ->where('id_viviendas', $idVivienda)
            ->where('anio', $anio)
            ->where('deleted', 0)
            ->orderBy('id')
            ->first();

        $now = now();

        if ($codigo === '') {
            if ($existente) {
                DB::table('mb_tarjetas')->where('id', $existente->id)
                    ->update(['deleted' => 1, 'updateuser' => Auth::id(), 'updatedat' => $now]);
            }
            return response()->json(['ok' => true, 'codigo' => null]);
        }

        // El mismo codigo no puede estar ya asignado a OTRA vivienda ese mismo año.
        $enUso = DB::table('mb_tarjetas')
            ->where('codigo', $codigo)
            ->where('anio', $anio)
            ->where('deleted', 0)
            ->where('id_viviendas', '!=', $idVivienda)
            ->first(['id_viviendas']);

        if ($enUso) {
            return response()->json(['error' => 'Ese código ya está asignado a otra vivienda este año.'], 409);
        }

        if ($existente) {
            DB::table('mb_tarjetas')->where('id', $existente->id)
                ->update(['codigo' => $codigo, 'updateuser' => Auth::id(), 'updatedat' => $now]);
        } else {
            DB::table('mb_tarjetas')->insert([
                'id_viviendas' => $idVivienda,
                'codigo'       => $codigo,
                'anio'         => $anio,
                'hidden'       => 0,
                'deleted'      => 0,
                'createuser'   => Auth::id(),
                'updateuser'   => Auth::id(),
                'createdat'    => $now,
                'updatedat'    => $now,
            ]);
        }

        return response()->json(['ok' => true, 'codigo' => $codigo]);
    }
}
