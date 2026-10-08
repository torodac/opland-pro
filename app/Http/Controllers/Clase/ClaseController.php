<?php
namespace App\Http\Controllers\Clase;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ClaseController extends Controller
{
    // ── Listado de cobros con búsqueda alumno/pagador ──────────────────────
    public function cobros(Request $request, Project $project)
    {
        $mes   = $request->input('mes', now()->format('Y-m'));
        $busca = trim((string) $request->input('q', ''));
        $solo  = $request->input('solo'); // 'pendientes' | null

        $query = DB::table('clase_cobros as c')
            
            ->leftJoin('clase_tutores as t', 't.id', '=', 'c.id_tutor')
            ->leftJoin('clase_clientes as al', 'al.id', '=', 'c.id_alumno')
            ->where('c.deleted', false)
            ->whereRaw("to_char(c.fecha_cobro, 'YYYY-MM') = ?", [$mes])
            ->select([
                'c.id', 'c.fecha_cobro', 'c.fecha_cobrado',
                'c.cantidad_total', 'c.cantidad_pagador', 'c.porcentaje',
                'c.nombre_alumno', 'c.nombre_pagador',
                'c.contrato_modificado', 'c.id_contrato',
                'c.estado_cobros as estado', 'c.forma_pago',
                DB::raw("COALESCE(al.nombre, c.nombre_alumno) as alumno_nombre"),
                DB::raw("COALESCE(t.nombre, c.nombre_pagador) as pagador_nombre"),
            ]);

        if ($busca !== '') {
            $query->where(function ($q) use ($busca) {
                $q->where('c.nombre_alumno', 'ilike', "%{$busca}%")
                  ->orWhere('c.nombre_pagador', 'ilike', "%{$busca}%")
                  ->orWhere('al.nombre', 'ilike', "%{$busca}%")
                  ->orWhere('t.nombre', 'ilike', "%{$busca}%");
            });
        }

        if ($solo === 'pendientes') {
            $query->where('c.estado_cobros', 'Pendiente');
        }

        $cobros = $query->orderByRaw("c.nombre_pagador, c.nombre_alumno")->get();

        // Opciones fijas (tablas diccionario eliminadas)
        $estados    = collect([
            (object)['id'=>null,'nombre'=>'Pendiente'],
            (object)['id'=>null,'nombre'=>'Cobrado'],
            (object)['id'=>null,'nombre'=>'Anulado'],
        ]);
        $formasPago = collect([
            (object)['id'=>null,'nombre'=>'Efectivo'],
            (object)['id'=>null,'nombre'=>'Tarjeta'],
            (object)['id'=>null,'nombre'=>'Transferencia/Bizum'],
        ]);

        // Stats del mes
        $stats = [
            'total'     => $cobros->count(),
            'pendiente' => $cobros->where('estado', 'Pendiente')->count(),
            'cobrado'   => $cobros->where('estado', 'Cobrado')->count(),
            'importe'   => $cobros->sum('cantidad_pagador'),
            'cobrado_imp' => $cobros->where('estado', 'Cobrado')->sum('cantidad_pagador'),
        ];

        // Meses disponibles
        $meses = DB::table('clase_cobros')
            ->where('deleted', false)
            ->selectRaw("to_char(fecha_cobro,'YYYY-MM') as m")
            ->distinct()
            ->orderByRaw("1 DESC")
            ->pluck('m');

        return view('clase.cobros', compact('project', 'cobros', 'mes', 'meses', 'busca', 'solo', 'stats', 'estados', 'formasPago'));
    }

    // ── Ficha de un cobro ─────────────────────────────────────────────────
    public function fichaCobro(Request $request, Project $project, int $id)
    {
        $cobro = DB::table('clase_cobros as c')
            ->leftJoin('clase_tutores as t',    't.id',  '=', 'c.id_tutor')
            ->leftJoin('clase_clientes as al',  'al.id', '=', 'c.id_alumno')
            ->leftJoin('clase_contratos as ct', 'ct.id', '=', 'c.id_contrato')
            ->leftJoin('clase_grupo as g',      'g.id',  '=', 'ct.id_grupo')
            ->where('c.id', $id)
            ->where('c.deleted', false)
            ->select(
                'c.*',
                DB::raw("COALESCE(al.nombre, c.nombre_alumno) as alumno_nombre_real"),
                DB::raw("COALESCE(t.nombre,  c.nombre_pagador) as pagador_nombre_real"),
                'al.id as alumno_id',
                't.id as tutor_id',
                'g.nombre as grupo_nombre'
            )
            ->first();
        abort_if(!$cobro, 404);
        $formasPago = ['Efectivo', 'Tarjeta', 'Transferencia/Bizum'];
        return view('clase.cobro-ficha', compact('project', 'cobro', 'formasPago'));
    }

