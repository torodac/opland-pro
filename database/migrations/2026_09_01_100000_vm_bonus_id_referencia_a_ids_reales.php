<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    // vm_bonus.id_referencia guardaba el NOMBRE de la persona/departamento (texto), no un id real
    // -- frágil ante cualquier cambio de nombre (caso real: se rompió el bonus de un usuario al
    // corregirle el nombre según nóminas). Se migra a id real para alcance=usuario (vm_usuarios.id)
    // y alcance=departamento (vm_departamentos.id); alcance=cargo sigue siendo texto libre, no
    // tiene tabla catálogo propia.
    public function up(): void
    {
        DB::statement("
            UPDATE vm_bonus b SET id_referencia = u.id::text, updatedat = now()
            FROM vm_usuarios u
            WHERE b.alcance = 'usuario' AND b.id_referencia = u.nombre
        ");

        DB::statement("
            UPDATE vm_bonus b SET id_referencia = d.id::text, updatedat = now()
            FROM vm_departamentos d
            WHERE b.alcance = 'departamento' AND b.id_referencia = d.nombre
        ");

        // Filas que no se pudieron migrar (el nombre guardado no coincide con ningún usuario o
        // departamento actual -- p. ej. un nombre ya desactualizado antes de esta migración). No
        // se tocan solas: se avisa para revisarlas a mano.
        $huerfanas = DB::table('vm_bonus')
            ->where('deleted', 0)
            ->whereIn('alcance', ['usuario', 'departamento'])
            ->whereRaw("id_referencia !~ '^[0-9]+$'")
            ->get(['id', 'alcance', 'id_referencia']);

        if ($huerfanas->isNotEmpty()) {
            Log::warning('vm_bonus con id_referencia sin migrar a id real (revisar a mano)', [
                'filas' => $huerfanas->toArray(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement("
            UPDATE vm_bonus b SET id_referencia = u.nombre, updatedat = now()
            FROM vm_usuarios u
            WHERE b.alcance = 'usuario' AND b.id_referencia = u.id::text
        ");

        DB::statement("
            UPDATE vm_bonus b SET id_referencia = d.nombre, updatedat = now()
            FROM vm_departamentos d
            WHERE b.alcance = 'departamento' AND b.id_referencia = d.id::text
        ");
    }
};
