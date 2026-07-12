<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    public function index(Project $project)
    {
        $tables = $project->tables()->withCount('fields')->orderBy('order')->get();

        // Adjuntar modulo desde admin_menu_items
        $moduloMap = \App\Models\MenuItem::where('project_id', $project->id)
            ->whereNotNull('project_table_id')
            ->pluck('modulo', 'project_table_id');
        $tables->each(fn($t) => $t->modulo = $moduloMap[$t->id] ?? null);

        $panelTables  = $tables->where('admin_only', false)->values();
        $configTables = $tables->where('admin_only', true)->values();

        // Orden de módulos guardado en el proyecto
        $moduloOrder = $project->modulo_order ?? [];

        $sortGroups = function ($grouped) use ($moduloOrder) {
            return $grouped->sortBy(function ($_, $modulo) use ($moduloOrder) {
                $idx = array_search((string) $modulo, array_map('strval', $moduloOrder));
                return $idx === false ? 9999 : $idx;
            });
        };

        $panelGroups  = $sortGroups($panelTables->groupBy('modulo'));
        $configGroups = $sortGroups($configTables->groupBy('modulo'));

        return view('config.tables.index', compact(
            'project', 'tables', 'panelTables', 'configTables', 'panelGroups', 'configGroups'
        ));
    }

    public function reorderModulos(Request $request, Project $project)
    {
        $request->validate(['order' => 'required|array']);
        $project->update(['modulo_order' => $request->input('order')]);
        return response()->json(['ok' => true]);
    }

    public function renameModulo(Request $request, Project $project, string $modulo)
    {
        $nuevo = trim($request->input('nombre', ''));
        if (!$nuevo) return response()->json(['error' => 'Nombre vacío'], 422);

        $order = array_map(fn($m) => $m === $modulo ? $nuevo : $m, $project->modulo_order ?? []);
        $project->update(['modulo_order' => $order]);

        \App\Models\MenuItem::where('project_id', $project->id)
            ->where('modulo', $modulo)
            ->update(['modulo' => $nuevo]);

        return response()->json(['ok' => true, 'nuevo' => $nuevo]);
    }

    public function deleteModulo(Request $request, Project $project, string $modulo)
    {
        $order = collect($project->modulo_order ?? [])
            ->filter(fn($m) => $m !== $modulo)
            ->values()
            ->toArray();
        $project->update(['modulo_order' => $order]);
        return response()->json(['ok' => true]);
    }

    public function setModulo(Request $request, Project $project, ProjectTable $table)
    {
        $modulo = $request->input('modulo') ?: null;
        \App\Models\MenuItem::where('project_id', $project->id)
            ->where('project_table_id', $table->id)
            ->update(['modulo' => $modulo]);
        return response()->json(['ok' => true]);
    }

    public function patch(Request $request, Project $project, ProjectTable $table)
    {
        $allowed = ['label', 'icon', 'admin_only', 'active', 'nombre_formula', 'nombre_ocultar_ficha', 'nombre_ocultar_listado', 'has_kanban', 'has_calendar', 'has_matrix', 'permite_eliminar'];
        $data    = $request->only($allowed);

        foreach (['admin_only', 'active', 'nombre_ocultar_ficha', 'nombre_ocultar_listado', 'has_kanban', 'has_calendar', 'has_matrix', 'permite_eliminar'] as $bool) {
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

        $data['has_kanban']      = $request->boolean('has_kanban');
        $data['has_calendar']    = $request->boolean('has_calendar');
        $data['has_matrix']      = $request->boolean('has_matrix');
        $data['active']          = $request->boolean('active');
        $data['admin_only']      = $request->boolean('admin_only');
        $data['permite_eliminar'] = $request->boolean('permite_eliminar');

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
        DB::table('admin_menu_items')->where('project_table_id', $table->id)->delete();
        $table->delete();

        return redirect()
            ->route('config.projects.tables.index', $project)
            ->with('success', 'Tabla eliminada.');
    }
}
