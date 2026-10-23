<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Quién ve una tarea SSCC concreta. Es la misma regla que el listado (ListadoController::aplicarVisibilidadSscc),
 * pero aplicada a una tarea: la ficha, el comentario, el Hecho y las fotos de retención la usan, para que una
 * tarea no sea accesible por la URL aunque no salga en el listado.
 *
 * La tarea debe traer: control_user, id_departamento e id_rol.
 */
class SsccVisibilidad
{
    public static function puedeVer(Project $project, object $tarea): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($user->isProjectAdmin($project)) {
            return true;
        }

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) {
            return false;
        }

        $slug = $project->slug;
        $yo   = DB::table($slug . '_usuarios')->where('id', $projectUserId)->first(['id', 'id_rol', 'id_departamento']);
        $rol  = $yo?->id_rol
            ? DB::table($slug . '_roles')->where('id', $yo->id_rol)->first(['id', 'todos_registros'])
            : null;

        // Un rol que ve todos los registros no tiene restricción.
        if (($rol->todos_registros ?? null) === 'todos') {
            return true;
        }

        $visibles = ($rol->todos_registros ?? null) === 'supervisados'
            ? RoleHierarchy::visibleUserIds($slug . '_roles', $slug . '_usuarios', (int) $projectUserId, (int) $rol->id)
            : [(int) $projectUserId];
        $visibles = array_map('intval', $visibles);

        // Asignada a alguien: se ve si ese alguien está entre los visibles. control_user guarda ids
        // como [2] o como ["2"], según cómo se escribiera.
        $asignados = json_decode((string) $tarea->control_user, true);
        if (is_numeric($asignados)) {
            $asignados = [$asignados];
        }
        if (is_array($asignados) && $asignados) {
            foreach ($asignados as $asignado) {
                if (in_array((int) $asignado, $visibles, true)) {
                    return true;
                }
            }

            return false;
        }

        // Sin nadie asignado: se ve si es de su departamento o de su rol.
        return ($yo->id_departamento && (int) $tarea->id_departamento === (int) $yo->id_departamento)
            || ($yo->id_rol && (int) $tarea->id_rol === (int) $yo->id_rol);
    }
}
