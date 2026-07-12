<?php

namespace App\Http\Controllers\Vm;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Controller;

class PropiedadesController extends Controller
{
    public function syncIcnea(Request $request, Project $project)
    {
        Artisan::call('icnea:sync-pro');
        return redirect()->route('listado', [$project->slug, 'propiedades'])
            ->with('flash', 'Sincronizacion con Icnea completada.');
    }
}
