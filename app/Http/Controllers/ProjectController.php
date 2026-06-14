<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $projects = Project::where('active', true)->orderBy('name')->get()
            ->filter(function ($project) use ($user) {
                if ($user->isProjectAdmin($project)) return true;
                return $user->projectUserId($project) !== null;
            })->values();

        if ($projects->count() === 1) {
            $homeUrl = $this->resolveHomeUrl($user, $projects->first());
            if ($homeUrl !== url('/')) {
                return redirect($homeUrl);
            }
        }

        return view('proyectos', compact('projects'));
    }

    public static function resolveHomeUrl($user, Project $project): string
    {
        // 1. Tabla de inicio del rol del usuario en este proyecto
        $role = optional($user)->getProjectRolePublic($project);
        $tabla = $role?->tabla_default ?? null;

        // 2. Si no tiene en el rol, usar la configuración del proyecto
        if (!$tabla) {
            try {
                $tabla = \DB::table($project->slug . '_configuracion')
                    ->where('nombre', 'inicio')
                    ->value('valor');
            } catch (\Exception $e) {
                $tabla = null;
            }
        }

        if ($tabla) {
            return route('listado', [$project->slug, $tabla]);
        }

        // 3. Fallback: primer ítem del menú del proyecto
        $first = $project->menuItems()->whereHas('projectTable', fn($q) => $q->where('admin_only', false))->first();
        if ($first?->projectTable) {
            return route('listado', [$project->slug, $first->projectTable->name]);
        }

        return url('/');
    }
}
