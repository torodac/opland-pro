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
            ->map(function ($r) use ($usuariosPorRol, $project) {
                $ver    = json_decode($r->ver    ?? '[]', true) ?: [];
                $editar = json_decode($r->editar ?? '[]', true) ?: [];

                return (object) [
                    'id'         => (int) $r->id,
                    'nombre'     => $r->nombre,
                    'url'        => route('ficha', [$project->slug, 'roles', $r->id]),
                    'ver'        => $ver,
                    'editar'     => $editar,
                    've_todo'    => empty($ver),      // lista vacía = sin restricción
                    'edita_todo' => empty($editar),
                    'usuarios'   => ($usuariosPorRol[$r->id] ?? collect())->pluck('nombre')->all(),
                ];
            });

        // ── Filas: las mismas entradas del sidebar y en su mismo orden ──────
        // Se reutiliza la relación que usa el sidebar y se le aplica la misma reordenación por
        // modulo_order (ver components/app-layout.blade.php). Reconstruir aquí el orden a mano
        // sería empezar a divergir a la primera vez que alguien reordene los módulos.
        $moduloOrder = array_flip(array_map('strval', $project->modulo_order ?? []));

        $items = $project->menuItems
            ->values()
            ->sortBy(fn($i) => $moduloOrder[(string) $i->modulo] ?? 9999)   // sort estable
            ->values();

        $filas = [];
        foreach ($items as $it) {
            $celdas = [];
            $tabla  = $it->projectTable?->name;

            foreach ($roles as $rol) {
                if ($tabla === null) {
                    // Sin tabla no hay permiso que comprobar: el ítem se pinta para todos.
                    $celdas[$rol->id] = 'sin_permiso';
                    continue;
                }

                $ve    = $rol->ve_todo    || in_array($tabla, $rol->ver, true);
                $edita = $rol->edita_todo || in_array($tabla, $rol->editar, true);

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
                'tabla'      => $tabla,
                'is_virtual' => (bool) ($it->projectTable?->is_virtual),
                // Las tablas marcadas "solo admin" no se pintan en el sidebar ni siquiera al
                // administrador, pero sus permisos existen igual, así que la matriz las incluye
                // y las señala en vez de ocultarlas.
                'admin_only' => (bool) ($it->projectTable?->admin_only),
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
