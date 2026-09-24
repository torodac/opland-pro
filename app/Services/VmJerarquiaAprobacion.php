<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Jerarquía de aprobación del informe mensual, definida persona a persona en el campo
// "Aprueba informe" de la ficha del usuario (vm_usuarios.id_aprueba_informe).
//
// Convive con RoleHierarchy (vm_roles.roles_supervisados) sin sustituirla: la jerarquía de
// ROLES sigue gobernando la visibilidad general (fichajes, listados, dashboard, PWA y el
// selector de la ficha del informe); esta gobierna QUIÉN FIRMA y, en el panel de aprobaciones,
// a quién ve cada usuario. Ver la pantalla /vm/jerarquias, que pinta las dos.
class VmJerarquiaAprobacion
{
    // Dirección general: firma siempre el último paso del circuito. Cuando es además quien
    // figura en la celda "Aprueba informe", el primer paso se salta -- no tiene sentido que
    // firme dos veces el mismo informe.
    public const ROL_DIRECCION_GENERAL = 3;
    public const ROL_DIRECTOR_RRHH     = 11;

    // Roles que ven a TODOS los trabajadores en el panel de aprobaciones aunque no aprueben a
    // nadie en esta jerarquía: firman un paso global (RRHH el segundo, Dirección el cuarto) y
    // sin esto no podrían firmar más que a su propia rama.
    public const ROLES_PANEL_COMPLETO = [self::ROL_DIRECCION_GENERAL, self::ROL_DIRECTOR_RRHH];

    // Además del titular de la celda, pueden firmar el paso "Supervisor" en su lugar, para que
    // una baja o unas vacaciones no bloqueen el cierre de mes (decisión explícita 2026-09-24).
    public const ROLES_FIRMAN_POR_SUPERVISOR = [self::ROL_DIRECCION_GENERAL, self::ROL_DIRECTOR_RRHH];

    // [id_usuario => id_aprobador] de los usuarios activos, descartando referencias a usuarios
    // borrados o inexistentes y la autoaprobación (que la ficha hoy no impide).
    public static function mapaAprobadores(): array
    {
        $filas = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->get(['id', 'id_aprueba_informe']);

        $activos = [];
        foreach ($filas as $f) $activos[(int) $f->id] = true;

        $mapa = [];
        foreach ($filas as $f) {
            $id  = (int) $f->id;
            $apr = $f->id_aprueba_informe ? (int) $f->id_aprueba_informe : null;
            if ($apr === null || $apr === $id || !isset($activos[$apr])) continue;
            $mapa[$id] = $apr;
        }

        return $mapa;
    }

    public static function aprobadorDe(int $userId): ?int
    {
        return self::mapaAprobadores()[$userId] ?? null;
    }

    // Todos los usuarios que cuelgan de $userId en la cadena, incluyéndolo a él. Recorrido
    // transitivo: quien aprueba a un coordinador ve también al equipo de ese coordinador.
    // El conjunto de visitados corta los ciclos (hoy nada impide en la ficha que A apruebe a B
    // y B a A).
    public static function ramaDe(int $userId): array
    {
        $mapa = self::mapaAprobadores();

        $hijos = [];
        foreach ($mapa as $id => $apr) $hijos[$apr][] = $id;

        $rama    = [$userId => true];
        $pendientes = [$userId];
        while ($pendientes) {
            $actual = array_shift($pendientes);
            foreach ($hijos[$actual] ?? [] as $hijo) {
                if (isset($rama[$hijo])) continue;
                $rama[$hijo]  = true;
                $pendientes[] = $hijo;
            }
        }

        return array_keys($rama);
    }

    // Paso en el que arranca el circuito para este usuario. Se salta "aprueba" cuando no tiene
    // aprobador asignado o cuando quien lo aprueba es Dirección general (que ya firma el último
    // paso): en ambos casos el informe empieza directamente en RRHH.
    public static function pasoInicial(int $userId): string
    {
        $aprobador = self::aprobadorDe($userId);
        if (!$aprobador) return 'rrhh';

        $rolAprobador = (int) DB::table('vm_usuarios')->where('id', $aprobador)->value('id_rol');

        return $rolAprobador === self::ROL_DIRECCION_GENERAL ? 'rrhh' : 'aprueba';
    }

    // ¿Puede $vmUserId (con rol $authRol) firmar el paso "Supervisor" del informe de $targetId?
    // Lo firma el titular de la celda; admin, Dirección general y RRHH pueden hacerlo en su
    // lugar para desatascar.
    public static function puedeFirmarAprueba(?int $vmUserId, int $targetId, bool $isAdmin, ?int $authRol): bool
    {
        if ($isAdmin) return true;
        if (in_array((int) $authRol, self::ROLES_FIRMAN_POR_SUPERVISOR, true)) return true;

        return $vmUserId !== null && self::aprobadorDe($targetId) === $vmUserId;
    }

    public static function vePanelCompleto(bool $isAdmin, ?int $authRol): bool
    {
        return $isAdmin || in_array((int) $authRol, self::ROLES_PANEL_COMPLETO, true);
    }
}
