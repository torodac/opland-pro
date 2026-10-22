<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Retira "Aprueba informe" (vm_usuarios.id_aprueba_informe), que dejo de gobernar nada el
// 08/10/2026 al invertirse la relacion: el aprobador sale desde entonces de la lista
// "Supervisa a" (vm_usuarios.supervisados), en la ficha del responsable.
//
// Comprobado antes de borrar que no quedaba ninguna lectura: el circuito de firmas usa
// VmJerarquiaAprobacion::mapaAprobadores(), la ficha de VM no ofrecia el campo, y el unico
// consumidor era la pestaña 2 de Jerarquias, que por eso dibujaba un arbol obsoleto. Esa
// pantalla pasa a leer supervisados en el mismo commit.
//
// VALORES EN EL MOMENTO DE BORRAR (22/10/2026), por si alguna vez hay que reconstruirlos.
// Coincidian con "Supervisa a" en 21 de 22 personas; la excepcion era Maria Auxiliadora Alba
// Guerrero, a quien este campo asignaba a Santi Ramon-Llin y que en el circuito real no tiene
// supervisor. Formato: id usuario => id aprobador.
//
//   2 => 8     3 => 15    5 => 15    8 => 1     9 => 8     10 => 50   12 => 1
//   13 => 15   16 => 15   18 => 15   19 => 15   20 => 15   22 => 1    23 => 8
//   24 => 1    26 => 29   29 => 67   30 => 1    33 => 15   51 => 15   52 => 29
//   66 => 50   69 => 29
return new class extends Migration
{
    public function up(): void
    {
        // Primero la declaracion del campo: mientras exista, la ficha generica lo ofreceria
        // y al guardar intentaria escribir una columna que ya no esta.
        $n = DB::table('admin_table_fields')
            ->where('name', 'id_aprueba_informe')
            ->whereIn('project_table_id', DB::table('admin_project_tables')
                ->where('name', 'usuarios')
                ->where('project_id', DB::table('admin_projects')->where('slug', 'vm')->value('id'))
                ->pluck('id'))
            ->delete();
        echo "  {$n} declaracion(es) de campo retirada(s)." . PHP_EOL;

        if (Schema::hasColumn('vm_usuarios', 'id_aprueba_informe')) {
            Schema::table('vm_usuarios', function (Blueprint $table) {
                $table->dropColumn('id_aprueba_informe');
            });
            echo '  Columna vm_usuarios.id_aprueba_informe eliminada.' . PHP_EOL;
        }
    }

    // Devuelve la columna vacia y su declaracion. Los valores NO se restauran: estan en el
    // comentario de arriba y en el historial de git, que es donde deben estar.
    public function down(): void
    {
        if (!Schema::hasColumn('vm_usuarios', 'id_aprueba_informe')) {
            Schema::table('vm_usuarios', function (Blueprint $table) {
                $table->unsignedBigInteger('id_aprueba_informe')->nullable();
            });
        }

        $tablaId = DB::table('admin_project_tables')
            ->where('name', 'usuarios')
            ->where('project_id', DB::table('admin_projects')->where('slug', 'vm')->value('id'))
            ->value('id');

        if ($tablaId && !DB::table('admin_table_fields')
                ->where('project_table_id', $tablaId)->where('name', 'id_aprueba_informe')->exists()) {
            DB::table('admin_table_fields')->insert([
                'project_table_id' => $tablaId,
                'name'   => 'id_aprueba_informe',
                'label'  => 'Aprueba informe',
                'type'   => 'desplegable',
                'extras' => 'ref:usuarios|rol:2,3,5,9,10,11,13',
                'order'  => 15,
                'required' => false,
                'in_list'  => false,
                'in_form'  => true,
                'hidden'   => 0,
                'blocked'  => 0,
                'createuser' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
