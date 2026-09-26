<?php

namespace App\Console\Commands;

use App\Http\Middleware\EnforceMenuTableAccess;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

// Audita qué rutas de proyecto quedan sin comprobación del permiso "Puede ver" del rol.
//
// EnforceMenuTableAccess cubre automáticamente toda ruta que cuelgue de una entrada del menú.
// Lo que no cuelga de ninguna no se puede deducir, así que este comando lo lista en vez de
// dejarlo invisible: es la parte que hay que revisar a mano cuando se añade una pantalla cuyas
// rutas no comparten prefijo con su entrada de menú.
//
//   php artisan opland:auditar-acceso            → todos los proyectos, solo lo descubierto
//   php artisan opland:auditar-acceso vm --todo  → un proyecto, incluyendo lo ya cubierto
class AuditarAccesoCommand extends Command
{
    protected $signature = 'opland:auditar-acceso {slug? : Slug del proyecto} {--todo : Mostrar también las rutas ya cubiertas}';
    protected $description = 'Lista las rutas de proyecto que no quedan cubiertas por el permiso "Puede ver" del rol';

    public function handle(): int
    {
        $proyectos = $this->argument('slug')
            ? Project::where('slug', $this->argument('slug'))->get()
            : Project::orderBy('slug')->get();

        if ($proyectos->isEmpty()) {
            $this->error('No hay proyectos que auditar.');
            return self::FAILURE;
        }

        $salida = 0;

        foreach ($proyectos as $project) {
            $pantallas = EnforceMenuTableAccess::pantallas($project);
            $cubiertas = [];
            $dinamicas = [];
            $sueltas   = [];

            foreach (Route::getRoutes() as $ruta) {
                $uri = '/' . ltrim($ruta->uri(), '/');
                if (!str_starts_with($uri, '/{project:slug}') && !str_starts_with($uri, '/{project}')) {
                    continue;
                }

                // Las rutas de un proyecto concreto llevan su middleware "<slug>.only"; auditando
                // vm no tiene sentido contar las exclusivas de mb, que ahí nunca se alcanzan.
                $soloDe = collect($ruta->gatherMiddleware())
                    ->first(fn($m) => is_string($m) && preg_match('/^[a-z]+\.only$/', $m));
                if ($soloDe && $soloDe !== $project->slug . '.only') {
                    continue;
                }

                // Sustituye el parámetro del proyecto por el slug real; el resto de parámetros se
                // dejan tal cual, no afectan a la resolución (que es por prefijo).
                $path = preg_replace('#^/\{project(:slug)?\}#', '/' . $project->slug, $uri);

                $tabla    = EnforceMenuTableAccess::tablaDeLaUrl($project, $path);
                $explicit = collect($ruta->gatherMiddleware())
                    ->first(fn($m) => is_string($m) && str_starts_with($m, 'table.access:'));

                // Rutas cuya tabla es un parámetro de la propia URL ({table}, {tipo}, {informe}):
                // no se pueden resolver desde el menú porque no se sabe a qué tabla apuntan hasta
                // que llega la petición. Las comprueban sus controladores (ListadoController,
                // FichaController, TareaController, PowerBiController) con canViewTable().
                $dinamica = (bool) preg_match('/\{(table|tipo|informe)\}/', $path);

                $fila = [implode('|', $ruta->methods()), $path, $tabla ?? '—', $explicit ? 'table.access' : ''];

                if ($tabla !== null || $explicit) $cubiertas[] = $fila;
                elseif ($dinamica)                $dinamicas[] = $fila;
                else                              $sueltas[]   = $fila;
            }

            $this->newLine();
            $this->line("<options=bold>{$project->slug}</> — " . count($pantallas) . ' pantalla(s) en el menú, '
                . count($cubiertas) . ' cubierta(s) por el menú, ' . count($dinamicas)
                . ' de tabla dinámica (las comprueba su controlador), ' . count($sueltas) . ' SIN CUBRIR');

            if ($this->option('todo') && $cubiertas) {
                $this->table(['Método', 'Ruta', 'Tabla', 'Extra'], $cubiertas);
            }

            if ($this->option('todo') && $dinamicas) {
                $this->line('De tabla dinámica, comprobadas por su controlador:');
                $this->table(['Método', 'Ruta', 'Tabla', 'Extra'], $dinamicas);
            }

            if ($sueltas) {
                $salida = 1;
                $this->warn('Sin comprobación derivada del menú (revisar si el controlador la hace por su cuenta):');
                $this->table(['Método', 'Ruta', 'Tabla', 'Extra'], $sueltas);
            }
        }

        return $salida === 0 ? self::SUCCESS : self::FAILURE;
    }
}
