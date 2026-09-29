<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\TableField;
use App\Services\EsquemaDesajustes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

// Da de alta como campos las columnas que existen en una tabla pero que nadie declaró.
//
// Por qué se puede automatizar: los campos nacen OCULTOS en formulario y en listado. Declarar
// una columna solo significa que la configuración la conoce; que salga por pantalla es una
// decisión aparte, con sus casillas. Así una columna nueva nunca aparece sola en la ficha de
// nadie, y a la vez deja de haber datos guardados que la aplicación ignora por completo.
//
// Lo que NO hace, a propósito: tocar el esquema. Solo crea filas de configuración para columnas
// que ya existen. La etiqueta se propone a partir del nombre ("bi" -> "Bi"), que es lo único
// deducible; la buena ("Base imponible") la pone una persona al publicar el campo.
//
//   php artisan opland:declarar-columnas            → simulación, no escribe
//   php artisan opland:declarar-columnas --apply    → declara
//   php artisan opland:declarar-columnas vm --apply → solo un proyecto
class DeclararColumnasCommand extends Command
{
    protected $signature = 'opland:declarar-columnas
                            {slug? : Limitar a un proyecto}
                            {--apply : Persistir; sin esta opción solo muestra lo que haría}';

    protected $description = 'Declara como campos ocultos las columnas que aún no están en la configuración';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $proyectos = $this->argument('slug')
            ? Project::where('slug', $this->argument('slug'))->get()
            : Project::orderBy('slug')->get();

        if ($proyectos->isEmpty()) {
            $this->error('No hay proyectos que revisar.');
            return self::FAILURE;
        }

        $totalNuevos = 0;
        $rotos = [];

        foreach ($proyectos as $project) {
            $tablas = ProjectTable::where('project_id', $project->id)
                ->where('active', true)->where('is_virtual', false)
                ->orderBy('name')->get();

            foreach ($tablas as $table) {
                if (!Schema::hasTable($table->getFullTableName())) continue;

                foreach (EsquemaDesajustes::camposSinColumna($table) as $f) {
                    $rotos[] = $table->getFullTableName() . '.' . $f->name;
                }

                $pendientes = EsquemaDesajustes::columnasSinDeclarar($project, $table);
                if (!$pendientes) continue;

                $orden = ($table->fields()->where('order', '<', 900)->max('order') ?? 0);

                foreach ($pendientes as $c) {
                    $tipo = array_key_exists($c['tipo'], TableField::$typeMap) ? $c['tipo'] : 'string';

                    $this->line(sprintf('  %-34s %-22s %-10s %s',
                        $table->getFullTableName() . '.' . $c['name'],
                        $c['tipo_bd'], $tipo, $c['con_datos'] ? 'CON DATOS' : ''));

                    if ($apply) {
                        $table->fields()->create([
                            'name'     => $c['name'],
                            'label'    => $c['label'],
                            'type'     => $tipo,
                            'order'    => ++$orden,
                            'required' => false,
                            'in_list'  => false,   // ocultas al nacer: ver el comentario de arriba
                            'in_form'  => false,
                            'extras'   => $c['extras'],
                        ]);
                    }
                    $totalNuevos++;
                }
            }
        }

        $this->newLine();
        $this->info(($apply ? 'Declaradas ' : 'Se declararían ') . $totalNuevos . ' columna(s), ocultas en formulario y listado.');

        if ($rotos) {
            $this->newLine();
            $this->warn('Campos declarados SIN columna física (rompen el guardado de esa ficha):');
            foreach ($rotos as $r) $this->line('  ' . $r);
        }

        if ($apply && ($totalNuevos || $rotos)) {
            Log::info("opland:declarar-columnas: {$totalNuevos} columnas declaradas, " . count($rotos) . ' campos sin columna.');
        }

        return self::SUCCESS;
    }
}
