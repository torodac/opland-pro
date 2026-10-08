<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClaseGenerarCobrosCommand extends Command
{
    protected $signature   = 'clase:generar-cobros';
    protected $description = 'Genera cobros del mes en curso para contratos activos de clase';

    public function handle(): int
    {
        $mes     = now()->format('Y-m');
        $inicio  = now()->startOfMonth()->toDateString();
        $fin     = now()->endOfMonth()->toDateString();
        $generados = 0; $ignorados = 0;

        $estadoPendiente = 'Pendiente';

        $contratos = DB::table('clase_contratos as ct')
            ->join('clase_clientes as al', 'al.id', '=', 'ct.id_alumno')
            ->where('ct.deleted', false)->where('ct.hidden', 0)
            ->where('ct.fecha_inicio', '<=', $fin)
            ->where(function ($q) use ($inicio) {
                $q->whereNull('ct.fecha_fin')->orWhere('ct.fecha_fin', '>=', $inicio);
            })
            ->select('ct.*', 'al.nombre as alumno_nombre')->get();

        foreach ($contratos as $ct) {
            $pagadores = DB::table('clase_tutores')
                ->where('id_alumno', $ct->id_alumno)->where('es_pagador', true)->where('deleted', false)->get();

            foreach ($pagadores as $tut) {
                $existe = DB::table('clase_cobros')
                    ->where('id_alumno', $ct->id_alumno)->where('id_tutor', $tut->id)
                    ->where('id_contrato', $ct->id)
                    ->whereRaw("to_char(fecha_cobro,'YYYY-MM') = ?", [$mes])
                    ->where('deleted', false)->exists();

                if ($existe) { $ignorados++; continue; }

                DB::table('clase_cobros')->insert([
                    'id_alumno'        => $ct->id_alumno,
                    'id_tutor'         => $tut->id,
                    'id_contrato'      => $ct->id,
                    'fecha_cobro'      => $inicio,
                    'cantidad_total'   => $ct->importe,
                    'cantidad_pagador' => round($ct->importe * $tut->porcentaje_pago / 100, 2),
                    'porcentaje'       => $tut->porcentaje_pago,
                    'estado_cobros' => $estadoPendiente,
                    'nombre_alumno'    => $ct->alumno_nombre,
                    'nombre_pagador'   => $tut->nombre,
                    'nombre'           => $ct->alumno_nombre . ' – ' . now()->format('m/Y'),
                    'createuser'       => 1,
                    'createdat'        => now(),
                    'updatedat'        => now(),
                ]);
                $generados++;
            }
        }

        $this->info("Cobros generados: {$generados}  |  Ignorados (ya existían): {$ignorados}");
        return 0;
    }
}
