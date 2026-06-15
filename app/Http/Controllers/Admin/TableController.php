<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Project $project)
    {
        $tables       = $project->tables()->withCount('fields')->orderBy('order')->get();
        $panelTables  = $tables->where('admin_only', false)->values();
        $configTables = $tables->where('admin_only', true)->values();

        return view('config.tables.index', compact('project', 'tables', 'panelTables', 'configTables'));
    }

    public function patch(Request $request, Project $project, ProjectTable $table)
    {
        $allowed = ['label', 'icon', 'admin_only', 'active', 'nombre_formula', 'nombre_ocultar_ficha', 'nombre_ocultar_listado'];
        $data    = $request->only($allowed);

        foreach (['admin_only', 'active', 'nombre_ocultar_ficha', 'nombre_ocultar_listado'] as $bool) {
            if (array_key_exists($bool, $data)) {
                $data[$bool] = filter_var($data[$bool], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $table->update($data);

        $menuUpdate = [];
        if (isset($data['icon']))  $menuUpdate['icon']  = $data['icon'];
        if (isset($data['label'])) $menuUpdate['label'] = $data['label'];
        if ($menuUpdate) {
            \App\Models\MenuItem::where('project_table_id', $table->id)->update($menuUpdate);
        }

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Project $project)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($request->ids as $position => $id) {
            $order = $position + 1;
            $project->tables()->where('id', $id)->update(['order' => $order]);
            \App\Models\MenuItem::where('project_table_id', $id)->update(['order' => $order]);
        }

        return response()->json(['ok' => true]);
    }

    public function create(Project $project)
    {
        return view('config.tables.form', ['project' => $project, 'table' => new ProjectTable()]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'  => 'required|alpha_dash|max:50|unique:project_tables,name,NULL,id,project_id,' . $project->id,
            'label' => 'required|string|max:100',
            'icon'  => 'nullable|string|max:50',
            'order' => 'integer',
        ]);

        $data['has_kanban']   = $request->boolean('has_kanban');
        $data['has_calendar'] = $request->boolean('has_calendar');
        $data['has_matrix']   = $request->boolean('has_matrix');

        $nextOrder = $project->tables()->max('order') + 1;
        $data['order'] = $nextOrder;

        $table = $project->tables()->create($data);

        $table->createDynamicTable();

        \App\Models\MenuItem::create([
            'project_id'       => $project->id,
            'label'            => $data['label'],
            'icon'             => $data['icon'] ?? null,
            'project_table_id' => $table->id,
            'order'            => $nextOrder,
        ]);

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Tabla creada.');
    }

    public function show(Project $project, ProjectTable $table)
    {
        return redirect()->route('config.projects.tables.fields.index', [$project, $table]);
    }

    public function edit(Project $project, ProjectTable $table)
    {
        return view('config.tables.form', compact('project', 'table'));
    }

    public function update(Request $request, Project $project, ProjectTable $table)
    {
        $data = $request->validate([
            'label'      => 'required|string|max:100',
            'icon'       => 'nullable|string|max:50',
            'order'      => 'integer',
        ]);

        $data['has_kanban']   = $request->boolean('has_kanban');
        $data['has_calendar'] = $request->boolean('has_calendar');
        $data['has_matrix']   = $request->boolean('has_matrix');
        $data['active']       = $request->boolean('active');
        $data['admin_only']   = $request->boolean('admin_only');

        $table->update($data);

        \App\Models\MenuItem::where('project_table_id', $table->id)
            ->update(array_filter([
                'label' => $data['label'] ?? null,
                'icon'  => $data['icon'] ?? null,
            ]));

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Tabla actualizada.');
    }

    public function updateTabs(Request $request, Project $project, ProjectTable $table)
    {
        $table->update(['tab_tables' => $request->input('tab_tables', [])]);

        return redirect()
            ->route('config.projects.tables.fields.index', [$project, $table])
            ->with('success', 'Pestañas actualizadas.');
    }

    public function destroy(Project $project, ProjectTable $table)
    {
        $table->delete();

        return redirect()
            ->route('config.projects.tables.index', $project)
            ->with('success', 'Tabla eliminada.');
    }
}
