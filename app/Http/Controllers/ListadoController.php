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

        // Filtro stat (específico de vm_propiedades)
        $stat = $request->input('stat');
        if ($fullTable === 'vm_propiedades' && $stat) {
            $ayer = now()->subDay()->toDateString();
            $hoy  = now()->toDateString();
            match ($stat) {
                'pte_info'        => $query->where('deleted', 0)->whereNull('fecha_inicio'),
                'posibles_bajas'  => $query->where('deleted', 0)->where(fn($q) => $q->whereNull('icnea_updatedat')->orWhereDate('icnea_updatedat', '<', $ayer)),
                'revisar_borrado' => $query->where('deleted', 1)->whereDate('icnea_updatedat', $hoy),
                default           => null,
            };
        } else {
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

        // Ordenación por columna
        $sortField = $request->input('sort');
        $sortDir   = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = $projectTable->listFields->pluck('name')->toArray();
        if ($sortField && in_array($sortField, $sortableFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderByDesc('id');
        }

        $registros = $query->paginate(50)->withQueryString();

        // Opciones para campos FK (type='id'/'desplegable')
        $restricted   = !$this->userCanSeeAllRecords($project);
        $ownProjectId = $restricted ? Auth::user()?->projectUserId($project) : null;
        $usuariosTable = $project->slug . '_usuarios';

        $fkOptions = [];
        foreach ($projectTable->listFields as $field) {
            $fullRef = $field->getRefFullTable($project->slug);
            if (!$fullRef) continue;
            $fkQuery = DB::table($fullRef)
                ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                ->orderBy('nombre');
            if ($restricted && $field->name === 'control_user' && $field->type === 'desplegable' && $fullRef === $usuariosTable) {
                $fkQuery->where('id', $ownProjectId);
            }
            $fkOptions[$field->name] = $fkQuery->pluck('nombre', 'id')->toArray();
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
        $tieneDeleted      = Schema::hasColumn($fullTable, 'deleted');
        $tieneHidden       = Schema::hasColumn($fullTable, 'hidden');
        $campoFile         = $projectTable->fields->firstWhere('type', 'file');
        $modoGaleria       = $request->input('modo') === 'galeria' && $campoFile !== null;
        // Para galería: mapa campo → tabla ref para construir URLs a fichas relacionadas
        $fkRefTablas       = $projectTable->listFields
            ->filter(fn($f) => in_array($f->type, ['id', 'desplegable']) && $f->getRefTable())
            ->mapWithKeys(fn($f) => [$f->name => $f->getRefTable()])
            ->toArray();

        // Mapa id→nombre de usuarios del proyecto para campos multiusuario (display)
        $usuariosMap   = [];
        $usuariosTable = $project->slug . '_usuarios';
        $allUsuarios   = [];
        if (Schema::hasTable($usuariosTable)) {
            $allUsuarios = DB::table($usuariosTable)
                ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
                ->get(['id', 'nombre']);
            $allUsuarios->each(function ($u) use (&$usuariosMap) {
                $usuariosMap[(int) $u->id]    = $u->nombre;
                $usuariosMap[(string) $u->id] = $u->nombre;
            });
        }

        // Lista filtrada para el formulario: solo el propio usuario si el rol está restringido
        $projectUsuarios = $allUsuarios->map(fn($u) => ['id' => $u->id, 'label' => $u->nombre ?? "#{$u->id}"])->values()->toArray();
        if (!$this->userCanSeeAllRecords($project)) {
            $ownId = Auth::user()?->projectUserId($project);
            if ($ownId) {
                $projectUsuarios = array_values(array_filter($projectUsuarios, fn($u) => (string) $u['id'] === (string) $ownId));
            }
        }

        // Stats específicas de vm_propiedades
        $tablStats = null;
        if ($fullTable === 'vm_propiedades') {
            $ayer = now()->subDay()->toDateString();
            $hoy  = now()->toDateString();
            $tablStats = [
                'pte_info'      => DB::table($fullTable)->where('deleted', 0)->whereNull('fecha_inicio')->count(),
                'posibles_bajas' => DB::table($fullTable)->where('deleted', 0)->where(fn($q) => $q->whereNull('icnea_updatedat')->orWhereDate('icnea_updatedat', '<', $ayer))->count(),
                'revisar_borrado' => DB::table($fullTable)->where('deleted', 1)->whereDate('icnea_updatedat', $hoy)->count(),
            ];
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
            'projectUsuarios'   => $projectUsuarios,
            'modoTabla'         => $modoTabla,
            'tablaNoDisponible' => $tablaNoDisponible,
            'requiredHidden'    => $requiredHidden,
            'tieneDeleted'      => $tieneDeleted,
            'tieneHidden'       => $tieneHidden,
            'modoGaleria'       => $modoGaleria,
            'campoFile'         => $campoFile,
            'fkRefTablas'       => $fkRefTablas,
            'camposFiltrablesGaleria' => $projectTable->listFields->filter(fn($f) => in_array($f->type, ['id', 'desplegable']) && $f->getRefTable()),
            'sortField'         => $sortField ?? null,
            'sortDir'           => $sortDir,
            'tablStats'         => $tablStats,
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

        $fieldType = $this->getControlUserFieldType($project, $fullTable);

        if ($fieldType === 'desplegable') {
            $query->where(function ($q) use ($projectUserId) {
                $q->whereNull('control_user')
                  ->orWhere('control_user', $projectUserId);
            });
        } else {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $cast    = $isPgsql ? '::text' : '';
            $query->where(function ($q) use ($projectUserId, $cast) {
                $q->whereNull('control_user')
                  ->orWhereRaw("control_user{$cast} = '[]'")
                  ->orWhereRaw("control_user{$cast} LIKE ?", ["%\"{$projectUserId}\"%"]);
            });
        }
    }

    private function getControlUserFieldType(Project $project, string $fullTable): string
    {
        $tableName = substr($fullTable, strlen($project->slug . '_'));
        $field = $project->tables()
            ->where('name', $tableName)
            ->first()
            ?->fields()
            ->where('name', 'control_user')
            ->value('type');
        return $field ?? 'multiusuario';
    }

    // Comprueba si el usuario puede editar registros de esta tabla según su rol
    private function userCanEdit(Project $project, string $tableName): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        return $user->canEditTable($project, $tableName);
    }

    private function userCanSeeAllRecords(Project $project): bool
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return true;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return true;

        $role = $this->getUserProjectRole($project, $projectUserId);
        return !$role || $role->todos_registros;
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
