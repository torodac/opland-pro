<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureProjectAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user    = Auth::user();
        $project = $request->route('project');

        if (!$user) abort(403);

        // Sin proyecto en ruta (ej. listado de proyectos): solo admin global
        if (!$project) {
            if (!$user->isAdmin()) abort(403, 'Acceso restringido a administradores.');
            return $next($request);
        }

        if (!$user->isProjectAdmin($project)) {
            abort(403, 'Acceso restringido a administradores del proyecto.');
        }

        return $next($request);
    }
}
