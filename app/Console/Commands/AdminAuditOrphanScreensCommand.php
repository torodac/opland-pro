<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Detecta pantallas registradas solo a medias: un admin_menu_items con url propia (no genérico
// de listado) pero sin project_table_id, por lo que no puede aparecer en /config/projects/{slug}
// aunque funcione perfectamente por URL directa. Ver DOC_TECNICO.md para el patrón de arreglo
// (fila virtual en admin_project_tables, is_virtual=true, sin tabla real detrás).
class AdminAuditOrphanScreensCommand extends Command
{
    protected $signature   = 'admin:audit-orphan-screens';
    protected $description = 'Lista pantallas con menú propio pero sin fila en admin_project_tables (invisibles en /config/projects)';

    public function handle(): int
    {
        $huerfanas = DB::table('admin_menu_items as m')
            ->join('admin_projects as p', 'p.id', '=', 'm.project_id')
            ->whereNotNull('m.url')
            ->where('m.url', '!=', '')
            ->whereNull('m.project_table_id')
            ->orderBy('p.slug')
            ->orderBy('m.label')
            ->get(['m.id', 'p.slug', 'm.label', 'm.url']);

        if ($huerfanas->isEmpty()) {
            $this->info('Sin pantallas huérfanas. Todo lo que tiene url propia está enlazado a admin_project_tables.');
            return self::SUCCESS;
        }

        $this->warn("{$huerfanas->count()} pantalla(s) con url propia y sin project_table_id (no aparecerán en /config/projects/{slug}):");
        $this->table(
            ['menu_item_id', 'proyecto', 'label', 'url'],
            $huerfanas->map(fn($h) => [$h->id, $h->slug, $h->label, $h->url])
        );
        $this->line('Arreglo: crear una fila virtual en admin_project_tables (is_virtual=true, sin tabla real) y enlazarla con UPDATE admin_menu_items SET project_table_id=... WHERE id=... — ver id=196 (mb, movs_bancarios_import_default) como plantilla.');

        Log::warning('admin:audit-orphan-screens: pantallas sin project_table_id detectadas', [
            'huerfanas' => $huerfanas->toArray(),
        ]);

        return self::FAILURE;
    }
}
