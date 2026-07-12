<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SuggestEquipoJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly string $fullTable,
        public readonly int    $id,
    ) {}

    public function handle(): void
    {
        $registro = DB::table($this->fullTable)->find($this->id);
        if (!$registro) return;

        // Re-verificar condiciones (puede que el usuario haya asignado equipo mientras esperaba)
        if ($registro->master_duraciones === null)  return;
        if ($registro->id_inventario    !== null)   return;
        if ($registro->equipo_propuesto !== null)   return;

        $idProp = $registro->id_propiedades ?? null;
        if (!$idProp) return;

        $inventario = DB::table('vm_inventario')
            ->where('id_propiedades', $idProp)
            ->where('deleted', 0)
            ->pluck('nombre')
            ->toArray();

        if (empty($inventario)) return;

        $lista  = implode(', ', $inventario);
        $nombre = $registro->nombre      ?? '';
        $desc   = $registro->descripcion ?? '';
        $coment = $registro->comentario  ?? '';

        $prompt = "Eres un asistente técnico de mantenimiento de apartamentos turísticos. "
            . "Analiza la siguiente tarea y selecciona el equipo o instalación más relacionado de la lista.\n\n"
            . "Tarea:\n"
            . "- Nombre: {$nombre}\n"
            . "- Descripción: {$desc}\n"
            . "- Comentario: {$coment}\n\n"
            . "Equipos disponibles en esta propiedad: {$lista}\n\n"
            . "Responde ÚNICAMENTE con el nombre exacto de uno de los equipos de la lista. "
            . "Si ninguno encaja, responde 'ninguno'.";

        $response = Http::timeout(55)->post('http://localhost:11434/api/generate', [
            'model'  => 'llama3.2:3b',
            'prompt' => $prompt,
            'stream' => false,
        ]);

        $respuesta = trim($response->json('response') ?? '');

        if ($respuesta && strtolower($respuesta) !== 'ninguno' && strlen($respuesta) <= 255) {
            DB::table($this->fullTable)->where('id', $this->id)->update([
                'equipo_propuesto' => $respuesta,
            ]);
        }
    }
}
