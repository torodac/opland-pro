<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Matriz de permisos: qué ve y qué edita cada rol en cada entrada del menú.
//
// La regla que hay que tener presente para leer la pantalla: en `vm_roles`, una lista VACÍA en
// "Puede ver" o "Puede editar" significa **sin restricción**, no "sin acceso" (ver
// User::canViewTable/canEditTable). Por eso un rol con las dos listas vacías aparece con todo
// concedido: es lo que le pasa hoy a Dirección general.
//
// Las entradas de menú sin tabla asociada no definen permiso ninguno: las ve todo el mundo, y se
// marcan como tales en vez de dejarlas en blanco, que se confundiría con "sin acceso".
class PermisosRolesController extends Controller
{
    public function index(Request $request, Project $project)
    {
        // ── Columnas: los roles, con los usuarios que los tienen ────────────
        $usuariosPorRol = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol'])
            ->groupBy('id_rol');

        $roles = DB::table('vm_roles')
            ->where('deleted', 0)
            ->orderBy('id')
            ->get(['id', 'nombre', 'ver', 'editar'])
            ->map(function ($r) use ($usuariosPorRol) {
                $ver    = json_decode($r->ver    ?? '[]', true) ?: [];
                $editar = json_decode($r->editar ?? '[]', true) ?: [];

                return (object) [
                    'id'         => (int) $r->id,
                    'nombre'     => $r->nombre,
                    'ver'        => $ver,
                    'editar'     => $editar,
                    've_todo'    => empty($ver),      // lista vacía = sin restricción
                    'edita_todo' => empty($editar),
                    'usuarios'   => ($usuariosPorRol[$r->id] ?? collect())->pluck('nombre')->all(),
                ];
            });

        // ── Filas: las entradas del menú, en el orden en que se pintan ──────
        $items = DB::table('admin_menu_items as m')
            ->leftJoin('admin_project_tables as t', 't.id', '=', 'm.project_table_id')
            ->where('m.project_id', $project->id)
            ->orderByRaw("coalesce(nullif(m.modulo, ''), 'zzz')")
            ->orderBy('m.order')
            ->get(['m.id', 'm.label', 'm.modulo', 'm.url', 't.name as tabla', 't.is_virtual']);

        $filas = [];
        foreach ($items as $it) {
            $celdas = [];

            foreach ($roles as $rol) {
                if ($it->tabla === null) {
                    // Sin tabla no hay permiso que comprobar: el ítem se pinta para todos.
                    $celdas[$rol->id] = 'sin_permiso';
                    continue;
                }

                $ve    = $rol->ve_todo    || in_array($it->tabla, $rol->ver, true);
                $edita = $rol->edita_todo || in_array($it->tabla, $rol->editar, true);

                $celdas[$rol->id] = match (true) {
                    $ve && $edita  => 'editar',
                    $ve            => 'ver',
                    // Editar sin poder ver es incoherente: la pantalla no aparece pero el
                    // permiso de edición sí está concedido. Se marca aparte a propósito.
                    $edita         => 'edita_sin_ver',
                    default        => 'nada',
                };
            }

            $filas[] = (object) [
                'label'      => $it->label,
                'modulo'     => $it->modulo ?: '—',
                'tabla'      => $it->tabla,
                'is_virtual' => (bool) $it->is_virtual,
                'celdas'     => $celdas,
            ];
        }

        return view('vm.permisos-roles', [
            'project'    => $project,
            'roles'      => $roles,
            'filas'      => $filas,
            'breadcrumb' => [['label' => 'Permisos por rol', 'url' => '']],
        ]);
    }
}
