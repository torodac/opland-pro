<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $cols = function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->nullable();
            $t->string('icnea_lodging_id', 20);
            $t->string('vm_propiedades_nombre')->nullable();
            $t->string('booking_id', 20)->index();
            $t->date('booking_date')->nullable();
            $t->string('booking_status', 30)->nullable();
            $t->date('check_in_date')->nullable();
            $t->date('check_out_date')->nullable();
            $t->smallInteger('number_of_adults')->default(0);
            $t->smallInteger('number_of_children')->default(0);
            $t->smallInteger('number_of_infants')->default(0);
            $t->string('guest_name')->nullable();
            $t->string('guest_email')->nullable();
            $t->string('guest_phone', 50)->nullable();
            $t->string('guest_language', 5)->nullable();
            $t->string('checkin_status', 20)->nullable();
            $t->json('trace')->nullable();
            $t->timestamp('icnea_updatedat')->nullable();
            $t->timestamp('createdat')->nullable();
            $t->timestamp('updatedat')->nullable();
        };

        Schema::create('vm_reservas', function (Blueprint $t) use ($cols) {
            $cols($t);
            $t->unique('booking_id');
        });

        Schema::create('vm_reservas_temp', $cols);
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_reservas');
        Schema::dropIfExists('vm_reservas_temp');
    }
};
