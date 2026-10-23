<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // 'logout' va en la lista a proposito: la pantalla de cambio ofrece cerrar sesion
            // para quien no quiera seguir, y sin esto su POST acabaria redirigido a la propia
            // pantalla -- el boton no daria error, simplemente no haria nada.
            if (!$request->routeIs('password.change', 'password.change.update', 'logout')) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
