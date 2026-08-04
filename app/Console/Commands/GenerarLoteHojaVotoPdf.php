<?php

namespace App\Console\Commands;

use App\Services\HojaVotoPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Genera el PDF de un único lote de hojas de voto y lo guarda en disco. Se invoca como
// proceso independiente (ver AsambleaGeneradorController::descargarPdf) para poder generar
// varios lotes en paralelo, ya que dompdf escala mal con documentos muy grandes.
class GenerarLoteHojaVotoPdf extends Command
{
    protected $signature = 'mb:generar-lote-hoja-voto {idAsamblea} {desde} {hasta} {outputPath}';
    protected $description = 'Genera un PDF de hojas de voto para un rango de hojas (uso interno)';

    public function handle(HojaVotoPdfService $service): int
    {
        $idAsamblea = (int) $this->argument('idAsamblea');
        $desde      = (int) $this->argument('desde');
        $hasta      = (int) $this->argument('hasta');
        $outputPath = $this->argument('outputPath');

        $asamblea = DB::table('mb_asambleas')->where('id', $idAsamblea)->where('deleted', 0)->first();
        if (!$asamblea) {
            $this->error('Asamblea no encontrada.');
            return 1;
        }

        $preguntas = DB::table('mb_asambleas_preguntas')
            ->where('id_asambleas', $idAsamblea)
            ->where('deleted', 0)
            ->orderBy('numero_pregunta')
            ->get();

        $contenido = $service->renderLote(
            $asamblea,
            $preguntas,
            range($desde, $hasta),
            $service->logoDataUri(),
            $service->fechaTexto($asamblea->fecha)
        );

        file_put_contents($outputPath, $contenido);

        return 0;
    }
}
