<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// El circuito de firmas, compartido por los informes que lo usan: el mensual de imputaciones y
// el de kilómetros.
//
// Antes vivía dentro de InformeImputacionesController. Se extrae al añadir el segundo informe
// porque son siete piezas (estado, firmas, huella del contenido, validación del paso en curso,
// firma manuscrita obligatoria, avance al siguiente paso y cierre) y duplicarlas significaba
// que cualquier corrección habría que acordarse de hacerla dos veces.
//
// Lo que NO entra aquí, porque es propio de cada informe: cómo se calcula la huella de su
// contenido y qué pasa al llegar a 'completado'. El mensual congela los registros del mes
// (blocked=1); el de kilómetros no congela nada, porque se alimenta de las mismas filas de
// vm_fichaje y el que terminara primero dejaría al otro sin poder corregirse.
class CircuitoFirmas
{
    public const MENSUAL = 'mensual';
    public const KM      = 'km';

    // aprueba → rrhh → trabajador → direccion → completado
    //
    // 'coordinador' se retiró del flujo (lo sustituyó 'aprueba', que se designa persona a persona
    // en la ficha en lugar de deducirse de la jerarquía de roles) y se conserva solo para que los
    // informes históricos que lo tienen firmado sigan avanzando.
    public const SIGUIENTE_PASO = [
        'aprueba'     => 'rrhh',
        'rrhh'        => 'trabajador',
        'coordinador' => 'trabajador',
        'trabajador'  => 'direccion',
        'direccion'   => 'completado',
    ];

    public const PASOS_VISIBLES = ['aprueba', 'rrhh', 'trabajador', 'direccion'];

    public const ETIQUETAS = [
        'aprueba'     => 'Supervisor',
        'rrhh'        => 'RRHH',
        'coordinador' => 'Coordinador',
        'trabajador'  => 'Trabajador',
        'direccion'   => 'Dirección',
        'completado'  => 'Completado',
    ];

    /** En qué paso está el informe de ese usuario y mes. */
    public static function pasoActual(string $informe, int $userId, int $year, int $month): string
    {
        return DB::table('vm_informes_estado')
            ->where('informe', $informe)
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->value('paso_actual')
            ?? VmJerarquiaAprobacion::pasoInicial($userId);
    }

    public static function estado(string $informe, int $userId, int $year, int $month): ?object
    {
        return DB::table('vm_informes_estado')
            ->where('informe', $informe)
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->first();
    }

    /** Firmas dadas, en el orden del circuito y con el nombre de quien firmó. */
    public static function firmas(string $informe, int $userId, int $year, int $month)
    {
        return DB::table('vm_informes_aprobaciones as a')
            ->join('admin_users as u', 'u.id', '=', 'a.aprobado_por')
            ->where('a.informe', $informe)
            ->where('a.id_usuario', $userId)->where('a.anio', $year)->where('a.mes', $month)
            ->orderByRaw("array_position(array['aprueba','rrhh','coordinador','trabajador','direccion'], a.step)")
            ->get(['a.step', 'a.aprobado_at', 'a.content_hash', 'u.name as aprobado_por_nombre', 'u.signature_path']);
    }

    /**
     * Registra la firma de $step y avanza al siguiente paso.
     *
     * Valida que sea justo el paso en curso (si no, 409: alguien se adelantó o el circuito se
     * reinició por una edición) y que quien firma tenga su firma manuscrita guardada. La huella
     * la calcula el informe: es lo que fija QUÉ se firmó. Se recibe como funcion y no como
     * cadena para no calcularla si la firma se va a rechazar -- componerla cuesta reconstruir el
     * informe entero.
     *
     * @param  callable():string  $contentHash
     * @return array{ok?:bool, paso_actual?:string, error?:string, status?:int}
     */
    public static function firmar(
        string $informe,
        int $userId,
        int $year,
        int $month,
        string $step,
        int $aprobadoPor,
        callable $contentHash,
        ?string $ip
    ): array {
        if (!isset(self::SIGUIENTE_PASO[$step])) {
            return ['error' => 'Paso de aprobación desconocido.', 'status' => 400];
        }

        $pasoActual = self::pasoActual($informe, $userId, $year, $month);
        if ($pasoActual !== $step) {
            return ['error' => "El informe no está en el paso '{$step}' (está en '{$pasoActual}').", 'status' => 409];
        }

        $firmaManuscrita = DB::table('admin_users')->where('id', $aprobadoPor)->value('signature_path');
        if (!$firmaManuscrita) {
            return ['error' => 'Debes registrar tu firma en tu perfil antes de continuar.', 'status' => 422];
        }

        $ahora = now();

        DB::table('vm_informes_aprobaciones')->upsert(
            [[
                'informe' => $informe,
                'id_usuario' => $userId, 'anio' => $year, 'mes' => $month, 'step' => $step,
                'aprobado_por' => $aprobadoPor, 'content_hash' => $contentHash(), 'ip_address' => $ip,
                'aprobado_at' => $ahora, 'createdat' => $ahora, 'updatedat' => $ahora,
            ]],
            ['id_usuario', 'anio', 'mes', 'informe', 'step'],
            ['aprobado_por', 'content_hash', 'ip_address', 'aprobado_at', 'updatedat']
        );

        $nuevoPaso = self::SIGUIENTE_PASO[$step];

        DB::table('vm_informes_estado')->upsert(
            [[
                'informe' => $informe,
                'id_usuario' => $userId, 'anio' => $year, 'mes' => $month,
                'en_aprobacion' => true, 'paso_actual' => $nuevoPaso,
                'marcado_por' => $aprobadoPor, 'marcado_at' => $ahora,
                'createdat' => $ahora, 'updatedat' => $ahora,
            ]],
            ['id_usuario', 'anio', 'mes', 'informe'],
            ['en_aprobacion', 'paso_actual', 'marcado_por', 'marcado_at', 'updatedat']
        );

        return ['ok' => true, 'paso_actual' => $nuevoPaso];
    }

    /** Devuelve el informe al principio del circuito, borrando las firmas dadas. */
    public static function reabrir(string $informe, int $userId, int $year, int $month): void
    {
        $ahora = now();

        DB::table('vm_informes_estado')
            ->where('informe', $informe)
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->update([
                'en_aprobacion' => false,
                'paso_actual'   => VmJerarquiaAprobacion::pasoInicial($userId),
                'updatedat'     => $ahora,
            ]);

        DB::table('vm_informes_aprobaciones')
            ->where('informe', $informe)
            ->where('id_usuario', $userId)->where('anio', $year)->where('mes', $month)
            ->delete();
    }
}
