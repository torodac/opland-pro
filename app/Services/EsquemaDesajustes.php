<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Contrasta las columnas reales de una tabla contra los campos declarados en admin_table_fields.
//
// Hay dos desajustes posibles y no son igual de graves:
//
//   - Columna sin declarar: el dato existe y se guarda, pero la aplicación no lo enseña ni deja
//     editarlo. Molesto, no roto. En septiembre de 2026 había 110 así en 40 tablas, entre ellas
//     los cuatro campos que la extracción de facturas rellena y nadie podía ver.
//   - Campo declarado sin columna: el formulario lo pinta y al guardar la ficha revienta, porque
//     se intenta escribir en una columna que no existe. Esto sí es un error en producción.
//
// La fuente es siempre el catálogo de PostgreSQL, así que no hay nada que mantener al día.
class EsquemaDesajustes
{
    // Fontanería que nunca se declara como campo y que no es un hallazgo.
    public const COLUMNAS_SISTEMA = [
        'id', 'createdat', 'updatedat', 'created_at', 'updated_at', 'createuser', 'updateuser',
        'hidden', 'deleted', 'blocked', 'remember_token', 'email_verified_at', 'password',
    ];

    // Tipo de campo que corresponde a cada tipo de columna de PostgreSQL. Es una propuesta:
    // quien declara el campo puede cambiarla.
    private const TIPO_SUGERIDO = [
        'character varying' => 'string',
        'text'              => 'text',
        'integer'           => 'int',
        'bigint'            => 'int',
        'smallint'          => 'smallint',   // casilla
        'numeric'           => 'decimal',
        'double precision'  => 'decimal',
        'boolean'           => 'tinyint',    // desplegable Sí/No
        'date'              => 'fecha',
        'timestamp without time zone' => 'timestamp',
        'time without time zone'      => 'time',
        'json'              => 'text',
        'jsonb'             => 'text',
    ];

    // Columnas sin declarar, con el tipo detectado y una etiqueta propuesta a partir del nombre.
    // La etiqueta es solo un punto de partida: la base de datos no sabe que "bi" se llama
    // "Base imponible" de cara al usuario.
    public static function columnasSinDeclarar(Project $project, ProjectTable $table): array
    {
        $full = $table->getFullTableName();
        if (!Schema::hasTable($full)) return [];

        $declarados = $table->fields()->pluck('name')->all();

        $cols = DB::select(
            'select column_name, data_type, character_maximum_length
               from information_schema.columns
              where table_schema = current_schema() and table_name = ?
              order by ordinal_position',
            [$full]
        );

        $fuera = array_merge(self::COLUMNAS_SISTEMA, $declarados);

        $salida = [];
        foreach ($cols as $c) {
            if (in_array($c->column_name, $fuera, true)) continue;

            $extras = self::extrasPropuestos($project, $c->column_name);

            $salida[] = [
                'name'      => $c->column_name,
                'tipo_bd'   => $c->data_type,
                // Una columna id_* que apunta a una tabla real es un desplegable, no un número.
                'tipo'      => $extras ? 'desplegable' : (self::TIPO_SUGERIDO[$c->data_type] ?? 'string'),
                'label'     => self::etiquetaPropuesta($c->column_name),
                'extras'    => $extras,
                'con_datos' => self::tieneDatos($full, $c->column_name),
            ];
        }

        return $salida;
    }

    // Campos declarados cuya columna no existe: el formulario los pinta y el guardado falla.
    public static function camposSinColumna(ProjectTable $table): array
    {
        $full = $table->getFullTableName();
        if (!Schema::hasTable($full)) return [];

        $fisicas = Schema::getColumnListing($full);

        return $table->fields()
            ->get(['id', 'name', 'label'])
            ->reject(fn($f) => in_array($f->name, $fisicas, true))
            ->values()
            ->all();
    }

    // Recuento de los dos desajustes en todas las tablas activas, para el informe diario.
    public static function resumenGlobal(): array
    {
        $proyectos = Project::pluck('slug', 'id');
        $sinDeclarar = 0; $rotos = []; $tablasAfectadas = 0;

        $tablas = ProjectTable::where('active', true)->where('is_virtual', false)->get();
        foreach ($tablas as $t) {
            $slug = $proyectos[$t->project_id] ?? null;
            if (!$slug || !Schema::hasTable($slug . '_' . $t->name)) continue;

            $proyecto = Project::find($t->project_id);
            $n = count(self::columnasSinDeclarar($proyecto, $t));
            if ($n) { $sinDeclarar += $n; $tablasAfectadas++; }

            foreach (self::camposSinColumna($t) as $f) {
                $rotos[] = $slug . '_' . $t->name . '.' . $f->name;
            }
        }

        return [
            'sin_declarar'     => $sinDeclarar,
            'tablas_afectadas' => $tablasAfectadas,
            'campos_rotos'     => $rotos,
        ];
    }

    private static function etiquetaPropuesta(string $columna): string
    {
        $t = str_replace('_', ' ', $columna);
        // "id_propiedades" describe una relación; la etiqueta útil es el nombre de lo referenciado.
        if (str_starts_with($t, 'id ')) $t = substr($t, 3);

        return mb_convert_case(trim($t), MB_CASE_TITLE, 'UTF-8');
    }

    // Una columna id_xxx casi siempre apunta a la tabla xxx del mismo proyecto. Se propone el
    // "ref:" solo si esa tabla existe de verdad, para no sugerir una relación inventada.
    private static function extrasPropuestos(Project $project, string $columna): ?string
    {
        if (!str_starts_with($columna, 'id_')) return null;

        $destino = substr($columna, 3);
        $existe = ProjectTable::where('project_id', $project->id)
            ->whereIn('name', [$destino, rtrim($destino, 's'), $destino . 's'])
            ->value('name');

        return $existe ? 'ref:' . $existe : null;
    }

    // Si la columna ya tiene datos, declararla es urgente: hay información guardada que nadie ve.
    private static function tieneDatos(string $tabla, string $columna): bool
    {
        try {
            return (bool) DB::selectOne(
                'select 1 as hay from "' . $tabla . '" where "' . $columna . '" is not null limit 1'
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
