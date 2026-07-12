<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->string('observacion', 500)->nullable()->after('hora_fin_auto');
        });
    }
    public function down(): void {
        Schema::table('vm_fichaje', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
