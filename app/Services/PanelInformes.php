<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Quién entra en el panel de aprobaciones de un mes.
//
// Los paneles listaban a todos los usuarios no borrados, sin mirar si la persona trabajaba ese
// mes: quien entraba más tarde aparecía hacia atrás en TODOS los meses con el informe vacío, y
// quien se daba de baja seguía apareciendo hacia delante para siempre. El caso que lo destapó:
// Francisco Jose Vega Lara, con contrato desde el 28/09/2026, salía en abril y en agosto con el
// mes entero marcado como "sin horario".
class PanelInformes
{
    // Fuera de los paneles siempre, por petición expresa y no por un criterio genérico:
    //   1  Santi Ramón-Llin  -- ficha de Dirección general, no pasa por el flujo de aprobación
    //   25 Ugo               -- sin contrato
    //   67 Julian Romaguera  -- sin contrato
    public const USUARIOS_EXCLUIDOS = [1, 25, 67];

    // Rol externo sin informe mensual propio: Proveedor limpieza.
    public const ROLES_EXCLUIDOS = [6];

    /**
     * Ids de los usuarios con contrato vigente en algún momento del mes. Mismo criterio que usa
     * el informe de RRHH para la plantilla activa: el contrato tiene que solaparse con el mes,
     * no empezar ni acabar dentro de él.
     *
     * @return array<int, int>
     */
    public static function conContratoEnMes(int $year, int $month): array
    {
        $ini = sprintf('%04d-%02d-01', $year, $month);
        $fin = date('Y-m-t', strtotime($ini));

        return DB::table('vm_contratos')
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->where('fecha_alta', '<=', $fin)
            ->where(fn($q) => $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>=', $ini))
            ->distinct()
            ->pluck('id_usuarios')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /** Aplica a una colección de usuarios las tres exclusiones del panel. */
    public static function filtrar($usuarios, int $year, int $month)
    {
        $conContrato = array_flip(self::conContratoEnMes($year, $month));

        return $usuarios
            ->reject(fn($u) => in_array((int) $u->id, self::USUARIOS_EXCLUIDOS, true))
            ->reject(fn($u) => in_array((int) ($u->id_rol ?? 0), self::ROLES_EXCLUIDOS, true))
            ->reject(fn($u) => !isset($conContrato[(int) $u->id]))
            ->values();
    }
}
