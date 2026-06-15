<?php

namespace App\Http\Controllers;

use App\Exports\TablaExport;
use App\Imports\TablaImport;
use App\Imports\TablaFromExcelImport;
use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\TableField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExcelController extends Controller
{
    // ── EXPORT ────────────────────────────────────────────────────────────────

    public function export(Request $request, Project $project, string $table)
    {
        $projectTable = $project->tables()->where('name', $table)->with('fields')->firstOrFail();
        $fullTable    = $projectTable->getFullTableName();
        $tipo         = $request->input('tipo', 'listado'); // 'listado' | 'tabla'

        $query = DB::table($fullTable);

        if ($tipo === 'tabla') {
            // Todas las columnas, todos los registros activos
            $query->where('deleted', 0)->orderByDesc('id');
            $usarCampos = $projectTable->fields; // todos los campos
        } else {
            // Mismos filtros que el listado actual
            if ($request->boolean('borrados')) {
                $query->where('deleted', 1);
            } else {
                $query->where('deleted', 0);
                $query->where('hidden', $request->boolean('ocultos') ? 1 : 0);
            }

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

            foreach ($projectTable->listFields as $field) {
                $param = 'f_' . $field->name;
                if ($field->type === 'fecha') {
                    if ($request->filled($param . '_desde')) $query->where($field->name, '>=', $request->input($param . '_desde'));
                    if ($request->filled($param . '_hasta')) $query->where($field->name, '<=', $request->input($param . '_hasta'));
                } elseif (in_array($field->type, ['select', 'tinyint'])) {
                    if ($request->filled($param)) $query->where($field->name, $request->input($param));
                }
            }

            $query->orderByDesc('id');
            $usarCampos = $projectTable->listFields; // solo columnas visibles
        }

        $registros = $query->get();
        $suffix    = $tipo === 'tabla' ? '_completo' : '_listado';
        $filename  = Str::slug($projectTable->label) . $suffix . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new TablaExport($project, $projectTable, $registros, $usarCampos, $tipo === 'tabla'), $filename);
    }

    // ── IMPORT (tabla existente) ───────────────────────────────────────────────

    public function importForm(Project $project, string $table)
    {
        $projectTable = $project->tables()->where('name', $table)->with('fields')->firstOrFail();

        return view('excel.import-form', compact('project', 'projectTable'));
    }

    public function importPreview(Request $request, Project $project, string $table)
    {
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv|max:20480']);

        $projectTable = $project->tables()->where('name', $table)->with('fields')->firstOrFail();

        $path = $request->file('archivo')->store('imports/tmp');

        $importer = new TablaFromExcelImport();
        Excel::import($importer, Storage::path($path));

        session(['excel_import_path' => $path]);

        $headings  = $importer->rows->isNotEmpty() ? array_keys($importer->rows->first()->toArray()) : [];
        $preview   = $importer->rows;
        $listFields    = $projectTable->fields->where('in_list', true)->pluck('name')->toArray();
        $projectTables = $project->tables()->pluck('name')->toArray();

        return view('excel.import-preview', compact('project', 'projectTable', 'headings', 'preview', 'listFields', 'projectTables'));
    }

    public function import(Request $request, Project $project, string $table)
    {
        $request->validate([
            'dup_mode'  => 'required|in:insert,update,skip',
            'key_fields'   => 'nullable|array',
            'key_fields.*' => 'string',
        ]);

        $projectTable = $project->tables()->where('name', $table)->with('fields')->firstOrFail();
        $path = session('excel_import_path');

        if (!$path) {
            return back()->withErrors(['archivo' => 'Sesión expirada. Vuelve a subir el archivo.']);
        }

        $keyFields = array_filter($request->input('key_fields', []));

        $importer = new TablaImport(
            $project,
            $projectTable,
            empty($keyFields) ? [] : $keyFields,
            $request->input('dup_mode'),
            auth()->id()
        );

        Excel::import($importer, Storage::path($path));

        session()->forget('excel_import_path');

        $msg = "Importación completada: {$importer->inserted} insertados";
        if ($importer->updated) $msg .= ", {$importer->updated} actualizados";
        if ($importer->skipped) $msg .= ", {$importer->skipped} omitidos";

        return redirect()->route('listado', [$project->slug, $table])->with('success', $msg);
    }

    // ── CREAR TABLA DESDE EXCEL ────────────────────────────────────────────────

    public function createFromExcelForm(Project $project)
    {
        return view('excel.create-from-excel', compact('project'));
    }

    public function createFromExcelPreview(Request $request, Project $project)
    {
        $request->validate([
            'archivo'     => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'table_name'  => 'required|alpha_dash|max:50',
            'table_label' => 'required|string|max:100',
        ]);

        $path = $request->file('archivo')->store('imports/tmp');
        session(['excel_create_path' => $path]);

        $importer = new TablaFromExcelImport();
        Excel::import($importer, Storage::path($path));

        $headings   = $importer->rows->isNotEmpty() ? array_keys($importer->rows->first()->toArray()) : [];
        $inferTypes = $importer->inferTypes();
        $preview    = $importer->rows;
        $tableName      = $request->input('table_name');
        $tableLabel     = $request->input('table_label');
        $projectTables  = $project->tables()->pluck('name')->toArray();

        return view('excel.create-from-excel-preview', compact(
            'project', 'headings', 'inferTypes', 'preview', 'tableName', 'tableLabel', 'projectTables'
        ));
    }

    public function createFromExcel(Request $request, Project $project)
    {
        $request->validate([
            'table_name'     => 'required|alpha_dash|max:50',
            'table_label'    => 'required|string|max:100',
            'fields'         => 'required|array',
            'fields.*.name'  => 'required|alpha_dash|max:50',
            'fields.*.label' => 'required|string|max:100',
            'fields.*.type'  => 'required|in:' . implode(',', array_keys(TableField::$typeMap)),
            'dup_mode'       => 'required|in:insert,update,skip',
            'key_fields'     => 'nullable|array',
            'key_fields.*'   => 'string',
        ]);

        $path = session('excel_create_path');
        if (!$path) {
            return back()->withErrors(['archivo' => 'Sesión expirada. Vuelve a subir el archivo.']);
        }

        DB::transaction(function () use ($request, $project, $path) {
            // 1. Crear la ProjectTable
            $nextOrder   = $project->tables()->max('order') + 1;
            $projectTable = $project->tables()->create([
                'name'       => $request->table_name,
                'label'      => $request->table_label,
                'order'      => $nextOrder,
                'admin_only' => false,
            ]);

            // 2. Crear MenuItem
            $projectTable->menuItem()->create([
                'project_id' => $project->id,
                'label'      => $request->table_label,
                'order'      => $nextOrder,
                'icon'       => 'fa-table',
            ]);

            // 3. Crear tabla física en BD y campos de sistema
            $projectTable->createDynamicTable();

            // 4. Crear campos (saltando los que ya creó createDynamicTable)
            $existingFieldNames = $projectTable->fields()->pluck('name')->toArray();
            foreach ($request->fields as $i => $fieldData) {
                if (in_array($fieldData['name'], $existingFieldNames)) {
                    continue;
                }
                $extras = null;
                if ($fieldData['type'] === 'desplegable' && !empty($fieldData['ref_table'])) {
                    $extras = 'ref:' . $fieldData['ref_table'];
                }
                $field = $projectTable->fields()->create([
                    'name'    => $fieldData['name'],
                    'label'   => $fieldData['label'],
                    'type'    => $fieldData['type'],
                    'extras'  => $extras,
                    'order'   => $i + 1,
                    'in_list' => true,
                    'in_form' => true,
                ]);
                $field->addColumnToTable();
            }

            // 5. Recargar con campos para el import
            $projectTable->load('fields');

            // 6. Importar datos
            $keyFields = array_filter($request->input('key_fields', []));
            $importer = new TablaImport(
                $project,
                $projectTable,
                empty($keyFields) ? [] : $keyFields,
                $request->input('dup_mode'),
                auth()->id()
            );
            Excel::import($importer, Storage::path($path));

            session()->forget('excel_create_path');
            session(['excel_create_result' => [
                'inserted' => $importer->inserted,
                'updated'  => $importer->updated,
                'skipped'  => $importer->skipped,
            ]]);
        });

        $result = session('excel_create_result', []);
        $msg = "Tabla creada. {$result['inserted']} registros importados";
        if ($result['skipped'] ?? 0) $msg .= ", {$result['skipped']} omitidos";

        return redirect()
            ->route('config.projects.tables.index', $project)
            ->with('success', $msg);
    }
}
