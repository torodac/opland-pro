<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Habilita la función unaccent() de PostgreSQL, usada por la búsqueda por texto del
    // listado genérico (ListadoController) para ignorar tildes/diacríticos. No aplica en
    // SQLite (entorno local), donde la búsqueda sigue igual que antes.
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP EXTENSION IF EXISTS unaccent');
        }
    }
};
