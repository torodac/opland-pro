<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_pwa_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('admin_user_id');
            $table->string('device', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('health_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->date('log_date');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('breakfast')->nullable();
            $table->boolean('breakfast_bad')->default(false);
            $table->text('mid_morning')->nullable();
            $table->boolean('mid_morning_bad')->default(false);
            $table->text('lunch')->nullable();
            $table->boolean('lunch_bad')->default(false);
            $table->text('snack')->nullable();
            $table->boolean('snack_bad')->default(false);
            $table->text('dinner')->nullable();
            $table->boolean('dinner_bad')->default(false);
            $table->text('sport')->nullable();
            $table->timestamps();

            $table->unique(['admin_user_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_daily_logs');
        Schema::dropIfExists('health_pwa_tokens');
    }
};
