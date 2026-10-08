<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->text('endpoint');
            $table->string('p256dh', 500);
            $table->string('auth', 200);
            $table->timestamp('createdat')->useCurrent();
            $table->timestamp('updatedat')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['admin_user_id', 'endpoint'], 'health_push_user_endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_push_subscriptions');
    }
};
