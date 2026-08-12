<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNfProject
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project');
        $slug = is_string($project) ? $project : ($project->slug ?? '');

        if ($slug !== 'nf') {
            abort(404);
        }

        return $next($request);
    }
}
