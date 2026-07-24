<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOplandProject
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project');
        $slug = is_string($project) ? $project : ($project->slug ?? '');

        if ($slug !== 'opland') {
            abort(404);
        }

        return $next($request);
    }
}
