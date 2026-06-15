<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckProjectAccess
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project');
        if (!$project) return $next($request);

        $user = Auth::user();

        if (!$user->canAccessProject($project)) {
            abort(403, 'No tienes acceso a este proyecto.');
        }

        $tableName = $request->route('table');
        if ($tableName && !$user->isProjectAdmin($project)) {
            $projectTable = $project->tables()->where('name', $tableName)->first();

            if ($projectTable && !$user->canViewTable($project, $tableName)) {
                abort(403, 'No tienes permisos para ver esta tabla.');
            }
        }

        return $next($request);
    }
}
