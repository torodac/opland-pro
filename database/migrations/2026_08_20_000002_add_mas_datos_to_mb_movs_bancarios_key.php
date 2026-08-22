<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mb_movs_bancarios', function ($table) {
            $table->dropUnique('mb_movs_bancarios_natural_key');
            $table->unique(['id_movs_cuenta', 'fecha', 'movimiento', 'mas_datos', 'importe', 'saldo'], 'mb_movs_bancarios_natural_key');
        });
    }

    public function down(): void
    {
        Schema::table('mb_movs_bancarios', function ($table) {
            $table->dropUnique('mb_movs_bancarios_natural_key');
            $table->unique(['id_movs_cuenta', 'fecha', 'movimiento', 'importe', 'saldo'], 'mb_movs_bancarios_natural_key');
        });
    }
};
