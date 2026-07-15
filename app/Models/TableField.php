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
    protected $table = 'admin_table_fields';

    protected $fillable = [
        'project_table_id', 'name', 'label', 'type',
        'order', 'required', 'in_list', 'in_form', 'extras', 'help_text',
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
        $base = explode('|', $this->extras ?? '', 2)[0];
        if (!str_starts_with($base, 'opt:')) {
            return [];
        }
        return explode(',', substr($base, 4));
    }

    // Lee una directiva adicional de extras separada por "|", ej: "opt:a,b|enables:campo:valor"
    public function getExtraDirective(string $key): ?string
    {
        $parts = explode('|', $this->extras ?? '');
        foreach ($parts as $part) {
            if (str_starts_with($part, $key . ':')) {
                return substr($part, strlen($key) + 1);
            }
        }
        return null;
    }

    // ¿Es un campo de FK a otra tabla dinámica?
    public function isForeignKey(): bool
    {
        return $this->type === 'desplegable';
    }

    // Nombre corto de la tabla referenciada: "ref:socios" → "socios", "ref:master.estados" → "estados"
    // Soporta "ref:inventario|parent:id_propiedades" — ignora la parte |parent:...
    public function getRefTable(): ?string
    {
        $base = explode('|', $this->extras ?? '', 2)[0];
        if (!str_starts_with($base, 'ref:')) return null;
        $ref = substr($base, 4);
        return str_contains($ref, '.') ? explode('.', $ref, 2)[1] : $ref;
    }

    // Nombre completo en BD: "ref:socios" → "{slug}_socios", "ref:master.estados" → "master_estados"
    // Para desplegable sin prefijo "ref:" (ej. "master_duraciones"), lo trata como nombre directo de tabla.
    // Soporta "ref:inventario|parent:id_propiedades" — ignora la parte |parent:...
    public function getRefFullTable(string $currentSlug): string
    {
        $base = explode('|', $this->extras ?? '', 2)[0];
        if (!str_starts_with($base, 'ref:')) {
            if ($this->type === 'desplegable' && $base !== '') return $base;
            return '';
        }
        $ref = substr($base, 4);
        if (str_contains($ref, '.')) {
            [$slug, $table] = explode('.', $ref, 2);
            return $slug . '_' . $table;
        }
        return $currentSlug . '_' . $ref;
    }

    // Si extras contiene "|parent:CAMPO", devuelve el nombre del campo padre para filtrar la FK
    public function getParentFilterField(): ?string
    {
        $extras = $this->extras ?? '';
        if (!str_contains($extras, '|parent:')) return null;
        $part = explode('|parent:', $extras, 2)[1];
        return explode('|', $part, 2)[0];
    }

    // ¿Es readonly (calculado automáticamente)?
    public function isAutocalc(): bool
    {
        return str_contains($this->extras ?? '', 'autocalc');
    }
}
