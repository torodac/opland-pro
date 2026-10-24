<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Último evento registrado por cada cerradura Nuki: cuándo y quién (o cómo) la abrió o cerró.
// Lo rellena vm:sync-nuki con GET /smartlock/{id}/log?limit=1. Solo lectura.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_cerraduras', function (Blueprint $table) {
            if (!Schema::hasColumn('vm_cerraduras', 'ultimo_uso_at')) {
                $table->timestamp('ultimo_uso_at')->nullable();
            }
            if (!Schema::hasColumn('vm_cerraduras', 'ultimo_uso_quien')) {
                $table->string('ultimo_uso_quien', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vm_cerraduras', function (Blueprint $table) {
            $table->dropColumn(['ultimo_uso_at', 'ultimo_uso_quien']);
        });
    }
};
