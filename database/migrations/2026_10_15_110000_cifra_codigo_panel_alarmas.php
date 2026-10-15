<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

// codigo_panel pasa a ser un campo sensible: se revela bajo registro, como la palabra clave y la
// contrasena de la app, y por tanto va cifrado en reposo.
//
// Los que entraron con la carga del Excel estan en claro y hay que cifrarlos. Se comprueba uno a
// uno si YA esta cifrado antes de tocarlo: volver a cifrar un valor cifrado lo dejaria ilegible
// para siempre, y esta migracion no tiene forma de saber si ya se ejecuto a medias.
return new class extends Migration
{
    public function up(): void
    {
        $filas = DB::table('vm_alarmas')
            ->whereNotNull('codigo_panel')->where('codigo_panel', '<>', '')
            ->get(['id', 'codigo_panel']);

        $cifrados = 0;
        foreach ($filas as $f) {
            try {
                Crypt::decryptString($f->codigo_panel);
                continue;   // ya estaba cifrado
            } catch (\Throwable $e) {
                // En claro: es lo que se espera de los que vinieron del Excel.
            }

            DB::table('vm_alarmas')->where('id', $f->id)
                ->update(['codigo_panel' => Crypt::encryptString($f->codigo_panel)]);
            $cifrados++;
        }

        echo "  {$cifrados} codigo(s) de panel cifrados de {$filas->count()}." . PHP_EOL;
    }

    public function down(): void
    {
        // Se descifran para dejarlos como estaban. Los que no se puedan descifrar se dejan
        // intactos: no se destruye nada que no se sepa interpretar.
        foreach (DB::table('vm_alarmas')->whereNotNull('codigo_panel')->get(['id', 'codigo_panel']) as $f) {
            try {
                DB::table('vm_alarmas')->where('id', $f->id)
                    ->update(['codigo_panel' => Crypt::decryptString($f->codigo_panel)]);
            } catch (\Throwable $e) {
                // ya estaba en claro
            }
        }
    }
};
