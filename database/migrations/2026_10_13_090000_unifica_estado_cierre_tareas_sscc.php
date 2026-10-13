<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// vm_tareas_sscc tenia DOS formas de decir "hecho": Completada y Finalizada. Era la unica tabla
// del sistema con la segunda -- sus dos hermanas (tareas_limpieza y tareas_mantenimiento) tienen
// la misma lista de opciones exactamente, menos esa palabra.
//
// De donde salio: el boton "Hecho" del panel de recordatorios SSCC se escribio el 2026-08-18
// eligiendo 'Finalizada', que no estaba en el desplegable; seis dias despues se anadio a las
// opciones para que la lista cuadrase con lo que el boton ya guardaba. No vino de que el negocio
// distinga una cosa de otra.
//
// El coste de dejarlo: cada consulta que quiera saber si una tarea esta cerrada tiene que
// acordarse de aceptar las dos, y la que no se acuerde se equivoca en silencio. Con la fianza de
// un huesped de por medio, esa equivocacion es decir que el dinero se ha devuelto cuando no.
return new class extends Migration
{
    private const CAMPO = 'estado';
    private const TABLA = 'tareas_sscc';
    private const CON    = 'opt:Nueva,Planificada,Vencida,Completada,Finalizada,Cancelada,Descartada';
    private const SIN    = 'opt:Nueva,Planificada,Vencida,Completada,Cancelada,Descartada';

    public function up(): void
    {
        // Primero los datos, luego la opcion: al reves dejaria filas con un valor que ya no
        // esta en la lista, que es justo el problema que creo esto.
        $migradas = DB::table('vm_tareas_sscc')
            ->where('estado', 'Finalizada')
            ->update(['estado' => 'Completada', 'updatedat' => now()]);

        DB::table('admin_table_fields')
            ->whereIn('project_table_id', $this->idTabla())
            ->where('name', self::CAMPO)
            ->where('extras', self::CON)
            ->update(['extras' => self::SIN, 'updated_at' => now()]);

        echo "  {$migradas} tarea(s) SSCC pasadas de Finalizada a Completada; opcion retirada del desplegable." . PHP_EOL;
    }

    public function down(): void
    {
        // Se devuelve la opcion a la lista, pero NO se reparten los datos: distinguir que filas
        // estaban en Finalizada antes de la migracion es imposible, y adivinarlo seria peor que
        // dejarlas todas en Completada, que es un estado valido en los dos mundos.
        DB::table('admin_table_fields')
            ->whereIn('project_table_id', $this->idTabla())
            ->where('name', self::CAMPO)
            ->where('extras', self::SIN)
            ->update(['extras' => self::CON, 'updated_at' => now()]);
    }

    private function idTabla(): array
    {
        return DB::table('admin_project_tables')->where('name', self::TABLA)->pluck('id')->all();
    }
};
