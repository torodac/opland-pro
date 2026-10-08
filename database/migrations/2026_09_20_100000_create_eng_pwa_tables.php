<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('eng_pwa_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('token', 64)->unique();
            $table->string('device', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('eng_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('vocab_id');
            $table->unsignedInteger('seen_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('next_due')->nullable();
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['admin_user_id', 'vocab_id'], 'eng_progress_user_vocab');
        });
    }

    public function down(): void {
        Schema::dropIfExists('eng_pwa_tokens');
        Schema::dropIfExists('eng_progress');
    }
};
