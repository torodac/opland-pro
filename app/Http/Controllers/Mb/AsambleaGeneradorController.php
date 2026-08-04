<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\HojaVotoPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class AsambleaGeneradorController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $asambleas = DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->get();

        $asamblea = $request->filled('id_asamblea')
            ? $asambleas->firstWhere('id', (int) $request->id_asamblea)
            : $asambleas->first();

        $preguntas = $asamblea
            ? DB::table('mb_asambleas_preguntas')
                ->where('id_asambleas', $asamblea->id)
                ->where('deleted', 0)
                ->orderBy('numero_pregunta')
                ->get()
            : collect();

        return view('mb.asamblea-generador', [
            'project'    => $project,
            'asambleas'  => $asambleas,
            'asamblea'   => $asamblea,
            'preguntas'  => $preguntas,
            'breadcrumb' => [
                ['label' => 'Asambleas', 'url' => route('listado', [$project->slug, 'asambleas'])],
                ['label' => 'Generar hojas de voto', 'url' => ''],
            ],
        ]);
    }

    public function crearAsamblea(Request $request, Project $project)
    {
        if (!$request->filled('fecha'))            return response()->json(['error' => 'La fecha es obligatoria.'], 422);
        if (!in_array($request->tipo, ['Ordinaria', 'Extraordinaria'], true)) {
            return response()->json(['error' => 'El tipo debe ser Ordinaria o Extraordinaria.'], 422);
        }
        $numeroPreguntas = (int) $request->numero_preguntas;
        if ($numeroPreguntas < 1 || $numeroPreguntas > 30) {
            return response()->json(['error' => 'El número de preguntas debe estar entre 1 y 30.'], 422);
        }

        $now    = now();
        $fecha  = $request->fecha;
        $tipo   = $request->tipo;
        $nombre = 'Asamblea ' . $tipo . ' — ' . \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y');

        $idAsamblea = DB::table('mb_asambleas')->insertGetId([
            'nombre'     => $nombre,
            'fecha'      => $fecha,
            'tipo'       => $tipo,
            'blocked'    => 0, 'hidden' => 0, 'deleted' => 0,
            'createdat'  => $now, 'updatedat' => $now,
        ]);

        $filas = [];
        for ($i = 1; $i <= $numeroPreguntas; $i++) {
            $filas[] = [
                'id_asambleas'    => $idAsamblea,
                'numero_pregunta' => $i,
                'texto'           => '',
                'deleted'         => 0,
                'createdat'       => $now,
                'updatedat'       => $now,
            ];
        }
        DB::table('mb_asambleas_preguntas')->insert($filas);

        return response()->json(['ok' => true, 'id' => $idAsamblea]);
    }

    // Solo se permite negrita/cursiva/subrayado — el resto de etiquetas se descarta al guardar.
    private const TAGS_PERMITIDAS = '<b><strong><i><em><u><br>';

    public function guardarPreguntas(Request $request, Project $project)
    {
        if (empty($request->id_asamblea)) return response()->json(['error' => 'Falta la asamblea.'], 422);
        $idAsamblea = (int) $request->id_asamblea;
        $preguntas  = $request->input('preguntas', []);

        foreach ($preguntas as $numero => $texto) {
            DB::table('mb_asambleas_preguntas')
                ->where('id_asambleas', $idAsamblea)
                ->where('numero_pregunta', (int) $numero)
                ->update(['texto' => strip_tags((string) $texto, self::TAGS_PERMITIDAS), 'updatedat' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function agregarPregunta(Request $request, Project $project)
    {
        if (empty($request->id_asamblea)) return response()->json(['error' => 'Falta la asamblea.'], 422);
        $idAsamblea = (int) $request->id_asamblea;

        $siguiente = (int) DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $idAsamblea)
            ->max('numero_pregunta') + 1;

        DB::table('mb_asambleas_preguntas')->insert([
            'id_asambleas'    => $idAsamblea,
            'numero_pregunta' => $siguiente,
            'texto'           => '',
            'deleted'         => 0,
            'createdat'       => now(),
            'updatedat'       => now(),
        ]);

        return response()->json(['ok' => true, 'numero_pregunta' => $siguiente]);
    }

    public function eliminarPregunta(Request $request, Project $project)
    {
        if (empty($request->id_asamblea) || !$request->filled('numero_pregunta')) {
            return response()->json(['error' => 'Faltan datos.'], 422);
        }

        DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', (int) $request->id_asamblea)
            ->where('numero_pregunta', (int) $request->numero_pregunta)
            ->update(['deleted' => 1, 'updatedat' => now()]);

        return response()->json(['ok' => true]);
    }

    // Tamaño de lote: generar 500 hojas en un único documento supera el límite de tiempo porque
    // el motor de layout de dompdf escala mal (no lineal) con el número de páginas. Generando
    // lotes independientes de 100 el coste total se mantiene lineal y cabe en el timeout.
    private const HOJAS_POR_LOTE = 100;

    public function descargarPdf(Request $request, Project $project, HojaVotoPdfService $service)
    {
        if (empty($request->id_asamblea)) abort(422, 'Falta la asamblea.');

        $asamblea = DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first();
        abort_if(!$asamblea, 404, 'Asamblea no encontrada.');

        $desde = (int) $request->input('desde', 1);
        $hasta = (int) $request->input('hasta', 1);
        abort_if($desde < 1 || $hasta < $desde, 422, 'Rango de hojas no válido.');
        abort_if($hasta - $desde + 1 > 600, 422, 'El rango no puede superar las 600 hojas de una vez.');

        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $asamblea->id)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get();
        abort_if($preguntas->isEmpty(), 422, 'Esta asamblea no tiene preguntas definidas.');

        set_time_limit(300);

        // Rango pequeño: un único PDF generado en el propio proceso, igual que antes.
        if ($hasta - $desde + 1 <= self::HOJAS_POR_LOTE) {
            $contenido = $service->renderLote(
                $asamblea, $preguntas, range($desde, $hasta),
                $service->logoDataUri(), $service->fechaTexto($asamblea->fecha)
            );

            return response($contenido, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"hojas_voto_{$desde}_a_{$hasta}.pdf\"",
            ]);
        }

        // Rango grande: cada lote de 100 es independiente, así que se generan en procesos php
        // separados EN PARALELO (uno por lote) en vez de secuencialmente — el servidor tiene
        // varios núcleos libres y dompdf ya de por sí escala mal con documentos grandes, así
        // que more vale muchos documentos pequeños a la vez que uno grande de uno en uno.
        $phpBinary = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan   = base_path('artisan');

        $procesos = [];
        for ($loteDesde = $desde; $loteDesde <= $hasta; $loteDesde += self::HOJAS_POR_LOTE) {
            $loteHasta  = min($loteDesde + self::HOJAS_POR_LOTE - 1, $hasta);
            $outputPath = tempnam(sys_get_temp_dir(), 'hoja_lote_');

            $proceso = new Process([
                $phpBinary, $artisan, 'mb:generar-lote-hoja-voto',
                $asamblea->id, $loteDesde, $loteHasta, $outputPath,
            ]);
            $proceso->setTimeout(280);
            $proceso->start();

            $procesos[] = ['proceso' => $proceso, 'desde' => $loteDesde, 'hasta' => $loteHasta, 'path' => $outputPath];
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'hojas_voto_') . '.zip';
        $zip     = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($procesos as $p) {
            $p['proceso']->wait();
            abort_if(!$p['proceso']->isSuccessful(), 500, 'Error generando el lote ' . $p['desde'] . '-' . $p['hasta'] . ': ' . $p['proceso']->getErrorOutput());

            $zip->addFile($p['path'], "hojas_voto_{$p['desde']}_a_{$p['hasta']}.pdf");
        }

        $zip->close();

        // Los ficheros temporales de cada lote no se pueden borrar hasta que el zip los haya
        // leído; addFile() los referencia por ruta y no los copia hasta close(), así que se
        // borran justo después.
        foreach ($procesos as $p) {
            @unlink($p['path']);
        }

        return response()->download($zipPath, "hojas_voto_{$desde}_a_{$hasta}.zip")->deleteFileAfterSend(true);
    }
}
