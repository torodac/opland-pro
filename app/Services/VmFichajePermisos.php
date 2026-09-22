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
    // Quién puede crear/editar fichajes de cualquier fecha; el resto, solo de los últimos días
    // (ver DIAS_LIMITE). Además de Dirección general (3) y Director de RRHH (11), desde el
    // 2026-09-22 también el área de Operaciones -- Dirección de Operaciones (10), Coordinador de
    // limpieza (2) y Coordinador de mantenimiento (5) --, que es quien tiene que resolver el
    // bloque "Incidencias de fichajes con turnos y descansos del Horario" del dashboard: ese
    // bloque lista fechas pasadas y con el límite de 2 días nunca podían dar de alta el fichaje
    // que falta.
    public const ROLES_SIN_LIMITE = [3, 11, 10, 2, 5];

    // Ver y editar el ajuste manual de horas extra (vm_fichaje.ajuste_he) sigue siendo cosa de
    // Dirección general y Director de RRHH. Vivía pegado a ROLES_SIN_LIMITE, y al abrir ese al
    // área de Operaciones les habría dado también esto, que es otra cosa: tocar a mano el saldo
    // de horas de una persona.
    public const ROLES_AJUSTE_HE = [3, 11];

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
        return self::rolEnLista($project, self::ROLES_SIN_LIMITE);
    }

    /**
     * Si puede ver y editar el ajuste manual de horas extra de un fichaje.
     */
    public static function puedeAjustarHe(Project $project): bool
    {
        return self::rolEnLista($project, self::ROLES_AJUSTE_HE);
    }

    /** @param int[] $roles */
    private static function rolEnLista(Project $project, array $roles): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isAdmin() || $user->isProjectAdmin($project)) return true;

        $authUserId = $user->projectUserId($project);
        $authRol    = $authUserId
            ? DB::table($project->slug . '_usuarios')->where('id', $authUserId)->value('id_rol')
            : null;

        return in_array((int) $authRol, $roles, true);
    }

    /**
     * Fecha más antigua para la que se puede crear un fichaje sin ser Dirección/RRHH.
     */
    public static function fechaMinima(): string
    {
        return now()->subDays(self::DIAS_LIMITE)->toDateString();
    }
}
