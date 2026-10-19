<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cómo se devuelve la fianza: TPV virtual, TPV físico o transferencia. Icnea no da el método de
// pago, así que se elige en la ficha. La transferencia necesita el IBAN del huésped, que llega
// por un enlace firmado a Filipinas y se guarda cifrado (cast `encrypted` en el modelo).
//
// fianza_token_iban es el secreto del enlace: sin él no hay forma de llegar al formulario, y se
// regenera al reenviarlo. fianza_iban_guardado_at bloquea la edición una vez guardado.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vm_reservas', 'fianza_canal_cobro')) {
            Schema::table('vm_reservas', function (Blueprint $table) {
                $table->string('fianza_canal_cobro', 30)->nullable();
                $table->text('fianza_iban_cifrado')->nullable();
                $table->string('fianza_token_iban', 64)->nullable();
                $table->timestamp('fianza_iban_guardado_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vm_reservas', 'fianza_canal_cobro')) {
            Schema::table('vm_reservas', function (Blueprint $table) {
                $table->dropColumn([
                    'fianza_canal_cobro', 'fianza_iban_cifrado',
                    'fianza_token_iban', 'fianza_iban_guardado_at',
                ]);
            });
        }
    }
};
