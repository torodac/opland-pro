<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vm_imputaciones ALTER COLUMN estado DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE vm_imputaciones SET estado = 'finalizada' WHERE estado IS NULL");
        DB::statement('ALTER TABLE vm_imputaciones ALTER COLUMN estado SET NOT NULL');
    }
};
