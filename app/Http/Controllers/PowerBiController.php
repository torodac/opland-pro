<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Embed de informes Power BI por proyecto. Reemplaza al PBIController legacy: en vez del
 * flujo de token de un solo uso (pensado para servir el iframe sin sesion), aqui el usuario
 * ya esta autenticado via 'auth' + 'project.access', asi que el token de embed de Power BI
 * se obtiene directamente en cada carga de la pagina.
 */
class PowerBiController extends Controller
{
    public function show(Project $project, string $informe)
    {
        // Un proyecto puede tener varios informes (p.ej. vm: operaciones/gerencia/gobernanta),
        // cada uno con su propia fila pseudo en admin_project_tables ("powerbi_<slug>", sin
        // tabla fisica real, igual que "dashboard") para que canViewTable() respete el 'ver'
        // de cada rol de forma independiente por informe.
        abort_unless(Auth::user()?->canViewTable($project, 'powerbi_' . $informe), 403);

        $report = DB::table('admin_pbi_reports')
            ->where('id_proyectos', $project->id)
            ->where('slug', $informe)
            ->where('deleted', 0)
            ->where('hidden', 0)
            ->first();

        abort_unless($report, 404, 'Este proyecto no tiene ningún informe de Power BI configurado con ese nombre.');

        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/token', [
            'grant_type'    => 'password',
            'username'      => config('services.powerbi.username'),
            'password'      => config('services.powerbi.password'),
            'client_id'     => config('services.powerbi.client_id'),
            'client_secret' => config('services.powerbi.client_secret'),
            'resource'      => 'https://analysis.windows.net/powerbi/api',
            'scope'         => 'openid',
        ]);

        $token = $response->json('access_token');

        abort_unless($token, 502, 'No se ha podido obtener el token de Power BI. Revisa las credenciales en .env.');

        return view('powerbi.show', [
            'project'        => $project,
            'token'          => $token,
            'reportId'       => $report->reportid,
            'embedUrl'       => 'https://app.powerbi.com/reportEmbed?reportId=' . $report->reportid,
            'pageNavigation' => (bool) $report->page_navigation,
            'filtersVisible' => (bool) $report->filters_visible,
            'reportPage'     => $report->reportpage,
            'filtros'        => $report->filtros ? json_decode($report->filtros, true) : [],
        ]);
    }
}
