<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GenerarCuotasController extends Controller
{
    private const TIPOS_VALIDOS = ['Ordinaria', 'Extraordinaria'];

    // Genera una cuota por cada vivienda activa: importe = módulo × superf_calculada_cuota
    // (mismo cálculo que usan ya las derramas emitidas manualmente en el histórico).
    // Si la vivienda tiene entregas a cuenta con saldo, se compensan automáticamente contra
    // la cuota nueva (FIFO por antigüedad de la entrega) antes de dejarla como pendiente.
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'concepto'   => ['required', 'string', 'max:255'],
            'ejercicio'  => ['required', 'string', 'max:9'],
            'tipo_cuota' => ['required', 'string', 'in:' . implode(',', self::TIPOS_VALIDOS)],
            'modulo'     => ['required', 'numeric', 'gt:0'],
        ]);

        $modulo = (float) $data['modulo'];
        $fecha  = now();
        $nombre = $data['concepto'] . ' - ' . $fecha->format('d/m/Y');
        $userId = Auth::id();

        $viviendas = DB::table('mb_viviendas')->where('deleted', 0)->get(['id', 'superf_calculada_cuota']);

        $propietarios = DB::table('mb_propietarios_historico as ph')
            ->join('mb_propietarios as p', 'p.id', '=', 'ph.id_propietarios')
            ->whereIn('ph.id_viviendas', $viviendas->pluck('id'))
            ->where('ph.deleted', 0)
            ->orderByRaw('(ph.fecha_hasta IS NULL) DESC, ph.fecha_desde DESC')
            ->get(['ph.id_viviendas', 'p.nombre'])
            ->groupBy('id_viviendas')
            ->map(fn ($rows) => $rows->first()->nombre);

        $entregasPorVivienda = DB::table('mb_entregas_cuenta')
            ->whereIn('id_viviendas', $viviendas->pluck('id'))
            ->where('deleted', 0)
            ->whereRaw('importe > importe_aplicado')
            ->orderBy('fecha')
            ->get(['id', 'id_viviendas', 'importe', 'importe_aplicado'])
            ->groupBy('id_viviendas');

        $totalEmitido     = 0.0;
        $totalCompensado  = 0.0;
        $filasSimples     = [];
        $filasConAplicacion = [];

        foreach ($viviendas as $v) {
            $importe = round($modulo * (float) ($v->superf_calculada_cuota ?? 0), 2);
            $totalEmitido += $importe;

            $datosCuota = [
                'id_viviendas'  => $v->id,
                'nombre'        => $nombre,
                'fecha_emision' => $fecha->toDateString(),
                'concepto'      => $data['concepto'],
                'ejercicio'     => $data['ejercicio'],
                'tipo_cuota'    => $data['tipo_cuota'],
                'propietario'   => $propietarios->get($v->id),
                'importe'       => $importe,
                'estado'        => 'Pendiente',
                'blocked'       => 0,
                'hidden'        => 0,
                'deleted'       => 0,
                'createuser'    => $userId,
                'createdat'     => $fecha,
                'updatedat'     => $fecha,
            ];

            $entregas = $entregasPorVivienda->get($v->id);
            if (!$entregas || $importe <= 0) {
                $datosCuota['pendiente'] = $importe;
                $filasSimples[] = $datosCuota;
                continue;
            }

            // FIFO: consume las entregas con saldo en orden de fecha hasta cubrir el importe de la cuota.
            $restante     = $importe;
            $aplicaciones = [];
            foreach ($entregas as $e) {
                if ($restante <= 0) {
                    break;
                }
                $saldoEntrega = round((float) $e->importe - (float) $e->importe_aplicado, 2);
                if ($saldoEntrega <= 0) {
                    continue;
                }
                $usar = min($saldoEntrega, $restante);
                $aplicaciones[] = ['id_entrega' => $e->id, 'importe' => $usar];
                $restante = round($restante - $usar, 2);
            }

            if (empty($aplicaciones)) {
                $datosCuota['pendiente'] = $importe;
                $filasSimples[] = $datosCuota;
                continue;
            }

            $compensado = round($importe - $restante, 2);
            $totalCompensado += $compensado;
            $datosCuota['pendiente'] = $restante;
            $filasConAplicacion[] = ['cuota' => $datosCuota, 'aplicaciones' => $aplicaciones];
        }

        DB::transaction(function () use ($filasSimples, $filasConAplicacion, $userId) {
            foreach (array_chunk($filasSimples, 500) as $chunk) {
                DB::table('mb_cuotas')->insert($chunk);
            }

            foreach ($filasConAplicacion as $item) {
                $idCuota = DB::table('mb_cuotas')->insertGetId($item['cuota']);

                foreach ($item['aplicaciones'] as $ap) {
                    DB::table('mb_entregas_cuenta_aplicaciones')->insert([
                        'id_entrega_cuenta' => $ap['id_entrega'],
                        'id_cuota'          => $idCuota,
                        'importe_aplicado'  => $ap['importe'],
                        'fecha_aplicacion'  => now()->toDateString(),
                        'createuser'        => $userId,
                        'createdat'         => now(),
                    ]);

                    DB::table('mb_entregas_cuenta')->where('id', $ap['id_entrega'])->update([
                        'importe_aplicado' => DB::raw('importe_aplicado + ' . $ap['importe']),
                        'updatedat'        => now(),
                        'updateuser'       => $userId,
                    ]);
                }
            }
        });

        $totalFilas = count($filasSimples) + count($filasConAplicacion);
        $mensaje = $totalFilas . ' cuotas generadas por un total de ' . number_format($totalEmitido, 2, ',', '.') . ' €.';
        if ($totalCompensado > 0) {
            $mensaje .= ' ' . number_format($totalCompensado, 2, ',', '.') . ' € compensados automáticamente con entregas a cuenta existentes ('
                . count($filasConAplicacion) . ' vivienda' . (count($filasConAplicacion) === 1 ? '' : 's') . ').';
        }

        return redirect()->route('listado', [$project->slug, 'cuotas'])->with('success', $mensaje);
    }
}
