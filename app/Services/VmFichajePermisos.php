<?php

namespace App\Services;

use App\Models\Project;
use App\Services\VmJerarquiaAprobacion;
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
    // Y sigue siendo solo de ellos: al abrir el límite de fecha a los supervisores (2026-10-08)
    // esto se dejó fuera a propósito. Es el único control que impide que alguien arregle el saldo
    // de horas de su propio equipo sin que quede rastro de rol.
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

        // La rama de APROBACIÓN se suma a la de roles, no la sustituye. Quien firma el informe
        // de alguien tiene que poder ver y corregir sus fichajes, aunque su rol no alcance a esa
        // persona -- y hasta ahora la visibilidad salía solo de los roles, así que el permiso de
        // edición habría prometido tocar fichajes que la visibilidad negaba. Misma unión que ya
        // hace el informe mensual en resolveParams() (decisión 2026-10-08).
        $rama = array_map('strval', VmJerarquiaAprobacion::ramaDe((int) $projectUserId));

        if (($role->todos_registros ?? null) === 'supervisados') {
            $porRoles = RoleHierarchy::visibleUserIds(
                $project->slug . '_roles',
                $project->slug . '_usuarios',
                (int) $projectUserId,
                (int) $role->id
            );

            return array_values(array_unique(array_merge(array_map('strval', $porRoles), $rama)));
        }

        return array_values(array_unique(array_merge([(string) $projectUserId], $rama)));
    }

    /**
     * Si puede crear o editar fichajes de fechas antiguas, sin el límite de los últimos días.
     *
     * Con $targetId, además de los roles de ROLES_SIN_LIMITE lo puede quien firme el informe de
     * esa persona: el supervisor firma el mes entero y con el límite de 2 días no podía corregir
     * lo que firma. Se resuelve por PERSONA y no por rol porque "supervisor" es una relación de
     * la ficha ("Supervisa a"), no un rol (decisión 2026-10-08).
     *
     * Sin $targetId se comporta como siempre -- hay sitios (la modal de alta, el dashboard) que
     * preguntan antes de saber de quién será el fichaje.
     */
    public static function puedeSinLimiteFecha(Project $project, ?int $targetId = null): bool
    {
        if (self::rolEnLista($project, self::ROLES_SIN_LIMITE)) return true;

        return $targetId !== null && self::esDeSuRamaDeAprobacion($project, $targetId);
    }

    /**
     * Si puede crear o editar el fichaje de esa persona: admin, Dirección general, Director de
     * RRHH, o quien firme su informe mensual. Incluye los propios fichajes, porque ramaDe()
     * incluye a la propia persona (decisión expresa 2026-10-08).
     */
    public static function puedeEditarFichajeDe(Project $project, int $targetId): bool
    {
        if (self::rolEnLista($project, self::ROLES_AJUSTE_HE)) return true;   // admin, 3 y 11

        return self::esDeSuRamaDeAprobacion($project, $targetId);
    }

    private static function esDeSuRamaDeAprobacion(Project $project, int $targetId): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $authUserId = $user->projectUserId($project);
        if (!$authUserId) return false;

        $rama = VmJerarquiaAprobacion::ramaDe((int) $authUserId);

        // ramaDe() incluye SIEMPRE a la propia persona, así que sin esta condición el permiso se
        // le concedería también a quien no supervisa a nadie: los 19 empleados sin rol
        // privilegiado podrían editar sus propios fichajes de cualquier mes, y eso no es lo que
        // se decidió -- la pregunta que se respondió era si un SUPERVISOR puede editar los
        // suyos. Para abrirlo a todos basta con quitar estas tres líneas.
        if (count($rama) <= 1) return false;

        return in_array($targetId, $rama, true);
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
