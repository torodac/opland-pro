<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('eng_librerias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('eng_librerias_usuarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('libreria_id');
            $table->unsignedBigInteger('admin_user_id');
            $table->timestamps();
            $table->unique(['libreria_id', 'admin_user_id']);
        });

        Schema::table('eng_vocabulario', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_user_id')->nullable()->after('id');
            $table->unsignedBigInteger('libreria_id')->nullable()->after('admin_user_id');
            $table->text('traduccion')->nullable()->after('frase');
        });
    }

    public function down(): void {
        Schema::dropIfExists('eng_librerias_usuarios');
        Schema::dropIfExists('eng_librerias');
        Schema::table('eng_vocabulario', function (Blueprint $table) {
            $table->dropColumn(['admin_user_id', 'libreria_id', 'traduccion']);
        });
    }
};
