<?php

namespace App\Http\Controllers\Mb;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AsambleaRepartoController extends Controller
{
    private function deudaSubquery()
    {
        return DB::raw("COALESCE((SELECT SUM(c.pendiente) FROM mb_cuotas c
                          WHERE c.id_viviendas = v.id AND c.estado NOT IN ('Anulada','Incobrable') AND c.pendiente > 0
                          AND c.tipo_cuota NOT IN ('G.dev.','Dudoso','Entrega a cuenta')), 0) as deuda");
    }

    private function propietarioSubquery()
    {
        return DB::raw("(SELECT p.nombre FROM mb_propietarios_historico ph
                          JOIN mb_propietarios p ON p.id = ph.id_propietarios
                          WHERE ph.id_viviendas = v.id AND ph.deleted = 0
                          ORDER BY (ph.fecha_hasta IS NULL) DESC, ph.fecha_desde DESC LIMIT 1) as propietario");
    }

    public function index(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        return view('mb.asamblea-reparto', [
            'project'  => $project,
            'asamblea' => $asamblea,
        ]);
    }

    public function backoffice(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        $q = trim((string) $request->input('q', ''));

        $totalViviendas = DB::table('mb_viviendas')->where('deleted', 0)->count();

        // Solo viviendas con hoja ya asignada: el listado empieza vacío y se va completando
        // conforme se registran entregas, con la más reciente primero.
        $viviendas = DB::table('mb_viviendas as v')
            ->join('mb_asambleas_hojas as ah', function ($j) use ($asamblea) {
                $j->on('ah.id_viviendas', '=', 'v.id')
                  ->where('ah.id_asambleas', $asamblea->id)
                  ->where('ah.deleted', 0);
            })
            ->where('v.deleted', 0)
            ->when($q !== '', fn ($qq) => $qq->where('v.nombre', 'ilike', '%' . $q . '%'))
            ->select([
                'v.id', 'v.nombre',
                'ah.numero_hoja', 'ah.fecha_entrega',
                $this->deudaSubquery(),
            ])
            ->orderByDesc('ah.fecha_entrega')
            ->get();

        $totalRepartidas = $viviendas->count();

        $viviendasOpciones = DB::table('mb_viviendas')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');

        return view('mb.asamblea-reparto-backoffice', [
            'project'           => $project,
            'asamblea'          => $asamblea,
            'viviendas'         => $viviendas,
            'viviendasOpciones' => $viviendasOpciones,
            'totalViviendas'    => $totalViviendas,
            'totalRepartidas'   => $totalRepartidas,
            'q'                 => $q,
            'breadcrumb'       => [
                ['label' => 'Asambleas', 'url' => route('listado', [$project->slug, 'asambleas'])],
                ['label' => 'Reparto de hojas', 'url' => ''],
            ],
        ]);
    }

    // Búsqueda exacta por código QR de la tarjeta de socio
    public function vivienda(Request $request, Project $project)
    {
        $asambleaId = (int) $request->id_asamblea;

        $query = DB::table('mb_viviendas as v')->where('v.deleted', 0);

        if ($request->filled('qr')) {
            // Una vivienda puede tener varias tarjetas: qr_codigo guarda los códigos
            // separados por coma (ej. "12345,67890"). Quitamos espacios para no depender
            // de si se escribió "12345, 67890" o "12345,67890" al rellenar el campo.
            $codigo = trim($request->qr);
            $query->whereRaw("? = ANY(string_to_array(REPLACE(v.qr_codigo, ' ', ''), ','))", [$codigo]);
        } elseif ($request->filled('id')) {
            $query->where('v.id', $request->id);
        } else {
            return response()->json(['error' => 'Falta el código o el id de la vivienda.'], 422);
        }

        $vivienda = $query->select(['v.id', 'v.nombre', $this->deudaSubquery(), $this->propietarioSubquery()])->first();

        if (!$vivienda) {
            return response()->json(['error' => 'Tarjeta no reconocida.'], 404);
        }

        $hojaActual = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $asambleaId)
            ->where('id_viviendas', $vivienda->id)
            ->where('deleted', 0)
            ->value('numero_hoja');

        return response()->json([
            'id'          => $vivienda->id,
            'nombre'      => $vivienda->nombre,
            'deuda'       => (float) $vivienda->deuda,
            'propietario' => $vivienda->propietario,
            'hoja_actual' => $hojaActual,
        ]);
    }

    // Búsqueda por nombre o código QR (fallback de teclado si falla el lector): lista de coincidencias
    public function buscarNombre(Request $request, Project $project)
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['resultados' => []]);
        }

        $resultados = DB::table('mb_viviendas')
            ->where('deleted', 0)
            ->where(function ($qq) use ($q) {
                $qq->where('nombre', 'ilike', '%' . $q . '%')
                   ->orWhere('qr_codigo', 'ilike', '%' . $q . '%');
            })
            ->orderBy('nombre')
            ->limit(40)
            ->get(['id', 'nombre']);

        return response()->json(['resultados' => $resultados]);
    }

    public function historico(Request $request, Project $project)
    {
        $asamblea = $request->filled('id_asamblea')
            ? DB::table('mb_asambleas')->where('id', $request->id_asamblea)->where('deleted', 0)->first()
            : DB::table('mb_asambleas')->where('deleted', 0)->orderByDesc('fecha')->first();

        abort_if(!$asamblea, 404, 'No hay ninguna asamblea creada todavía.');

        $asignaciones = DB::table('mb_asambleas_hojas as ah')
            ->join('mb_viviendas as v', 'v.id', '=', 'ah.id_viviendas')
            ->where('ah.id_asambleas', $asamblea->id)
            ->where('ah.deleted', 0)
            ->orderByDesc('ah.fecha_entrega')
            ->get(['v.id as id_viviendas', 'v.nombre', 'ah.numero_hoja', 'ah.fecha_entrega']);

        return view('mb.asamblea-reparto-historico', [
            'project'      => $project,
            'asamblea'     => $asamblea,
            'asignaciones' => $asignaciones,
        ]);
    }

    public function anularHoja(Request $request, Project $project)
    {
        if (empty($request->id_asamblea) || empty($request->id_viviendas)) {
            return response()->json(['error' => 'Faltan datos.'], 422);
        }

        $anuladas = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', (int) $request->id_asamblea)
            ->where('id_viviendas', (int) $request->id_viviendas)
            ->where('deleted', 0)
            ->update(['deleted' => 1, 'updateuser' => Auth::id(), 'updatedat' => now()]);

        if (!$anuladas) {
            return response()->json(['error' => 'No se encontró la asignación.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function registrarHoja(Request $request, Project $project)
    {
        if (empty($request->id_asamblea))  return response()->json(['error' => 'Falta la asamblea.'], 422);
        if (empty($request->id_viviendas)) return response()->json(['error' => 'Falta la vivienda.'], 422);
        if (!$request->filled('numero_hoja') || !is_numeric($request->numero_hoja)) {
            return response()->json(['error' => 'Número de hoja no válido.'], 422);
        }

        $idAsamblea  = (int) $request->id_asamblea;
        $idVivienda  = (int) $request->id_viviendas;
        $numeroHoja  = (int) $request->numero_hoja;
        $now         = now();
        $userId      = Auth::id();

        // Esa misma hoja no puede estar ya entregada a OTRA vivienda distinta. Se avisa y
        // solo se sigue adelante si el que guarda confirma explícitamente (forzar=1).
        if (!$request->boolean('forzar')) {
            $enUso = DB::table('mb_asambleas_hojas as ah')
                ->join('mb_viviendas as v', 'v.id', '=', 'ah.id_viviendas')
                ->where('ah.id_asambleas', $idAsamblea)
                ->where('ah.numero_hoja', $numeroHoja)
                ->where('ah.id_viviendas', '!=', $idVivienda)
                ->where('ah.deleted', 0)
                ->first(['v.nombre']);

            if ($enUso) {
                return response()->json([
                    'error_hoja_en_uso' => true,
                    'vivienda_en_uso'   => $enUso->nombre,
                ], 409);
            }
        }

        $existente = DB::table('mb_asambleas_hojas')
            ->where('id_asambleas', $idAsamblea)
            ->where('id_viviendas', $idVivienda)
            ->where('deleted', 0)
            ->first();

        if ($existente && (int) $existente->numero_hoja !== $numeroHoja) {
            DB::table('mb_asambleas_hojas_historico')->insert([
                'id_asambleas' => $idAsamblea,
                'id_viviendas' => $idVivienda,
                'numero_hoja'  => $existente->numero_hoja,
                'fecha_baja'   => $now,
                'createuser'   => $userId,
                'createdat'    => $now,
            ]);
        }

        DB::table('mb_asambleas_hojas')->updateOrInsert(
            ['id_asambleas' => $idAsamblea, 'id_viviendas' => $idVivienda],
            [
                'numero_hoja'   => $numeroHoja,
                'fecha_entrega' => $now,
                'deleted'       => 0,
                'updateuser'    => $userId,
                'updatedat'     => $now,
                'createuser'    => $existente?->createuser ?? $userId,
                'createdat'     => $existente?->createdat ?? $now,
            ]
        );

        return response()->json([
            'ok'          => true,
            'sobrescrita' => $existente && (int) $existente->numero_hoja !== $numeroHoja,
            'anterior'    => $existente?->numero_hoja,
        ]);
    }
}
