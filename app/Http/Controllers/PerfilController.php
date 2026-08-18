<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $signatureUrl = $user->signature_path ? Storage::disk('public')->url($user->signature_path) : null;

        return view('perfil', ['user' => $user, 'signature_url' => $signatureUrl]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'password'              => ['nullable', 'confirmed', Password::min(8)],
            'password_confirmation' => 'nullable',
        ]);

        $user->update(['name' => $data['name']]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    // Firma manuscrita capturada en canvas (signature_pad), enviada como PNG en base64.
    public function updateSignature(Request $request)
    {
        $request->validate(['signature' => 'required|string']);

        $base64 = preg_replace('#^data:image/\w+;base64,#', '', $request->signature);
        $binary = base64_decode($base64, true);
        abort_if($binary === false, 422, 'Firma inválida.');

        $user = Auth::user();
        $path = 'signatures/' . $user->id . '.png';
        Storage::disk('public')->put($path, $binary);

        $user->update(['signature_path' => $path]);

        return back()->with('success', 'Firma guardada correctamente.');
    }
}
