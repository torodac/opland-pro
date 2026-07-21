<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('vm_novaciones_gastos', function (Blueprint $table) {
            $table->timestamp('ultima_sincronizacion')->nullable()->after('updatedat');
        });
    }
    public function down(): void {
        Schema::table('vm_novaciones_gastos', function (Blueprint $table) {
            $table->dropColumn('ultima_sincronizacion');
        });
    }
};
