<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ApiController extends Controller
{
    // POST /api/token  { email, password }  → { token }  (token Sanctum de usuario admin)
    public function token(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    // Devuelve el slug autorizado: si viene token de proyecto solo ese slug, si viene usuario admin cualquiera
    private function authorizedSlug(Request $request): ?string
    {
        return $request->attributes->get('project_token_slug');
    }

    private function checkAccess(Request $request, Project $project): bool
    {
        // Token de proyecto: solo permite el slug propio
        $projectSlug = $this->authorizedSlug($request);
        if ($projectSlug !== null) {
            return $projectSlug === $project->slug;
        }

        // Token Sanctum: verifica rol de usuario
        $user = $request->user();
        if (!$user) return false;

        return DB::table('admin_user_roles')
            ->where('user_id', $user->id)
            ->where(function ($q) use ($project) {
                $q->where('role', 'admin')
                  ->orWhere('role', 'admin_' . $project->slug);
            })
            ->exists();
    }

    // GET /api/{slug}  → lista de tablas del proyecto
    public function tables(Request $request, string $slug)
    {
        $project = Project::where('slug', $slug)->firstOrFail();

        abort_unless($this->checkAccess($request, $project), 403, 'Sin acceso al proyecto');

        $tables = $project->tables()
            ->where('active', true)
            ->orderBy('order')
            ->get(['name', 'label']);

        return response()->json(['project' => $project->name, 'tables' => $tables]);
    }

    // GET /api/{slug}/{tabla}?page=1&per_page=500
    public function data(Request $request, string $slug, string $tabla)
    {
        $project = Project::where('slug', $slug)->firstOrFail();

        abort_unless($this->checkAccess($request, $project), 403, 'Sin acceso al proyecto');

        $projectTable = $project->tables()->where('name', $tabla)->firstOrFail();

        $fullTable = $projectTable->getFullTableName();

        abort_unless(Schema::hasTable($fullTable), 404, "Tabla no encontrada");

        $perPage = min((int) $request->input('per_page', 500), 5000);
        $page    = max((int) $request->input('page', 1), 1);

        $query = DB::table($fullTable);

        $total = $query->count();
        $rows  = $query->orderBy('id', 'desc')
                       ->skip($perPage * ($page - 1))
                       ->take($perPage)
                       ->get();

        return response()->json([
            'table'    => $tabla,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'next'     => ($page * $perPage) < $total ? $page + 1 : null,
            'data'     => $rows,
        ]);
    }
}
