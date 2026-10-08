<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

// A quién puede elegir cada persona en el desplegable de un informe mensual (imputaciones o
// kilómetros).
//
// Vivía dentro de InformeImputacionesController::resolveParams(). El informe de kilómetros tenía
// su propia versión, mucho más corta: solo Dirección general, RRHH y administradores podían
// elegir usuario, así que un supervisor no podía abrir -- ni por tanto aprobar-- el informe de
// kilómetros de su gente. Se extrae para que los dos usen la MISMA lógica por construcción, en
// lugar de una copia que pueda divergir (2026-10-08).
class AlcanceInformes
{
    // Ven y pueden elegir a cualquiera, y generar el PDF de todos.
    public const ROLES_TODOS = [
        VmJerarquiaAprobacion::ROL_DIRECCION_GENERAL,
        VmJerarquiaAprobacion::ROL_DIRECTOR_RRHH,
    ];

    // Eligen dentro de su equipo según la jerarquía de ROLES (vm_roles.roles_supervisados),
    // el mismo mecanismo que fichajes y listados: Dirección de Operaciones, Coordinador de
    // mantenimiento y Coordinador de limpieza.
    public const ROLES_EQUIPO = [10, 5, 2];

    /**
     * @return array{usuarios: \Illuminate\Support\Collection, canSelect: bool, canSelectTodos: bool, vmUserId: ?int, rol: ?int}
     */
    public static function para(Project $project, $user, bool $isAdmin): array
    {
        $vmUserId = $user->projectUserId($project);
        $rol      = $vmUserId ? (int) DB::table($project->slug . '_usuarios')->where('id', $vmUserId)->value('id_rol') : null;

        $canSelectTodos  = $isAdmin || in_array($rol, self::ROLES_TODOS, true);
        $canSelectEquipo = !$canSelectTodos && in_array($rol, self::ROLES_EQUIPO, true);

        if ($canSelectTodos) {
            return [
                'usuarios'       => DB::table($project->slug . '_usuarios')->where('deleted', 0)
                    ->orderBy('nombre')->get(['id', 'nombre', 'id_rol', 'admin_user_id']),
                'canSelect'      => true,
                'canSelectTodos' => true,
                'vmUserId'       => $vmUserId ? (int) $vmUserId : null,
                'rol'            => $rol,
            ];
        }

        // Unión de las DOS jerarquías, no sustitución (decisión 2026-09-24):
        //   - roles      → RoleHierarchy sobre vm_roles.roles_supervisados;
        //   - aprobación → la rama que cuelga de esta persona en "Supervisa a".
        // Lo segundo solo AÑADE: quien tiene que firmar un informe necesita poder abrirlo aunque
        // su rol no alcance a esa persona -- el caso de Dirección contabilidad, que supervisa
        // Contabilidad y Transformación digital pero no figura en ninguna de las listas de roles.
        $porRoles = $canSelectEquipo && $vmUserId
            ? RoleHierarchy::visibleUserIds(
                $project->slug . '_roles', $project->slug . '_usuarios', (int) $vmUserId, (int) $rol
              )
            : [];

        $porAprobacion = $vmUserId ? VmJerarquiaAprobacion::ramaDe((int) $vmUserId) : [];

        $ids = array_values(array_unique(array_merge(
            array_map('intval', $porRoles),
            array_map('intval', $porAprobacion)
        )));

        $usuarios = $ids
            ? DB::table($project->slug . '_usuarios')->where('deleted', 0)->whereIn('id', $ids)
                ->orderBy('nombre')->get(['id', 'nombre', 'id_rol', 'admin_user_id'])
            : collect();

        return [
            'usuarios'       => $usuarios,
            // Se puede elegir en cuanto hay alguien más que uno mismo en la lista.
            'canSelect'      => $usuarios->count() > 1,
            'canSelectTodos' => false,
            'vmUserId'       => $vmUserId ? (int) $vmUserId : null,
            'rol'            => $rol,
        ];
    }

    /**
     * El usuario del informe que se va a mostrar: el pedido si está a su alcance, y si no el
     * propio. Manipular el parámetro no sirve para ver el informe de otro.
     */
    public static function usuarioElegido(array $alcance, $pedido): int
    {
        if (!$alcance['canSelect']) {
            return (int) ($alcance['vmUserId'] ?? 0);
        }

        $userId = (int) ($pedido ?? $alcance['vmUserId'] ?? ($alcance['usuarios']->first()->id ?? 0));

        if (!$alcance['canSelectTodos'] && !$alcance['usuarios']->contains('id', $userId)) {
            return (int) ($alcance['vmUserId'] ?? 0);
        }

        return $userId;
    }
}
