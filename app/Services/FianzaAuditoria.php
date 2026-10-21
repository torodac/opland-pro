<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Escribe un paso del proceso de devolución de una fianza en vm_reservas_auditoria. Solo añade filas.
// Si el usuario no se pasa, se toma el que tiene la sesión (null en el proceso automático).
class FianzaAuditoria
{
    public static function registrar(int $reservaId, string $accion, array $detalle = [], ?int $usuarioId = null): void
    {
        DB::table('vm_reservas_auditoria')->insert([
            'id_reservas' => $reservaId,
            'accion'      => $accion,
            'detalle'     => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
            'id_usuario'  => $usuarioId ?? Auth::id(),
            'created_at'  => now(),
        ]);
    }
}
