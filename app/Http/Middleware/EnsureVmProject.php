<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVmProject
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project');
        $slug = is_string($project) ? $project : ($project->slug ?? '');

        if ($slug !== 'vm') {
            abort(404);
        }

        return $next($request);
    }
}
