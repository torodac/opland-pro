<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeAprobacionGuard
{
    /**
     * Comprueba si el usuario+mes de $fecha tiene el flujo de aprobación iniciado
     * (vm_informes_estado.en_aprobacion) y, si es así, deja constancia en
     * vm_informes_ediciones_log y reinicia el flujo completo a "rrhh" (se borran
     * las firmas ya dadas en vm_informes_aprobaciones). Se llama SIEMPRE después de
     * que la escritura ya haya ocurrido -- nunca bloquea ni revierte el guardado,
     * solo avisa, registra y reinicia el flujo.
     *
     * $editadoPor: admin_users.id de quien edita. En backoffice se omite (usa la
     * sesión de auth()); la PWA no tiene sesión Laravel, así que sus llamadas deben
     * pasar explícitamente $user->admin_user_id.
     */
    public static function checkAndLog(
        int $idUsuario,
        string $fecha,
        string $tabla,
        string $accion,
        ?int $idRegistro,
        Request $request,
        ?int $editadoPor = null
    ): ?string {
        $anio = (int) substr($fecha, 0, 4);
        $mes = (int) substr($fecha, 5, 2);

        $enAprobacion = DB::table('vm_informes_estado')
            ->where('id_usuario', $idUsuario)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('en_aprobacion', true)
            ->exists();

        if (!$enAprobacion) {
            return null;
        }

        $origen = $editadoPor !== null ? 'pwa' : 'backoffice';
        $ahora = now();

        DB::table('vm_informes_ediciones_log')->insert([
            'id_usuario'  => $idUsuario,
            'anio'        => $anio,
            'mes'         => $mes,
            'tabla'       => $tabla,
            'accion'      => $accion,
            'id_registro' => $idRegistro,
            'editado_por' => $editadoPor ?? auth()->id(),
            'editado_at'  => $ahora,
            'ip_address'  => $request->ip(),
            'origen'      => $origen,
            'createdat'   => $ahora,
            'updatedat'   => $ahora,
        ]);

        // La edición invalida todo el flujo de aprobación (RRHH -> coordinador -> trabajador ->
        // dirección): vuelve al principio y se borran las firmas ya dadas, sin excepción.
        DB::table('vm_informes_estado')
            ->where('id_usuario', $idUsuario)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->update(['en_aprobacion' => false, 'paso_actual' => 'rrhh', 'updatedat' => $ahora]);

        DB::table('vm_informes_aprobaciones')
            ->where('id_usuario', $idUsuario)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->delete();

        return 'Aviso: este informe estaba en proceso de aprobación. Al editarlo, el flujo de firmas ha quedado reiniciado.';
    }
}
