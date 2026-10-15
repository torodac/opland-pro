<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Securitas Direct se llama Verisure. Son 20 de las 36 alarmas, asi que dejarlo con el nombre
// viejo haria que la mayoria del listado estuviera mal etiquetada.
//
// Solo se toca el campo "empresa". El tipo de alarma ("PANEL SECURITAS", "CUADRADO SECURITAS")
// se deja como esta: describe el aparato que hay en la pared, no a la compania, y cambiarlo
// seria renombrar algo que la gente reconoce por ese nombre.
return new class extends Migration
{
    public function up(): void
    {
        $n = DB::table('vm_alarmas')->where('empresa', 'SECURITAS DIRECT')->update(['empresa' => 'VERISURE']);
        echo "  {$n} alarma(s) pasan de SECURITAS DIRECT a VERISURE." . PHP_EOL;
    }

    public function down(): void
    {
        DB::table('vm_alarmas')->where('empresa', 'VERISURE')->update(['empresa' => 'SECURITAS DIRECT']);
    }
};
