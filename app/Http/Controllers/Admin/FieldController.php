<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\TableField;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Project $project, ProjectTable $table)
    {
        $fields        = $table->fields()->orderBy('order')->get();
        $relatedTables = $table->relatedTables();

        return view('config.fields.index', compact('project', 'table', 'fields', 'relatedTables'));
    }

    public function create(Project $project, ProjectTable $table)
    {
        return view('config.fields.form', [
            'project' => $project,
            'table'   => $table,
            'field'   => new TableField(),
            'types'   => array_diff_key(TableField::$typeMap, array_flip(['id', 'multitabla'])),
        ]);
    }

    public function store(Request $request, Project $project, ProjectTable $table)
    {
        $data = $request->validate([
            'name'   => 'required|alpha_dash|max:50',
            'label'  => 'required|string|max:100',
            'type'   => 'required|in:' . implode(',', array_keys(TableField::$typeMap)),
            'order'  => 'integer',
            'extras' => 'nullable|string|max:255',
        ]);

        $data['required'] = $request->boolean('required');
        $data['in_list']  = $request->boolean('in_list');
        $data['in_form']  = $request->boolean('in_form');
        $data['extras']   = $this->normalizeExtras($data['type'] ?? null, $data['extras'] ?? null);

        $field = $table->fields()->create($data);

        $field->addColumnToTable();

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Campo creado.');
    }

    public function edit(Project $project, ProjectTable $table, TableField $field)
    {
        return view('config.fields.form', [
            'project' => $project,
            'table'   => $table,
            'field'   => $field,
            'types'   => array_diff_key(TableField::$typeMap, array_flip(['id', 'multitabla'])),
        ]);
    }

    public function update(Request $request, Project $project, ProjectTable $table, TableField $field)
    {
        $data = $request->validate([
            'label'  => 'required|string|max:100',
            'order'  => 'integer',
            'extras' => 'nullable|string|max:255',
        ]);

        $data['required'] = $request->boolean('required');
        $data['in_list']  = $request->boolean('in_list');
        $data['in_form']  = $request->boolean('in_form');
        $data['extras']   = $this->normalizeExtras($field->type, $data['extras'] ?? null);

        $field->update($data);

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Campo actualizado.');
    }

    public function patch(Request $request, Project $project, ProjectTable $table, TableField $field)
    {
        $allowed = ['label', 'order', 'extras', 'in_list', 'in_form', 'required'];
        $data    = $request->only($allowed);

        foreach (['in_list', 'in_form', 'required'] as $bool) {
            if (array_key_exists($bool, $data)) {
                $data[$bool] = filter_var($data[$bool], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $field->update($data);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Project $project, ProjectTable $table)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($request->ids as $position => $id) {
            $table->fields()->where('id', $id)->update(['order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Project $project, ProjectTable $table, TableField $field)
    {
        $field->delete();

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Campo eliminado.');
    }

    private function normalizeExtras(?string $type, ?string $extras): ?string
    {
        if (!$extras) return $extras;
        if ($type === 'select' && !str_starts_with($extras, 'opt:') && !str_starts_with($extras, 'ref:')) {
            return 'opt:' . $extras;
        }
        return $extras;
    }
}
