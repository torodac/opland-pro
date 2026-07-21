<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('id_departamento')->nullable()->after('departamento');
        });

        // Backfill: matchea el texto libre existente contra el catalogo nuevo
        $mapa = DB::table('vm_departamentos')->pluck('id', 'nombre');
        foreach ($mapa as $nombre => $id) {
            DB::table('vm_usuarios')->where('departamento', $nombre)->update(['id_departamento' => $id]);
        }
    }
    public function down(): void {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->dropColumn('id_departamento');
        });
    }
};
