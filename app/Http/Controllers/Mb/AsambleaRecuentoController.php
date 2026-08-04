<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AsambleaRecuentoController extends Controller
{
    private function tallies(int $idAsamblea): array
    {
        $rows = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->select('numero_pregunta', 'voto', DB::raw('count(*) as n'))
            ->groupBy('numero_pregunta', 'voto')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->numero_pregunta][$r->voto] = (int) $r->n;
        }

        return $out;
    }

    public function index(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get(['numero_pregunta', 'texto']);

        $totalHojas = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->count();

        return view('mb.asamblea-recuento', [
            'project'    => $project,
            'asamblea'   => $asamblea,
            'preguntas'  => $preguntas,
            'totalHojas' => $totalHojas,
            'tallies'    => $this->tallies($asamblea->id),
        ]);
    }

    public function backoffice(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get(['numero_pregunta', 'texto']);

        $totalHojas = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->count();

        return view('mb.asamblea-recuento-backoffice', [
            'project'    => $project,
            'asamblea'   => $asamblea,
            'preguntas'  => $preguntas,
            'totalHojas' => $totalHojas,
            'tallies'    => $this->tallies($asamblea->id),
            'breadcrumb' => [
                ['label' => 'Asambleas', 'url' => route('listado', [$project->slug, 'asambleas'])],
                ['label' => 'Recuento en directo', 'url' => ''],
            ],
        ]);
    }

    public function listado(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get(['numero_pregunta', 'texto']);

        $hojas = DB::table('mb_asambleas_hojas as ah')
            ->join('mb_viviendas as v', 'v.id', '=', 'ah.id_viviendas')
            ->where('ah.id_asambleas', $asamblea->id)
            ->where('ah.deleted', 0)
            ->orderBy('ah.numero_hoja')
            ->get(['ah.numero_hoja', 'v.nombre']);

        $votos = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $asamblea->id)
            ->get(['numero_hoja', 'numero_pregunta', 'voto']);

        $votosPorHoja = [];
        foreach ($votos as $v) {
            $votosPorHoja[$v->numero_hoja][$v->numero_pregunta] = $v->voto;
        }

        return view('mb.asamblea-recuento-listado', [
            'project'      => $project,
            'asamblea'     => $asamblea,
            'preguntas'    => $preguntas,
            'hojas'        => $hojas,
            'votosPorHoja' => $votosPorHoja,
            'breadcrumb'   => [
                ['label' => 'Asambleas', 'url' => route('listado', [$project->slug, 'asambleas'])],
                ['label' => 'Listado de votos', 'url' => ''],
            ],
        ]);
    }

    public function eliminarVoto(Request $request, Project $project)
    {
        if (empty($request->id_asamblea) || !$request->filled('numero_hoja') || !$request->filled('numero_pregunta')) {
            return response()->json(['error' => 'Faltan datos.'], 422);
        }

        $borrados = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', (int) $request->id_asamblea)
            ->where('numero_hoja', (int) $request->numero_hoja)
            ->where('numero_pregunta', (int) $request->numero_pregunta)
            ->delete();

        if (!$borrados) {
            return response()->json(['error' => 'No se encontró ese voto.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function tallyRefresh(Request $request, Project $project)
    {
        $idAsamblea = (int) $request->id_asamblea;

        return response()->json(['tallies' => $this->tallies($idAsamblea)]);
    }

    public function registrarVoto(Request $request, Project $project)
    {
        $texto = trim((string) $request->input('texto', ''));

        // Formato asumido a partir del texto visible junto a cada QR en el PDF de hojas de
        // voto: "Hoja de voto 001 1 N" / "...1 S" — pendiente de confirmar con un escaneo real.
        if (!preg_match('/hoja\s*(?:de\s*voto)?\s*(\d+)\D+(\d+)\D*([NS])\b/i', $texto, $m)) {
            return response()->json(['error' => 'No se ha reconocido el formato del QR.', 'texto' => $texto], 422);
        }

        $idAsamblea     = (int) $request->id_asamblea;
        $numeroHoja     = (int) $m[1];
        $numeroPregunta = (int) $m[2];
        $voto           = strtoupper($m[3]);

        try {
            DB::table('mb_asambleas_votos')->insert([
                'id_asambleas'    => $idAsamblea,
                'numero_hoja'     => $numeroHoja,
                'numero_pregunta' => $numeroPregunta,
                'voto'            => $voto,
                'fecha'           => now(),
                'createuser'      => Auth::id(),
                'createdat'       => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23505') { // unique_violation (Postgres)
                return response()->json([
                    'ok' => false, 'duplicado' => true,
                    'numero_hoja' => $numeroHoja, 'numero_pregunta' => $numeroPregunta, 'voto' => $voto,
                ]);
            }
            throw $e;
        }

        return response()->json([
            'ok' => true,
            'numero_hoja' => $numeroHoja, 'numero_pregunta' => $numeroPregunta, 'voto' => $voto,
            'tallies' => $this->tallies($idAsamblea),
        ]);
    }
}