    public function actualizarCobro(Request $request, Project $project, int $id)
    {
        $data = $request->validate([
            'nombre_alumno'    => 'nullable|string|max:150',
            'nombre_pagador'   => 'nullable|string|max:150',
            'cantidad_total'   => 'nullable|numeric|min:0',
            'porcentaje'       => 'nullable|numeric|min:0|max:100',
            'cantidad_pagador' => 'nullable|numeric|min:0',
            'fecha_cobro'      => 'nullable|date',
            'notas'            => 'nullable|string|max:500',
        ]);
        $data['updateuser'] = auth()->id();
        $data['updatedat']  = now();
        // recalcular si cambian total o porcentaje
        if (isset($data['cantidad_total']) && isset($data['porcentaje'])) {
            $data['cantidad_pagador'] = round($data['cantidad_total'] * $data['porcentaje'] / 100, 2);
        }
        DB::table('clase_cobros')->where('id', $id)->update(array_filter($data, fn($v) => $v !== null));
        return response()->json(['ok' => true]);
    }

    public function actualizarEstadoCobro(Request $request, Project $project, int $id)
    {
        $data = $request->validate([
            'estado_cobros' => 'required|in:Pendiente,Cobrado,Anulado',
            'forma_pago'    => 'nullable|string|max:50',
        ]);
        $update = [
            'estado_cobros' => $data['estado_cobros'],
            'updateuser'    => auth()->id(),
            'updatedat'     => now(),
        ];
        if ($data['estado_cobros'] === 'Cobrado') {
            $update['forma_pago']    = $data['forma_pago'] ?? null;
            $update['fecha_cobrado'] = now()->toDateString();
        } elseif ($data['estado_cobros'] === 'Pendiente') {
            $update['forma_pago']    = null;
            $update['fecha_cobrado'] = null;
        }
        DB::table('clase_cobros')->where('id', $id)->update($update);
        return response()->json(['ok' => true]);
    }

    // ── Cobrar un lote desde la cesta ──────────────────────────────────────
    public function cobrarLote(Request $request, Project $project)
    {
        $data = $request->validate([
            'ids'              => 'required|array|min:1',
            'ids.*'            => 'integer',
            'formas_pago'      => 'required|array|min:1',
            'formas_pago.*.forma'   => 'required|string|max:50',
            'formas_pago.*.importe' => 'required|numeric|min:0.01',
        ]);

        $formaResumen = collect($data['formas_pago'])->pluck('forma')->join(' + ');

        DB::table('clase_cobros')
            ->whereIn('id', $data['ids'])
            ->where('deleted', false)
            ->update([
                'estado_cobros' => 'Cobrado',
                'forma_pago'    => $formaResumen,
                'fecha_cobrado' => now()->toDateString(),
                'updateuser'    => auth()->id(),
                'updatedat'     => now(),
            ]);

        // Datos de cobros para el recibo
        $cobrosData = DB::table('clase_cobros')
            ->whereIn('id', $data['ids'])
            ->select('id', 'nombre_alumno', 'nombre_pagador', 'id_tutor',
                     'id_contrato', 'cantidad_pagador', 'fecha_cobro')
            ->get();

        $pagador    = $cobrosData->first()->nombre_pagador ?? '';
        $total      = round($cobrosData->sum('cantidad_pagador'), 2);
        $mes        = substr((string)($cobrosData->first()->fecha_cobro ?? now()->format('Y-m-d')), 0, 7);
        $idTutor    = $cobrosData->first()->id_tutor;
        $email      = $idTutor ? DB::table('clase_tutores')->where('id', $idTutor)->value('email') : null;

        // Número de recibo: R-YYYY/NNN por año
        $year    = now()->year;
        $cuenta  = DB::table('clase_recibos')->where('deleted', false)->whereYear('createdat', $year)->count();
        $numRecibo = 'R-' . $year . '/' . str_pad($cuenta + 1, 3, '0', STR_PAD_LEFT);

        $reciboId = DB::table('clase_recibos')->insertGetId([
            'numero_recibo'  => $numRecibo,
            'fecha'          => now()->toDateString(),
            'mes'            => $mes,
            'pagador_nombre' => $pagador,
            'pagador_email'  => $email,
            'cobros_ids'     => json_encode($data['ids']),
            'importe_total'  => $total,
            'formas_pago'    => json_encode($data['formas_pago']),
            'createuser'     => auth()->id(),
            'createdat'      => now(),
            'updatedat'      => now(),
        ]);

        return response()->json(['ok' => true, 'n' => count($data['ids']),
                                 'recibo_id' => $reciboId, 'numero_recibo' => $numRecibo]);
    }

