<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users    = User::with('roles')->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('config.users.index', compact('users', 'projects'));
    }

    public function create()
    {
        abort(403, 'Los usuarios se crean desde la tabla Usuarios de cada proyecto.');
    }

    public function store(Request $request)
    {
        abort(403, 'Los usuarios se crean desde la tabla Usuarios de cada proyecto.');
    }

    public function edit(User $user)
    {
        $projects = Project::orderBy('name')->get();

        return view('config.users.form', compact('user', 'projects'));
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|unique:admin_users,email,' . $user->id,
            'roles'   => 'nullable|array',
            'roles.*' => 'string|max:100',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', Password::min(8)];
        }

        $data = $request->validate($rules);

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        // Sync roles: remove old, add new
        $newRoles = $data['roles'] ?? [];
        $existing = $user->roles()->pluck('role')->toArray();

        foreach (array_diff($existing, $newRoles) as $remove) {
            $user->roles()->where('role', $remove)->delete();
        }
        foreach (array_diff($newRoles, $existing) as $add) {
            UserRole::create(['user_id' => $user->id, 'role' => $add]);
        }

        return redirect()->route('config.users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('config.users.index')->with('success', 'Usuario eliminado.');
    }

    public function impersonate(User $user)
    {
        // Solo el admin global puede impersonar
        abort_unless(auth()->user()->isAdmin(), 403);
        // No impersonar a sí mismo
        abort_if(auth()->id() === $user->id, 400);

        session(['impersonating' => auth()->id()]);
        auth()->login($user);

        return redirect()->route('proyectos');
    }

    public function stopImpersonating()
    {
        $originalId = session()->pull('impersonating');
        abort_unless($originalId, 403);

        $original = User::findOrFail($originalId);
        auth()->login($original);

        return redirect()->route('config.users.index');
    }
}
