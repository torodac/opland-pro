<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// El campo "Tipo" de vm_tareas_mantenimiento lleva declarado como select (opt:Reparación,Revisión)
// desde siempre, pero vacio en 3.353 de 3.354 filas: nadie lo rellenaba. Desde ahora el sync lo
// pone por departamento de Breezeway, y este backfill hace lo propio con lo ya importado.
//
// Todas las existentes son de "maintenance", porque "inspection" nunca entro hasta hoy: por eso
// el backfill es un valor unico y no hay que mirar de donde vino cada una.
//
// Solo toca las que estan VACIAS. La unica fila con valor es un "Novación" que tampoco esta entre
// las opciones del select; es una incoherencia anterior y se deja como esta, porque corregirla
// aqui seria cambiar un dato que nadie ha pedido cambiar.
return new class extends Migration
{
    public function up(): void
    {
        $afectadas = DB::table('vm_tareas_mantenimiento')
            ->where(fn($q) => $q->whereNull('Tipo')->orWhere('Tipo', ''))
            ->update(['Tipo' => 'Reparación']);

        // El departamento de origen, solo en las que vinieron de Breezeway: una tarea creada a
        // mano en Opland no tiene departamento de Breezeway, y poner uno seria inventarselo.
        DB::table('vm_tareas_mantenimiento')
            ->whereNotNull('breezeway_task_id')
            ->whereNull('departamento_breezeway')
            ->update(['departamento_breezeway' => 'maintenance']);

        echo "  Tipo rellenado en {$afectadas} tareas de mantenimiento." . PHP_EOL;
    }

    public function down(): void
    {
        // No se revierte: distinguir las que este backfill relleno de las que luego rellene el
        // sync es imposible, y vaciar el campo a ciegas destruiria datos buenos.
    }
};
