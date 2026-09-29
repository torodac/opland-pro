<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Columna donde se guarda la respuesta en bruto de Claude al interpretar una factura recibida.
// Es lo único que permite después entender por qué un importe salió mal, así que se guarda
// siempre, incluso cuando la respuesta no se pudo parsear.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('opland_fta_soportadas', 'interpretacion')) return;

        Schema::table('opland_fta_soportadas', function (Blueprint $table) {
            $table->text('interpretacion')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('opland_fta_soportadas', 'interpretacion')) return;

        Schema::table('opland_fta_soportadas', function (Blueprint $table) {
            $table->dropColumn('interpretacion');
        });
    }
};
