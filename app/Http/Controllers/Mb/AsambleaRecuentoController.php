<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AsambleaRecuentoController extends Controller
{
    private function tallies(int $idAsamblea, array $numerosAnulados = []): array
    {
        $rows = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->when(!empty($numerosAnulados), fn($q) => $q->whereNotIn('numero_hoja', $numerosAnulados))
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

        $numerosActuales = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $idAsamblea)
            ->where('deleted', 0)
            ->pluck('numero_hoja');

        $totalHojas = $numerosActuales->count();

        // Hojas anuladas (dadas de baja/reemplazadas): no deben contar como recontadas aunque
        // tengan votos escaneados de antes de anularse -- esos votos se reportan aparte, como
        // aviso de que puede haber que revisar el recuento.
        $numerosAnulados = DB::table('mb_asambleas_hojas_historico')
            ->where('id_asambleas', $idAsamblea)
            ->pluck('numero_hoja')
            ->unique();

        // Votos escaneados de una hoja que nunca se registró como repartida (ni vigente ni en el
        // histórico) -- probablemente un número mal escaneado. No deben computar en el recuento
        // hasta que se les asigne una vivienda.
        $numerosConocidos = $numerosActuales->merge($numerosAnulados)->unique();
        $numerosSinVivienda = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->whereNotIn('numero_hoja', $numerosConocidos)
            ->distinct()
            ->pluck('numero_hoja');

        $numerosExcluidos = $numerosAnulados->merge($numerosSinVivienda)->unique();

        $hojasRecontadas = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->whereNotIn('numero_hoja', $numerosExcluidos)
            ->distinct()
            ->count('numero_hoja');

        $hojasAnuladasConVoto = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->whereIn('numero_hoja', $numerosAnulados)
            ->distinct()
            ->count('numero_hoja');

        $hojasSinVivienda = $numerosSinVivienda->count();

        // Hojas repartidas sin ningún voto escaneado todavía -- pendientes de recontar mientras el
        // recuento está en marcha, o abstención total en las 6 preguntas si ya ha terminado.
        $hojasSinVotacion = $totalHojas - $hojasRecontadas;

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
                $h->cancelada = false;
                return $h;
            });

        // Hojas dadas de baja/reasignadas (mb_asambleas_hojas_historico): se muestran también en
        // el listado, marcadas como "Cancelada", para poder revisar sus votos si los tuviera.
        $hojasCanceladas = DB::table('mb_asambleas_hojas_historico as ah')
            ->join('mb_viviendas as v', 'v.id', '=', 'ah.id_viviendas')
            ->where('ah.id_asambleas', $idAsamblea)
            ->get(['ah.numero_hoja', 'v.nombre'])
            ->map(function ($h) use ($ultimaActividad) {
                $h->ultima_actividad = $ultimaActividad[$h->numero_hoja] ?? null;
                $h->cancelada = true;
                return $h;
            });

        // Hojas con votos pero sin vivienda asignada (numero_hoja no reconocido): se muestran
        // también en el listado, marcadas como "Sin vivienda", para poder localizarlas y
        // corregir el número de hoja o registrar el reparto que falta.
        $hojasSinViviendaRows = $numerosSinVivienda->map(function ($numeroHoja) use ($ultimaActividad) {
            return (object) [
                'numero_hoja'      => $numeroHoja,
                'nombre'           => null,
                'ultima_actividad' => $ultimaActividad[$numeroHoja] ?? null,
                'cancelada'        => false,
                'sinVivienda'      => true,
            ];
        });

        $hojasTodas = $hojas->concat($hojasCanceladas)->concat($hojasSinViviendaRows);

        $conActividad = $hojasTodas->filter(fn($h) => $h->ultima_actividad !== null)->sortByDesc('ultima_actividad')->values();
        $sinActividad = $hojasTodas->filter(fn($h) => $h->ultima_actividad === null)->sortBy('numero_hoja')->values();
        $hojasOrdenadas = $conActividad->concat($sinActividad)->values();

        $votos = DB::table('mb_asambleas_votos')
            ->where('id_asambleas', $idAsamblea)
            ->get(['numero_hoja', 'numero_pregunta', 'voto']);

        $votosPorHoja = [];
        foreach ($votos as $v) {
            $votosPorHoja[$v->numero_hoja][$v->numero_pregunta] = $v->voto;
        }

        return [
            'preguntas'            => $preguntas,
            'totalHojas'           => $totalHojas,
            'hojasRecontadas'      => $hojasRecontadas,
            'hojasAnuladasConVoto' => $hojasAnuladasConVoto,
            'hojasSinVotacion'     => $hojasSinVotacion,
            'hojasSinVivienda'     => $hojasSinVivienda,
            'tallies'              => $this->tallies($idAsamblea, $numerosExcluidos->all()),
            'hojas'                => $hojasOrdenadas,
            'votosPorHoja'         => $votosPorHoja,
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

    public function exportarListado(Request $request, Project $project)
    {
        $idAsamblea = (int) $request->id_asamblea;
        $estado     = $this->estado($idAsamblea);

        $headers = ['Hoja', 'Vivienda', 'Estado'];
        foreach ($estado['preguntas'] as $p) {
            $headers[] = 'P' . $p->numero_pregunta;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');

        $rowNum = 2;
        foreach ($estado['hojas'] as $h) {
            $tieneVoto = !empty($estado['votosPorHoja'][$h->numero_hoja]);
            $sinVivienda = $h->sinVivienda ?? false;
            $estadoTexto = $h->cancelada ? 'Cancelada' : ($sinVivienda ? 'Sin vivienda' : (!$tieneVoto ? 'Sin votación' : ''));
            $row = [$h->numero_hoja, $h->nombre ?? '', $estadoTexto];
            foreach ($estado['preguntas'] as $p) {
                $voto = $estado['votosPorHoja'][$h->numero_hoja][$p->numero_pregunta] ?? null;
                $row[] = $voto === 'S' ? 'Sí' : ($voto === 'N' ? 'No' : '');
            }
            $sheet->fromArray([$row], null, "A{$rowNum}");
            $rowNum++;
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF97316']],
        ]);
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = 'recuento_asamblea_' . $idAsamblea . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
