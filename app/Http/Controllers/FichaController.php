<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        $valor = $registro->control_user ?? null;

        // Determinar tipo del campo control_user
        $fieldType = $this->getControlUserFieldType($project, $fullTable);

        if ($fieldType === 'desplegable') {
            // Valor único (entero): null = sin restricción
            if ($valor === null || $valor === '') return;
            abort_if((string) $valor !== (string) $projectUserId, 403);
        } else {
            // multiusuario: JSON array — vacío = sin restricción
            $ids = json_decode($valor ?? '[]', true) ?? [];
            if (empty($ids)) return;
            $ids = array_map('strval', $ids);
            abort_if(!in_array((string) $projectUserId, $ids), 403);
        }
    }

    private function getControlUserFieldType(Project $project, string $fullTable): string
    {
        // Extraer el nombre de la tabla dinámica (sin slug)
        $tableName = substr($fullTable, strlen($project->slug . '_'));
        $field = $project->tables()
            ->where('name', $tableName)
            ->first()
            ?->fields()
            ->where('name', 'control_user')
            ->value('type');
        return $field ?? 'multiusuario';
    }

    private function resolveTable(Project $project, string $table): ProjectTable
    {
        return $project->tables()
            ->where('name', $table)
            ->with(['fields', 'project'])
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

        $usuarios   = $this->resolveUsers($registro->createuser ?? null, $registro->updateuser ?? null);
        $fkOptions  = $this->loadFkOptions($project, $projectTable);
        $tabs       = $this->loadTabData($project, $projectTable, $id);
        $canEdit    = (Auth::user()?->canEditTable($project, $projectTable->name) ?? false)
                      && !($registro->blocked ?? false);
        $usuariosMap = $this->loadUsuariosMap($project);

        $camposFicha = ($projectTable->nombre_ocultar_ficha && $projectTable->nombre_formula)
            ? $projectTable->fields->where('name', '!=', 'nombre')->values()
            : $projectTable->fields;

        return view('ficha', [
            'project'          => $project,
            'projectTable'     => $projectTable,
            'campos'           => $camposFicha,
            'registro'         => $registro,
            'fkOptions'        => $fkOptions,
            'tabs'             => $tabs,
            'prefill'          => [],
            'canEdit'          => $canEdit,
            'usuariosMap'      => $usuariosMap,
            'tieneDeleted'     => \Illuminate\Support\Facades\Schema::hasColumn($fullTable, 'deleted'),
            'tieneHidden'      => \Illuminate\Support\Facades\Schema::hasColumn($fullTable, 'hidden'),
            'projectTables'    => $project->tables()->where('admin_only', false)->orderBy('order')->get(),
            'projectUsuarios'  => $this->loadUsuariosForForm($project, $projectTable),
            'createUser'       => $usuarios[(int) $registro->createuser] ?? null,
            'updateUser'       => $usuarios[(int) $registro->updateuser] ?? null,
            'breadcrumb'       => [
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

        $fullTable = $projectTable->getFullTableName();

        return view('ficha', [
            'project'          => $project,
            'projectTable'     => $projectTable,
            'campos'           => $camposFicha,
            'registro'         => null,
            'prefill'          => $prefill,
            'fkOptions'        => $this->loadFkOptions($project, $projectTable),
            'tabs'             => [],
            'usuariosMap'      => $this->loadUsuariosMap($project),
            'tieneDeleted'     => \Illuminate\Support\Facades\Schema::hasColumn($fullTable, 'deleted'),
            'tieneHidden'      => \Illuminate\Support\Facades\Schema::hasColumn($fullTable, 'hidden'),
            'projectUsuarios'  => $this->loadUsuariosForForm($project, $projectTable),
            'projectTables'    => $project->tables()->where('admin_only', false)->orderBy('order')->get(),
            'createUser'       => null,
            'updateUser'       => null,
            'breadcrumb'       => [
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
        abort_if($registro?->blocked ?? false, 403, 'Este registro está bloqueado y no puede editarse.');
        $this->validateRequired($request, $projectTable);
        $data = $this->filterData($request, $projectTable);
        $data['updateuser'] = $this->currentUserId() ?? DB::table($projectTable->getFullTableName())->where('id', $id)->value('updateuser');
        $data['updatedat']  = now();
        if ($projectTable->nombre_formula) {
            $projectTable->load('fields');
            $registro = DB::table($projectTable->getFullTableName())->find($id);
            $rowData  = array_merge((array) $registro, $data);
            $data['nombre'] = $projectTable->resolveNombre($rowData);
        }

        DB::table($projectTable->getFullTableName())->where('id', $id)->update($data);

        if ($projectTable->name === 'usuarios') {
            $this->syncProjectUser($project, $id, $data);
        }

        return redirect()->route('ficha', [$project->slug, $table, $id]);
    }

    public function resetPassword(Project $project, string $table, int $id)
    {
        abort_unless($table === 'usuarios', 404);
        abort_unless(Auth::user()?->isProjectAdmin($project), 403);

        $fullTable = $project->slug . '_usuarios';
        $registro  = DB::table($fullTable)->find($id);
        abort_if(!$registro, 404);

        $appUser = User::where('email', $registro->mail ?? '')->first();
        abort_if(!$appUser, 404, 'No se encontró la cuenta de acceso vinculada.');

        $appUser->update([
            'password'             => Hash::make('bienvenido'),
            'must_change_password' => true,
        ]);

        return back()->with('success', "Contraseña de {$registro->nombre} restablecida a 'bienvenido'.");
    }

    public function block(Project $project, string $table, int $id)
    {
        abort_unless(Auth::user()?->isProjectAdmin($project), 403);
        $projectTable = $this->resolveTable($project, $table);
        $fullTable    = $projectTable->getFullTableName();
        $registro     = DB::table($fullTable)->find($id);

        DB::table($fullTable)->where('id', $id)->update([
            'blocked'    => ($registro->blocked ?? 0) ? 0 : 1,
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

    // Borrar: soft delete (toggle deleted=0/1)
    public function borrar(Project $project, string $table, int $id)
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

        // Al borrar, limpiar archivos del storage
        if ($newDeleted === 1) {
            foreach ($projectTable->fields->where('type', 'file') as $field) {
                $path = $registro->{$field->name} ?? null;
                if ($path) Storage::disk('public')->delete($path);
            }
        }

        if ($projectTable->name === 'usuarios') {
            $deactivate = $newDeleted === 1 || (bool) $registro->hidden;
            DB::table($fullTable)->where('id', $id)
                ->update(['acceso' => $deactivate ? 'sin acceso' : 'APP y web']);
        }

        return back();
    }

    // Eliminar: hard DELETE de la base de datos (irreversible)
    public function eliminar(Project $project, string $table, int $id)
    {
        $projectTable = $this->resolveTable($project, $table);
        abort_unless($projectTable->permite_eliminar, 403);
        abort_unless(Auth::user()?->canEditTable($project, $table), 403);

        $fullTable = $projectTable->getFullTableName();
        $registro  = DB::table($fullTable)->find($id);
        abort_if(!$registro, 404);

        // Borrar archivos del storage antes de eliminar el registro
        foreach ($projectTable->fields->where('type', 'file') as $field) {
            $path = $registro->{$field->name} ?? null;
            if ($path) Storage::disk('public')->delete($path);
        }

        DB::table($fullTable)->where('id', $id)->delete();

        return redirect()->route('listado', [$project->slug, $table])
            ->with('success', 'Registro eliminado definitivamente.');
    }

    // Carga opciones [id => nombre] para cada campo FK (type='id'/'desplegable' con extras='ref:tabla')
    private function loadFkOptions(Project $project, ProjectTable $projectTable): array
    {
        $restricted    = !$this->userCanSeeAllRecords($project);
        $ownProjectId  = $restricted ? Auth::user()?->projectUserId($project) : null;
        $usuariosTable = $project->slug . '_usuarios';

        $options = [];
        foreach ($projectTable->fields as $field) {
            $fullRef = $field->getRefFullTable($project->slug);
            if (!$fullRef) continue;

            $query = DB::table($fullRef)
                ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                ->orderBy('nombre');

            // Si es control_user como desplegable y el rol está restringido, solo el propio usuario
            if ($restricted && $field->name === 'control_user' && $field->type === 'desplegable' && $fullRef === $usuariosTable) {
                $query->where('id', $ownProjectId);
            }

            $options[$field->name] = $query->pluck('nombre', 'id')->toArray();
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
                ->first(fn($f) => in_array($f->type, ['id', 'desplegable']) && $f->getRefTable() === $projectTable->name);
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
                    ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                    ->orderBy('nombre')
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

    private function loadUsuarios(Project $project, ?array $allowedRolIds = null): array
    {
        $table = $project->slug . '_usuarios';
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return [];

        $q = DB::table($table)
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0));

        if ($allowedRolIds) {
            $q->whereIn('id_rol', $allowedRolIds);
        }

        return $q->get(['id', 'nombre'])
            ->map(fn($u) => ['id' => $u->id, 'label' => $u->nombre ?? "#{$u->id}"])
            ->values()
            ->toArray();
    }

    // Devuelve solo el propio usuario si el rol no permite ver todos los registros
    private function loadUsuariosForForm(Project $project, ?\App\Models\ProjectTable $projectTable = null): array
    {
        // Filtrar por roles si algún campo multiusuario tiene extras "roles:X,Y"
        $allowedRolIds = null;
        if ($projectTable) {
            $rolesExtras = $projectTable->fields
                ->where('type', 'multiusuario')
                ->map(fn($f) => $f->extras)
                ->filter(fn($e) => str_starts_with((string) $e, 'roles:'))
                ->first();
            if ($rolesExtras) {
                $allowedRolIds = array_map('intval', explode(',', substr($rolesExtras, 6)));
            }
        }

        $all = $this->loadUsuarios($project, $allowedRolIds);
        if ($this->userCanSeeAllRecords($project)) return $all;

        $ownId = Auth::user()?->projectUserId($project);
        if (!$ownId) return $all;

        return array_values(array_filter($all, fn($u) => (string) $u['id'] === (string) $ownId));
    }

    private function userCanSeeAllRecords(Project $project): bool
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return true;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return true;

        $usuariosTable = $project->slug . '_usuarios';
        $rolesTable    = $project->slug . '_roles';
        if (!\Illuminate\Support\Facades\Schema::hasTable($usuariosTable) ||
            !\Illuminate\Support\Facades\Schema::hasTable($rolesTable)) return true;

        $usuario = DB::table($usuariosTable)->find($projectUserId);
        if (!$usuario || !$usuario->id_rol) return true;

        $role = DB::table($rolesTable)->find($usuario->id_rol);
        return !$role || $role->todos_registros;
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

        $updateData = [
            $fieldName   => $request->input('value'),
            'updateuser' => $this->currentUserId() ?? DB::table($projectTable->getFullTableName())->where('id', $id)->value('updateuser'),
            'updatedat'  => now(),
        ];

        // Recalcular nombre si la tabla tiene fórmula y el campo modificado forma parte de ella
        if ($projectTable->nombre_formula && str_contains($projectTable->nombre_formula, $fieldName)) {
            $projectTable->load('fields');
            $registro   = DB::table($projectTable->getFullTableName())->where('id', $id)->first();
            $rowData    = array_merge((array) $registro, [$fieldName => $request->input('value')]);
            $updateData['nombre'] = $projectTable->resolveNombre($rowData);
        }

        DB::table($projectTable->getFullTableName())->where('id', $id)->update($updateData);

        return response()->json(['ok' => true, 'nombre' => $updateData['nombre'] ?? null]);
    }

    private function currentUserId(): ?int
    {
        return Auth::id();
    }

    // Solo permite guardar los campos definidos en table_fields (evita mass assignment en tablas dinámicas)
    private function filterData(Request $request, ProjectTable $projectTable): array
    {
        $fullTable = $projectTable->getFullTableName();
        $allowed   = $projectTable->fields->pluck('name')
            ->filter(fn($name) => Schema::hasColumn($fullTable, $name))
            ->toArray();
        $data = $request->only($allowed);

        // Si la tabla tiene fórmula de nombre, no incluir nombre del form (se calcula luego)
        if ($projectTable->nombre_formula) {
            unset($data['nombre']);
        }

        // Campos JSON múltiple: llegan como array, guardar como JSON
        foreach ($projectTable->fields->whereIn('type', ['multitabla', 'multiusuario']) as $field) {
            $data[$field->name] = json_encode($request->input($field->name, []));
        }

        // Campos file: guardar el archivo subido; si no se sube nada, no sobreescribir
        foreach ($projectTable->fields->where('type', 'file') as $field) {
            if ($request->hasFile($field->name)) {
                $path = $request->file($field->name)->store(
                    $projectTable->project->slug . '/' . $projectTable->name,
                    'public'
                );
                $data[$field->name] = $path;
            } else {
                unset($data[$field->name]);
            }
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
