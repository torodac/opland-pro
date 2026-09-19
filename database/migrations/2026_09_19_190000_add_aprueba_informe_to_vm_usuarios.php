<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Quién aprueba el informe mensual de cada persona. Es el primer paso del flujo de firmas, por
// persona en vez de por jerarquía de roles: sustituye al paso "coordinador", que se deducía de
// vm_roles.roles_supervisados y no permitía excepciones. El paso arranca desactivado (ver
// InformeImputacionesController::firmarPaso), así que rellenar el campo no cambia nada todavía.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('id_aprueba_informe')->nullable()->after('id_departamento');
        });
    }

    public function down(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->dropColumn('id_aprueba_informe');
        });
    }
};
