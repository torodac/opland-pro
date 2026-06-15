<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ficha de un registro: ver, editar, archivar, borrar.
 * Como el listado, trabaja sobre tablas dinámicas via DB::table().
 */
class FichaController extends Controller
{
    private function abortIfNoRecordAccess(Project $project, string $fullTable, object $registro): void
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return;
        if (!\Illuminate\Support\Facades\Schema::hasColumn($fullTable, 'control_user')) return;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return;

        $role = $user->getProjectRolePublic($project);
        if (!$role || $role->todos_registros) return;

        $ids = json_decode($registro->control_user ?? '[]', true) ?? [];
        if (empty($ids)) return; // sin restricción cuando control_user es null o []
        $ids = array_map('strval', $ids);

        abort_if(!in_array((string) $projectUserId, $ids), 403);
    }

    private function resolveTable(Project $project, string $table): ProjectTable
    {
        return $project->tables()
            ->where('name', $table)
            ->with('fields')
            ->firstOrFail();
    }

    public function show(Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);
        $fullTable    = $projectTable->getFullTableName();
        $registro     = DB::table($fullTable)->find($id);

        abort_if(!$registro, 404);

        // Control de acceso a nivel de registro
        $this->abortIfNoRecordAccess($project, $fullTable, $registro);

        $usuarios   = $this->resolveUsers($registro->createuser, $registro->updateuser);
        $fkOptions  = $this->loadFkOptions($project, $projectTable);
        $tabs       = $this->loadTabData($project, $projectTable, $id);
        $canEdit    = (Auth::user()?->canEditTable($project, $projectTable->name) ?? false)
                      && !$registro->blocked;
        $usuariosMap = $this->loadUsuariosMap($project);

        $camposFicha = ($projectTable->nombre_ocultar_ficha && $projectTable->nombre_formula)
            ? $projectTable->fields->where('name', '!=', 'nombre')->values()
            : $projectTable->fields;

        return view('ficha', [
            'project'        => $project,
            'projectTable'   => $projectTable,
            'campos'         => $camposFicha,
            'registro'       => $registro,
            'fkOptions'      => $fkOptions,
            'tabs'           => $tabs,
            'prefill'        => [],
            'canEdit'        => $canEdit,
            'usuariosMap'    => $usuariosMap,
            'projectTables'   => $project->tables()->where('admin_only', false)->orderBy('order')->get(),
            'projectUsuarios' => $this->loadUsuarios($project),
            'createUser'     => $usuarios[(int) $registro->createuser] ?? null,
            'updateUser'     => $usuarios[(int) $registro->updateuser] ?? null,
            'breadcrumb'     => [
                ['label' => $projectTable->label,  'url' => route('listado', [$project->slug, $table])],
                ['label' => $registro->nombre ?? "#{$id}", 'url' => ''],
            ],
        ]);
    }

    public function create(Project $project, string $table)
    {
        $projectTable = $this->resolveTable($project, $table);

        // Prerellenar campos desde query string (ej: al crear desde pestaña de tabla relacionada)
        $prefill = request()->only($projectTable->fields->pluck('name')->toArray());

        $camposFicha = ($projectTable->nombre_ocultar_ficha && $projectTable->nombre_formula)
            ? $projectTable->fields->where('name', '!=', 'nombre')->values()
            : $projectTable->fields;

        return view('ficha', [
            'project'        => $project,
            'projectTable'   => $projectTable,
            'campos'         => $camposFicha,
            'registro'       => null,
            'prefill'        => $prefill,
            'fkOptions'      => $this->loadFkOptions($project, $projectTable),
            'tabs'           => [],
            'usuariosMap'    => $this->loadUsuariosMap($project),
            'projectUsuarios' => $this->loadUsuarios($project),
            'projectTables'  => $project->tables()->where('admin_only', false)->orderBy('order')->get(),
            'createUser'     => null,
            'updateUser'     => null,
            'breadcrumb'    => [
                ['label' => $projectTable->label, 'url' => route('listado', [$project->slug, $table])],
                ['label' => 'Nuevo',              'url' => ''],
            ],
        ]);
    }

    public function store(Request $request, Project $project, string $table)
    {
        $projectTable = $this->resolveTable($project, $table);
        $this->validateRequired($request, $projectTable);
        $data = $this->filterData($request, $projectTable);
        $data['createuser'] = $this->currentUserId();
        $data['updateuser'] = $this->currentUserId();
        $data['createdat']  = now();
        $data['updatedat']  = now();
        if ($projectTable->nombre_formula) {
            $projectTable->load('fields');
            $data['nombre'] = $projectTable->resolveNombre($data);
        }

        $id = DB::table($projectTable->getFullTableName())->insertGetId($data);

        if ($projectTable->name === 'usuarios') {
            $this->syncProjectUser($project, $id, $data);
        }

        if (request()->wantsJson()) {
            return response()->json(['id' => $id]);
        }

        return redirect()->route('ficha', [$project->slug, $table, $id]);
    }

    public function update(Request $request, Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);
        $registro = DB::table($projectTable->getFullTableName())->find($id);
        abort_if($registro?->blocked, 403, 'Este registro está bloqueado y no puede editarse.');
        $this->validateRequired($request, $projectTable);
        $data = $this->filterData($request, $projectTable);
        $data['updateuser'] = $this->currentUserId() ?? DB::table($projectTable->getFullTableName())->where('id', $id)->value('updateuser');
        $data['updatedat']  = now();
        if ($projectTable->nombre_formula) {
            $projectTable->load('fields');
            $data['nombre'] = $projectTable->resolveNombre($data);
        }

        DB::table($projectTable->getFullTableName())->where('id', $id)->update($data);

        if ($projectTable->name === 'usuarios') {
            $this->syncProjectUser($project, $id, $data);
        }

        return redirect()->route('ficha', [$project->slug, $table, $id]);
    }

    public function block(Project $project, string $table, int $id)
    {
        abort_unless(Auth::user()?->isProjectAdmin($project), 403);
        $projectTable = $this->resolveTable($project, $table);
        $fullTable    = $projectTable->getFullTableName();
        $registro     = DB::table($fullTable)->find($id);

        DB::table($fullTable)->where('id', $id)->update([
            'blocked'    => $registro->blocked ? 0 : 1,
            'updateuser' => $this->currentUserId() ?? $registro->updateuser,
            'updatedat'  => now(),
        ]);

        return back();
    }

    public function archive(Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);
        $fullTable    = $projectTable->getFullTableName();
        $registro     = DB::table($fullTable)->find($id);
        $newHidden    = $registro->hidden ? 0 : 1;

        DB::table($fullTable)->where('id', $id)->update([
            'hidden'     => $newHidden,
            'updateuser' => $this->currentUserId() ?? $registro->updateuser,
            'updatedat'  => now(),
        ]);

        if ($projectTable->name === 'usuarios') {
            $deactivate = $newHidden === 1 || (bool) $registro->deleted;
            DB::table($fullTable)->where('id', $id)
                ->update(['acceso' => $deactivate ? 'sin acceso' : 'APP y web']);
        }

        return back();
    }

    public function destroy(Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);
        $fullTable    = $projectTable->getFullTableName();
        $registro     = DB::table($fullTable)->find($id);
        $newDeleted   = $registro->deleted ? 0 : 1;

        DB::table($fullTable)->where('id', $id)->update([
            'deleted'    => $newDeleted,
            'updateuser' => $this->currentUserId() ?? $registro->updateuser,
            'updatedat'  => now(),
        ]);

        if ($projectTable->name === 'usuarios') {
            $deactivate = $newDeleted === 1 || (bool) $registro->hidden;
            DB::table($fullTable)->where('id', $id)
                ->update(['acceso' => $deactivate ? 'sin acceso' : 'APP y web']);
        }

        return back();
    }

    // Carga opciones [id => nombre] para cada campo FK (type='id' con extras='ref:tabla')
    private function loadFkOptions(Project $project, ProjectTable $projectTable): array
    {
        $options = [];
        foreach ($projectTable->fields as $field) {
            $fullRef = $field->getRefFullTable($project->slug);
            if (!$fullRef) continue;
            $options[$field->name] = DB::table($fullRef)
                ->where('deleted', 0)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray();
        }
        return $options;
    }

    // Carga registros de las tablas relacionadas seleccionadas como pestañas
    private function loadTabData(Project $project, ProjectTable $projectTable, int $id): array
    {
        $tabs = [];
        $tabTableNames = $projectTable->getTabTables();
        if (empty($tabTableNames)) return $tabs;

        foreach ($tabTableNames as $relName) {
            $relTable = $project->tables()
                ->where('name', $relName)
                ->with('fields')
                ->first();
            if (!$relTable) continue;

            // Encontrar el campo FK que apunta a esta tabla
            $fkField = $relTable->fields
                ->first(fn($f) => $f->type === 'id' && $f->getRefTable() === $projectTable->name);
            if (!$fkField) continue;

            $rows = DB::table($relTable->getFullTableName())
                ->where($fkField->name, $id)
                ->where('deleted', 0)
                ->orderBy('id')
                ->get();

            // FK options para las celdas de la tabla relacionada
            $tabFkOptions = [];
            foreach ($relTable->fields as $field) {
                $fullRef = $field->getRefFullTable($project->slug);
                if (!$fullRef) continue;
                $tabFkOptions[$field->name] = DB::table($fullRef)
                    ->where('deleted', 0)->orderBy('nombre')
                    ->pluck('nombre', 'id')->toArray();
            }

            $tabs[] = [
                'table'     => $relTable,
                'fkField'   => $fkField,
                'rows'      => $rows,
                'fkOptions' => $tabFkOptions,
                'campos'    => $relTable->fields->where('in_list', true)->values(),
            ];
        }

        return $tabs;
    }

    private function loadUsuarios(Project $project): array
    {
        $table = $project->slug . '_usuarios';
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return [];

        return DB::table($table)
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
            ->get(['id', 'nombre'])
            ->map(fn($u) => ['id' => $u->id, 'label' => $u->nombre ?? "#{$u->id}"])
            ->values()
            ->toArray();
    }

    private function loadUsuariosMap(Project $project): array
    {
        $map = [];
        foreach ($this->loadUsuarios($project) as $u) {
            $map[(int) $u['id']]    = $u['label'];
            $map[(string) $u['id']] = $u['label'];
        }
        return $map;
    }

    private function resolveUsers(int|null ...$ids): array
    {
        $ids = array_filter(array_unique($ids));
        if (empty($ids)) return [];

        return DB::table('admin_users')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->toArray();
    }

    // Cuando se guarda en {slug}_usuarios, crea/actualiza la cuenta global y el rol
    private function syncProjectUser(Project $project, int $proyectoUserId, array $data): void
    {
        $email = $data['mail'] ?? null;
        if (!$email) return;

        $nombre = $data['nombre'] ?? $email;
        $role   = $project->slug . '_usuarios';

        $appUser = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => Hash::make('bienvenido'), 'must_change_password' => true]
        );

        if (!$appUser->wasRecentlyCreated) {
            $appUser->update(['name' => $nombre, 'email' => $email]);
        }

        UserRole::firstOrCreate(['user_id' => $appUser->id, 'role' => $role]);

        DB::table($project->slug . '_usuarios')
            ->where('id', $proyectoUserId)
            ->update(['admin_user_id' => $appUser->id]);
    }

    public function updateField(Request $request, Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);

        $fieldName = $request->input('field');
        $allowed   = $projectTable->fields->pluck('name')->toArray();

        abort_if(!in_array($fieldName, $allowed), 422, 'Campo no permitido.');

        DB::table($projectTable->getFullTableName())->where('id', $id)->update([
            $fieldName   => $request->input('value'),
            'updateuser' => $this->currentUserId() ?? DB::table($projectTable->getFullTableName())->where('id', $id)->value('updateuser'),
            'updatedat'  => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function currentUserId(): ?int
    {
        return Auth::id();
    }

    // Solo permite guardar los campos definidos en table_fields (evita mass assignment en tablas dinámicas)
    private function filterData(Request $request, ProjectTable $projectTable): array
    {
        $allowed = $projectTable->fields->pluck('name')->toArray();
        $data    = $request->only($allowed);

        // Campos JSON múltiple: llegan como array, guardar como JSON
        foreach ($projectTable->fields->whereIn('type', ['multitabla', 'multiusuario']) as $field) {
            $data[$field->name] = json_encode($request->input($field->name, []));
        }

        return $data;
    }

    private function validateRequired(Request $request, ProjectTable $projectTable): void
    {
        $rules = [];
        foreach ($projectTable->fields->where('required', true) as $field) {
            // nombre se calcula automáticamente cuando hay fórmula; no validar como required
            if ($field->name === 'nombre' && $projectTable->nombre_formula) continue;
            $rules[$field->name] = 'required';
        }

        if ($rules) {
            $labels = $projectTable->fields->pluck('label', 'name')->toArray();
            $request->validate($rules, [], $labels);
        }
    }
}
