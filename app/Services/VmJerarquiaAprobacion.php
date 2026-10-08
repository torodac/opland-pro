<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Jerarquía de aprobación del informe mensual, definida en el campo "Supervisa a" de la ficha
// del responsable (vm_usuarios.supervisados): la lista de las personas cuyo informe firma.
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

    /**
     * [id_usuario => id_aprobador] de los usuarios activos.
     *
     * La fuente es **vm_usuarios.supervisados**: la lista, en la ficha de cada responsable, de
     * las personas cuyo informe firma. Se informa así desde el 2026-10-08; antes estaba al
     * revés, en un desplegable "Aprueba informe" en la ficha de cada trabajador
     * (`id_aprueba_informe`), que se conserva en la base de datos pero ya no se lee ni se
     * ofrece en la ficha. Ver 3.124 del DOC_TECNICO.
     *
     * Se descartan los usuarios borrados o inexistentes y la autosupervisión. Si una persona
     * apareciera en DOS listas, gana la del responsable de id más bajo y se deja constancia en
     * el log: el circuito de firmas asume un único aprobador y, sin este aviso, el informe lo
     * firmaría quien saliera primero en la consulta. MultiusuarioGuard lo impide al guardar, así
     * que llegar aquí significa que algo entró por otra vía.
     */
    public static function mapaAprobadores(): array
    {
        $filas = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get(['id', 'supervisados']);

        $activos = [];
        foreach ($filas as $f) $activos[(int) $f->id] = true;

        $mapa = [];
        foreach ($filas as $f) {
            $responsable = (int) $f->id;

            foreach (json_decode($f->supervisados ?? '[]', true) ?: [] as $supervisado) {
                $sid = (int) $supervisado;

                if ($sid === $responsable || !isset($activos[$sid])) continue;

                if (isset($mapa[$sid])) {
                    Log::warning("VmJerarquiaAprobacion: el usuario {$sid} está en la lista "
                        . "\"Supervisa a\" de {$mapa[$sid]} y de {$responsable}. Se usa el primero; "
                        . 'corrige una de las dos fichas.');
                    continue;
                }

                $mapa[$sid] = $responsable;
            }
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
