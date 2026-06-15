<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListadoController extends Controller
{
    public function index(Request $request, Project $project, string $table)
    {
        $projectTable = $project->tables()
            ->where('name', $table)
            ->with(['listFields', 'fields'])
            ->firstOrFail();

        $fullTable = $projectTable->getFullTableName();

        $query = DB::table($fullTable);

        // Borrados / archivados
        if ($request->boolean('borrados')) {
            $query->where('deleted', 1);
        } else {
            $query->where('deleted', 0);
            if ($request->boolean('ocultos')) {
                $query->where('hidden', 1);
            } else {
                $query->where('hidden', 0);
            }
        }

        // Búsqueda global por texto (ILIKE en PostgreSQL, LIKE en SQLite)
        if ($request->filled('q')) {
            $q      = $request->q;
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($sub) use ($q, $projectTable, $likeOp) {
                foreach ($projectTable->listFields as $field) {
                    if (in_array($field->type, ['string', 'text', 'email', 'telefono'])) {
                        $sub->orWhere($field->name, $likeOp, "%{$q}%");
                    }
                }
            });
        }

        // Filtros por campo
        foreach ($projectTable->listFields as $field) {
            $param = 'f_' . $field->name;

            if ($field->type === 'fecha') {
                if ($request->filled($param . '_desde')) {
                    $query->where($field->name, '>=', $request->input($param . '_desde'));
                }
                if ($request->filled($param . '_hasta')) {
                    $query->where($field->name, '<=', $request->input($param . '_hasta'));
                }
            } elseif (in_array($field->type, ['select', 'tinyint'])) {
                if ($request->filled($param)) {
                    $query->where($field->name, $request->input($param));
                }
            }
        }

        // Filtro control_user: si el usuario no tiene acceso a todos los registros
        $this->applyControlUserFilter($query, $project, $fullTable);

        $registros = $query->orderByDesc('id')->paginate(50)->withQueryString();

        // Opciones para campos FK (type='id')
        $fkOptions = [];
        foreach ($projectTable->listFields as $field) {
            $fullRef = $field->getRefFullTable($project->slug);
            if (!$fullRef) continue;
            $fkOptions[$field->name] = DB::table($fullRef)
                ->where('deleted', 0)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray();
        }

        // Comprueba si el rol del usuario permite editar esta tabla
        $canEdit = $this->userCanEdit($project, $projectTable->name);

        // Modo tabla editable: solo si todos los campos required están en el listado
        $allFields     = $projectTable->fields;
        $listFieldNames = $projectTable->listFields->pluck('name');
        $requiredHidden = $allFields->where('required', true)
                                    ->filter(fn($f) => !$listFieldNames->contains($f->name));

        $modoTabla         = $request->input('modo') === 'tabla' && $requiredHidden->isEmpty();
        $tablaNoDisponible = $request->input('modo') === 'tabla' && $requiredHidden->isNotEmpty();

        // Mapa id→nombre de usuarios del proyecto para campos multiusuario
        $usuariosMap   = [];
        $usuariosTable = $project->slug . '_usuarios';
        if (Schema::hasTable($usuariosTable)) {
            DB::table($usuariosTable)
                ->get(['id', 'nombre'])
                ->each(function ($u) use (&$usuariosMap) {
                    $usuariosMap[(int) $u->id]    = $u->nombre;
                    $usuariosMap[(string) $u->id] = $u->nombre;
                });
        }

        return view('listado', [
            'canEdit'           => $canEdit,
            'project'           => $project,
            'projectTable'      => $projectTable,
            'campos'            => $projectTable->nombre_ocultar_listado && $projectTable->nombre_formula
                                    ? $projectTable->listFields->where('name', '!=', 'nombre')->values()
                                    : $projectTable->listFields,
            'registros'         => $registros,
            'fkOptions'         => $fkOptions,
            'usuariosMap'       => $usuariosMap,
            'modoTabla'         => $modoTabla,
            'tablaNoDisponible' => $tablaNoDisponible,
            'requiredHidden'    => $requiredHidden,
            'breadcrumb'        => [
                ['label' => $projectTable->label, 'url' => ''],
            ],
        ]);
    }

    // Aplica filtro control_user si el rol del usuario no tiene todos_registros
    private function applyControlUserFilter($query, Project $project, string $fullTable): void
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return;
        if (!Schema::hasColumn($fullTable, 'control_user')) return;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return;

        $role = $this->getUserProjectRole($project, $projectUserId);
        if (!$role || $role->todos_registros) return;

        $query->whereRaw("control_user LIKE ?", ["%\"{$projectUserId}\"%"]);
    }

    // Comprueba si el usuario puede editar registros de esta tabla según su rol
    private function userCanEdit(Project $project, string $tableName): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        return $user->canEditTable($project, $tableName);
    }

    // Carga el registro de rol del usuario en {slug}_roles
    private function getUserProjectRole(Project $project, int $projectUserId): ?object
    {
        $usuariosTable = $project->slug . '_usuarios';
        $rolesTable    = $project->slug . '_roles';

        if (!Schema::hasTable($usuariosTable) || !Schema::hasTable($rolesTable)) return null;

        $usuario = DB::table($usuariosTable)->find($projectUserId);
        if (!$usuario || !$usuario->id_rol) return null;

        return DB::table($rolesTable)->find($usuario->id_rol);
    }
}
