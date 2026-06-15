<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Acceso restringido a administradores globales.');
        }
        return $next($request);
    }
}
