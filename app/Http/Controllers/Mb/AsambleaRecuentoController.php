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

    // Estado completo de la pantalla de recuento: stats, tarjetas por pregunta y el listado
    // hoja x pregunta ordenado por última actividad (para que la hoja que se está contando
    // ahora mismo quede siempre arriba).
    private function estado(int $idAsamblea): array
    {
        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $idAsamblea)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get(['numero_pregunta', 'texto']);

        $totalHojas = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $idAsamblea)
            ->where('deleted', 0)
            ->count();

        $hojasRecontadas = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->distinct()
            ->count('numero_hoja');

        $ultimaActividad = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->select('numero_hoja', DB::raw('MAX(fecha) as ultima'))
            ->groupBy('numero_hoja')
            ->pluck('ultima', 'numero_hoja');

        $hojas = DB::table('mb_asambleas_hojas as ah')
            ->join('mb_viviendas as v', 'v.id', '=', 'ah.id_viviendas')
            ->where('ah.id_asambleas', $idAsamblea)
            ->where('ah.deleted', 0)
            ->get(['ah.numero_hoja', 'v.nombre'])
            ->map(function ($h) use ($ultimaActividad) {
                $h->ultima_actividad = $ultimaActividad[$h->numero_hoja] ?? null;
                return $h;
            });

        $conActividad = $hojas->filter(fn($h) => $h->ultima_actividad !== null)->sortByDesc('ultima_actividad')->values();
        $sinActividad = $hojas->filter(fn($h) => $h->ultima_actividad === null)->sortBy('numero_hoja')->values();
        $hojasOrdenadas = $conActividad->concat($sinActividad)->values();

        $votos = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->get(['numero_hoja', 'numero_pregunta', 'voto']);

        $votosPorHoja = [];
        foreach ($votos as $v) {
            $votosPorHoja[$v->numero_hoja][$v->numero_pregunta] = $v->voto;
        }

        return [
            'preguntas'       => $preguntas,
            'totalHojas'      => $totalHojas,
            'hojasRecontadas' => $hojasRecontadas,
            'tallies'         => $this->tallies($idAsamblea),
            'hojas'           => $hojasOrdenadas,
            'votosPorHoja'    => $votosPorHoja,
        ];
    }

    public function backoffice(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        return view('mb.asamblea-recuento-backoffice', array_merge(
            $this->estado($asamblea->id),
            [
                'project'    => $project,
                'asamblea'   => $asamblea,
                'breadcrumb' => [
                    ['label' => 'Asambleas', 'url' => route('listado', [$project->slug, 'asambleas'])],
                    ['label' => 'Recuento en directo', 'url' => ''],
                ],
            ]
        ));
    }

    public function estadoRefresh(Request $request, Project $project)
    {
        $idAsamblea = (int) $request->id_asamblea;

        return response()->json($this->estado($idAsamblea));
    }

    public function eliminarVoto(Request $request, Project $project)
    {
        if (empty($request->id_asamblea) || !$request->filled('numero_hoja') || !$request->filled('numero_pregunta')) {
            return response()->json(['error' => 'Faltan datos.'], 422);
        }

        $idAsamblea = (int) $request->id_asamblea;

        $borrados = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->where('numero_hoja', (int) $request->numero_hoja)
            ->where('numero_pregunta', (int) $request->numero_pregunta)
            ->delete();

        if (!$borrados) {
            return response()->json(['error' => 'No se encontró ese voto.'], 404);
        }

        return response()->json(array_merge(['ok' => true], $this->estado($idAsamblea)));
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

        $anterior = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->where('numero_hoja', $numeroHoja)
            ->where('numero_pregunta', $numeroPregunta)
            ->value('voto');

        // Un segundo escaneo de la misma hoja+pregunta corrige el voto anterior (por si se
        // escaneó la respuesta equivocada), en vez de rechazarse como duplicado. createuser
        // y createdat no se tocan en la corrección: solo se actualiza voto y fecha (esta
        // última es la que determina qué hoja aparece arriba del listado por actividad).
        DB::table('mb_asambleas_votos')->upsert(
            [
                'id_asambleas'    => $idAsamblea,
                'numero_hoja'     => $numeroHoja,
                'numero_pregunta' => $numeroPregunta,
                'voto'            => $voto,
                'fecha'           => now(),
                'createuser'      => Auth::id(),
                'createdat'       => now(),
            ],
            ['id_asambleas', 'numero_hoja', 'numero_pregunta'],
            ['voto', 'fecha']
        );

        return response()->json(array_merge([
            'ok'          => true,
            'numero_hoja' => $numeroHoja, 'numero_pregunta' => $numeroPregunta, 'voto' => $voto,
            'corregido'   => $anterior !== null && $anterior !== $voto,
            'anterior'    => $anterior,
        ], $this->estado($idAsamblea)));
    }
}
