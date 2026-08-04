<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class HojaVotoPdfService
{
    public function qrDataUri(string $texto): string
    {
        $writer = new PngWriter();
        $qrCode = new QrCode($texto, size: 100, margin: 4);

        return $writer->write($qrCode)->getDataUri();
    }

    public function renderLote($asamblea, $preguntas, array $hojas, ?string $logoDataUri, string $fechaTexto): string
    {
        $qrs = [];
        foreach ($hojas as $numeroHoja) {
            foreach ($preguntas as $p) {
                $base = 'Hoja de voto ' . str_pad($numeroHoja, 3, '0', STR_PAD_LEFT) . ' ' . $p->numero_pregunta . ' ';
                $qrs[$numeroHoja][$p->numero_pregunta] = [
                    'N' => $this->qrDataUri($base . 'N'),
                    'S' => $this->qrDataUri($base . 'S'),
                ];
            }
        }

        // Espacio (en px CSS) reservado a las preguntas dentro de la página, calibrado para que
        // 8 preguntas quepan justo con el hueco base de 10px. Si hay menos, repartimos el
        // sobrante como separación extra entre preguntas para que ocupen todo el hueco.
        $alturaReservada = 836;
        $alturaMediaCaja = 90;
        $numPreguntas    = $preguntas->count();
        $espacioPregunta = $numPreguntas > 0
            ? max(10, ($alturaReservada - $numPreguntas * $alturaMediaCaja) / $numPreguntas)
            : 10;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('mb.hojas-voto-pdf', [
            'asamblea'        => $asamblea,
            'preguntas'       => $preguntas,
            'hojas'           => $hojas,
            'qrs'             => $qrs,
            'logoDataUri'     => $logoDataUri,
            'fechaTexto'      => $fechaTexto,
            'espacioPregunta' => $espacioPregunta,
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    public function logoDataUri(): ?string
    {
        $logoPath = public_path('mb/logo-asamblea.jpg');

        return file_exists($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    }

    public function fechaTexto(string $fecha): string
    {
        return mb_strtoupper(\Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('D [DE] MMMM [DE] YYYY'));
    }
}
