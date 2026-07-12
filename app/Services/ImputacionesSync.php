<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ImputacionesSync
{
    private const TABLAS_POR_TIPO = [
        'limpieza'      => 'vm_tareas_limpieza',
        'mantenimiento' => 'vm_tareas_mantenimiento',
        'piscina'       => 'vm_tareas_piscinas',
    ];

    /**
     * Inserta una nueva imputación de tiempo. Append-only: nunca se borra ni se
     * sustituye una imputación existente al crear una nueva. control_user determina
     * la visibilidad de la tarea; las imputaciones no tienen estado.
     */
    public static function insertar(string $tipo, int $idTarea, int $idUsuario, int $minutos, ?string $observacion, string $fechaImputacion, ?float $lat = null, ?float $lng = null): int
    {
        $id = DB::table('vm_imputaciones')->insertGetId([
            'tipo'             => $tipo,
            'id_tarea'         => $idTarea,
            'id_usuario'       => $idUsuario,
            'duracion'         => $minutos,
            'observacion'      => $observacion,
            'fecha_imputacion' => $fechaImputacion,
            'createdat'        => now(),
            'lat'              => $lat,
            'lng'              => $lng,
        ]);

        self::recalcularTiempo($tipo, $idTarea);

        return $id;
    }

    /**
     * Edita una imputación existente (solo tiempo, observación y fecha). El llamador
     * es responsable de comprobar que la imputación pertenece al usuario y que su
     * fecha_imputacion no supera el límite de 2 días editable.
     */
    public static function actualizar(int $idImputacion, int $minutos, ?string $observacion, string $fechaImputacion): void
    {
        $imp = DB::table('vm_imputaciones')->where('id', $idImputacion)->first();
        if (!$imp) return;

        DB::table('vm_imputaciones')->where('id', $idImputacion)->update([
            'duracion'         => $minutos,
            'observacion'      => $observacion,
            'fecha_imputacion' => $fechaImputacion,
            'updatedat'        => now(),
        ]);

        self::recalcularTiempo($imp->tipo, $imp->id_tarea);
    }

    /**
     * tiempo de la tarea = suma de todas las imputaciones de todos los control_user
     * (presentes o no), porque representa trabajo real ya hecho.
     */
    private static function recalcularTiempo(string $tipo, int $idTarea): void
    {
        $tabla = self::TABLAS_POR_TIPO[$tipo] ?? null;
        if (!$tabla) return;

        $totalMin = (int) DB::table('vm_imputaciones')
            ->where('tipo', $tipo)
            ->where('id_tarea', $idTarea)
            ->sum('duracion');

        DB::table($tabla)->where('id', $idTarea)->update([
            'tiempo' => self::minutesToTime($totalMin),
        ]);
    }

    private static function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }
}
