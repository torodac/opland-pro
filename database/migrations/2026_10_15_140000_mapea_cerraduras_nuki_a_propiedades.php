<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ata a su propiedad las cerraduras Nuki que el cruce automatico no resuelve.
//
// Ese cruce compara nombres y solo acepta candidata UNICA, asi que descarta casos obvios para
// una persona: "Guadalmina 829" encaja a la vez con "GUADALMINA CASA 829" y con "VILLA
// MOSS-GUADALMINA 539", y ante la duda no ata nada antes que atar mal. Estas 15 las confirmo
// Tomas el 15/10/2026 mirando la lista.
//
// Va en migracion y no en un script suelto para que quede constancia de quien decidio que, y
// para que se pueda deshacer.
return new class extends Migration
{
    /** nombre de la cerradura en Nuki => id de vm_propiedades */
    private const MAPEO = [
        'Oasis 5 - 2A'     => 48,    // OASIS MARBELLA 5-2A
        'Villa La Plage'   => 128,   // LA PLAGE - REAL DE ZARAGOZA
        'Villa Fleur'      => 118,   // VILLA LE FLEUR
        'Guadalmina 829'   => 2,     // GUADALMINA CASA 829
        'Terrazas 8-2-2'   => 121,   // TERRAZAS BANUS 8-2-2
        'Villa Camelia'    => 24,    // CAMELIAS 152
        'VILLA JAZMINES'   => 83,    // JAZMINES 179
        'Villa Azahar'     => 125,   // VILLA SANTA MARIA - AZAHAR
        'Villa Lumea'      => 112,   // VILLA SOFIA - LUMEA
        'THE ADDRESS -701' => 306,   // Villa The Address
        'Medina 22'        => 49,    // MEDINA BANUS 22 ATICO
        'Senator 6-A'      => 79,    // VILLA SENATOR
        'Banus 45H'        => 44,    // MARINA BANUS IV 5 H
        'Marblanc'         => 333,   // Villa El Presidente
        'White Orchid'     => 113,   // VILLA CARTUJA
    ];

    public function up(): void
    {
        $hechas = 0;
        $fallan = [];

        foreach (self::MAPEO as $cerradura => $idPropiedad) {
            if (!DB::table('vm_propiedades')->where('id', $idPropiedad)->exists()) {
                $fallan[] = "{$cerradura}: la propiedad {$idPropiedad} no existe";
                continue;
            }

            // Solo se rellena lo que esta vacio: si alguien ya la ato a otra cosa, manda esa.
            $n = DB::table('vm_cerraduras')
                ->where('nombre', $cerradura)
                ->whereNull('id_propiedades')
                ->update(['id_propiedades' => $idPropiedad, 'updated_at' => now()]);

            if ($n === 0) {
                $fallan[] = "{$cerradura}: no hay ninguna cerradura con ese nombre sin atar";
                continue;
            }
            $hechas += $n;
        }

        echo "  {$hechas} cerradura(s) atadas a su propiedad." . PHP_EOL;
        foreach ($fallan as $f) {
            echo "  AVISO - {$f}" . PHP_EOL;
        }
    }

    public function down(): void
    {
        foreach (self::MAPEO as $cerradura => $idPropiedad) {
            DB::table('vm_cerraduras')
                ->where('nombre', $cerradura)->where('id_propiedades', $idPropiedad)
                ->update(['id_propiedades' => null, 'updated_at' => now()]);
        }
    }
};
