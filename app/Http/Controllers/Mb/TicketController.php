<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    // Cobro en mostrador (carrito de mb/viviendas): decrementa directamente el pendiente de
    // cada cuota. Pendiente de definir si esto debe integrarse con el mecanismo de entregas a
    // cuenta / compensación FIFO (ver memoria project_mb_ticket_carrito_pendiente) — por ahora
    // es un camino aparte, sin tocar mb_entregas_cuenta.
    // El desglose efectivo/tarjeta es del TOTAL del ticket, no por cuota individual (un mismo
    // cobro puede repartirse entre ambos, caso raro pero real — no importa qué cuota concreta
    // "es" de cada uno).
    public function confirmar(Request $request, Project $project)
    {
        $data = $request->validate([
            'cuotas'              => ['required', 'array', 'min:1'],
            'cuotas.*.id_cuota'   => ['required', 'integer', 'exists:mb_cuotas,id'],
            'importe_efectivo'    => ['required', 'numeric', 'min:0'],
            'importe_tarjeta'     => ['required', 'numeric', 'min:0'],
        ]);

        $userId = Auth::id();

        $ticketId = DB::transaction(function () use ($data, $userId) {
            $total  = 0.0;
            $lineas = [];

            foreach ($data['cuotas'] as $item) {
                $cuota = DB::table('mb_cuotas')->where('id', $item['id_cuota'])->lockForUpdate()->first();
                abort_if(!$cuota || (float) $cuota->pendiente <= 0, 422, "La cuota #{$item['id_cuota']} ya no tiene importe pendiente.");

                $importe = round((float) $cuota->pendiente, 2);
                $total  += $importe;

                $lineas[] = [
                    'id_viviendas' => $cuota->id_viviendas,
                    'id_cuota'     => $cuota->id,
                    'concepto'     => trim($cuota->ejercicio . ' ' . $cuota->tipo_cuota),
                    'importe'      => $importe,
                ];

                DB::table('mb_cuotas')->where('id', $cuota->id)->update([
                    'pendiente'  => 0,
                    'updatedat'  => now(),
                    'updateuser' => $userId,
                ]);
            }

            $importeEfectivo = round((float) $data['importe_efectivo'], 2);
            $importeTarjeta  = round((float) $data['importe_tarjeta'], 2);
            abort_if(abs(($importeEfectivo + $importeTarjeta) - round($total, 2)) > 0.01, 422,
                'El desglose de efectivo y tarjeta no coincide con el total del ticket.');

            $formaPago = $importeEfectivo > 0 && $importeTarjeta > 0
                ? 'Mixto'
                : ($importeTarjeta > 0 ? 'Tarjeta' : 'Efectivo');

            $ticketId = DB::table('mb_tickets')->insertGetId([
                'fecha'            => now()->toDateString(),
                'forma_pago'       => $formaPago,
                'importe_efectivo' => $importeEfectivo,
                'importe_tarjeta'  => $importeTarjeta,
                'total'            => round($total, 2),
                'createuser'       => $userId,
                'updateuser'       => $userId,
                'createdat'        => now(),
                'updatedat'        => now(),
            ]);

            foreach ($lineas as $linea) {
                DB::table('mb_tickets_lineas')->insert($linea + [
                    'id_ticket'  => $ticketId,
                    'createdat'  => now(),
                ]);
            }

            return $ticketId;
        });

        return response()->json(['ok' => true, 'ticket_id' => $ticketId]);
    }

    public function imprimir(Request $request, Project $project, int $ticket)
    {
        $ticketRow = DB::table('mb_tickets')->where('id', $ticket)->where('deleted', 0)->first();
        abort_if(!$ticketRow, 404, 'Ticket no encontrado.');

        $lineas = DB::table('mb_tickets_lineas as tl')
            ->join('mb_viviendas as v', 'v.id', '=', 'tl.id_viviendas')
            ->where('tl.id_ticket', $ticket)
            ->orderBy('v.nombre')
            ->get(['v.nombre as vivienda', 'tl.concepto', 'tl.importe']);

        $logoPath    = public_path('mb/logo-ticket.png');
        $logoDataUri = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        return view('mb.ticket-print', [
            'ticket'      => $ticketRow,
            'lineas'      => $lineas,
            'logoDataUri' => $logoDataUri,
        ]);
    }
}