    // ── Generar cobros del mes actual ──────────────────────────────────────
    public function generarCobros(Request $request, Project $project)
    {
        $mes      = now()->format('Y-m');
        $inicio   = now()->startOfMonth()->toDateString();
        $fin      = now()->endOfMonth()->toDateString();
        $generados = 0;
        $ignorados = 0;

        // Contratos activos este mes
        $contratos = DB::table('clase_contratos as ct')
            ->join('clase_clientes as al', 'al.id', '=', 'ct.id_alumno')
            ->where('ct.deleted', false)
            ->where('ct.hidden', 0)
            ->where('ct.fecha_inicio', '<=', $fin)
            ->where(function ($q) use ($inicio) {
                $q->whereNull('ct.fecha_fin')->orWhere('ct.fecha_fin', '>=', $inicio);
            })
            ->select('ct.*', 'al.nombre as alumno_nombre')
            ->get();

        $estadoPendiente = 'Pendiente';

        foreach ($contratos as $ct) {
            // Tutores pagadores
            $pagadores = DB::table('clase_tutores_alumnos as ta')
                ->join('clase_tutores as t', 't.id', '=', 'ta.id_tutor')
                ->where('ta.id_alumno', $ct->id_alumno)
                ->where('ta.es_pagador', true)
                ->where('ta.deleted', false)
                ->where('t.deleted', false)
                ->select('t.id as id', 't.nombre', 'ta.porcentaje_pago')
                ->get();

            foreach ($pagadores as $tut) {
                // ¿Ya existe cobro para este alumno+tutor+contrato+mes?
                $existe = DB::table('clase_cobros')
                    ->where('id_alumno',  $ct->id_alumno)
                    ->where('id_tutor',   $tut->id)
                    ->where('id_contrato', $ct->id)
                    ->whereRaw("to_char(fecha_cobro,'YYYY-MM') = ?", [$mes])
                    ->where('deleted', false)
                    ->exists();

                if ($existe) { $ignorados++; continue; }

                $cantPagador = round($ct->importe * $tut->porcentaje_pago / 100, 2);

                DB::table('clase_cobros')->insert([
                    'id_alumno'        => $ct->id_alumno,
                    'id_tutor'         => $tut->id,
                    'id_contrato'      => $ct->id,
                    'fecha_cobro'      => $inicio,
                    'cantidad_total'   => $ct->importe,
                    'cantidad_pagador' => $cantPagador,
                    'porcentaje'       => $tut->porcentaje_pago,
                    'estado_cobros' => $estadoPendiente,
                    'nombre_alumno'    => $ct->alumno_nombre,
                    'nombre_pagador'   => $tut->nombre,
                    'nombre'           => $ct->alumno_nombre . ' – ' . now()->format('m/Y'),
                    'createuser'       => auth()->id() ?? 1,
                    'createdat'        => now(),
                    'updatedat'        => now(),
                ]);
                $generados++;
            }
        }

        return response()->json(['ok' => true, 'generados' => $generados, 'ignorados' => $ignorados]);
    }
    // ── Ficha personalizada alumno ─────────────────────────────────────────
    public function fichaAlumno(Request $request, Project $project, int $id)
    {
        $alumno = DB::table('clase_clientes')->where('id', $id)->firstOrFail();

        $tutores = DB::table('clase_tutores_alumnos as ta')
            ->join('clase_tutores as t', 't.id', '=', 'ta.id_tutor')
            ->where('ta.id_alumno', $id)
            ->where('ta.deleted', false)
            ->where('t.deleted', false)
            ->select(
                'ta.id',
                't.id as tutor_id',
                't.nombre',
                't.telefono',
                't.email',
                't.tipo_tutor',
                'ta.es_pagador',
                'ta.puede_recoger',
                'ta.recibe_comunicaciones',
                'ta.porcentaje_pago'
            )
            ->orderByDesc('ta.es_pagador')
            ->orderBy('t.nombre')
            ->get();

        $contratos = DB::table('clase_contratos as ct')
            ->leftJoin('clase_grupo as g', 'g.id', '=', 'ct.id_grupo')
            ->where('ct.id_alumno', $id)
            ->where('ct.deleted', false)
            ->select('ct.*', 'g.nombre as grupo_nombre')
            ->orderByDesc('ct.fecha_inicio')
            ->get();

        // Círculos de cobro por contrato
        $cobrosPorContrato = DB::table('clase_cobros as c')
            ->whereIn('c.id_contrato', $contratos->pluck('id'))
            ->where('c.deleted', false)
            ->select('c.id', 'c.id_contrato', 'c.fecha_cobro', 'c.estado_cobros', 'c.cantidad_pagador', 'c.nombre_pagador')
            ->get()
            ->groupBy('id_contrato');

        foreach ($contratos as $ct) {
            $cobros = $cobrosPorContrato->get($ct->id, collect());
            $cursor = \Carbon\Carbon::parse($ct->fecha_inicio)->startOfMonth();
            $fin    = $ct->fecha_fin
                ? \Carbon\Carbon::parse($ct->fecha_fin)->startOfMonth()
                : now()->startOfMonth();
            $meses = [];
            while ($cursor->lte($fin)) {
                $m   = $cursor->format('Y-m');
                $row = $cobros->first(fn($c) => \Carbon\Carbon::parse($c->fecha_cobro)->format('Y-m') === $m);
                $meses[] = [
                    'mes'     => $m,
                    'cobro_id' => $row?->id,
                    'estado'  => $row ? strtolower($row->estado_cobros) : 'sin_generar',
                    'importe' => $row?->cantidad_pagador,
                    'pagador' => $row?->nombre_pagador,
                ];
                $cursor->addMonth();
            }
            $ct->mesesCobro = $meses;
        }

        $generos    = collect([
            (object)['id'=>'Masculino',      'nombre'=>'Masculino'],
            (object)['id'=>'Femenino',       'nombre'=>'Femenino'],
            (object)['id'=>'No especificado','nombre'=>'No especificado'],
        ]);
        $grupos     = DB::table('clase_grupo')->where('deleted', false)->orderBy('nombre')->get();
        $activo     = $contratos->contains(function ($c) {
            $hoy = now()->toDateString();
            return $c->fecha_inicio <= $hoy && (!$c->fecha_fin || $c->fecha_fin >= $hoy);
        });

        return view('clase.alumno', compact('project','alumno','tutores','contratos','generos','grupos','activo'));
    }

