<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Jerarquías: visualiza en forma de árbol los DOS organigramas que conviven en VM y que hoy
// gobiernan cosas distintas del Informe mensual:
//
//   1. Roles    -- vm_roles.roles_supervisados (una lista de ids de rol por rol). Es la que usa
//                  RoleHierarchy para decidir a quién ve cada usuario (informe, fichajes,
//                  listados, dashboard y PWA). Relación entre ROLES, no entre personas.
//   2. Aprueba  -- vm_usuarios.id_aprueba_informe. Relación entre PERSONAS concretas, pensada
//                  para el circuito de firma del informe mensual.
//
// La página es de solo lectura y no impone gate de rol propio: el acceso se configura desde
// /config/projects sobre la tabla virtual del menú, igual que informe-financiero/operativo.
class JerarquiasController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $roles = DB::table('vm_roles')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get(['id', 'nombre', 'roles_supervisados']);

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol', 'id_aprueba_informe']);

        [$arbolRoles, $sueltosRoles]     = $this->arbolRoles($roles, $usuarios);
        [$arbolAprueba, $sueltosAprueba] = $this->arbolAprueba($roles, $usuarios);

        return view('vm.jerarquias', [
            'project'         => $project,
            'arbol_roles'     => $arbolRoles,
            'sueltos_roles'   => $sueltosRoles,
            'arbol_aprueba'   => $arbolAprueba,
            'sueltos_aprueba' => $sueltosAprueba,
            'breadcrumb'      => [
                ['label' => 'Jerarquías', 'url' => ''],
            ],
        ]);
    }

    // ── Árbol de roles ────────────────────────────────────────────────────────
    // Nodo = un rol, con los trabajadores que lo tienen. Raíz = rol al que nadie supervisa y que
    // sí supervisa a alguien. "Suelto" = rol sin relación en ninguna dirección (ni supervisa ni
    // es supervisado), como hoy Dirección general o Director RRHH, con roles_supervisados = [].
    private function arbolRoles($roles, $usuarios): array
    {
        $porRol = [];
        foreach ($usuarios as $u) {
            $porRol[(int) $u->id_rol][] = $u->nombre;
        }

        $hijos = [];
        $supervisados = [];
        foreach ($roles as $r) {
            $subs = json_decode($r->roles_supervisados ?? '[]', true) ?: [];
            $subs = array_values(array_unique(array_map('intval', $subs)));
            $hijos[(int) $r->id] = $subs;
            foreach ($subs as $s) $supervisados[$s] = true;
        }

        $meta = [];
        foreach ($roles as $r) {
            $id = (int) $r->id;
            $meta[$id] = [
                'id'          => $id,
                'titulo'      => $r->nombre,
                'subtitulo'   => null,
                'personas'    => $porRol[$id] ?? [],
                'vacio_texto' => 'Sin trabajadores con este rol',
            ];
        }

        $raices  = [];
        $sueltos = [];
        foreach ($roles as $r) {
            $id = (int) $r->id;
            if (isset($supervisados[$id])) continue;          // cuelga de otro rol
            if (!empty($hijos[$id])) $raices[]  = $id;         // es cabecera de un árbol
            else                     $sueltos[] = $meta[$id];  // sin relación en ninguna dirección
        }

        $arbol = array_map(fn($id) => $this->construir($id, $hijos, $meta, []), $raices);

        return [$arbol, $sueltos];
    }

    // ── Árbol de aprobación ───────────────────────────────────────────────────
    // Nodo = una persona, con su rol debajo. Raíz = persona sin aprobador que sí aprueba a
    // alguien. "Suelto" = persona sin aprobador y a la que nadie tiene asignada.
    private function arbolAprueba($roles, $usuarios): array
    {
        $nombreRol = $roles->pluck('nombre', 'id');
        $activos   = $usuarios->pluck('id')->map(fn($id) => (int) $id)->flip();

        $hijos = [];
        $meta  = [];
        foreach ($usuarios as $u) {
            $id  = (int) $u->id;
            $rol = (int) $u->id_rol;
            $meta[$id] = [
                'id'          => $id,
                'titulo'      => $u->nombre,
                // El rol va como subtítulo con su id delante, en el mismo formato que la pastilla
                // del árbol de roles, para poder cruzar las dos pestañas de un vistazo.
                'subtitulo'   => $rol ? $rol . ' · ' . ($nombreRol[$rol] ?? 'rol desconocido') : 'Sin rol asignado',
                'personas'    => [],
                'vacio_texto' => null,
            ];
        }

        foreach ($usuarios as $u) {
            $apr = $u->id_aprueba_informe ? (int) $u->id_aprueba_informe : null;
            // Un aprobador borrado o inexistente se trata como "sin aprobador", para que la
            // persona no desaparezca del árbol por un dato colgado.
            if ($apr === null || !$activos->has($apr) || $apr === (int) $u->id) continue;
            $hijos[$apr][] = (int) $u->id;
        }

        $raices  = [];
        $sueltos = [];
        foreach ($usuarios as $u) {
            $id  = (int) $u->id;
            $apr = $u->id_aprueba_informe ? (int) $u->id_aprueba_informe : null;
            $tieneAprobador = $apr !== null && $activos->has($apr) && $apr !== $id;
            if ($tieneAprobador) continue;
            if (!empty($hijos[$id])) $raices[]  = $id;
            else                     $sueltos[] = $meta[$id];
        }

        $arbol = array_map(fn($id) => $this->construir($id, $hijos, $meta, []), $raices);

        return [$arbol, $sueltos];
    }

    // Monta recursivamente un nodo y sus descendientes. $camino corta los ciclos: en aprobación
    // nada impide hoy que A apruebe a B y B a A, y sin este corte la recursión no terminaría.
    private function construir(int $id, array $hijos, array $meta, array $camino): array
    {
        $nodo = $meta[$id] ?? ['id' => $id, 'titulo' => '#' . $id, 'subtitulo' => null, 'personas' => [], 'vacio_texto' => null];

        if (in_array($id, $camino, true)) {
            $nodo['ciclo']  = true;
            $nodo['hijos']  = [];
            return $nodo;
        }

        $camino[] = $id;
        $nodo['ciclo'] = false;
        $nodo['hijos'] = array_map(
            fn($h) => $this->construir($h, $hijos, $meta, $camino),
            $hijos[$id] ?? []
        );

        return $nodo;
    }
}
