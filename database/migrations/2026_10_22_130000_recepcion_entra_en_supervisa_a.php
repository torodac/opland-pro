<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Añade Coordinador recepcion (14) y Empleado Recepcion (16) a los roles que pueden figurar
// como valor del campo "Supervisa a" (vm_usuarios.supervisados).
//
// Ese filtro decide a QUIEN se puede marcar como supervisado, y Recepcion entera estaba fuera:
// ni un Empleado Recepcion podia ser supervisado por nadie -- lo detecto Riccardo Abbene el
// 22/10/2026 -- ni un Coordinador recepcion podia estarlo por Direccion de Operaciones, que es
// justo lo que dice la jerarquia de roles (el rol 10 supervisa ["2","5","14"]).
//
// Los dos roles que siguen fuera a proposito son Direccion general (3) y Director RRHH (11):
// son la cuspide del circuito y no los supervisa nadie.
//
// Esto NO basta para que Riccardo tenga supervisor de recepcion: no existe ninguna persona con
// el rol 14. Dar ese rol a alguien es una decision de negocio, no de codigo.
return new class extends Migration
{
    private const ANTES   = 'roles:1,2,4,5,6,7,8,9,10,12,13|exclusivo';
    private const DESPUES = 'roles:1,2,4,5,6,7,8,9,10,12,13,14,16|exclusivo';

    public function up(): void
    {
        $this->cambiar(self::ANTES, self::DESPUES);
    }

    public function down(): void
    {
        $this->cambiar(self::DESPUES, self::ANTES);
    }

    private function cambiar(string $de, string $a): void
    {
        $fila = DB::table('admin_table_fields')->where('name', 'supervisados')->first(['id', 'extras']);

        if (!$fila) {
            echo '  AVISO - no existe el campo "supervisados", se omite.' . PHP_EOL;
            return;
        }

        // Si alguien lo ha tocado a mano entretanto, parar antes que pisar su cambio.
        if ($fila->extras !== $de) {
            echo "  AVISO - extras inesperado ({$fila->extras}), se deja intacto." . PHP_EOL;
            return;
        }

        DB::table('admin_table_fields')->where('id', $fila->id)->update(['extras' => $a]);
        echo "  \"Supervisa a\": {$a}" . PHP_EOL;
    }
};
