<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

// Quién puede publicar el cuadrante de cada departamento.
//
// El permiso NO es una regla de rol: es un dato de la ficha del usuario (vm_usuarios.
// publica_horarios, lista de ids de departamento). Esa es la diferencia que importa en la
// práctica: cubrir las vacaciones de un coordinador es añadirle su departamento a quien le
// sustituye y quitárselo al volver, desde el front y sin desplegar nada. Con una regla de rol
// haría falta tocar código o inventar un rol de suplente.
//
// Dirección general y Director de RRHH mantienen acceso a todos los departamentos, igual que en
// el resto del sistema: firman y responden de lo que publique cualquiera.
class VmPublicaHorarios
{
    /** Roles que alcanzan a todos los departamentos sin necesidad de llevarlos en la ficha. */
    public const ROLES_TODOS = [3, 11];

    /**
     * Ids de departamento que esta persona puede publicar.
     *
     * @return int[]
     */
    public static function departamentosDe(Project $project, $user): array
    {
        if (!$user) {
            return [];
        }

        if ($user->isProjectAdmin($project) || in_array(self::rolDe($project, $user), self::ROLES_TODOS, true)) {
            return self::visibles();
        }

        $vmUserId = $user->projectUserId($project);
        if (!$vmUserId) {
            return [];
        }

        $lista = json_decode(
            (string) DB::table('vm_usuarios')->where('id', $vmUserId)->value('publica_horarios'),
            true
        ) ?: [];

        // Se cruza con los departamentos visibles: uno que deje de llevar cuadrante no debe seguir
        // publicándose solo porque quedó escrito en una ficha.
        return array_values(array_intersect(array_map('intval', $lista), self::visibles()));
    }

    /** ¿Puede esta persona publicar el cuadrante de este departamento? */
    public static function puede(Project $project, $user, int $departamento): bool
    {
        return in_array($departamento, self::departamentosDe($project, $user), true);
    }

    /** Departamentos que llevan cuadrante. */
    private static function visibles(): array
    {
        return DB::table('vm_departamentos')
            ->where('visible_horarios', true)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private static function rolDe(Project $project, $user): ?int
    {
        $vmUserId = $user->projectUserId($project);

        return $vmUserId
            ? (int) DB::table('vm_usuarios')->where('id', $vmUserId)->value('id_rol')
            : null;
    }
}
