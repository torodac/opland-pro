<?php

namespace App\Console\Commands;

use App\Services\ClaudeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClasificarMovimientosCommand extends Command
{
    protected $signature   = 'movimientos:clasificar
                                {--limit= : Máximo de movimientos a procesar en esta ejecución}
                                {--dry-run : No escribe nada, solo muestra qué haría}';
    protected $description = 'Clasifica automáticamente movimientos de rodcar_movs sin categorizar (mapeo directo, similitud histórica, IA)';

    private int $umbralConfianza;
    private float $umbralSimilitud;
    private string $modelo;
    private int $numEjemplos;
    private bool $dryRun;

    public function handle(): int
    {
        $this->umbralConfianza = config('rodcar.clasificacion.umbral_confianza_ia');
        $this->umbralSimilitud = config('rodcar.clasificacion.umbral_similitud_trgm');
        $this->modelo          = config('rodcar.clasificacion.modelo');
        $this->numEjemplos     = config('rodcar.clasificacion.num_ejemplos_few_shot');
        $this->dryRun          = (bool) $this->option('dry-run');

        $query = DB::table('rodcar_movs')
            ->whereNull('estado_clasificacion')
            ->where('deleted', false)
            ->orderBy('id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $movimientos = $query->get();
        $this->info(count($movimientos) . ' movimientos sin clasificar.' . ($this->dryRun ? ' (dry-run, no se escribe nada)' : ''));

        $contadores = ['mapeado' => 0, 'similitud' => 0, 'ia_alta' => 0, 'pendiente' => 0];

        foreach ($movimientos as $mov) {
            $resultado = $this->clasificarUno($mov);
            $contadores[$resultado] = ($contadores[$resultado] ?? 0) + 1;
        }

        $this->newLine();
        $this->info('Resumen: ' . json_encode($contadores));

        return self::SUCCESS;
    }

    private function clasificarUno(object $mov): string
    {
        // FASE 1: mapeo directo
        $normalizado = mb_strtoupper(trim($mov->nombre));
        $mapeo = DB::table('rodcar_movs_mapeo')
            ->where('nombre_normalizado', $normalizado)
            ->whereNotNull('id_movs_tipo1')
            ->first();

        if ($mapeo) {
            $this->aplicar($mov, 1, $mapeo->id_movs_tipo1, $mapeo->id_movs_tipo2, 'mapeado', null, 'Coincidencia exacta en tabla de mapeos.', definitivo: true);
            $this->line("  [{$mov->id}] {$mov->nombre} -> mapeado (fase 1)");
            return 'mapeado';
        }

        // FASE 2: similitud trigram contra historico ya validado a mano
        $similar = DB::table('rodcar_movs')
            ->where('estado_clasificacion', 'validado_manual')
            ->whereNotNull('id_movs_tipo1')
            ->where('id', '<>', $mov->id)
            ->selectRaw('id_movs_tipo1, id_movs_tipo2, similarity(nombre, ?) as sim', [$mov->nombre])
            ->whereRaw('similarity(nombre, ?) >= ?', [$mov->nombre, $this->umbralSimilitud])
            ->orderByDesc('sim')
            ->first();

        if ($similar) {
            $confianza = (int) round($similar->sim * 100);
            $this->aplicar($mov, 2, $similar->id_movs_tipo1, $similar->id_movs_tipo2, 'clasificado_ia_alta_confianza', $confianza,
                "Similitud del {$confianza}% con un movimiento histórico validado manualmente.", definitivo: false);
            $this->line("  [{$mov->id}] {$mov->nombre} -> similitud {$confianza}% (fase 2)");
            return 'similitud';
        }

        // FASE 3: Claude, con ejemplos reales como few-shot (para no inventar categorias)
        $ejemplos = DB::table('rodcar_movs')
            ->whereNotNull('rodcar_movs.id_movs_tipo1')
            ->where('rodcar_movs.id', '<>', $mov->id)
            ->join('rodcar_movs_tipo1', 'rodcar_movs_tipo1.id', '=', 'rodcar_movs.id_movs_tipo1')
            ->leftJoin('rodcar_movs_tipo2', 'rodcar_movs_tipo2.id', '=', 'rodcar_movs.id_movs_tipo2')
            ->selectRaw(
                'rodcar_movs.nombre, rodcar_movs.importe, rodcar_movs_tipo1.nombre as tipo1_nombre, rodcar_movs_tipo2.nombre as tipo2_nombre, similarity(rodcar_movs.nombre, ?) as sim',
                [$mov->nombre]
            )
            ->orderByDesc('sim')
            ->limit($this->numEjemplos)
            ->get();

        [$tipo1Id, $tipo2Id, $confianza, $justificacion] = $this->clasificarConClaude($mov, $ejemplos);
        $estado = ($tipo1Id && $confianza >= $this->umbralConfianza) ? 'clasificado_ia_alta_confianza' : 'pendiente_validacion';

        $this->aplicar($mov, 3, $tipo1Id, $tipo2Id, $estado, $confianza, $justificacion, definitivo: false);

        if ($estado === 'clasificado_ia_alta_confianza') {
            $this->line("  [{$mov->id}] {$mov->nombre} -> IA {$confianza}% (fase 3, alta confianza)");
            return 'ia_alta';
        }

        $this->line("  [{$mov->id}] {$mov->nombre} -> pendiente de validación (fase 3, confianza {$confianza}%)");
        return 'pendiente';
    }

    /** @return array{0: ?int, 1: ?int, 2: int, 3: string} */
    private function clasificarConClaude(object $mov, \Illuminate\Support\Collection $ejemplos): array
    {
        if ($ejemplos->isEmpty()) {
            return [null, null, 0, 'Sin ejemplos históricos similares para comparar.'];
        }

        $listaEjemplos = $ejemplos->map(fn ($e, $i) =>
            ($i + 1) . ". \"{$e->nombre}\" ({$e->importe} EUR) -> Tipo: \"{$e->tipo1_nombre}\", Subtipo: \"" . ($e->tipo2_nombre ?? '(ninguno)') . '"'
        )->implode("\n");

        $prompt = <<<PROMPT
        Eres un clasificador de movimientos bancarios personales. Debes asignar tipo y subtipo a un movimiento nuevo, basándote ÚNICAMENTE en los ejemplos de movimientos ya clasificados que aparecen abajo (son los más parecidos que existen en el histórico).

        Movimiento a clasificar:
        - Concepto: "{$mov->nombre}"
        - Importe: {$mov->importe} EUR

        Ejemplos de movimientos similares ya clasificados:
        {$listaEjemplos}

        Instrucciones:
        - Elige EXACTAMENTE un par (Tipo, Subtipo) de los que aparecen en los ejemplos de arriba (copia el texto tal cual, sin inventar ni modificar nombres), el que mejor encaje para el movimiento a clasificar.
        - Si ninguno de los ejemplos encaja razonablemente, devuelve "tipo" y "subtipo" como null.
        - "confianza" es un entero de 0 a 100 sobre lo seguro que estás de tu elección.
        - Responde ÚNICAMENTE con un JSON válido, sin texto adicional, sin markdown, con este formato exacto:
        {"tipo":"...","subtipo":"...","confianza":0,"justificacion":"..."}
        PROMPT;

        $claude = new ClaudeService();
        $raw    = $claude->preguntar($prompt, 512, $this->modelo);
        $json   = $this->extractJson($raw);

        if (!$json) {
            return [null, null, 0, 'No se pudo interpretar la respuesta de la IA.'];
        }

        $confianza     = (int) ($json['confianza'] ?? 0);
        $justificacion = (string) ($json['justificacion'] ?? '');
        $tipo1Nombre   = $json['tipo'] ?? null;
        $tipo2Nombre   = $json['subtipo'] ?? null;

        if (!$tipo1Nombre) {
            return [null, null, $confianza, $justificacion ?: 'La IA no encontró un ejemplo suficientemente parecido.'];
        }

        $tipo1Id = DB::table('rodcar_movs_tipo1')->where('nombre', $tipo1Nombre)->value('id');
        $tipo2Id = $tipo2Nombre ? DB::table('rodcar_movs_tipo2')->where('nombre', $tipo2Nombre)->value('id') : null;

        if (!$tipo1Id) {
            return [null, null, 0, "La IA devolvió un tipo ('{$tipo1Nombre}') que no coincide con ninguno de los ejemplos dados."];
        }

        return [$tipo1Id, $tipo2Id, $confianza, $justificacion];
    }

    private function aplicar(object $mov, int $fase, ?int $tipo1Id, ?int $tipo2Id, string $estado, ?int $confianza, string $justificacion, bool $definitivo): void
    {
        if ($this->dryRun) return;

        DB::table('rodcar_movs_clasificacion_log')->insert([
            'id_movs' => $mov->id, 'fase' => $fase,
            'id_movs_tipo1' => $tipo1Id, 'id_movs_tipo2' => $tipo2Id,
            'confianza' => $confianza, 'justificacion' => $justificacion,
            'createdat' => now(), 'updatedat' => now(),
        ]);

        $this->actualizarMov($mov->id, $tipo1Id, $tipo2Id, $estado, $fase, $confianza, $justificacion, $definitivo);
    }

    private function actualizarMov(int $id, ?int $tipo1Id, ?int $tipo2Id, string $estado, int $fase, ?int $confianza, string $justificacion, bool $definitivo): void
    {
        $update = [
            'estado_clasificacion' => $estado,
            'fase_clasificacion'   => $fase,
            'confianza_ia'         => $confianza,
            'justificacion_ia'     => $justificacion,
            'clasificado_en'       => now(),
            'updatedat'            => now(),
        ];

        // Fase 1 (mapeo exacto): va directa a los campos definitivos.
        // Fases 2 y 3: solo propuesta, a la espera de validación manual.
        if ($definitivo) {
            if ($tipo1Id) $update['id_movs_tipo1'] = $tipo1Id;
            if ($tipo2Id) $update['id_movs_tipo2'] = $tipo2Id;
        } else {
            $update['id_movs_tipo1_propuesto'] = $tipo1Id;
            $update['id_movs_tipo2_propuesto'] = $tipo2Id;
        }

        DB::table('rodcar_movs')->where('id', $id)->update($update);
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false) return null;

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
