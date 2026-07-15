<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve la jerarquia de supervision entre roles de un proyecto,
 * a partir de la columna {slug}_roles.roles_supervisados (json con ids de rol).
 */
class RoleHierarchy
{
    // IDs de roles subordinados (directos e indirectos) a partir de un rol raiz
    public static function subordinateRoleIds(string $rolesTable, int $rootRoleId): array
    {
        if (!Schema::hasTable($rolesTable) || !Schema::hasColumn($rolesTable, 'roles_supervisados')) {
            return [];
        }

        $allRoles = DB::table($rolesTable)
            ->where('deleted', 0)
            ->get(['id', 'roles_supervisados'])
            ->keyBy('id');

        $root = $allRoles[$rootRoleId] ?? null;
        if (!$root) return [];

        $directSubs = json_decode($root->roles_supervisados ?? '[]', true) ?? [];
        if (empty($directSubs)) return [];

        $visited = [];
        $toVisit = $directSubs;

        while (!empty($toVisit)) {
            $roleId = array_shift($toVisit);
            if (in_array($roleId, $visited)) continue;
            $visited[] = $roleId;
            $r = $allRoles[$roleId] ?? null;
            if (!$r) continue;
            $subs = json_decode($r->roles_supervisados ?? '[]', true) ?? [];
            foreach ($subs as $sub) {
                if (!in_array($sub, $visited)) $toVisit[] = $sub;
            }
        }

        return $visited;
    }

    // IDs de usuario (propio + equipo supervisado) visibles para un usuario del proyecto
    public static function visibleUserIds(string $rolesTable, string $usuariosTable, int $ownUserId, ?int $roleId): array
    {
        $ids = [(string) $ownUserId];
        if (!$roleId) return $ids;

        $subRoleIds = self::subordinateRoleIds($rolesTable, $roleId);
        if (empty($subRoleIds)) return $ids;

        $subUserIds = DB::table($usuariosTable)
            ->whereIn('id_rol', $subRoleIds)
            ->where('deleted', 0)
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        return array_values(array_unique(array_merge($ids, $subUserIds)));
    }
}
