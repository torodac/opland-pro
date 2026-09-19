<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

// Permisos de fichaje compartidos entre FichajeController, el dashboard y el listado por
// usuario/mes. Vivían dentro de FichajeController -- la visibilidad como método privado y el
// límite de fecha repetido en tres sitios -- y hacían falta fuera para decidir qué empleados
// ofrecer en la modal de alta y si enseñar o no el botón de crear.
class VmFichajePermisos
{
    // Dirección general y Director de RRHH pueden crear/editar fichajes de cualquier fecha; el
    // resto solo de los últimos días (ver DIAS_LIMITE).
    public const ROLES_SIN_LIMITE = [3, 11];

    public const DIAS_LIMITE = 2;

    /**
     * Ids de vm_usuarios que el usuario autenticado puede ver, o null si los ve todos.
     */
    public static function usuariosVisibles(Project $project): ?array
    {
        $user = auth()->user();
        if (!$user || $user->isProjectAdmin($project)) return null;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return null;

        $role = $user->getProjectRolePublic($project);
        if (!$role || ($role->todos_registros ?? null) === 'todos') return null;

        if (($role->todos_registros ?? null) === 'supervisados') {
            return RoleHierarchy::visibleUserIds(
                $project->slug . '_roles',
                $project->slug . '_usuarios',
                (int) $projectUserId,
                (int) $role->id
            );
        }

        return [(string) $projectUserId];
    }

    /**
     * Si puede crear o editar fichajes de fechas antiguas, sin el límite de los últimos días.
     */
    public static function puedeSinLimiteFecha(Project $project): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isAdmin() || $user->isProjectAdmin($project)) return true;

        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId
            ? DB::table($project->slug . '_usuarios')->where('id', $authUserId)->value('id_rol')
            : null;

        return in_array((int) $authRol, self::ROLES_SIN_LIMITE, true);
    }

    /**
     * Fecha más antigua para la que se puede crear un fichaje sin ser Dirección/RRHH.
     */
    public static function fechaMinima(): string
    {
        return now()->subDays(self::DIAS_LIMITE)->toDateString();
    }
}
