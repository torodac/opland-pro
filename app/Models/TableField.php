<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Campo configurado dentro de una tabla.
 * "type" determina cómo se renderiza en listado, ficha y formulario.
 * "extras" permite configuración adicional (opciones de select, readonly, etc.)
 */
class TableField extends Model
{
    protected $fillable = [
        'project_table_id', 'name', 'label', 'type',
        'order', 'required', 'in_list', 'in_form', 'extras',
    ];

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    protected $casts = [
        'required' => 'boolean',
        'in_list'  => 'boolean',
        'in_form'  => 'boolean',
    ];

    public function projectTable()
    {
        return $this->belongsTo(ProjectTable::class);
    }

    // Tipos de campo disponibles y su columna SQL correspondiente
    public static array $typeMap = [
        'text'      => 'text',
        'string'    => 'string',
        'int'       => 'integer',
        'decimal'   => 'decimal',
        'tinyint'   => 'boolean',   // YES/NO select
        'smallint'  => 'boolean',   // checkbox
        'fecha'     => 'date',
        'timestamp' => 'timestamp',
        'time'      => 'time',
        'email'     => 'string',
        'telefono'  => 'string',
        'password'  => 'string',
        'file'      => 'string',    // ruta al archivo
        'select'       => 'string',          // opciones fijas en extras
        'desplegable'  => 'unsignedBigInteger', // FK a otra tabla dinámica
        'multiusuario' => 'json',          // multiselección de usuarios del proyecto
        // internos (no disponibles en UI):
        'id'           => 'unsignedBigInteger',
        'multitabla'   => 'json',
    ];

    // Añade esta columna a la tabla dinámica en la BD
    public function addColumnToTable(): void
    {
        $tableName = $this->projectTable->getFullTableName();

        if (Schema::hasColumn($tableName, $this->name)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $colType = self::$typeMap[$this->type] ?? 'string';

            match ($colType) {
                'text'             => $table->text($this->name)->nullable(),
                'integer'          => $table->integer($this->name)->nullable(),
                'decimal'          => $table->decimal($this->name, 10, 2)->nullable(),
                'boolean'          => $table->boolean($this->name)->nullable(),
                'date'             => $table->date($this->name)->nullable(),
                'timestamp'        => $table->timestamp($this->name)->nullable(),
                'time'             => $table->time($this->name)->nullable(),
                'unsignedBigInteger' => $table->unsignedBigInteger($this->name)->nullable(),
                'json'             => $table->json($this->name)->nullable(),
                default            => $table->string($this->name)->nullable(),
            };
        });
    }

    // Devuelve las opciones si el tipo es "select" (extras: "opt:Sí,No,Pendiente")
    public function getOptions(): array
    {
        if (!str_starts_with($this->extras ?? '', 'opt:')) {
            return [];
        }
        return explode(',', substr($this->extras, 4));
    }

    // ¿Es un campo de FK a otra tabla dinámica?
    public function isForeignKey(): bool
    {
        return $this->type === 'desplegable';
    }

    // Nombre corto de la tabla referenciada: "ref:socios" → "socios", "ref:master.estados" → "estados"
    public function getRefTable(): ?string
    {
        if (!str_starts_with($this->extras ?? '', 'ref:')) return null;
        $ref = substr($this->extras, 4);
        return str_contains($ref, '.') ? explode('.', $ref, 2)[1] : $ref;
    }

    // Nombre completo en BD: "ref:socios" → "{slug}_socios", "ref:master.estados" → "master_estados"
    // Para desplegable sin prefijo "ref:" (ej. "master_duraciones"), lo trata como nombre directo de tabla.
    public function getRefFullTable(string $currentSlug): string
    {
        $extras = $this->extras ?? '';
        if (!str_starts_with($extras, 'ref:')) {
            // tolerancia: desplegable con extras = nombre directo de tabla
            if ($this->type === 'desplegable' && $extras !== '') return $extras;
            return '';
        }
        $ref = substr($extras, 4);
        if (str_contains($ref, '.')) {
            [$slug, $table] = explode('.', $ref, 2);
            return $slug . '_' . $table;
        }
        return $currentSlug . '_' . $ref;
    }

    // ¿Es readonly (calculado automáticamente)?
    public function isAutocalc(): bool
    {
        return str_contains($this->extras ?? '', 'autocalc');
    }
}
