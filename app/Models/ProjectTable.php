<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Tabla configurada dentro de un proyecto.
 * "name" es el slug de la tabla (ej: "socios").
 * La tabla real en BD se llama "{project.slug}_{name}" (ej: "gym_socios").
 * Esta clase solo guarda la configuración — no los datos.
 */
class ProjectTable extends Model
{
    protected $fillable = [
        'project_id', 'name', 'label', 'icon', 'order',
        'has_kanban', 'has_calendar', 'has_matrix', 'active', 'admin_only', 'tab_tables',
    ];

    protected $casts = [
        'has_kanban'   => 'boolean',
        'has_calendar' => 'boolean',
        'has_matrix'   => 'boolean',
        'active'       => 'boolean',
        'admin_only'   => 'boolean',
        'tab_tables'   => 'array',
    ];

    // Tablas del mismo proyecto que referencian a esta tabla mediante un campo FK
    public function relatedTables()
    {
        return $this->project->tables()
            ->where('id', '!=', $this->id)
            ->whereHas('fields', fn($q) => $q->where('type', 'id')->where('extras', 'ref:' . $this->name))
            ->get();
    }

    // Tablas seleccionadas para mostrar como pestañas
    public function getTabTables(): array
    {
        return $this->tab_tables ?? [];
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function menuItem()
    {
        return $this->hasOne(MenuItem::class, 'project_table_id');
    }

    // Campos configurados para esta tabla, ordenados
    public function fields()
    {
        return $this->hasMany(TableField::class)->orderBy('order');
    }

    // Campos que aparecen en el listado
    public function listFields()
    {
        return $this->hasMany(TableField::class)->where('in_list', true)->orderBy('order');
    }

    // Nombre real de la tabla en la base de datos
    public function getFullTableName(): string
    {
        return $this->project->slug . '_' . $this->name;
    }

    // Crea la tabla dinámica en la BD y los campos de sistema en table_fields
    public function createDynamicTable(): void
    {
        $tableName = $this->getFullTableName();

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable();
                $table->tinyInteger('hidden')->default(0);
                $table->tinyInteger('deleted')->default(0);
                $table->unsignedBigInteger('createuser')->nullable();
                $table->unsignedBigInteger('updateuser')->nullable();
                $table->timestamp('createdat')->nullable();
                $table->timestamp('updatedat')->nullable();
            });
        }

        // Campos de sistema visibles en formulario y listado
        $systemFields = [
            ['name' => 'nombre',   'label' => 'Nombre',   'type' => 'string',  'order' => 1,   'in_list' => true,  'in_form' => true,  'required' => true],
            ['name' => 'hidden', 'label' => 'Oculto', 'type' => 'tinyint', 'order' => 997, 'in_list' => false, 'in_form' => false, 'required' => false],
            ['name' => 'deleted',  'label' => 'Borrado',  'type' => 'tinyint', 'order' => 998, 'in_list' => false, 'in_form' => false, 'required' => false],
        ];

        foreach ($systemFields as $f) {
            if (!$this->fields()->where('name', $f['name'])->exists()) {
                $this->fields()->create($f);
            }
        }
    }
}
