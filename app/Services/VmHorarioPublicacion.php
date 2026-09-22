<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Hasta dónde puede mirar el cuadrante el trabajador desde la PWA.
//
// Cada semana se publica u oculta desde el planificador. Ocultar una semana levanta un muro: la
// PWA no muestra ESA semana ni ninguna posterior, aunque las siguientes estén publicadas. Así,
// para abrir el cuadrante hasta cierta fecha basta con publicar hasta ahí y dejar oculta la
// primera semana que todavía se está montando, sin tener que ir ocultando una a una las de
// después.
//
// Por defecto, sin fila en vm_horarios_publicacion:
//   - las semanas pasadas y la corriente están PUBLICADAS (ya se han trabajado, no hay nada que
//     esconder);
//   - las futuras están OCULTAS, porque el cuadrante se monta con antelación y hasta que no se
//     publica expresamente no debe verlo el equipo.
//
// Es decir, el muro por defecto está en la semana que viene, y publicar hacia adelante es un acto
// deliberado.
class VmHorarioPublicacion
{
    /**
     * Primera semana oculta, la que levanta el muro.
     *
     * Es la más temprana entre: la primera semana ocultada a mano, y la primera semana futura que
     * nadie ha publicado (que está oculta por defecto). Siempre devuelve algo.
     *
     * @return array{anio:int, semana:int}
     */
    public static function primeraOculta(): array
    {
        $ocultaExplicita = DB::table('vm_horarios_publicacion')
            ->where('publicado', false)
            ->orderBy('anio')->orderBy('semana')
            ->first(['anio', 'semana']);

        $muro = self::primeraFuturaSinPublicar();

        if ($ocultaExplicita) {
            $explicita = ['anio' => (int) $ocultaExplicita->anio, 'semana' => (int) $ocultaExplicita->semana];
            if ([$explicita['anio'], $explicita['semana']] < [$muro['anio'], $muro['semana']]) {
                return $explicita;
            }
        }

        return $muro;
    }

    /**
     * La primera semana posterior a la corriente que nadie ha publicado expresamente. Se avanza
     * semana a semana mientras haya publicación explícita, así que para en el primer hueco.
     *
     * @return array{anio:int, semana:int}
     */
    private static function primeraFuturaSinPublicar(): array
    {
        $hoy = new \DateTime();
        $publicadas = DB::table('vm_horarios_publicacion')
            ->where('publicado', true)
            ->get(['anio', 'semana'])
            ->map(fn ($f) => (int) $f->anio . '-' . (int) $f->semana)
            ->flip();

        $cursor = (clone $hoy)->modify('+7 days');   // la semana que viene
        while ($publicadas->has((int) $cursor->format('o') . '-' . (int) $cursor->format('W'))) {
            $cursor->modify('+7 days');
        }

        return ['anio' => (int) $cursor->format('o'), 'semana' => (int) $cursor->format('W')];
    }

    /**
     * ¿Puede el trabajador ver esta semana en la PWA?
     */
    public static function visible(int $anio, int $semana): bool
    {
        $muro = self::primeraOculta();

        return [$anio, $semana] < [$muro['anio'], $muro['semana']];
    }

    /**
     * Última semana visible en formato ISO (YYYY-Www), para que la PWA sepa dónde parar de
     * navegar.
     */
    public static function ultimaVisible(): string
    {
        $muro = self::primeraOculta();

        // La anterior a la del muro. setISODate admite semana 0, que DateTime resuelve como la
        // última del año anterior, así que el cambio de año sale gratis.
        $fecha = new \DateTime();
        $fecha->setISODate($muro['anio'], $muro['semana'] - 1);

        return $fecha->format('o') . '-W' . $fecha->format('W');
    }

    /**
     * Estado de una semana concreta (true = publicada). Sin fila, publicada si no es futura.
     */
    public static function estaPublicada(int $anio, int $semana): bool
    {
        $valor = DB::table('vm_horarios_publicacion')
            ->where('anio', $anio)->where('semana', $semana)
            ->value('publicado');

        if ($valor !== null) return (bool) $valor;

        $hoy = new \DateTime();

        return [$anio, $semana] <= [(int) $hoy->format('o'), (int) $hoy->format('W')];
    }

    /**
     * Publica u oculta una semana.
     */
    public static function guardar(int $anio, int $semana, bool $publicado, ?int $usuario = null): void
    {
        $ahora = now();

        DB::table('vm_horarios_publicacion')->upsert(
            [[
                'anio'       => $anio,
                'semana'     => $semana,
                'publicado'  => $publicado,
                'updateuser' => $usuario,
                'createdat'  => $ahora,
                'updatedat'  => $ahora,
            ]],
            ['anio', 'semana'],
            ['publicado', 'updateuser', 'updatedat']
        );
    }
}
