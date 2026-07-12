<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Project;

class CheckTableAccess
{
    // Mapa de valores de parámetro de ruta que no coinciden con el nombre de tabla directo.
    private const PARAM_MAP = [
        'piscina' => 'piscinas',
    ];

    public function handle(Request $request, Closure $next, string $tableName): mixed
    {
        $project = $request->route('project');
        if (!$project instanceof Project) {
            $slug    = is_string($project) ? $project : ($project->slug ?? '');
            $project = Project::where('slug', $slug)->firstOrFail();
        }

        // Soporte para nombres dinámicos: "tareas_{tipo}" → lee {tipo} de la ruta.
        $resolved = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($request) {
            $val = $request->route($m[1]) ?? $m[0];
            return self::PARAM_MAP[$val] ?? $val;
        }, $tableName);

        if (!auth()->user()?->canViewTable($project, $resolved)) {
            abort(403, 'No tienes acceso a esta sección.');
        }

        return $next($request);
    }
}
