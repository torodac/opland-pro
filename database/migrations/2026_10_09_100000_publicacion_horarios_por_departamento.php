<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// La publicacion del cuadrante pasa de ser una sola para toda la empresa a una por departamento,
// para que cada coordinador publique lo suyo sin esperar ni pisar a los demas.
//
// Lo delicado es la conversion de lo que ya hay. La publicacion funciona como un muro: la primera
// semana oculta tapa esa y TODAS las posteriores. Si las filas existentes se quedaran sin
// departamento, el muro de cada departamento se calcularia sobre la nada y la PWA abriria de
// golpe cuadrantes a medio montar. Por eso cada fila se replica a los cuatro departamentos
// visibles: el estado que ve el trabajador el dia del despliegue es exactamente el de la vispera.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_horarios_publicacion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_departamento')->nullable()->after('semana');
        });

        $departamentos = DB::table('vm_departamentos')
            ->where('visible_horarios', true)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->pluck('id');

        $previas = DB::table('vm_horarios_publicacion')->get();

        // La única antigua impide dos filas de la misma semana, que es justo lo que las copias
        // van a crear, así que se retira ANTES de replicar y no después.
        DB::statement('ALTER TABLE vm_horarios_publicacion
                       DROP CONSTRAINT IF EXISTS vm_horarios_publicacion_anio_semana_unique');

        // Primero las copias para los demas departamentos, y despues se marca la original con el
        // primero: asi no hay un instante con dos filas identicas sin departamento.
        foreach ($previas as $fila) {
            foreach ($departamentos->slice(1) as $idDep) {
                DB::table('vm_horarios_publicacion')->insert([
                    'anio'            => $fila->anio,
                    'semana'          => $fila->semana,
                    'id_departamento' => $idDep,
                    'publicado'       => $fila->publicado,
                    'updateuser'      => $fila->updateuser,
                    'createdat'       => $fila->createdat,
                    'updatedat'       => now(),
                ]);
            }

            DB::table('vm_horarios_publicacion')->where('id', $fila->id)
                ->update(['id_departamento' => $departamentos->first(), 'updatedat' => now()]);
        }

        Schema::table('vm_horarios_publicacion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_departamento')->nullable(false)->change();
            $table->unique(['anio', 'semana', 'id_departamento'], 'vm_horarios_pub_anio_semana_dep_unique');
            $table->foreign('id_departamento')->references('id')->on('vm_departamentos')->cascadeOnDelete();
        });

        // Quien publica el cuadrante de cada departamento. Lista de ids de departamento en JSON,
        // en la ficha del usuario: el permiso deja de ser una regla de rol y pasa a ser un dato
        // que se cambia desde el front, que es lo que permite cubrir unas vacaciones sin
        // desplegar nada.
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->text('publica_horarios')->nullable()->after('supervisados');
        });
    }

    public function down(): void
    {
        Schema::table('vm_usuarios', fn(Blueprint $t) => $t->dropColumn('publica_horarios'));

        Schema::table('vm_horarios_publicacion', function (Blueprint $table) {
            $table->dropForeign(['id_departamento']);
            $table->dropUnique('vm_horarios_pub_anio_semana_dep_unique');
        });

        // Al volver atras solo puede quedar una fila por semana: se conserva la del departamento
        // de id mas bajo, que es la que esta migracion dejo como original.
        DB::statement('DELETE FROM vm_horarios_publicacion a
                       USING vm_horarios_publicacion b
                       WHERE a.anio = b.anio AND a.semana = b.semana AND a.id > b.id');

        Schema::table('vm_horarios_publicacion', function (Blueprint $table) {
            $table->dropColumn('id_departamento');
            $table->unique(['anio', 'semana'], 'vm_horarios_publicacion_anio_semana_unique');
        });
    }
};
