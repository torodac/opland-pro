<?php

namespace App\Http\Controllers\Vmf;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class FacturasUploadController extends Controller
{
    public function index(Request $request, Project $project)
    {
        return view('vmf.facturas-form', ['project' => $project]);
    }
}