    // ── API: guardar/actualizar tutor ──────────────────────────────────────
    public function guardarTutor(Request $request, Project $project)
    {
        $data = $request->validate([
            'id_alumno'             => 'required|integer',
            'nombre'                => 'required|string|max:150',
            'telefono'              => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'tipo_tutor'            => 'nullable|string|max:50',
            'puede_recoger'         => 'boolean',
            'recibe_comunicaciones' => 'boolean',
            'es_pagador'            => 'boolean',
            'porcentaje_pago'       => 'numeric|min:0|max:100',
        ]);
        if (!empty($data['recibe_comunicaciones']) && empty($data['email'])) {
            return response()->json(['ok' => false, 'error' => 'El email es obligatorio si el tutor recibe comunicaciones.'], 422);
        }

        // Buscar o crear tutor master (dedup por nombre)
        $master = DB::table('clase_tutores')
            ->whereRaw("LOWER(TRIM(nombre)) = LOWER(TRIM(?))", [$data['nombre']])
            ->where('deleted', false)
            ->first();

        if (!$master) {
            $masterId = DB::table('clase_tutores')->insertGetId([
                'nombre'      => $data['nombre'],
                'tipo_tutor'  => $data['tipo_tutor'] ?? null,
                'telefono'    => $data['telefono'] ?? null,
                'email'       => $data['email'] ?? null,
                'deleted'     => false,
                'createuser'  => auth()->id(),
                'updateuser'  => auth()->id(),
                'createdat'   => now(),
                'updatedat'   => now(),
            ]);
        } else {
            $masterId = $master->id;
            DB::table('clase_tutores')->where('id', $masterId)->update([
                'tipo_tutor' => $data['tipo_tutor'] ?? $master->tipo_tutor,
                'telefono'   => !empty($data['telefono']) ? $data['telefono'] : $master->telefono,
                'email'      => !empty($data['email'])    ? $data['email']    : $master->email,
                'updatedat'  => now(),
                'updateuser' => auth()->id(),
            ]);
        }

        // Crear relación tutor-alumno
        $relId = DB::table('clase_tutores_alumnos')->insertGetId([
            'id_tutor'              => $masterId,
            'id_alumno'             => $data['id_alumno'],
            'es_pagador'            => $data['es_pagador'] ?? false,
            'puede_recoger'         => $data['puede_recoger'] ?? false,
            'recibe_comunicaciones' => $data['recibe_comunicaciones'] ?? true,
            'porcentaje_pago'       => $data['porcentaje_pago'] ?? 0,
            'deleted'               => false,
            'createuser'            => auth()->id(),
            'updateuser'            => auth()->id(),
            'createdat'             => now(),
            'updatedat'             => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $relId]);
    }

