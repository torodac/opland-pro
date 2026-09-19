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
