<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->input('api_token');

        if (!$token) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        // Token de proyecto: busca en la tabla projects
        $project = Project::where('api_token', $token)->first();
        if ($project) {
            $request->attributes->set('project_token_slug', $project->slug);
            return $next($request);
        }

        // Token Sanctum (usuario admin)
        $pat = PersonalAccessToken::findToken($token);
        if (!$pat || !$pat->tokenable) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        auth()->setUser($pat->tokenable);
        return $next($request);
    }
}