    public function actualizarTutor(Request $request, Project $project, int $id)
    {
        // $id = clase_tutores_alumnos.id (relationship ID)
        $data = $request->validate([
            'nombre'                => 'required|string|max:150',
            'telefono'              => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'tipo_tutor'            => 'nullable|string|max:50',
            'puede_recoger'         => 'boolean',
            'recibe_comunicaciones' => 'boolean',
            'es_pagador'            => 'boolean',
            'porcentaje_pago'       => 'numeric|min:0|max:100',
        ]);
        if (!empty($data['recibe_comunicaciones']) && empty($data['email'])) {
            return response()->json(['ok' => false, 'error' => 'El email es obligatorio si el tutor recibe comunicaciones.'], 422);
        }
        $rel = DB::table('clase_tutores_alumnos')->where('id', $id)->where('deleted', false)->first();
        if (!$rel) return response()->json(['ok' => false, 'error' => 'Relación no encontrada'], 404);

        DB::table('clase_tutores')->where('id', $rel->id_tutor)->update([
            'nombre'     => $data['nombre'],
            'tipo_tutor' => $data['tipo_tutor'] ?? null,
            'telefono'   => $data['telefono'] ?? null,
            'email'      => $data['email'] ?? null,
            'updatedat'  => now(),
            'updateuser' => auth()->id(),
        ]);

        DB::table('clase_tutores_alumnos')->where('id', $id)->update([
            'es_pagador'            => $data['es_pagador'] ?? false,
            'puede_recoger'         => $data['puede_recoger'] ?? false,
            'recibe_comunicaciones' => $data['recibe_comunicaciones'] ?? false,
            'porcentaje_pago'       => $data['porcentaje_pago'] ?? 0,
            'updatedat'             => now(),
            'updateuser'            => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function borrarTutor(Project $project, int $id)
    {
        // Borrar relación (clase_tutores_alumnos.id)
        DB::table('clase_tutores_alumnos')->where('id', $id)->update([
            'deleted'    => true,
            'updatedat'  => now(),
            'updateuser' => auth()->id(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function borrarMasterTutor(Project $project, int $id)
    {
        DB::table('clase_tutores_alumnos')->where('id_tutor', $id)->update(['deleted' => true, 'updatedat' => now()]);
        DB::table('clase_tutores')->where('id', $id)->update(['deleted' => true, 'updatedat' => now()]);
        return response()->json(['ok' => true]);
    }

    public function actualizarDatosTutor(Request $request, Project $project, int $id)
    {
        $data = $request->validate([
            'nombre'    => 'required|string|max:150',
            'tipo_tutor'=> 'nullable|string|max:50',
            'telefono'  => 'nullable|string|max:30',
            'email'     => 'nullable|email|max:150',
        ]);
        $data['updatedat']  = now();
        $data['updateuser'] = auth()->id();
        DB::table('clase_tutores')->where('id', $id)->where('deleted', false)->update($data);
        return response()->json(['ok' => true]);
    }

    public function listadoTutores(Request $request, Project $project)
    {
        $busca = trim($request->get('q', ''));
        $query = DB::table('clase_tutores as t')
            ->leftJoin('clase_tutores_alumnos as ta', function ($j) {
                $j->on('ta.id_tutor', '=', 't.id')->where('ta.deleted', false);
            })
            ->leftJoin('clase_clientes as c', function ($j) {
                $j->on('c.id', '=', 'ta.id_alumno')->where('c.deleted', false);
            })
            ->where('t.deleted', false)
            ->select(
                't.id', 't.nombre', 't.telefono', 't.email', 't.tipo_tutor',
                DB::raw('COUNT(ta.id) as n_alumnos'),
                DB::raw("STRING_AGG(c.nombre, ', ' ORDER BY c.nombre) as alumnos_nombres")
            )
            ->groupBy('t.id', 't.nombre', 't.telefono', 't.email', 't.tipo_tutor')
            ->orderBy('t.nombre');
        if ($busca) {
            $query->where(function ($q) use ($busca) {
                $q->where('t.nombre',   'ilike', "%{$busca}%")
                  ->orWhere('t.email',    'ilike', "%{$busca}%")
                  ->orWhere('t.telefono', 'ilike', "%{$busca}%");
            });
        }
        $tutores = $query->paginate(30)->withQueryString();
        return view('clase.tutores', compact('project', 'tutores', 'busca'));
    }

    public function fichaTutor(Request $request, Project $project, int $id)
    {
        $tutor = DB::table('clase_tutores')->where('id', $id)->where('deleted', false)->first();
        abort_if(!$tutor, 404);
        $relaciones = DB::table('clase_tutores_alumnos as ta')
            ->join('clase_clientes as c', 'c.id', '=', 'ta.id_alumno')
            ->where('ta.id_tutor', $id)
            ->where('ta.deleted', false)
            ->where('c.deleted', false)
            ->select('ta.*', 'c.nombre as alumno_nombre')
            ->orderBy('c.nombre')
            ->get();
        $tiposTutor = ['Madre', 'Padre', 'Abuelo/a', 'Tutor legal', 'Otro parentesco', 'Otro'];
        return view('clase.tutor-ficha', compact('project', 'tutor', 'relaciones', 'tiposTutor'));
    }

    // ── API: guardar contrato alumno ───────────────────────────────────────
    public function guardarContrato(Request $request, Project $project, int $alumnoId)
    {
        $data = $request->validate([
            'id_grupo'    => 'required|integer',
            'fecha_inicio'=> 'required|date',
            'fecha_fin'   => 'nullable|date',
            'importe'     => 'required|numeric|min:0',
            'lunes'       => 'boolean', 'martes'   => 'boolean', 'miercoles' => 'boolean',
            'jueves'      => 'boolean', 'viernes'  => 'boolean', 'sabado'    => 'boolean',
            'domingo'     => 'boolean',
            'descripcion' => 'nullable|string',
        ]);
        $al = DB::table('clase_clientes')->where('id', $alumnoId)->value('nombre');
        $gr = DB::table('clase_grupo')->where('id', $data['id_grupo'])->value('nombre');
        $data['id_alumno']  = $alumnoId;
        $data['nombre']     = "{$al} – {$gr}";
        $data['createuser'] = auth()->id();
        $data['createdat']  = now();
        $data['updatedat']  = now();
        $id = DB::table('clase_contratos')->insertGetId($data);
        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function borrarContrato(Project $project, int $id)
    {
        DB::table('clase_contratos')->where('id', $id)->update(['deleted' => true, 'updatedat' => now()]);
        return response()->json(['ok' => true]);
    }

    public function actualizarContrato(Request $request, Project $project, int $id)
    {
        $data = $request->validate([
            'id_grupo'    => 'required|integer',
            'fecha_inicio'=> 'required|date',
            'fecha_fin'   => 'nullable|date',
            'importe'     => 'required|numeric|min:0',
            'lunes'       => 'boolean', 'martes'   => 'boolean', 'miercoles' => 'boolean',
            'jueves'      => 'boolean', 'viernes'  => 'boolean', 'sabado'    => 'boolean',
            'domingo'     => 'boolean',
            'descripcion' => 'nullable|string',
        ]);
        $ct = DB::table('clase_contratos')->where('id', $id)->first();
        $al = DB::table('clase_clientes')->where('id', $ct->id_alumno)->value('nombre');
        $gr = DB::table('clase_grupo')->where('id', $data['id_grupo'])->value('nombre');
        $data['nombre']    = "{$al} – {$gr}";
        $data['updatedat'] = now();
        DB::table('clase_contratos')->where('id', $id)->update($data);
        return response()->json(['ok' => true]);
    }

    // ── Búsqueda de tutores existentes (autocomplete modal) ───────────────
    public function buscarTutores(Request $request, Project $project)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) return response()->json([]);

        $tutores = DB::table('clase_tutores')
            ->where('deleted', false)
            ->where('nombre', 'ilike', "%{$q}%")
            ->select('nombre', 'telefono', 'email', 'tipo_tutor')
            ->orderBy('nombre')
            ->distinct()
            ->limit(8)
            ->get();

        return response()->json($tutores);
    }

    // ── Duplicar alumno: crea ficha vacía + copia tutores ─────────────────
    public function duplicarAlumno(Request $request, Project $project, int $id)
    {
        $relaciones = DB::table('clase_tutores_alumnos')
            ->where('id_alumno', $id)
            ->where('deleted', false)
            ->get();

        $nuevoId = DB::table('clase_clientes')->insertGetId([
            'nombre'     => '',
            'createuser' => auth()->id(),
            'createdat'  => now(),
            'updatedat'  => now(),
        ]);

        foreach ($relaciones as $t) {
            DB::table('clase_tutores_alumnos')->insert([
                'id_tutor'              => $t->id_tutor,
                'id_alumno'             => $nuevoId,
                'es_pagador'            => $t->es_pagador,
                'puede_recoger'         => $t->puede_recoger,
                'recibe_comunicaciones' => $t->recibe_comunicaciones,
                'porcentaje_pago'       => $t->porcentaje_pago,
                'deleted'               => false,
                'createuser'            => auth()->id(),
                'updateuser'            => auth()->id(),
                'createdat'             => now(),
                'updatedat'             => now(),
            ]);
        }

        return response()->json([
            'ok'  => true,
            'url' => route('clase.alumnos_form', [$project->slug, $nuevoId]),
        ]);
    }

    // ── Listado de grupos ────────────────────────────────────────────────────
    public function listadoGrupos(Request $request, Project $project)
    {
        $hoy = now()->toDateString();
        $grupos = DB::table('clase_grupo as g')
            ->leftJoin('clase_contratos as ct', function ($j) use ($hoy) {
                $j->on('ct.id_grupo', '=', 'g.id')
                  ->where('ct.deleted', false)
                  ->where('ct.fecha_inicio', '<=', $hoy)
                  ->where(function ($q) use ($hoy) {
                      $q->whereNull('ct.fecha_fin')->orWhere('ct.fecha_fin', '>=', $hoy);
                  });
            })
            ->where('g.deleted', false)
            ->select('g.*', DB::raw('COUNT(DISTINCT ct.id_alumno) as n_alumnos'))
            ->groupBy('g.id','g.nombre','g.descripcion','g.lunes','g.martes','g.miercoles',
                      'g.jueves','g.viernes','g.sabado','g.domingo','g.hidden','g.deleted',
                      'g.createuser','g.updateuser','g.createdat','g.updatedat')
            ->orderBy('g.nombre')
            ->get();
        return view('clase.grupos', compact('project', 'grupos'));
    }

    public function nuevoGrupo(Request $request, Project $project)
    {
        $grupo = null;
        $alumnos = collect();
        return view('clase.grupo-ficha', compact('project', 'grupo', 'alumnos'));
    }

    public function fichaGrupo(Request $request, Project $project, int $id)
    {
        $grupo = DB::table('clase_grupo')->where('id', $id)->where('deleted', false)->first();
        abort_if(!$grupo, 404);
        $hoy = now()->toDateString();
        $alumnos = DB::table('clase_contratos as ct')
            ->join('clase_clientes as c', 'c.id', '=', 'ct.id_alumno')
            ->where('ct.id_grupo', $id)
            ->where('ct.deleted', false)
            ->where('c.deleted', false)
            ->where('ct.fecha_inicio', '<=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('ct.fecha_fin')->orWhere('ct.fecha_fin', '>=', $hoy);
            })
            ->select('c.id', 'c.nombre as alumno_nombre', 'ct.fecha_inicio', 'ct.importe')
            ->orderBy('c.nombre')
            ->get();
        return view('clase.grupo-ficha', compact('project', 'grupo', 'alumnos'));
    }

    public function guardarGrupo(Request $request, Project $project)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'lunes'       => 'boolean', 'martes'    => 'boolean', 'miercoles' => 'boolean',
            'jueves'      => 'boolean', 'viernes'   => 'boolean', 'sabado'    => 'boolean',
            'domingo'     => 'boolean',
        ]);
        $data['createuser'] = auth()->id();
        $data['updateuser'] = auth()->id();
        $data['createdat']  = now();
        $data['updatedat']  = now();
        $id = DB::table('clase_grupo')->insertGetId($data);
        return response()->json(['ok' => true, 'url' => route('clase.grupos.ficha', [$project->slug, $id])]);
    }

    public function actualizarGrupo(Request $request, Project $project, int $id)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'lunes'       => 'boolean', 'martes'    => 'boolean', 'miercoles' => 'boolean',
            'jueves'      => 'boolean', 'viernes'   => 'boolean', 'sabado'    => 'boolean',
            'domingo'     => 'boolean',
        ]);
        $data['updateuser'] = auth()->id();
        $data['updatedat']  = now();
        DB::table('clase_grupo')->where('id', $id)->update($data);
        return response()->json(['ok' => true]);
    }

    public function borrarGrupo(Project $project, int $id)
    {
        DB::table('clase_grupo')->where('id', $id)->update(['deleted' => true, 'updatedat' => now()]);
        return response()->json(['ok' => true]);
    }

    // ── Listado de recibos ────────────────────────────────────────────────
    public function recibos(Request $request, Project $project)
    {
        $recibos = DB::table('clase_recibos')
            ->where('deleted', false)
            ->orderByDesc('createdat')
            ->paginate(50);

        return view('clase.recibos', compact('project', 'recibos'));
    }

    // ── Ver / descargar PDF de un recibo ──────────────────────────────────
    public function verReciboPdf(Request $request, Project $project, int $id)
    {
        $recibo = DB::table('clase_recibos')->where('id', $id)->where('deleted', false)->first();
        if (!$recibo) abort(404);

        $cobrosIds = json_decode($recibo->cobros_ids, true) ?: [];
        $cobros = DB::table('clase_cobros as c')
            ->leftJoin('clase_contratos as ct', 'ct.id', '=', 'c.id_contrato')
            ->leftJoin('clase_grupo as g',      'g.id',  '=', 'ct.id_grupo')
            ->whereIn('c.id', $cobrosIds)
            ->select('c.nombre_alumno', 'c.cantidad_pagador', 'c.fecha_cobro',
                     'ct.nombre as contrato_nombre', 'g.nombre as grupo_nombre')
            ->get();

        $porAlumno = $cobros->groupBy('nombre_alumno');
        $formas    = json_decode($recibo->formas_pago, true) ?: [];

        $logoPath = public_path('projects/clase/logo.png');
        $src = imagecreatefrompng($logoPath);
        $thumb = imagecreatetruecolor(80, 80);
        imagealphablending($thumb, false); imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, 80, 80, imagesx($src), imagesy($src));
        ob_start(); imagepng($thumb, null, 6); $pngData = ob_get_clean();
        imagedestroy($src); imagedestroy($thumb);
        $logoB64 = 'data:image/png;base64,' . base64_encode($pngData);
        $pdf = Pdf::loadView('clase.recibo-pdf', [
            'recibo'    => $recibo,
            'porAlumno' => $porAlumno,
            'formas'    => $formas,
            'logoB64'   => $logoB64,
        ])->setPaper('a4', 'portrait');

        $filename = ($recibo->numero_factura ?? $recibo->numero_recibo) . '.pdf';
        $filename = str_replace('/', '-', $filename);

        return $pdf->stream($filename);
    }

    // ── Facturar un recibo ────────────────────────────────────────────────
    public function facturarRecibo(Request $request, Project $project, int $id)
    {
        $recibo = DB::table('clase_recibos')->where('id', $id)->where('deleted', false)->first();
        if (!$recibo) return response()->json(['ok' => false, 'error' => 'Recibo no encontrado']);
        if ($recibo->numero_factura) return response()->json(['ok' => false, 'error' => 'Ya está facturado']);

        // Número de factura: YYYY/NNNN por año
        $year   = now()->year;
        $cuenta = DB::table('clase_recibos')->whereNotNull('numero_factura')
                    ->where('deleted', false)->whereYear('fecha_factura', $year)->count();
        $numFactura = $year . '/' . str_pad($cuenta + 1, 4, '0', STR_PAD_LEFT);

        DB::table('clase_recibos')->where('id', $id)->update([
            'numero_factura' => $numFactura,
            'fecha_factura'  => now()->toDateString(),
            'updatedat'      => now(),
        ]);

        // Enviar por email si hay dirección
        if ($recibo->pagador_email) {
            $recibo = DB::table('clase_recibos')->find($id);
            $cobrosIds = json_decode($recibo->cobros_ids, true) ?: [];
            $cobros = DB::table('clase_cobros as c')
                ->leftJoin('clase_contratos as ct', 'ct.id', '=', 'c.id_contrato')
                ->leftJoin('clase_grupo as g',      'g.id',  '=', 'ct.id_grupo')
                ->whereIn('c.id', $cobrosIds)
                ->select('c.nombre_alumno', 'c.cantidad_pagador', 'c.fecha_cobro',
                         'ct.nombre as contrato_nombre', 'g.nombre as grupo_nombre')
                ->get();
            $porAlumno = $cobros->groupBy('nombre_alumno');
            $formas    = json_decode($recibo->formas_pago, true) ?: [];

            $logoPath = public_path('projects/clase/logo.png');
            $src = imagecreatefrompng($logoPath);
            $thumb = imagecreatetruecolor(80, 80);
            imagealphablending($thumb, false); imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $src, 0, 0, 0, 0, 80, 80, imagesx($src), imagesy($src));
            ob_start(); imagepng($thumb, null, 6); $pngData = ob_get_clean();
            imagedestroy($src); imagedestroy($thumb);
            $logoB64 = 'data:image/png;base64,' . base64_encode($pngData);
            $pdfContent = Pdf::loadView('clase.recibo-pdf', [
                'recibo'    => $recibo,
                'porAlumno' => $porAlumno,
                'formas'    => $formas,
                'logoB64'   => $logoB64,
            ])->setPaper('a4', 'portrait')->output();

            Mail::to($recibo->pagador_email)
                ->send(new \App\Mail\ClaseReciboPdfMail($recibo, $pdfContent));
        }

        return response()->json(['ok' => true, 'numero_factura' => $numFactura,
                                 'enviado' => (bool)$recibo->pagador_email]);
    }

}
