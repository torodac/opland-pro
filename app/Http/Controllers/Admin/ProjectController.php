<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount('tables')->orderBy('name')->get();

        return view('config.projects.index', compact('projects'));
    }

    public function create()
    {
        return view('config.projects.form', ['project' => new Project()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:50|unique:projects|alpha_dash',
            'description' => 'nullable|string|max:255',
            'logo'        => 'nullable|image|max:2048',
            'favicon'     => 'nullable|file|mimes:ico,png,svg|max:512',
        ]);

        unset($data['logo'], $data['favicon']);

        $project = Project::create($data);

        $this->handleFileUploads($request, $project);

        $project->createDefaultTables();

        return redirect()->route('config.projects.index')->with('success', 'Proyecto creado.');
    }

    public function edit(Project $project)
    {
        return view('config.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'logo'        => 'nullable|image|max:2048',
            'favicon'     => 'nullable|file|mimes:ico,png,svg|max:512',
        ]);

        // Solo actualiza active si viene en el request (formulario de edición completo)
        if ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        }
        unset($data['logo'], $data['favicon']);

        $project->update($data);

        $this->handleFileUploads($request, $project);

        $redirect = $request->input('_redirect', route('config.projects.index'));

        return redirect($redirect)->with('success', 'Proyecto actualizado.');
    }

    private function handleFileUploads(Request $request, Project $project): void
    {
        $dir = public_path('projects/' . $project->slug);
        File::ensureDirectoryExists($dir);

        if ($request->hasFile('logo')) {
            $ext  = $request->file('logo')->getClientOriginalExtension();
            $name = 'logo.' . $ext;
            $request->file('logo')->move($dir, $name);
            $project->update(['logo' => 'projects/' . $project->slug . '/' . $name]);
        }

        if ($request->hasFile('favicon')) {
            $ext  = $request->file('favicon')->getClientOriginalExtension();
            $name = 'favicon.' . $ext;
            $request->file('favicon')->move($dir, $name);
            $project->update(['favicon' => 'projects/' . $project->slug . '/' . $name]);
        }
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('config.projects.index')->with('success', 'Proyecto eliminado.');
    }

    public function show(Project $project)
    {
        return redirect()->route('config.projects.tables.index', $project);
    }
}
