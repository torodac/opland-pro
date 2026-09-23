<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Único sitio donde se decide qué tipos de ausencia se ofrecen. Vivía duplicado palabra por
// palabra en AusenciaController y VmUsuarioController, con la diferencia de que solo una de las
// dos copias aplicaba el filtro de tipos retirados.
class VmAusenciaTipos
{
    // Tipos que siguen existiendo en registros antiguos pero ya no se ofrecen al dar de alta:
    // "Compensación" se sustituyó por "Comp. horas" y "Comp. festivo", que sí dicen qué se
    // compensa. Los registros históricos se siguen viendo con su texto.
    public const RETIRADOS = ['Compensación'];

    // Etiqueta de cada tipo de horario, que es a la vez el tipo de ausencia equivalente: el
    // cuadrante guarda claves ('comp_festivo') y las ausencias el texto del catálogo
    // ('Comp. festivo'). Lo usan el planificador, la PWA, el dashboard y la conciliación que crea
    // la ausencia a partir del horario.
    public const LABEL_HORARIO = [
        'turno'        => 'Turno',
        'descanso'     => 'Descanso',
        'vacaciones'   => 'Vacaciones',
        'baja'         => 'Baja',
        'comp_festivo' => 'Comp. festivo',
        'comp_horas'   => 'Comp. horas',
        'asuntos'      => 'Asuntos propios',
        'absentismo'   => 'Absentismo',
    ];

    public static function labelHorario(?string $tipoHorario): string
    {
        return self::LABEL_HORARIO[$tipoHorario] ?? (string) $tipoHorario;
    }

    /**
     * ¿El tipo del cuadrante dice lo mismo que el de la ausencia registrada? Se compara por la
     * etiqueta, no por la cadena cruda, porque las dos tablas guardan formatos distintos. Un tipo
     * de horario desconocido se da por bueno, para no inventar conflictos sobre datos que no
     * entendemos.
     */
    public static function coincideConHorario(?string $tipoHorario, ?string $tipoAusencia): bool
    {
        if (!isset(self::LABEL_HORARIO[$tipoHorario])) return true;

        return self::LABEL_HORARIO[$tipoHorario] === $tipoAusencia;
    }

    /** @return string[] */
    public static function opciones(): array
    {
        $field = DB::table('admin_table_fields as tf')
            ->join('admin_project_tables as pt', 'tf.project_table_id', '=', 'pt.id')
            ->where('pt.name', 'ausencias')
            ->where('tf.name', 'tipo')
            ->value('tf.extras');

        if (!$field) return [];

        $tipos = array_map('trim', explode(',', str_replace('opt:', '', $field)));

        return array_values(array_filter($tipos, fn ($t) => !in_array($t, self::RETIRADOS, true)));
    }
}
