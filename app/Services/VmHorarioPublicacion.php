<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Hasta dónde puede mirar el cuadrante el trabajador desde la PWA.
//
// Cada semana se publica u oculta desde el planificador, **por departamento**: Limpieza puede
// tener abierta hasta la semana 45 mientras Mantenimiento se queda en la 42, sin que uno espere
// al otro. Ocultar una semana levanta un muro: la PWA no muestra ESA semana ni ninguna posterior
// de ese departamento, aunque las siguientes estén publicadas. Así, para abrir el cuadrante hasta
// cierta fecha basta con publicar hasta ahí y dejar oculta la primera semana que todavía se está
// montando, sin tener que ir ocultando una a una las de después.
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
     * Primera semana oculta de un departamento, la que levanta el muro.
     *
     * Es la más temprana entre: la primera semana ocultada a mano, y la primera semana futura que
     * nadie ha publicado (que está oculta por defecto). Siempre devuelve algo.
     *
     * Sin departamento devuelve el muro MÁS RESTRICTIVO de todos. Es el caso del trabajador sin
     * departamento asignado: ante la duda, se le enseña lo menos posible en vez de abrirle de par
     * en par el cuadrante de todos.
     *
     * @return array{anio:int, semana:int}
     */
    public static function primeraOculta(?int $departamento = null): array
    {
        if ($departamento === null) {
            return self::muroMasRestrictivo();
        }

        $ocultaExplicita = DB::table('vm_horarios_publicacion')
            ->where('id_departamento', $departamento)
            ->where('publicado', false)
            ->orderBy('anio')->orderBy('semana')
            ->first(['anio', 'semana']);

        $muro = self::primeraFuturaSinPublicar($departamento);

        if ($ocultaExplicita) {
            $explicita = ['anio' => (int) $ocultaExplicita->anio, 'semana' => (int) $ocultaExplicita->semana];
            if ([$explicita['anio'], $explicita['semana']] < [$muro['anio'], $muro['semana']]) {
                return $explicita;
            }
        }

        return $muro;
    }

    /** El muro que menos enseña de entre todos los departamentos visibles. */
    private static function muroMasRestrictivo(): array
    {
        $muros = DB::table('vm_departamentos')
            ->where('visible_horarios', true)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->pluck('id')
            ->map(fn($id) => self::primeraOculta((int) $id));

        if ($muros->isEmpty()) {
            return self::primeraFuturaSinPublicar(null);
        }

        return $muros->sortBy(fn($m) => [$m['anio'], $m['semana']])->first();
    }

    /**
     * La primera semana posterior a la corriente que nadie ha publicado expresamente para este
     * departamento. Se avanza semana a semana mientras haya publicación explícita, así que para
     * en el primer hueco.
     *
     * @return array{anio:int, semana:int}
     */
    private static function primeraFuturaSinPublicar(?int $departamento): array
    {
        $hoy = new \DateTime();
        $publicadas = DB::table('vm_horarios_publicacion')
            ->where('publicado', true)
            ->when($departamento !== null, fn($q) => $q->where('id_departamento', $departamento))
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
     * ¿Puede el trabajador de este departamento ver esta semana en la PWA?
     */
    public static function visible(int $anio, int $semana, ?int $departamento = null): bool
    {
        $muro = self::primeraOculta($departamento);

        return [$anio, $semana] < [$muro['anio'], $muro['semana']];
    }

    /**
     * Última semana visible en formato ISO (YYYY-Www), para que la PWA sepa dónde parar de
     * navegar.
     */
    public static function ultimaVisible(?int $departamento = null): string
    {
        $muro = self::primeraOculta($departamento);

        // La anterior a la del muro. setISODate admite semana 0, que DateTime resuelve como la
        // última del año anterior, así que el cambio de año sale gratis.
        $fecha = new \DateTime();
        $fecha->setISODate($muro['anio'], $muro['semana'] - 1);

        return $fecha->format('o') . '-W' . $fecha->format('W');
    }

    /**
     * Estado de una semana concreta de un departamento (true = publicada). Sin fila, publicada si
     * no es futura.
     */
    public static function estaPublicada(int $anio, int $semana, int $departamento): bool
    {
        $valor = DB::table('vm_horarios_publicacion')
            ->where('anio', $anio)->where('semana', $semana)
            ->where('id_departamento', $departamento)
            ->value('publicado');

        if ($valor !== null) return (bool) $valor;

        $hoy = new \DateTime();

        return [$anio, $semana] <= [(int) $hoy->format('o'), (int) $hoy->format('W')];
    }

    /**
     * Publica u oculta una semana de un departamento.
     */
    public static function guardar(int $anio, int $semana, int $departamento, bool $publicado, ?int $usuario = null): void
    {
        $ahora = now();

        DB::table('vm_horarios_publicacion')->upsert(
            [[
                'anio'            => $anio,
                'semana'          => $semana,
                'id_departamento' => $departamento,
                'publicado'       => $publicado,
                'updateuser'      => $usuario,
                'createdat'       => $ahora,
                'updatedat'       => $ahora,
            ]],
            ['anio', 'semana', 'id_departamento'],
            ['publicado', 'updateuser', 'updatedat']
        );
    }
}
