<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TareaController extends Controller
{
    private static array $TIPOS = ['limpieza', 'mantenimiento', 'piscina'];

    private function tableSuffix(string $tipo): string
    {
        return $tipo === 'piscina' ? 'piscinas' : $tipo;
    }

    private function isRestricted(Project $project): bool
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return false;
        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return false;
        $rolesTable    = $project->slug . '_roles';
        $usuariosTable = $project->slug . '_usuarios';
        $usuario = DB::table($usuariosTable)->where('id', $projectUserId)->first();
        if (!$usuario || !$usuario->id_rol) return false;
        $role = DB::table($rolesTable)->find($usuario->id_rol);
        return $role && !$role->todos_registros;
    }

    private function getTableProjectId(string $tableName): ?int
    {
        return DB::table('admin_project_tables')->where('name', $tableName)->value('id');
    }

    public function show(Request $request, Project $project, string $tipo, int $id)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);

        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canViewTable($project, $tableName), 403);

        $tabla = 'vm_' . $tableName;
        $tarea = DB::table($tabla)->where('id', $id)->firstOrFail();

        $propiedad = $tarea->id_propiedades
            ? DB::table('vm_propiedades')->where('id', $tarea->id_propiedades)->first()
            : null;

        // Fotos de la tarea
        $fotoCol = match($tipo) {
            'limpieza'      => 'id_tareas_limpieza',
            'mantenimiento' => 'id_tareas_mantenimiento',
            default         => 'id_tareas_piscinas',
        };
        $fotos = DB::table('vm_fotos')
            ->where($fotoCol, $id)
            ->where('deleted', 0)
            ->orderBy('createdat')
            ->get(['id', 'file_foto', 'nombre', 'createdat']);

        $controlUserIds = array_map('intval', json_decode($tarea->control_user ?? '[]', true) ?? []);

        $usuarios = DB::table('vm_usuarios')
            ->whereIn('id', $controlUserIds)
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // Opciones del campo Tipo (select) desde admin_table_fields
        $ptId      = $this->getTableProjectId($tableName);
        $tipoExtras = $ptId
            ? DB::table('admin_table_fields')
                ->where('project_table_id', $ptId)
                ->where('name', 'Tipo')
                ->value('extras')
            : null;
        $tipoOptions = [];
        if ($tipoExtras && str_starts_with((string) $tipoExtras, 'opt:')) {
            $tipoOptions = array_values(array_filter(array_map('trim', explode(',', substr($tipoExtras, 4)))));
        }

        // Opciones del campo Estado (select) desde admin_table_fields — mismo formato
        // "opt:A,B,C" que el resto de selects (Tipo incluido), separado por comas.
        $estadoExtras = $ptId
            ? DB::table('admin_table_fields')
                ->where('project_table_id', $ptId)
                ->where('name', 'estado')
                ->value('extras')
            : null;
        $estadoOptions = [];
        if ($estadoExtras && str_starts_with((string) $estadoExtras, 'opt:')) {
            $estadoOptions = array_values(array_filter(array_map('trim', explode(',', substr($estadoExtras, 4)))));
        }

        // Filtro de usuarios disponibles para control_user
        $cuExtras = $ptId
            ? DB::table('admin_table_fields')
                ->where('project_table_id', $ptId)
                ->where('name', 'control_user')
                ->value('extras')
            : null;

        $usuariosTable = $project->slug . '_usuarios';
        $allUsuarios = DB::table($usuariosTable)
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol']);

        if ($cuExtras && str_starts_with((string) $cuExtras, 'roles:')) {
            $allowedRolIds = array_map('intval', explode(',', substr($cuExtras, 6)));
            $allUsuarios   = $allUsuarios->filter(fn($u) => in_array((int) ($u->id_rol ?? 0), $allowedRolIds))->values();
        }

        if ($this->isRestricted($project)) {
            $ownId               = Auth::user()->projectUserId($project);
            $usuariosDisponibles = $allUsuarios->filter(fn($u) => (int) $u->id === (int) $ownId)->values();
        } else {
            $usuariosDisponibles = $allUsuarios;
        }

        $imputaciones = DB::table('vm_imputaciones as i')
            ->where('i.tipo', $tipo)
            ->where('i.id_tarea', $id)
            ->join('vm_usuarios as u', 'u.id', '=', 'i.id_usuario')
            ->orderBy('i.fecha_imputacion')
            ->orderBy('i.id')
            ->get(['i.id', 'i.id_usuario', 'u.nombre as usuario_nombre',
                   'i.duracion', 'i.fecha_imputacion', 'i.observacion']);

        $totalImputado    = $imputaciones->sum('duracion');
        $tiempoPorUsuario = [];
        foreach ($imputaciones->groupBy("id_usuario") as $uid => $rows) {
            $tiempoPorUsuario[(int)$uid] = (int)$rows->sum("duracion");
        }
        $maxPorUsuario = count($tiempoPorUsuario) ? max($tiempoPorUsuario) : 1;

        $usuariosConImputaciones = $imputaciones->pluck('id_usuario')->unique()->values()->toArray();

        $imputadosIds = $imputaciones->pluck('id_usuario')->unique()->values()->toArray();
        if (empty($imputadosIds)) {
            $badgeImp = 'sin_imputar';
        } elseif (!array_diff($controlUserIds, $imputadosIds)) {
            $badgeImp = 'todos';
        } else {
            $badgeImp = 'parcial';
        }

        $tablaLabel = ['limpieza' => 'tareas_limpieza', 'mantenimiento' => 'tareas_mantenimiento', 'piscina' => 'tareas_piscinas'];
        $canEdit    = auth()->user()->canEditTable($project, $tableName);

        return view('vm.tarea', compact(
            'project', 'tipo', 'tarea', 'propiedad', 'fotos',
            'usuarios', 'usuariosDisponibles', 'usuariosConImputaciones',
            'imputaciones', 'totalImputado',
            'badgeImp', 'tiempoPorUsuario', 'maxPorUsuario',
            'tablaLabel', 'canEdit', 'tipoOptions', 'estadoOptions'
        ));
    }

    public function update(Request $request, Project $project, string $tipo, int $id)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        $data = $request->validate([
            'nombre'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'Tipo'             => 'nullable|string|max:100',
            'estado'           => 'nullable|string|max:100',
            'fecha_planificada'=> 'nullable|date',
        ]);

        $data['updatedat'] = now();
        DB::table('vm_' . $tableName)->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }

    public function updateAsignados(Request $request, Project $project, string $tipo, int $id)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        $ids = array_map('intval', $request->input('ids', []));
        $ids = array_values(array_unique(array_filter($ids)));

        DB::table('vm_' . $tableName)->where('id', $id)->update([
            'control_user' => json_encode($ids),
            'updatedat'    => now(),
        ]);

        return response()->json(['ok' => true, 'ids' => $ids]);
    }

    public function storeImputacion(Request $request, Project $project, string $tipo, int $id)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        $request->validate([
            'id_usuario'       => 'required|integer|exists:vm_usuarios,id',
            'duracion'         => 'required|integer|min:1',
            'fecha_imputacion' => 'required|date',
            'observacion'      => 'nullable|string|max:500',
        ]);

        $impId = DB::table('vm_imputaciones')->insertGetId([
            'tipo'             => $tipo,
            'id_tarea'         => $id,
            'id_usuario'       => $request->id_usuario,
            'duracion'         => $request->duracion,
            'fecha_imputacion' => $request->fecha_imputacion,
            'observacion'      => $request->observacion ?: null,
            'estado'           => 'finalizada',
            'createdat'        => now(),
        ]);

        $usuario = DB::table('vm_usuarios')->where('id', $request->id_usuario)->value('nombre');

        return response()->json([
            'ok'  => true,
            'imp' => [
                'id'               => $impId,
                'id_usuario'       => (int) $request->id_usuario,
                'usuario_nombre'   => $usuario,
                'duracion'         => (int) $request->duracion,
                'fecha_imputacion' => $request->fecha_imputacion,
                'observacion'      => $request->observacion ?: null,
            ],
        ]);
    }

    public function updateImputacion(Request $request, Project $project, string $tipo, int $id, int $impId)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        $request->validate([
            'duracion'         => 'required|integer|min:1',
            'fecha_imputacion' => 'required|date',
            'observacion'      => 'nullable|string|max:500',
        ]);

        DB::table('vm_imputaciones')
            ->where('id', $impId)
            ->where('tipo', $tipo)
            ->where('id_tarea', $id)
            ->update([
                'duracion'         => $request->duracion,
                'fecha_imputacion' => $request->fecha_imputacion,
                'observacion'      => $request->observacion ?: null,
                'updatedat'        => now(),
            ]);

        $imp = DB::table('vm_imputaciones as i')
            ->join('vm_usuarios as u', 'u.id', '=', 'i.id_usuario')
            ->where('i.id', $impId)
            ->first(['i.id', 'i.id_usuario', 'u.nombre as usuario_nombre',
                     'i.duracion', 'i.fecha_imputacion', 'i.observacion']);

        return response()->json(['ok' => true, 'imp' => [
            'id'               => $imp->id,
            'id_usuario'       => $imp->id_usuario,
            'usuario_nombre'   => $imp->usuario_nombre,
            'duracion'         => (int) $imp->duracion,
            'fecha_imputacion' => $imp->fecha_imputacion,
            'observacion'      => $imp->observacion,
        ]]);
    }

    public function toggleBorrar(Request $request, Project $project, string $tipo, int $id): JsonResponse
    {
        abort_unless(auth()->user()->canEditTable($project, 'tareas_' . $this->tableSuffix($tipo)), 403);
        $tabla  = 'vm_tareas_' . $this->tableSuffix($tipo);
        $tarea  = DB::table($tabla)->where('id', $id)->firstOrFail();
        $newVal = $tarea->deleted ? 0 : 1;
        DB::table($tabla)->where('id', $id)->update(['deleted' => $newVal, 'updatedat' => now(), 'updateuser' => auth()->id()]);
        return response()->json(['deleted' => $newVal]);
    }

    public function toggleOcultar(Request $request, Project $project, string $tipo, int $id): JsonResponse
    {
        abort_unless(auth()->user()->canEditTable($project, 'tareas_' . $this->tableSuffix($tipo)), 403);
        $tabla  = 'vm_tareas_' . $this->tableSuffix($tipo);
        $tarea  = DB::table($tabla)->where('id', $id)->firstOrFail();
        $newVal = $tarea->hidden ? 0 : 1;
        DB::table($tabla)->where('id', $id)->update(['hidden' => $newVal, 'updatedat' => now(), 'updateuser' => auth()->id()]);
        return response()->json(['hidden' => $newVal]);
    }

    public function deleteImputacion(Request $request, Project $project, string $tipo, int $id, int $impId)
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        DB::table('vm_imputaciones')
            ->where('id', $impId)
            ->where('tipo', $tipo)
            ->where('id_tarea', $id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function storeFoto(Request $req, Project $project, string $tipo, int $id): JsonResponse
    {
        $req->validate(['foto' => 'required|image|max:10240']);
        $file = $req->file('foto');
        $ext  = strtolower($file->getClientOriginalExtension());
        $filename = str_replace('.', '', uniqid('f', true)) . '.' . $ext;
        $path = 'vm/fotos/' . $filename;
        $file->storeAs('vm/fotos', $filename, 'public');

        $fotoCol = match($tipo) {
            'limpieza'      => 'id_tareas_limpieza',
            'mantenimiento' => 'id_tareas_mantenimiento',
            default         => 'id_tareas_piscinas',
        };

        $fotoId = DB::table('vm_fotos')->insertGetId([
            $fotoCol      => $id,
            'file_foto'   => $path,
            'deleted'     => 0,
            'blocked'     => 0,
            'hidden'      => 0,
            'createuser'  => auth()->id(),
            'createdat'   => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $fotoId, 'url' => asset('storage/' . $path)]);
    }

    public function deleteFoto(Request $req, Project $project, string $tipo, int $id, int $fotoId): JsonResponse
    {
        DB::table('vm_fotos')
            ->where('id', $fotoId)
            ->update(['deleted' => 1, 'updateuser' => auth()->id(), 'updatedat' => now()]);

        return response()->json(['ok' => true]);
    }


    public function renameFoto(Request $req, Project $project, string $tipo, int $id, int $fotoId): JsonResponse
    {
        $req->validate(['nombre' => 'required|string|max:255']);
        DB::table('vm_fotos')
            ->where('id', $fotoId)
            ->update(['nombre' => $req->nombre, 'updateuser' => auth()->id(), 'updatedat' => now()]);
        return response()->json(['ok' => true]);
    }


    // ── LISTADO ────────────────────────────────────────────────────────────────

    public function index(Request $request, Project $project, string $tipo): \Illuminate\Contracts\View\View
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $suffix    = $this->tableSuffix($tipo);
        $tableName = 'tareas_' . $suffix;
        abort_unless(auth()->user()->canViewTable($project, $tableName), 403);

        $tabla   = 'vm_' . $tableName;
        $fotoCol = ['limpieza' => 'id_tareas_limpieza', 'mantenimiento' => 'id_tareas_mantenimiento', 'piscina' => 'id_tareas_piscinas'][$tipo];
        $canEdit = auth()->user()->canEditTable($project, $tableName);

        // Usuarios disponibles (filtrados por rol si procede)
        $ptId     = DB::table('admin_project_tables')->where('project_id', $project->id)->where('name', $tableName)->value('id');
        $cuExtras = $ptId ? DB::table('admin_table_fields')
            ->where('project_table_id', $ptId)->where('name', 'control_user')->value('extras') : null;

        // Opciones del campo Estado, para el filtro del listado (mismo formato "opt:A,B,C")
        $estadoExtras = $ptId ? DB::table('admin_table_fields')
            ->where('project_table_id', $ptId)->where('name', 'estado')->value('extras') : null;
        $estadoOptions = [];
        if ($estadoExtras && str_starts_with((string) $estadoExtras, 'opt:')) {
            $estadoOptions = array_values(array_filter(array_map('trim', explode(',', substr($estadoExtras, 4)))));
        }

        $usuariosTable = $project->slug . '_usuarios';
        $allUsuarios = DB::table($usuariosTable)
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'id_rol']);

        if ($cuExtras && str_starts_with((string)$cuExtras, 'roles:')) {
            $allowedRolIds = array_map('intval', explode(',', substr($cuExtras, 6)));
            $allUsuarios   = $allUsuarios->filter(fn($u) => in_array((int)($u->id_rol ?? 0), $allowedRolIds))->values();
        }
        $usuariosMap = $allUsuarios->keyBy('id');

        // Propiedades
        $propiedades = DB::table('vm_propiedades')
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->orderBy('nombre')->get(['id', 'nombre']);

        // Subqueries
        $impSub = DB::table('vm_imputaciones')
            ->where('tipo', $tipo)
            ->selectRaw("id_tarea, SUM(duracion) as total_min, COUNT(DISTINCT id_usuario) as imp_user_count, COALESCE(json_agg(DISTINCT id_usuario)::text, '[]') as imp_user_ids")
            ->groupBy('id_tarea');

        $fotoSub = DB::table('vm_fotos')
            ->whereNotNull($fotoCol)
            ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
            ->selectRaw($fotoCol . ' as tarea_id, COUNT(*) as foto_count')
            ->groupBy($fotoCol);

        // Query principal
        $query = DB::table($tabla . ' as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->leftJoinSub($impSub, 'imp', 'imp.id_tarea', '=', 't.id')
            ->leftJoinSub($fotoSub, 'f', 'f.tarea_id', '=', 't.id')
            ->select([
                't.id', 't.nombre', 't.fecha_planificada', 't.control_user', 't.estado',
                't.deleted', 't.hidden', 't.blocked',
                'p.nombre as propiedad_nombre',
                DB::raw('COALESCE(imp.total_min, 0) as total_min'),
                DB::raw('COALESCE(imp.imp_user_count, 0) as imp_user_count'),
                DB::raw("COALESCE(imp.imp_user_ids, '[]') as imp_user_ids"),
                DB::raw('COALESCE(f.foto_count, 0) as foto_count'),
                DB::raw('COALESCE(json_array_length(t.control_user::json), 0) as cu_count'),
            ]);

        // Visibilidad
        if ($request->boolean('borrados')) {
            $query->where('t.deleted', 1);
        } else {
            $query->where('t.deleted', 0);
        }
        if ($request->boolean('ocultos')) {
            $query->where('t.hidden', 1);
        } else {
            $query->where(fn($q) => $q->whereNull('t.hidden')->orWhere('t.hidden', 0));
        }

        // Búsqueda
        if ($q = $request->input('q')) {
            $query->where('t.nombre', 'ilike', '%' . $q . '%');
        }

        // Filtros de campo
        if ($prop = $request->input('f_propiedad')) {
            $query->where('t.id_propiedades', $prop);
        }
        if ($fd = $request->input('f_fecha_desde')) {
            $query->where('t.fecha_planificada', '>=', $fd);
        }
        if ($fh = $request->input('f_fecha_hasta')) {
            $query->where('t.fecha_planificada', '<=', $fh);
        }
        if ($resp = $request->input('f_responsable')) {
            $query->whereRaw('t.control_user::jsonb @> ?::jsonb', [json_encode([(int)$resp])]);
        }
        if ($estadoFiltro = $request->input('f_estado')) {
            $query->where('t.estado', $estadoFiltro);
        }
        if ($ffd = $request->input('f_fecha_fin_desde')) {
            $query->where('t.fecha_finalizacion', '>=', $ffd);
        }
        if ($ffh = $request->input('f_fecha_fin_hasta')) {
            $query->where('t.fecha_finalizacion', '<=', $ffh);
        }

        // Filtro por stat activo
        $hoy  = now()->toDateString();
        $stat = $request->input('stat');
        $noCerrada = fn($q) => $q->whereNull('t.estado')->orWhereNotIn('t.estado', ['Completada', 'Cancelada', 'Descartada']);
        if ($stat === 'vigentes') {
            $query->where($noCerrada);
        } elseif ($stat === 'vencidas') {
            $query->where('t.estado', 'Vencida');
        } elseif ($stat === 'no_imputadas') {
            $query->where('t.estado', 'Completada')->whereRaw('COALESCE(imp.total_min, 0) = 0');
        } elseif ($stat === 'propias' && Schema::hasColumn($tabla, 'breezeway_task_id')) {
            $query->whereNull('t.breezeway_task_id');
        }

        // Ordenable por cabecera, igual que el listado generico. "Resp." no es ordenable
        // porque control_user es un array JSON, no un valor simple comparable.
        $columnasOrdenables = [
            'fecha_planificada' => 't.fecha_planificada',
            'propiedad_nombre'  => 'propiedad_nombre',
            'nombre'            => 't.nombre',
            'total_min'         => 'total_min',
        ];
        $sortField = $request->input('sort');
        $sortDir   = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        if ($sortField && isset($columnasOrdenables[$sortField])) {
            $query->orderBy($columnasOrdenables[$sortField], $sortDir)->orderBy('t.id', 'desc');
        } else {
            $sortField = null;
            $query->orderByRaw('t.fecha_planificada ASC NULLS LAST, t.id DESC');
        }

        $tareas = $query->paginate(25)->withQueryString();

        // Stats (base limpia: sin borrados, sin ocultos, sin filtro de stat)
        // "Vigentes"/"vencidas" ya excluyen Completada/Cancelada porque esas se ocultan solas
        // en cuanto tienen imputacion (o siempre, si Cancelada) durante el sync de Breezeway.
        $statsBase = fn() => DB::table($tabla . ' as t')
            ->where('t.deleted', 0)
            ->where(fn($q) => $q->whereNull('t.hidden')->orWhere('t.hidden', 0))
            ->leftJoinSub(clone $impSub, 'imp', 'imp.id_tarea', '=', 't.id');

        $vigentes    = $statsBase()->where($noCerrada)->count();
        $vencidas    = $statsBase()->where('t.estado', 'Vencida')->count();
        $noImputadas = $statsBase()->where('t.estado', 'Completada')->whereRaw('COALESCE(imp.total_min, 0) = 0')->count();
        // Piscinas no tiene breezeway_task_id (Breezeway nunca sincroniza ese departamento):
        // todas sus tareas son "propias" por definicion, sin necesidad de filtrar la columna.
        $propias = Schema::hasColumn($tabla, 'breezeway_task_id')
            ? $statsBase()->whereNull('t.breezeway_task_id')->count()
            : $statsBase()->count();

        $colores = [
            'limpieza'      => ['bg' => '#E6F1FB', 'bd' => '#378ADD', 'tx' => '#0C447C'],
            'mantenimiento' => ['bg' => '#FAEEDA', 'bd' => '#EF9F27', 'tx' => '#633806'],
            'piscina'       => ['bg' => '#E1F5EE', 'bd' => '#1D9E75', 'tx' => '#085041'],
        ];
        $c         = $colores[$tipo];
        $tipoLabel = ['limpieza' => 'Limpieza', 'mantenimiento' => 'Mantenimiento', 'piscina' => 'Piscinas'][$tipo];
        $tipoIcon  = ['limpieza' => 'ti-sparkles', 'mantenimiento' => 'ti-tool', 'piscina' => 'ti-droplet'][$tipo];

        return view('vm.tareas_list', compact(
            'project', 'tipo', 'tableName', 'tareas', 'propias',
            'allUsuarios', 'usuariosMap', 'propiedades', 'estadoOptions',
            'vigentes', 'vencidas', 'noImputadas',
            'canEdit', 'c', 'tipoLabel', 'tipoIcon', 'stat',
            'sortField', 'sortDir'
        ));
    }

    public function store(Request $request, Project $project, string $tipo): \Illuminate\Http\JsonResponse
    {
        abort_unless(in_array($tipo, self::$TIPOS), 404);
        $tableName = 'tareas_' . $this->tableSuffix($tipo);
        abort_unless(auth()->user()->canEditTable($project, $tableName), 403);

        $data = $request->validate([
            'nombre'            => 'required|string|max:255',
            'id_propiedades'    => 'nullable|integer',
            'fecha_planificada' => 'nullable|date',
            'control_user'      => 'nullable|array',
            'control_user.*'    => 'integer',
        ]);

        $tabla  = 'vm_' . $tableName;
        $userId = auth()->user()->projectUserId($project);
        $now    = now();

        $id = DB::table($tabla)->insertGetId([
            'nombre'            => $data['nombre'],
            'id_propiedades'    => $data['id_propiedades'] ?? null,
            'fecha_planificada' => $data['fecha_planificada'] ?? null,
            'control_user'      => json_encode($data['control_user'] ?? []),
            'deleted'           => 0,
            'hidden'            => 0,
            'blocked'           => 0,
            'createuser'        => $userId,
            'updateuser'        => $userId,
            'createdat'         => $now,
            'updatedat'         => $now,
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

}