<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Comprobar campo acceso en {slug}_usuarios (solo usuarios no-admin)
        if (!$user->isProjectAdmin($project)) {
            $usuariosTable = $project->slug . '_usuarios';
            if (Schema::hasTable($usuariosTable)) {
                $acceso = DB::table($usuariosTable)
                    ->where('admin_user_id', $user->id)
                    ->value('acceso');
                // APP = solo móvil, sin acceso = nada; ambos bloquean la web
                if (in_array($acceso, ['APP', 'sin acceso'])) {
                    abort(403, 'No tienes acceso a este proyecto.');
                }
            }
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
