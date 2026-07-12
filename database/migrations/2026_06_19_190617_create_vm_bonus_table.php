<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_bonus', function (Blueprint $table) {
            $table->id();
            $table->string('alcance', 20);
            $table->string('id_referencia', 100);
            $table->string('meses', 50);
            $table->decimal('importe', 10, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->smallInteger('deleted')->default(0);
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('vm_bonus')->insert([
            ['alcance'=>'usuario',      'id_referencia'=>'Araceli',    'meses'=>'6,12',      'importe'=>5000.00, 'fecha_inicio'=>'2025-06-01', 'fecha_fin'=>null, 'descripcion'=>'Jefe Operaciones - Adm/Finanzas', 'deleted'=>0],
            ['alcance'=>'usuario',      'id_referencia'=>'Riccardo',   'meses'=>'3,6,9,12',  'importe'=>300.00,  'fecha_inicio'=>'2025-07-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
            ['alcance'=>'usuario',      'id_referencia'=>'Carmen A',   'meses'=>'3,6,9,12',  'importe'=>500.00,  'fecha_inicio'=>'2025-07-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
            ['alcance'=>'cargo',        'id_referencia'=>'Limpiadora', 'meses'=>'3,6,9,12',  'importe'=>200.00,  'fecha_inicio'=>'2024-01-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
            ['alcance'=>'cargo',        'id_referencia'=>'Limpiadora', 'meses'=>'6,7,8,9',   'importe'=>300.00,  'fecha_inicio'=>'2024-01-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
            ['alcance'=>'usuario',      'id_referencia'=>'Antonio',    'meses'=>'6,12',      'importe'=>6000.00, 'fecha_inicio'=>'2024-01-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
            ['alcance'=>'usuario',      'id_referencia'=>'Auxi',       'meses'=>'6,12',      'importe'=>250.00,  'fecha_inicio'=>'2024-01-01', 'fecha_fin'=>null, 'descripcion'=>null, 'deleted'=>0],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_bonus');
    }
};
