<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntregasCuentaController extends Controller
{
    // Movimientos informativos en mb_cuotas: se insertan/visualizan, pero no cuentan como
    // deuda compensable ni en ningún otro cálculo (confirmado por el cliente 2026-08-08).
    private const TIPOS_CUOTA_INFORMATIVOS = ['G.dev.', 'Dudoso', 'Entrega a cuenta'];

    // Registra una entrega a cuenta para una vivienda. Si la vivienda tiene movimientos
    // Pendiente/Demandada con pendiente > 0, devuelve la lista para que el usuario decida
    // manualmente cuáles compensar con el saldo de la entrega (paso 2, ver aplicar()).
    public function store(Request $request, Project $project, int $vivienda)
    {
        $data = $request->validate([
            'fecha'      => ['required', 'date'],
            'importe'    => ['required', 'numeric', 'gt:0'],
            'concepto'   => ['nullable', 'string', 'max:255'],
            'forma_pago' => ['nullable', 'string', 'max:20'],
        ]);

        $userId = Auth::id();

        $idEntrega = DB::table('mb_entregas_cuenta')->insertGetId([
            'id_viviendas'     => $vivienda,
            'fecha'            => $data['fecha'],
            'importe'          => $data['importe'],
            'importe_aplicado' => 0,
            'concepto'         => $data['concepto'] ?? null,
            'forma_pago'       => $data['forma_pago'] ?? null,
            'createuser'       => $userId,
            'createdat'        => now(),
            'updatedat'        => now(),
        ]);

        return $this->respuestaPendientes(
            $vivienda,
            $idEntrega,
            (float) $data['importe'],
            'Entrega registrada. La vivienda no tiene movimientos pendientes que compensar; el saldo queda disponible para la próxima cuota.'
        );
    }

    // Reconsulta los movimientos pendientes/demandados de la vivienda de una entrega ya
    // existente con saldo, para reabrir la modal de asignación (ej. desde el desplegable
    // de viviendas, botón de "compensar" en la fila de la entrega).
    public function pendientes(Request $request, Project $project, int $entrega)
    {
        $entregaRow = DB::table('mb_entregas_cuenta')->where('id', $entrega)->where('deleted', 0)->first();
        abort_unless($entregaRow, 404);

        $saldo = round((float) $entregaRow->importe - (float) $entregaRow->importe_aplicado, 2);

        return $this->respuestaPendientes(
            (int) $entregaRow->id_viviendas,
            $entrega,
            $saldo,
            'Esta entrega ya no tiene movimientos pendientes que compensar.'
        );
    }

    private function respuestaPendientes(int $vivienda, int $idEntrega, float $saldo, string $mensajeSinPendientes)
    {
        $pendientes = DB::table('mb_cuotas')
            ->where('id_viviendas', $vivienda)
            ->whereNotIn('estado', ['Anulada', 'Incobrable'])
            ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->where('pendiente', '>', 0)
            ->orderBy('fecha_emision')
            ->get(['id', 'fecha_emision', 'concepto', 'ejercicio', 'tipo_cuota', 'estado', 'pendiente']);

        if ($pendientes->isEmpty()) {
            return response()->json([
                'ok'                    => true,
                'requiere_compensacion' => false,
                'mensaje'               => $mensajeSinPendientes,
            ]);
        }

        $viviendasADemandar = $this->viviendasProximasAPrescribir();
        $esADemandar        = in_array($vivienda, $viviendasADemandar, true);

        return response()->json([
            'ok'                    => true,
            'requiere_compensacion' => true,
            'id_entrega'            => $idEntrega,
            'saldo'                 => $saldo,
            'movimientos'           => $pendientes->map(fn ($p) => [
                'id'            => $p->id,
                'fecha_emision' => $p->fecha_emision,
                'concepto'      => $p->concepto,
                'ejercicio'     => $p->ejercicio,
                'tipo_cuota'    => $p->tipo_cuota,
                'estado'        => $p->estado,
                'pendiente'     => (float) $p->pendiente,
                'a_demandar'    => $esADemandar,
            ])->values(),
        ]);
    }

    // Aplica (compensa) el saldo de una entrega a cuenta contra las cuotas que el usuario
    // haya activado, con el importe que haya indicado para cada una (topado server-side).
    public function aplicar(Request $request, Project $project, int $entrega)
    {
        $data = $request->validate([
            'aplicaciones'             => ['required', 'array', 'min:1'],
            'aplicaciones.*.id_cuota'  => ['required', 'integer', 'exists:mb_cuotas,id'],
            'aplicaciones.*.importe'   => ['required', 'numeric', 'gt:0'],
        ]);

        $userId = Auth::id();

        $totalAplicado = DB::transaction(function () use ($data, $entrega, $userId) {
            $entregaRow = DB::table('mb_entregas_cuenta')->where('id', $entrega)->lockForUpdate()->first();
            abort_unless($entregaRow, 404);

            $saldoDisponible = round((float) $entregaRow->importe - (float) $entregaRow->importe_aplicado, 2);
            $totalAplicado   = 0.0;

            foreach ($data['aplicaciones'] as $item) {
                $cuota = DB::table('mb_cuotas')->where('id', $item['id_cuota'])->lockForUpdate()->first();
                if (!$cuota) {
                    continue;
                }

                $importe = round((float) $item['importe'], 2);
                $maximo  = min((float) $cuota->pendiente, $saldoDisponible - $totalAplicado);

                abort_if($importe > $maximo + 0.001, 422, "El importe indicado para la cuota #{$cuota->id} supera lo pendiente de esa cuota o el saldo disponible de la entrega.");

                DB::table('mb_entregas_cuenta_aplicaciones')->insert([
                    'id_entrega_cuenta' => $entrega,
                    'id_cuota'          => $cuota->id,
                    'importe_aplicado'  => $importe,
                    'fecha_aplicacion'  => now()->toDateString(),
                    'createuser'        => $userId,
                    'createdat'         => now(),
                ]);

                DB::table('mb_cuotas')->where('id', $cuota->id)->update([
                    'pendiente'  => round((float) $cuota->pendiente - $importe, 2),
                    'updatedat'  => now(),
                    'updateuser' => $userId,
                ]);

                $totalAplicado += $importe;
            }

            DB::table('mb_entregas_cuenta')->where('id', $entrega)->update([
                'importe_aplicado' => round((float) $entregaRow->importe_aplicado + $totalAplicado, 2),
                'updatedat'        => now(),
                'updateuser'       => $userId,
            ]);

            return $totalAplicado;
        });

        return response()->json(['ok' => true, 'total_aplicado' => $totalAplicado]);
    }

    // Misma regla de prescripción que ViviendasController/ListadoController (duplicación aceptada).
    private function viviendasProximasAPrescribir(): array
    {
        $hoy       = now();
        $anio      = (int) $hoy->format('n') >= 7 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
        $nextStart = ($anio + 1) . '-07-01';
        $nextEnd   = ($anio + 2) . '-06-30';

        return DB::table('mb_cuotas')
            ->where('estado', 'Pendiente')
            ->where('pendiente', '>', 0)
            ->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->whereRaw("(fecha_emision + INTERVAL '5 years') BETWEEN ? AND ?", [$nextStart, $nextEnd])
            ->distinct()
            ->pluck('id_viviendas')
            ->filter()
            ->values()
            ->all();
    }
}
