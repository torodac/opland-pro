<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Login insensible a mayusculas: Auth::attempt() compara el email tal cual con un
        // where('email', ...) exacto (la columna no tiene collation/citext case-insensitive), asi
        // que se resuelve aqui la capitalizacion real guardada antes de pasarsela.
        $email = User::whereRaw('LOWER(email) = LOWER(?)', [$credentials['email']])->value('email');
        if ($email) {
            $credentials['email'] = $email;
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'Las credenciales no son correctas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
