<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Punto de partida del saldo de horas extra tras la migración desde el sistema anterior
// (Hostinger): antes de esta fecha, VmHorasService::saldoAcumuladoHoras() no reconstruye el
// histórico a partir de vm_fichaje (que puede no tener datos completos de años anteriores) --
// toma el saldo ya conciliado contra el informe mensual firmado y solo suma lo que pase a partir
// de esa fecha. Nulo por defecto: sin estos dos campos rellenos, el cálculo sigue funcionando
// exactamente igual que antes (100% basado en fichajes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->decimal('saldo_horas_inicial', 10, 2)->nullable()->after('sede');
            $table->date('saldo_inicial_a_fecha')->nullable()->after('saldo_horas_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('vm_usuarios', function (Blueprint $table) {
            $table->dropColumn(['saldo_horas_inicial', 'saldo_inicial_a_fecha']);
        });
    }
};
