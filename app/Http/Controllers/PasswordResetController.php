<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_codes')->where('email', $request->email)->delete();
            DB::table('password_reset_codes')->insert([
                'email'      => $request->email,
                'code'       => $token,
                'expires_at' => now()->addMinutes(30),
            ]);

            $link = route('password.reset-form', ['token' => $token, 'email' => $request->email]);

            Mail::to($user->email)->send(new PasswordResetCode($link, config('app.name')));
        }

        return back()->with('info', 'Si el email existe en el sistema, recibirás el enlace en breve.');
    }

    public function showReset(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        $row = DB::table('password_reset_codes')
            ->where('email', $email)
            ->where('code', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return redirect()->route('password.request')
                ->with('error', 'El enlace no es válido o ha caducado. Solicita uno nuevo.');
        }

        return view('auth.reset-password', compact('token', 'email'));
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $row = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return back()->withInput()
                ->withErrors(['password' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.']);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_codes')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
