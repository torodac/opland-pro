<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ítem del menú lateral de un proyecto.
 * Puede apuntar a una tabla dinámica (project_table_id) o a una URL directa.
 * Soporta submenús via parent_id.
 */
class MenuItem extends Model
{
    protected $table = 'admin_menu_items';

    protected $fillable = [
        'project_id', 'label', 'icon', 'project_table_id', 'url', 'parent_id', 'order',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectTable()
    {
        return $this->belongsTo(ProjectTable::class, 'project_table_id');
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    // URL a la que apunta este ítem
    public function resolveUrl(): string
    {
        if ($this->url) {
            // Algunos items tienen la URL guardada en relativo (p.ej. "/mb/pyg"), mientras que las
            // resueltas via route() de mas abajo son siempre absolutas. url() normaliza ambos casos
            // a absoluta (si ya lo es, la deja igual) para que la comparacion con request()->url()
            // en el sidebar (marcador data-sidebar-active) funcione sea cual sea el formato guardado.
            return url($this->url);
        }
        if ($this->projectTable) {
            $taskMap = [
                'tareas_limpieza'      => 'limpieza',
                'tareas_mantenimiento' => 'mantenimiento',
                'tareas_piscinas'      => 'piscina',
            ];
            if (isset($taskMap[$this->projectTable->name]) && $this->project->slug === 'vm') {
                return route('vm.tarea.list', [
                    'project' => $this->project->slug,
                    'tipo'    => $taskMap[$this->projectTable->name],
                ]);
            }
            return route('listado', [
                'project' => $this->project->slug,
                'table'   => $this->projectTable->name,
            ]);
        }
        return '#';
    }
}
