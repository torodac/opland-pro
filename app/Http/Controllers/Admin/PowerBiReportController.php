<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Administración de los informes de Power BI embebidos de un proyecto (tabla
 * admin_pbi_reports). Un proyecto puede tener varios informes (p.ej. vm: Operaciones,
 * Gerencia, Gobernanta), cada uno con su propio enlace en el sidebar y su propio permiso
 * por rol (via la fila pseudo "powerbi_<slug>" en admin_project_tables, igual que "dashboard").
 */
class PowerBiReportController extends Controller
{
    // 'filtros' llega como texto JSON del textarea: un array de {tabla, columna, valor} que se
    // aplican siempre, bloqueados, al embeber (ver PowerBiController::show()). Valida a mano en
    // vez de con una regla 'json' porque tambien hay que comprobar la forma de cada elemento,
    // no solo que sea JSON valido.
    private function parseFiltros(Request $request): ?string
    {
        $texto = trim((string) $request->input('filtros', ''));
        if ($texto === '') return null;

        $decoded = json_decode($texto, true);
        abort_if(json_last_error() !== JSON_ERROR_NONE, 422, 'El campo Filtros no es JSON válido: ' . json_last_error_msg());
        abort_unless(is_array($decoded), 422, 'El campo Filtros debe ser un array JSON.');
        foreach ($decoded as $filtro) {
            abort_unless(
                is_array($filtro) && isset($filtro['tabla'], $filtro['columna'], $filtro['valor']),
                422,
                'Cada filtro debe tener "tabla", "columna" y "valor". Ejemplo: [{"tabla":"clientes","columna":"id","valor":"5"}]'
            );
        }

        return json_encode(array_values($decoded));
    }

    public function index(Project $project)
    {
        $reports = DB::table('admin_pbi_reports')
            ->where('id_proyectos', $project->id)
            ->where('deleted', 0)
            ->orderBy('createdat')
            ->get();

        return view('config.powerbi.index', compact('project', 'reports'));
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'label'           => 'required|string|max:255',
            'reportid'        => 'required|string|max:255',
            'reportpage'      => 'nullable|string|max:255',
            'page_navigation' => 'nullable|boolean',
            'filters_visible' => 'nullable|boolean',
        ]);

        $slug = Str::slug($data['label'], '_') ?: 'default';
        // Evitar colisión si ya existe ese slug para el proyecto (incluye soft-deleted, para no
        // reventar el indice unico parcial admin_pbi_reports_proyecto_slug_unique).
        $base = $slug;
        $i = 2;
        while (DB::table('admin_pbi_reports')->where('id_proyectos', $project->id)->where('slug', $slug)->where('deleted', 0)->exists()) {
            $slug = $base . '_' . $i++;
        }

        DB::table('admin_pbi_reports')->insert([
            'id_proyectos'    => $project->id,
            'slug'            => $slug,
            'label'           => $data['label'],
            'reportid'        => $data['reportid'],
            'reportpage'      => $data['reportpage'] ?? null,
            'page_navigation' => $request->boolean('page_navigation', true),
            'filters_visible' => $request->boolean('filters_visible', false),
            'filtros'         => $this->parseFiltros($request),
            'hidden'          => 0,
            'deleted'         => 0,
            'createuser'      => auth()->id(),
            'updateuser'      => auth()->id(),
            'createdat'       => now(),
            'updatedat'       => now(),
        ]);

        $tabla = DB::table('admin_project_tables')->where('project_id', $project->id)->where('name', 'powerbi_' . $slug)->first();
        $tablaId = $tabla->id ?? DB::table('admin_project_tables')->insertGetId([
            'project_id' => $project->id,
            'name'       => 'powerbi_' . $slug,
            'label'      => $data['label'],
            'icon'       => 'fa-solid fa-chart-column',
            'order'      => 0,
            'is_virtual' => true,
            'admin_only' => false,
            'createuser' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = "/{$project->slug}/powerbi_report/{$slug}";
        if (!DB::table('admin_menu_items')->where('project_id', $project->id)->where('url', $url)->exists()) {
            DB::table('admin_menu_items')
                ->where('project_id', $project->id)->where('order', '>=', 1)->where('order', '<', 900)
                ->increment('order');
            DB::table('admin_menu_items')->insert([
                'project_id'       => $project->id,
                'label'            => $data['label'],
                'icon'             => 'fa-solid fa-chart-column',
                'project_table_id' => $tablaId,
                'url'              => $url,
                'order'            => 1,
            ]);
        }

        return back()->with('success', "Informe «{$data['label']}» creado.");
    }

    public function update(Request $request, Project $project, int $report)
    {
        $data = $request->validate([
            'label'           => 'required|string|max:255',
            'reportid'        => 'required|string|max:255',
            'reportpage'      => 'nullable|string|max:255',
            'page_navigation' => 'nullable|boolean',
            'filters_visible' => 'nullable|boolean',
        ]);

        $row = DB::table('admin_pbi_reports')->where('id', $report)->where('id_proyectos', $project->id)->firstOrFail();

        DB::table('admin_pbi_reports')->where('id', $report)->update([
            'label'           => $data['label'],
            'reportid'        => $data['reportid'],
            'reportpage'      => $data['reportpage'] ?? null,
            'page_navigation' => $request->boolean('page_navigation', true),
            'filters_visible' => $request->boolean('filters_visible', false),
            'filtros'         => $this->parseFiltros($request),
            'updateuser'      => auth()->id(),
            'updatedat'       => now(),
        ]);

        // El label del enlace del sidebar y de la fila pseudo de permisos siguen al del informe.
        DB::table('admin_menu_items')
            ->where('project_id', $project->id)
            ->where('url', "/{$project->slug}/powerbi_report/{$row->slug}")
            ->update(['label' => $data['label']]);
        DB::table('admin_project_tables')
            ->where('project_id', $project->id)->where('name', 'powerbi_' . $row->slug)
            ->update(['label' => $data['label']]);

        return back()->with('success', "Informe «{$data['label']}» actualizado.");
    }

    public function destroy(Project $project, int $report)
    {
        $row = DB::table('admin_pbi_reports')->where('id', $report)->where('id_proyectos', $project->id)->firstOrFail();

        DB::table('admin_pbi_reports')->where('id', $report)->update(['deleted' => 1, 'updateuser' => auth()->id(), 'updatedat' => now()]);
        DB::table('admin_menu_items')->where('project_id', $project->id)->where('url', "/{$project->slug}/powerbi_report/{$row->slug}")->delete();

        return back()->with('success', "Informe «{$row->label}» eliminado.");
    }
}
