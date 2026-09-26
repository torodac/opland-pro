<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTable;

// Impide que un registro se apunte a sí mismo en un campo que referencia a su propia tabla.
//
// El caso que lo motiva es vm_usuarios.id_aprueba_informe ("Aprueba informe"): nada impedía
// poner a la propia persona como quien aprueba su informe mensual, es decir, autoaprobarse el
// primer paso del circuito de firma. Pero la regla no es de ese campo ni de ese proyecto: en
// cualquier campo autorreferenciado (un usuario que se supervisa, una propiedad que es su propia
// propiedad padre, una cuenta que es su propia cuenta madre) el valor es siempre un error.
//
// Se aplica en los dos sitios: el desplegable de la ficha ya no ofrece el propio registro
// (FichaController::loadFkOptions) y el guardado lo rechaza aunque la petición venga a mano
// (update / updateField / bulkUpdate). Solo lo primero se saltaría con un POST directo.
class AutoreferenciaGuard
{
    // Nombres de los campos de $projectTable cuyo "ref:" apunta a la propia tabla.
    public static function campos(Project $project, ProjectTable $projectTable): array
    {
        $propia = $projectTable->getFullTableName();

        return $projectTable->fields
            ->filter(fn($f) => $f->getRefFullTable($project->slug) === $propia)
            ->pluck('name')
            ->all();
    }

    // Mensaje de error si alguno de los datos a guardar apunta a $registroId, o null si todo
    // bien. $registroIds permite validar una actualización masiva de una sola pasada.
    public static function error(Project $project, ProjectTable $projectTable, array $datos, array $registroIds): ?string
    {
        $campos = self::campos($project, $projectTable);
        if (!$campos) return null;

        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $datos)) continue;

            $valor = $datos[$campo];
            if ($valor === null || $valor === '') continue;

            if (in_array((int) $valor, array_map('intval', $registroIds), true)) {
                $label = $projectTable->fields->firstWhere('name', $campo)?->label ?? $campo;
                return "El campo \"{$label}\" no puede apuntar al propio registro.";
            }
        }

        return null;
    }
}
