<?php

namespace App\Http\Controllers\Nf;

use App\Http\Controllers\Controller;
use App\Mail\NfDocumentoMail;
use App\Mail\NfPagoMail;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class FitnessController extends Controller
{
    // Ficha de cliente: datos personales + servicios contratados.
    public function ficha(Project $project, int $id)
    {
        $cliente = DB::table('nf_clientes')->where('id', $id)->firstOrFail();

        $contratos = DB::table('nf_contratos as c')
            ->leftJoin('nf_tipo as t', 't.id', '=', 'c.id_tipo')
            ->where('c.id_clientes', $id)
            ->where('c.deleted', false)
            ->where(fn($q) => $q->whereNull('c.hidden')->orWhere('c.hidden', 0))
            ->orderByDesc('c.fecha_inicio')
            ->orderByDesc('c.id')
            ->select('c.*', 't.nombre as tipo_nombre')
            ->get();

        $hoy = now()->toDateString();
        $activo = $contratos->contains(fn($c) => $c->fecha_inicio <= $hoy && $c->fecha_fin >= $hoy);

        // Desglose mensual de cobro (solo Fitness): un círculo por mes del contrato, verde si ese
        // mes tiene un pago generado y cobrado (Pagada), rojo en cualquier otro caso (pendiente,
        // anulado o sin pago generado todavía).
        $pagosPorContrato = DB::table('nf_pagos')
            ->whereIn('id_contratos', $contratos->pluck('id'))
            ->select('id', 'id_contratos', 'fecha_pago', 'id_estado_pagos', 'cantidad', 'deleted')
            ->get()
            ->groupBy('id_contratos');
        foreach ($contratos as $c) {
            $c->mesesCobro = $this->mesesCobroFitness($c, $pagosPorContrato);
        }

        $generos = DB::table('nf_genero')->orderBy('id')->get();
        $gruposFitness = DB::table('nf_grupo')->where('id_tipo', 2)->where('deleted', false)->orderBy('nombre')->get();
        $colorGrupo = $this->colorGrupo();

        return view('nf.cliente', compact('project', 'cliente', 'contratos', 'activo', 'generos', 'gruposFitness', 'colorGrupo'));
    }

    // Array de ['estado' => 'pagado'|'pendiente'|'anulado'|'sin_generar', 'pago_id' => ?int,
    // 'mes' => 'YYYY-MM', 'importe' => ?float], uno por cada mes natural entre fecha_inicio y
    // fecha_fin del contrato. Solo aplica a Fitness
    // (id_tipo=2); Osteopatía es una sesión suelta sin concepto de "meses" y devuelve vacío.
    // 'sin_generar' (círculo hueco, sin link) = nunca ha existido pago ese mes -- distinto de
    // 'anulado' (aspa) = existió un pago pero se anuló/borró (caso legítimo, no un fallo) --
    // distinto de 'pendiente' (círculo rojo) = el pago existe y sigue vivo, pero no cobrado.
    private function mesesCobroFitness(object $contrato, \Illuminate\Support\Collection $pagosPorContrato): array
    {
        if ((int) $contrato->id_tipo !== 2) {
            return [];
        }

        $pagos = $pagosPorContrato->get($contrato->id, collect());
        $cursor = \Carbon\Carbon::parse($contrato->fecha_inicio)->startOfMonth();
        $fin = \Carbon\Carbon::parse($contrato->fecha_fin)->startOfMonth();

        $meses = [];
        while ($cursor->lte($fin)) {
            $mesActual = $cursor->format('Y-m');
            $pagosDelMes = $pagos->filter(fn($p) => \Carbon\Carbon::parse($p->fecha_pago)->format('Y-m') === $mesActual);
            $pago = $pagosDelMes->first(fn($p) => !$p->deleted) ?? $pagosDelMes->sortByDesc('id')->first();

            $estado = match (true) {
                !$pago => 'sin_generar',
                $pago->deleted || (int) $pago->id_estado_pagos === 3 => 'anulado',
                (int) $pago->id_estado_pagos === 2 => 'pagado',
                default => 'pendiente',
            };
            $meses[] = [
                'estado'  => $estado,
                'pago_id' => $pago->id ?? null,
                'mes'     => $mesActual,
                'importe' => $pago->cantidad ?? null,
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    // Colores de los badges de grupo/servicio, compartidos entre la ficha de cliente y pagos_form.
    private function colorGrupo(): array
    {
        return [
            'Verde' => '#3D8B5A', 'Azul' => '#3B6FA6', 'Rojo' => '#B5432F',
            'Morado' => '#7B4FA0', 'Amarillo' => '#D2A72C', 'Rosa' => '#C9698B',
            'Naranja' => '#D2762C',
        ];
    }

    // Dashboard con navegador de ejercicio (temporada sept-agosto) y matriz de grupos x día de
    // contrato (1/2), con el recuento de contratos Fitness de esa temporada (no borrados/ocultos).
    // El "ejercicio" se identifica por su año de inicio: ejercicio=2025 -> 01/09/2025-31/08/2026.
    public function dashboard(Project $project, ?string $ejercicio = null)
    {
        $anioActualEjercicio = now()->month >= 9 ? now()->year : now()->year - 1;
        $anio = ($ejercicio && preg_match('/^\d{4}$/', $ejercicio)) ? (int) $ejercicio : $anioActualEjercicio;

        $grupos = DB::table('nf_grupo')->where('id_tipo', 2)->where('deleted', false)->orderBy('nombre')->get();

        $hoy = now()->toDateString();

        $contratos = DB::table('nf_contratos as c')
            ->join('nf_clientes as cl', 'cl.id', '=', 'c.id_clientes')
            ->where('c.id_tipo', 2)
            ->where('c.deleted', false)
            ->where(fn($q) => $q->whereNull('c.hidden')->orWhere('c.hidden', 0))
            ->where('c.fecha_inicio', '<=', $hoy)
            ->where(fn($q) => $q->whereNull('c.fecha_fin')->orWhere('c.fecha_fin', '>=', $hoy))
            ->orderBy('cl.nombre')
            ->select('c.id_grupo', 'c.dia1', 'c.dia2', 'cl.id as id_cliente', 'cl.nombre as cliente')
            ->get();

        $matriz = $grupos->map(fn($g) => [
            'id' => $g->id,
            'nombre' => $g->nombre,
            'dia1' => $contratos->where('id_grupo', $g->id)->where('dia1', true)->count(),
            'dia2' => $contratos->where('id_grupo', $g->id)->where('dia2', true)->count(),
        ]);

        // Inscritos por grupo, embebido en la página -- el listado del dashboard se rellena en
        // el cliente al pulsar un grupo (vacío por defecto), sin ida y vuelta al servidor.
        $inscritosPorGrupo = $grupos->mapWithKeys(fn($g) => [
            $g->id => $contratos->where('id_grupo', $g->id)->map(fn($c) => [
                'id_cliente' => $c->id_cliente,
                'nombre'     => $c->cliente,
                'dia1'       => (bool) $c->dia1,
                'dia2'       => (bool) $c->dia2,
            ])->values(),
        ]);

        return view('nf.dashboard', [
            'project' => $project,
            'anio' => $anio,
            'ejercicioLabel' => "{$anio} - " . ($anio + 1),
            'ejercicioAnterior' => $anio - 1,
            'ejercicioSiguiente' => $anio + 1,
            'matriz' => $matriz,
            'inscritosPorGrupo' => $inscritosPorGrupo,
            'totalContratos' => $contratos->count(),
            'colorGrupo' => $this->colorGrupo(),
        ]);
    }

    // Pantalla "Pagos" con navegador de mes-año: lista los pagos de ese mes únicamente y permite
    // generar los pendientes desde ahí (réplica de fitness/pagos/{mes} del legacy opland-app,
    // adaptada a un mes en formato "YYYY-MM" en vez de "mYYYY").
    public function pagosList(Request $request, Project $project, ?string $mes = null)
    {
        $mes = $mes && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : now()->format('Y-m');
        $inicioMes = \Carbon\Carbon::createFromFormat('Y-m-d', $mes . '-01');

        // 'todos' = ambos stats desactivados, sin filtrar por estado (clic de nuevo sobre el
        // stat ya activo lo desactiva en vez de dejarlo fijo en pendiente/cobrado).
        $estado = in_array($request->input('estado'), ['pendiente', 'cobrado', 'todos'], true) ? $request->input('estado') : 'pendiente';
        $nombre = $request->input('nombre');
        $grupo  = $request->input('grupo');

        // Base compartida por los stats (Pendiente/Cobrado) y el listado: mes + filtros de
        // nombre/grupo, pero SIN el filtro de estado, para que los importes de los stats no
        // cambien al alternar entre ellos (solo cambia cuál está resaltado/activo).
        $base = DB::table('nf_pagos as p')
            ->join('nf_clientes as cli', 'cli.id', '=', 'p.id_clientes')
            ->leftJoin('nf_contratos as c', 'c.id', '=', 'p.id_contratos')
            ->where('p.deleted', false)
            ->whereBetween('p.fecha_pago', [$inicioMes->toDateString(), $inicioMes->copy()->endOfMonth()->toDateString()]);

        if ($nombre) {
            $base->where('cli.nombre', 'ilike', "%{$nombre}%");
        }
        if ($grupo) {
            $base->where('c.id_grupo', $grupo);
        }

        $totalPendiente = (clone $base)->where('p.id_estado_pagos', 1)->sum('p.cantidad');
        $totalPagado    = (clone $base)->where('p.id_estado_pagos', 2)->sum('p.cantidad');

        // Ordenación por columna: mapa nombre público -> columna real (tras los joins de abajo).
        $columnasOrdenables = [
            'cliente'  => 'cli.nombre',
            'servicio' => 'c.nombre',
            'importe'  => 'p.cantidad',
            'estado'   => 'e.nombre',
            'fecha'    => 'p.fecha_pago',
        ];
        $sort = array_key_exists($request->input('sort'), $columnasOrdenables) ? $request->input('sort') : 'cliente';
        $dir  = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $pagos = (clone $base)
            ->when($estado !== 'todos', fn ($q) => $q->where('p.id_estado_pagos', $estado === 'cobrado' ? 2 : 1))
            ->leftJoin('nf_estado_pagos as e', 'e.id', '=', 'p.id_estado_pagos')
            ->leftJoin('nf_forma_pago as fp', 'fp.id', '=', 'p.id_forma_pago')
            ->orderBy($columnasOrdenables[$sort], $dir)
            ->select(
                'p.*',
                'cli.nombre as cliente_nombre',
                'cli.telefono as cliente_telefono',
                'c.nombre as contrato_nombre',
                'c.id_tipo as contrato_id_tipo',
                'e.nombre as estado_nombre',
                'fp.nombre as forma_pago_nombre'
            )
            ->get();

        $grupos = DB::table('nf_grupo')->where('deleted', false)->orderBy('id_tipo')->orderBy('nombre')->get();

        return view('nf.pagos-list', [
            'project' => $project,
            'mes' => $mes,
            'mesLabel' => ucfirst($inicioMes->translatedFormat('F Y')),
            'mesAnterior' => $inicioMes->copy()->subMonth()->format('Y-m'),
            'mesSiguiente' => $inicioMes->copy()->addMonth()->format('Y-m'),
            'pagos' => $pagos,
            'colorGrupo' => $this->colorGrupo(),
            'grupos' => $grupos,
            'estado' => $estado,
            'nombre' => $nombre,
            'grupo' => $grupo,
            'sort' => $sort,
            'dir' => $dir,
            'totalPendiente' => $totalPendiente,
            'totalPagado' => $totalPagado,
        ]);
    }

    // Resuelve grupo/fecha_fin/dia1/dia2 según las reglas de negocio del legacy: Osteopatía
    // ("Consulta") es fija, sin días, fecha_fin = fecha_inicio; Fitness exige grupo elegido y
    // permite fecha_fin y días de asistencia propios.
    private function resolverDatosContrato(array $data): array
    {
        $esOsteopatia = (int) $data['id_tipo'] === 1;

        if ($esOsteopatia) {
            $grupo = DB::table('nf_grupo')->where('id_tipo', 1)->first();
            abort_if(!$grupo, 500, 'No existe el grupo "Consulta" para Osteopatía.');
            return [$grupo, $data['fecha_inicio'], false, false, $esOsteopatia];
        }

        $grupo = DB::table('nf_grupo')->where('id', $data['id_grupo'] ?? null)->where('id_tipo', 2)->first();
        abort_if(!$grupo, 422, 'Selecciona un grupo válido.');
        $fechaFin = $data['fecha_fin'] ?? $data['fecha_inicio'];
        $dia1 = (bool) ($data['dia1'] ?? false);
        $dia2 = (bool) ($data['dia2'] ?? false);
        return [$grupo, $fechaFin, $dia1, $dia2, $esOsteopatia];
    }

    private function validarDatosContrato(Request $request): array
    {
        return $request->validate([
            'id_tipo' => 'required|integer|in:1,2',
            'id_grupo' => 'nullable|integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date',
            'dia1' => 'nullable|boolean',
            'dia2' => 'nullable|boolean',
            'importe' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
        ]);
    }

    // Alta de un nuevo contrato desde la ficha del cliente (modal).
    // Réplica de las reglas de FitnessController@guardar_contrato del legacy opland-app:
    // Osteopatía ("Consulta") es una sesión suelta sin grupo a elegir, sin días recurrentes,
    // fecha_fin = fecha_inicio, y genera automáticamente su pago ya cobrado en efectivo.
    // Fitness es una inscripción con grupo elegido, fecha_fin y días de asistencia; sus pagos
    // se generan más adelante desde /nf/pagos ("Generar pagos").
    public function guardarContrato(Request $request, Project $project, int $clienteId)
    {
        DB::table('nf_clientes')->where('id', $clienteId)->firstOrFail();

        $data = $this->validarDatosContrato($request);
        [$grupo, $fechaFin, $dia1, $dia2, $esOsteopatia] = $this->resolverDatosContrato($data);
        $now = now();

        $idContrato = DB::table('nf_contratos')->insertGetId([
            'id_clientes' => $clienteId,
            'id_tipo' => $data['id_tipo'],
            'id_grupo' => $grupo->id,
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $fechaFin,
            'importe' => $data['importe'],
            'dia1' => $dia1,
            'dia2' => $dia2,
            'nombre' => $grupo->nombre,
            'descripcion' => $data['descripcion'] ?? null,
            'createuser' => Auth::id(),
            'createdat' => $now,
            'updatedat' => $now,
            'hidden' => 0,
            'deleted' => false,
        ]);

        if ($esOsteopatia) {
            DB::table('nf_pagos')->insert([
                'id_clientes' => $clienteId,
                'id_contratos' => $idContrato,
                'cantidad' => $data['importe'],
                'fecha_pago' => $data['fecha_inicio'],
                'fecha_pagado' => $data['fecha_inicio'],
                'id_estado_pagos' => 2,
                'id_forma_pago' => 1,
                'createuser' => Auth::id(),
                'createdat' => $now,
                'updatedat' => $now,
                'hidden' => 0,
                'deleted' => false,
            ]);
        }

        return back()->with('msg', 'Contrato creado correctamente.');
    }

    // Edición de un contrato existente desde la ficha del cliente. A diferencia del alta, editar
    // no vuelve a generar el pago automático de Osteopatía (eso solo ocurre al crear).
    public function actualizarContrato(Request $request, Project $project, int $contratoId)
    {
        $contrato = DB::table('nf_contratos')->where('id', $contratoId)->firstOrFail();

        $data = $this->validarDatosContrato($request);
        [$grupo, $fechaFin, $dia1, $dia2] = $this->resolverDatosContrato($data);

        DB::table('nf_contratos')->where('id', $contratoId)->update([
            'id_tipo' => $data['id_tipo'],
            'id_grupo' => $grupo->id,
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $fechaFin,
            'importe' => $data['importe'],
            'dia1' => $dia1,
            'dia2' => $dia2,
            'nombre' => $grupo->nombre,
            'descripcion' => $data['descripcion'] ?? null,
            'updateuser' => Auth::id(),
            'updatedat' => now(),
        ]);

        return back()->with('msg', 'Contrato actualizado correctamente.');
    }

    // Marca un pago como cobrado (Efectivo=1 / Banco=2) y avisa al cliente por email.
    // Réplica de FitnessController@pagar_pago del legacy opland-app.
    public function pagarPago(Project $project, int $id, int $formaPago)
    {
        $pago = DB::table('nf_pagos')->where('id', $id)->first();
        abort_if(!$pago, 404);

        DB::table('nf_pagos')->where('id', $id)->update([
            'id_estado_pagos' => 2,
            'id_forma_pago' => $formaPago,
            'fecha_pagado' => now(),
            'updateuser' => Auth::id(),
            'updatedat' => now(),
        ]);

        $cliente = DB::table('nf_clientes')->where('id', $pago->id_clientes)->first();
        if ($cliente && $cliente->email) {
            $details = [
                'title' => 'Confirmación Automática de Pago',
                'header' => 'Estimad@ ' . $cliente->nombre . ',',
                'body' => 'Nature Fitness ha recibido el pago de ' . $pago->cantidad . ' euros en concepto del mes de '
                    . \Carbon\Carbon::parse($pago->fecha_pago)->translatedFormat('M Y') . '.',
            ];
            Mail::to($cliente->email)->send(new NfPagoMail($details));
        }

        return back()->with('msg', 'Pago confirmado correctamente.');
    }

    // Envía por email el documento adjunto y lo marca como enviado.
    // Réplica de FitnessController@enviar_documento del legacy opland-app.
    public function enviarDocumento(Project $project, int $id)
    {
        $documento = DB::table('nf_documentos')->where('id', $id)->first();
        abort_if(!$documento, 404);

        $cliente = DB::table('nf_clientes')->where('id', $documento->id_clientes)->first();
        abort_if(!$cliente || !$cliente->email, 422, 'El cliente no tiene email registrado.');

        $details = [
            'title' => $documento->nombre,
            'header' => 'Estimad@ ' . $cliente->nombre . ',',
            'file' => $documento->file_documento,
            'body' => 'Adjunto encontrarás el documento ' . $documento->nombre,
        ];
        Mail::to($cliente->email)->send(new NfDocumentoMail($details));

        DB::table('nf_documentos')->where('id', $id)->update([
            'id_estado_documento' => 2,
            'fecha_envio' => now(),
            'updateuser' => Auth::id(),
            'updatedat' => now(),
        ]);

        return back()->with('msg', 'Documento enviado correctamente.');
    }

    // Genera los pagos pendientes del mes para cada contrato activo que todavía no tenga uno.
    // Réplica de FitnessController@generar_pagos del legacy opland-app.
    public function generarPagos(Request $request, Project $project)
    {
        $mes = $request->input('mes'); // formato YYYY-MM
        abort_unless(preg_match('/^\d{4}-\d{2}$/', (string) $mes), 422, 'Mes inválido.');

        $inicioMes = $mes . '-01';
        $finMes = date('Y-m-t', strtotime($inicioMes));

        $contratos = DB::table('nf_contratos')
            ->where('deleted', false)
            ->where('fecha_inicio', '<=', $finMes)
            ->where('fecha_fin', '>=', $inicioMes)
            ->get();

        $pagosExistentes = DB::table('nf_pagos')
            ->where('fecha_pago', '>=', $inicioMes)
            ->where('fecha_pago', '<=', $finMes)
            ->pluck('id_contratos')
            ->all();

        $creados = 0;
        $now = now();
        foreach ($contratos as $contrato) {
            if (in_array($contrato->id, $pagosExistentes, true)) continue;
            DB::table('nf_pagos')->insert([
                'id_clientes' => $contrato->id_clientes,
                'id_contratos' => $contrato->id,
                'cantidad' => $contrato->importe,
                'fecha_pago' => $inicioMes,
                'id_estado_pagos' => 1,
                'createuser' => Auth::id(),
                'createdat' => $now,
                'updatedat' => $now,
                'hidden' => 0,
                'deleted' => false,
            ]);
            $creados++;
        }

        return back()->with('msg', "Pagos generados correctamente ({$creados} nuevos).");
    }
}
