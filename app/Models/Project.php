<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Proyecto = tenant/cliente. Cada proyecto tiene su propio conjunto de tablas dinámicas.
 * El slug se usa como prefijo: proyecto "gym" → tablas "gym_socios", "gym_clases", etc.
 */
class Project extends Model
{
    protected $table = 'admin_projects';

    protected $fillable = ['name', 'slug', 'logo', 'favicon', 'description', 'active', 'modulo_order'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = strtolower($value);
    }

    protected $casts = ['active' => 'boolean', 'modulo_order' => 'array'];

    // Un proyecto tiene muchas tablas configuradas
    public function tables()
    {
        return $this->hasMany(ProjectTable::class)->orderBy('order');
    }

    // Un proyecto tiene un menú lateral
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class)
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereNull('project_table_id')
                   ->orWhereHas('projectTable', fn($t) => $t->where('active', True));
            })
            ->with(['projectTable', 'children.projectTable'])
            ->orderBy('order');
    }

    // Nombre real de una tabla dinámica: "gym" + "socios" → "gym_socios"
    public function dynamicTable(string $tableName): string
    {
        return $this->slug . '_' . $tableName;
    }

    // Crea las tablas especiales "roles" y "usuarios" al crear un proyecto
    public function createDefaultTables(): void
    {
        $this->createRolesTable();
        $this->createUsuariosTable();
    }

    private function createRolesTable(): void
    {
        $tableName = $this->slug . '_roles';

        $projectTable = $this->tables()->firstOrCreate(
            ['name' => 'roles'],
            ['label' => 'Roles', 'icon' => 'fa-shield', 'order' => 900, 'active' => true, 'admin_only' => true]
        );

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $t) {
                $t->id();
                $t->string('nombre')->nullable();
                $t->string('tabla_default', 100)->nullable();
                $t->json('ver')->nullable();
                $t->json('editar')->nullable();
                $t->json('roles_supervisados')->nullable();
                $t->string('todos_registros', 20)->default('personales');
                $t->tinyInteger('hidden')->default(0);
                $t->tinyInteger('deleted')->default(0);
                $t->unsignedBigInteger('createuser')->nullable();
                $t->unsignedBigInteger('updateuser')->nullable();
                $t->timestamp('createdat')->nullable();
                $t->timestamp('updatedat')->nullable();
            });
        }

        $fields = [
            ['name' => 'nombre',          'label' => 'Nombre',                  'type' => 'string',       'order' => 1,  'in_list' => true,  'in_form' => true,  'required' => true],
            ['name' => 'tabla_default',   'label' => 'Tabla por defecto',       'type' => 'string',       'order' => 10, 'in_list' => true,  'in_form' => true,  'required' => false],
            ['name' => 'todos_registros', 'label' => 'Visibilidad de registros', 'type' => 'select',    'order' => 20, 'in_list' => true,  'in_form' => true,  'required' => false, 'extras' => 'opt:personales,supervisados,todos|enables:roles_supervisados:supervisados'],
            ['name' => 'ver',             'label' => 'Puede ver',               'type' => 'multitabla',   'order' => 30, 'in_list' => false, 'in_form' => true,  'required' => false],
            ['name' => 'editar',          'label' => 'Puede editar',            'type' => 'multitabla',   'order' => 40, 'in_list' => false, 'in_form' => true,  'required' => false],
            ['name' => 'roles_supervisados', 'label' => 'Roles supervisados',   'type' => 'multiusuario', 'order' => 45, 'in_list' => false, 'in_form' => true,  'required' => false, 'extras' => 'source:roles'],
            ['name' => 'hidden',          'label' => 'Oculto',                  'type' => 'tinyint',      'order' => 997,'in_list' => false, 'in_form' => false, 'required' => false],
            ['name' => 'deleted',         'label' => 'Borrado',                 'type' => 'tinyint',      'order' => 998,'in_list' => false, 'in_form' => false, 'required' => false],
        ];

        foreach ($fields as $f) {
            if (!$projectTable->fields()->where('name', $f['name'])->exists()) {
                $projectTable->fields()->create($f);
            }
        }

        MenuItem::firstOrCreate(
            ['project_id' => $this->id, 'project_table_id' => $projectTable->id],
            ['label' => 'Roles', 'icon' => 'fa-shield', 'order' => 900]
        );
    }

    private function createUsuariosTable(): void
    {
        $tableName = $this->slug . '_usuarios';

        $projectTable = $this->tables()->firstOrCreate(
            ['name' => 'usuarios'],
            ['label' => 'Usuarios', 'icon' => 'fa-users', 'order' => 901, 'active' => true, 'admin_only' => false]
        );

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $t) {
                $t->id();
                $t->string('nombre')->nullable();
                $t->string('alias', 50)->nullable();
                $t->string('mail')->nullable();
                $t->unsignedBigInteger('id_rol')->nullable();
                $t->string('dni', 20)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->string('acceso', 20)->default('APP y web');
                $t->unsignedBigInteger('admin_user_id')->nullable();
                $t->tinyInteger('hidden')->default(0);
                $t->tinyInteger('deleted')->default(0);
                $t->unsignedBigInteger('createuser')->nullable();
                $t->unsignedBigInteger('updateuser')->nullable();
                $t->timestamp('createdat')->nullable();
                $t->timestamp('updatedat')->nullable();
            });
        }

        $fields = [
            ['name' => 'nombre',   'label' => 'Nombre',    'type' => 'string',   'order' => 1,   'in_list' => true,  'in_form' => true,  'required' => true],
            ['name' => 'alias',    'label' => 'Alias',     'type' => 'string',   'order' => 2,   'in_list' => true,  'in_form' => true,  'required' => false],
            ['name' => 'mail',     'label' => 'Email',     'type' => 'email',    'order' => 10,  'in_list' => true,  'in_form' => true,  'required' => true],
            ['name' => 'id_rol',   'label' => 'Rol',       'type' => 'desplegable', 'order' => 20,  'in_list' => true,  'in_form' => true,  'required' => true,  'extras' => 'ref:roles'],
            ['name' => 'dni',      'label' => 'DNI',       'type' => 'string',   'order' => 30,  'in_list' => false, 'in_form' => true,  'required' => false],
            ['name' => 'telefono', 'label' => 'Teléfono',  'type' => 'telefono', 'order' => 40,  'in_list' => false, 'in_form' => true,  'required' => false],
            ['name' => 'acceso',   'label' => 'Acceso',    'type' => 'select',   'order' => 50,  'in_list' => true,  'in_form' => true,  'required' => false, 'extras' => 'opt:APP,web,APP y web,sin acceso'],
            ['name' => 'hidden',   'label' => 'Oculto',    'type' => 'tinyint',  'order' => 997, 'in_list' => false, 'in_form' => false, 'required' => false],
            ['name' => 'deleted',  'label' => 'Borrado',   'type' => 'tinyint',  'order' => 998, 'in_list' => false, 'in_form' => false, 'required' => false],
        ];

        foreach ($fields as $f) {
            if (!$projectTable->fields()->where('name', $f['name'])->exists()) {
                $projectTable->fields()->create($f);
            }
        }

        MenuItem::firstOrCreate(
            ['project_id' => $this->id, 'project_table_id' => $projectTable->id],
            ['label' => 'Usuarios', 'icon' => 'fa-users', 'order' => 901]
        );
    }
}
