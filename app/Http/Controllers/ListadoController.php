<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\RoleHierarchy;

class ListadoController extends Controller
{
    // Movimientos informativos en mb_cuotas: se insertan/visualizan, pero no cuentan como
    // deuda, emisión ni cobro en ningún cálculo (confirmado por el cliente 2026-08-08).
    private const TIPOS_CUOTA_INFORMATIVOS = ['G.dev.', 'Dudoso', 'Entrega a cuenta'];

    public function index(Request $request, Project $project, string $table)
    {
        $projectTable = $project->tables()
            ->where('name', $table)
            ->with(['listFields', 'fields'])
            ->firstOrFail();

        $fullTable    = $projectTable->getFullTableName();
        $tieneDeleted = Schema::hasColumn($fullTable, 'deleted');
        $tieneHidden  = Schema::hasColumn($fullTable, 'hidden');
        $sortField    = $request->input('sort');
        $sortDir      = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = $this->filteredSortedQuery($request, $project, $projectTable);

        $registros = $query->paginate(50)->withQueryString();

        // Opciones para campos FK (type='id'/'desplegable')
        $visibleIds    = $this->resolveVisibleUserIds($project);
        $usuariosTable = $project->slug . '_usuarios';

        $fkOptions = [];
        foreach ($projectTable->listFields as $field) {
            $fullRef = $field->getRefFullTable($project->slug);
            if (!$fullRef) continue;
            $fkQuery = DB::table($fullRef)->orderBy('nombre');
            if (Schema::hasColumn($fullRef, 'deleted')) {
                $fkQuery->where('deleted', 0);
            }
            if (Schema::hasColumn($fullRef, 'hidden')) {
                $fkQuery->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0));
            }
            if ($visibleIds !== null && $field->name === 'control_user' && $field->type === 'desplegable' && $fullRef === $usuariosTable) {
                $fkQuery->whereIn('id', $visibleIds);
            }
            $fkOptions[$field->name] = $fkQuery->pluck('nombre', 'id')->toArray();
        }

        // Comprueba si el rol del usuario permite editar esta tabla
        $canEdit = $this->userCanEdit($project, $projectTable->name);

        // Modo tabla editable: solo si todos los campos required están en el listado
        $allFields     = $projectTable->fields;
        $listFieldNames = $projectTable->listFields->pluck('name');
        $requiredHidden = $allFields->where('required', true)
                                    ->filter(fn($f) => !$listFieldNames->contains($f->name));

        $modoTabla         = $request->input('modo') === 'tabla' && $requiredHidden->isEmpty();
        $tablaNoDisponible = $request->input('modo') === 'tabla' && $requiredHidden->isNotEmpty();
        $campoFile         = $projectTable->fields->firstWhere('type', 'file');
        $modoGaleria       = $request->input('modo') === 'galeria' && $campoFile !== null;
        // Para galería: mapa campo → tabla ref para construir URLs a fichas relacionadas
        $fkRefTablas       = $projectTable->listFields
            ->filter(fn($f) => in_array($f->type, ['id', 'desplegable']) && $f->getRefTable())
            ->mapWithKeys(fn($f) => [$f->name => $f->getRefTable()])
            ->toArray();

        // Mapa id→nombre de usuarios del proyecto para campos multiusuario (display)
        $usuariosMap   = [];
        $usuariosTable = $project->slug . '_usuarios';
        $allUsuarios   = collect([]);
        if (Schema::hasTable($usuariosTable)) {
            $allUsuarios = DB::table($usuariosTable)
                ->where(fn($q) => $q->whereNull('deleted')->orWhere('deleted', 0))
                ->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))
                ->get(['id', 'nombre', 'id_rol']);
            $allUsuarios->each(function ($u) use (&$usuariosMap) {
                $usuariosMap[(int) $u->id]    = $u->nombre;
                $usuariosMap[(string) $u->id] = $u->nombre;
            });
        }

        // Filtrar por roles si algún campo multiusuario tiene extras "roles:X,Y"
        $rolesExtras = $projectTable->fields
            ->where('type', 'multiusuario')
            ->map(fn($f) => $f->extras)
            ->filter(fn($e) => str_starts_with((string) $e, 'roles:'))
            ->first();
        if ($rolesExtras) {
            $allowedRolIds = array_map('intval', explode(',', substr($rolesExtras, 6)));
            $allUsuarios   = $allUsuarios->filter(fn($u) => in_array((int) ($u->id_rol ?? 0), $allowedRolIds));
        }

        // Lista filtrada para el formulario: solo el propio usuario si el rol está restringido
        $projectUsuarios = $allUsuarios->map(fn($u) => ['id' => $u->id, 'label' => $u->nombre ?? "#{$u->id}"])->values()->toArray();
        if ($visibleIds !== null) {
            $projectUsuarios = array_values(array_filter($projectUsuarios, fn($u) => in_array((string) $u['id'], $visibleIds)));
        }

        // Stats específicas por tabla
        $tablStats = null;

        if ($fullTable === 'vm_reservas') {
            $hoy      = now()->toDateString();
            $manana   = now()->addDay()->toDateString();
            $pasado   = now()->addDays(2)->toDateString();
            $notCancelled = fn($q) => $q->whereNotIn('booking_status', ['cancelled', 'canceled']);
            $tablStats = [
                'en_curso' => DB::table($fullTable)->where($notCancelled)->where('check_in_date', '<=', $hoy)->where('check_out_date', '>=', $hoy)->count(),
                'manana'   => DB::table($fullTable)->where($notCancelled)->where('check_in_date', $manana)->count(),
                'pasado'   => DB::table($fullTable)->where($notCancelled)->where('check_in_date', $pasado)->count(),
            ];
        }

        $ejercicioCuotasSel = null;
        $ejercicioActualDefault = null;
        $sumSuperficieViviendas = null;
        $countViviendasActivas = null;
        $aDemandarTooltip = null;
        if ($fullTable === 'mb_cuotas') {
            $ejercicioCuotasSel = $request->input('ejercicio') ?: $this->ejercicioActualCuotas();
            $tablStats = $this->cuotasStats($ejercicioCuotasSel);
            $ejercicioActualDefault = $this->ejercicioActualCuotas();
            $sumSuperficieViviendas = (float) DB::table('mb_viviendas')->where('deleted', 0)->sum(DB::raw('COALESCE(superf_calculada_cuota,0)'));
            $countViviendasActivas  = DB::table('mb_viviendas')->where('deleted', 0)->count();
            $aDemandarTooltip       = $this->aDemandarPorAnio($ejercicioActualDefault);
        }

        $nfClientesTelefono = null;
        if ($fullTable === 'nf_pagos') {
            $nfClientesTelefono = DB::table('nf_clientes')->pluck('telefono', 'id')->toArray();
        }

        $breezewayPendientesHeader = null;
        if ($fullTable === 'vm_usuarios') {
            $breezewayPendientesHeader = DB::table('vm_breezeway_pendientes')
                ->where('deleted', 0)
                ->orderByDesc('fecha_alta')
                ->get(['nombre', 'email']);
        }

        $icneaSync = null;
        if ($fullTable === 'vm_propiedades') {
            $ayer = now()->subDay()->toDateString();
            $hoy  = now()->toDateString();
            $baseStats = fn() => DB::table($fullTable)->whereNotNull('icnea_code')->where('deleted', 0)->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0));
            $tablStats = [
                'pte_info'          => $baseStats()->where(fn($q) => $q->whereNull('fecha_inicio')->orWhereNull('tipo_renta'))->count(),
                'posibles_bajas'    => $baseStats()->where(fn($q) => $q->whereNull('icnea_updatedat')->orWhereDate('icnea_updatedat', '<', $ayer))->count(),
                'revisar_borrado'   => DB::table($fullTable)->whereNotNull('icnea_code')->where('deleted', 1)->whereDate('icnea_updatedat', $hoy)->count(),
                'ocultas'           => DB::table($fullTable)->where('deleted', 0)->where('hidden', 1)->count(),
                'sin_breezeway'     => $baseStats()->whereNull('breezeway_home_id')->count(),
                'codigo_compartido' => count($this->propiedadesConCodigoCompartido()),
            ];
            $icneaSync = Cache::get('icnea_sync_result');
        }

        return view('listado', [
            'canEdit'           => $canEdit,
            'project'           => $project,
            'projectTable'      => $projectTable,
            'campos'            => $projectTable->nombre_ocultar_listado && $projectTable->nombre_formula
                                    ? $projectTable->listFields->where('name', '!=', 'nombre')->values()
                                    : $projectTable->listFields,
            'registros'         => $registros,
            'fkOptions'         => $fkOptions,
            'usuariosMap'       => $usuariosMap,
            'projectUsuarios'   => $projectUsuarios,
            'modoTabla'         => $modoTabla,
            'tablaNoDisponible' => $tablaNoDisponible,
            'requiredHidden'    => $requiredHidden,
            'tieneDeleted'      => $tieneDeleted,
            'tieneHidden'       => $tieneHidden,
            'modoGaleria'       => $modoGaleria,
            'campoFile'         => $campoFile,
            'fkRefTablas'       => $fkRefTablas,
            'camposFiltrablesGaleria' => $projectTable->listFields->filter(fn($f) => in_array($f->type, ['id', 'desplegable']) && $f->getRefTable()),
            'sortField'         => $sortField ?? null,
            'sortDir'           => $sortDir,
            'tablStats'         => $tablStats,
            'icneaSync'         => $icneaSync,
            'breezewayPendientesHeader' => $breezewayPendientesHeader,
            'ejercicioCuotasSel' => $ejercicioCuotasSel,
            'ejercicioActualDefault' => $ejercicioActualDefault,
            'sumSuperficieViviendas' => $sumSuperficieViviendas,
            'countViviendasActivas'  => $countViviendasActivas,
            'aDemandarTooltip'       => $aDemandarTooltip,
            'nfClientesTelefono' => $nfClientesTelefono,
            'breadcrumb'        => [
                ['label' => $projectTable->label, 'url' => ''],
            ],
        ]);
    }

    // Devuelve el listado de IDs que cumplen el filtro/búsqueda activos (sin paginar),
    // para el botón "Copiar IDs" -- usa exactamente los mismos filtros que index().
    public function ids(Request $request, Project $project, string $table)
    {
        $projectTable = $project->tables()
            ->where('name', $table)
            ->with(['listFields'])
            ->firstOrFail();

        abort_unless(Auth::user()?->canViewTable($project, $table), 403);

        $ids = $this->filteredSortedQuery($request, $project, $projectTable)->pluck('id');

        return response()->json(['ids' => $ids, 'count' => $ids->count()]);
    }

    // Construye la query de listado con todos los filtros (stat, borrados/ocultos, búsqueda,
    // filtros por campo, control_user) y la ordenación aplicados, sin paginar.
    // Compartido entre index() (paginado) e ids() (todos los IDs, para "Copiar IDs").
    public function filteredSortedQuery(Request $request, Project $project, ProjectTable $projectTable)
    {
        $fullTable    = $projectTable->getFullTableName();
        $tieneDeleted = Schema::hasColumn($fullTable, 'deleted');
        $tieneHidden  = Schema::hasColumn($fullTable, 'hidden');

        $query = DB::table($fullTable);

        // Filtro stat
        $stat = $request->input('stat');
        if ($fullTable === 'vm_reservas' && $stat) {
            $hoy    = now()->toDateString();
            $manana = now()->addDay()->toDateString();
            $pasado = now()->addDays(2)->toDateString();
            $query->whereNotIn('booking_status', ['cancelled', 'canceled']);
            match ($stat) {
                'en_curso' => $query->where('check_in_date', '<=', $hoy)->where('check_out_date', '>=', $hoy),
                'manana'   => $query->where('check_in_date', $manana),
                'pasado'   => $query->where('check_in_date', $pasado),
                default    => null,
            };
        } elseif ($fullTable === 'vm_propiedades' && $stat) {
            $ayer = now()->subDay()->toDateString();
            $hoy  = now()->toDateString();
            if ($stat !== 'codigo_compartido') {
                $query->whereNotNull('icnea_code');
            }
            match ($stat) {
                'pte_info'          => $query->where('deleted', 0)->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))->where(fn($q) => $q->whereNull('fecha_inicio')->orWhereNull('tipo_renta')),
                'posibles_bajas'    => $query->where('deleted', 0)->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))->where(fn($q) => $q->whereNull('icnea_updatedat')->orWhereDate('icnea_updatedat', '<', $ayer)),
                'revisar_borrado'   => $query->where('deleted', 1)->whereDate('icnea_updatedat', $hoy),
                'ocultas'           => $query->where('deleted', 0)->where('hidden', 1),
                'sin_breezeway'     => $query->where('deleted', 0)->where(fn($q) => $q->whereNull('hidden')->orWhere('hidden', 0))->whereNull('breezeway_home_id'),
                'codigo_compartido' => $query->whereIn('id', $this->propiedadesConCodigoCompartido()),
                default             => null,
            };
        } elseif ($fullTable === 'mb_cuotas' && $stat) {
            $ejercicioSel = $request->input('ejercicio') ?: $this->ejercicioActualCuotas();
            $rangoSel     = $this->ejercicioRango($ejercicioSel);
            $anioSel      = (int) substr($ejercicioSel, 0, 4);
            $query->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS);
            match ($stat) {
                'emitido_anio'       => $query->where('ejercicio', $ejercicioSel)->where('estado', '!=', 'Anulada'),
                'pendiente_anio'     => $query->where('ejercicio', $ejercicioSel)->where('estado', '!=', 'Anulada')->where('pendiente', '>', 0),
                'cobrado_ejercicio'  => $query->where('ejercicio', $ejercicioSel)->where('estado', 'Pagada')->whereBetween('fecha_pago', $rangoSel),
                'cobrado_anteriores' => $query->whereRaw('CAST(LEFT(ejercicio, 4) AS INTEGER) < ?', [$anioSel])->where('estado', 'Pagada')->whereBetween('fecha_pago', $rangoSel),
                'pendiente_total'    => $query->where('estado', '!=', 'Anulada')->where('pendiente', '>', 0),
                'demandado_total'    => $query->where('estado', 'Demandada'),
                'a_demandar'         => $query->whereIn('id_viviendas', $this->viviendasProximasAPrescribir())->where('estado', 'Pendiente')->where('pendiente', '>', 0),
                default              => null,
            };
        } else {
            // Borrados / archivados (solo si las columnas existen)
            if ($tieneDeleted) {
                if ($request->boolean('borrados')) {
                    $query->where('deleted', 1);
                } else {
                    $query->where('deleted', 0);
                }
            }
            if ($tieneHidden && !$request->boolean('borrados')) {
                if ($request->boolean('ocultos')) {
                    $query->where('hidden', 1);
                } else {
                    $query->where('hidden', 0);
                }
            }
        }

        // Búsqueda global por texto (ILIKE en PostgreSQL, LIKE en SQLite)
        if ($request->filled('q')) {
            $q      = $request->q;
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($sub) use ($q, $projectTable, $likeOp) {
                foreach ($projectTable->listFields as $field) {
                    if (in_array($field->type, ['string', 'text', 'email', 'telefono'])) {
                        $sub->orWhere($field->name, $likeOp, "%{$q}%");
                    }
                }
            });
        }

        // Filtros por campo
        foreach ($projectTable->listFields as $field) {
            $param = 'f_' . $field->name;

            if ($field->type === 'fecha') {
                if ($request->filled($param . '_desde')) {
                    $query->where($field->name, '>=', $request->input($param . '_desde'));
                }
                if ($request->filled($param . '_hasta')) {
                    $query->where($field->name, '<=', $request->input($param . '_hasta'));
                }
            } elseif (in_array($field->type, ['select', 'tinyint', 'smallint', 'id', 'desplegable'])) {
                if ($request->filled($param)) {
                    $query->where($field->name, $request->input($param));
                }
            }
        }

        // Filtro control_user: si el usuario no tiene acceso a todos los registros
        $this->applyControlUserFilter($query, $project, $fullTable);

        // Ordenación por columna
        $sortField = $request->input('sort');
        $sortDir   = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = $projectTable->listFields->pluck('name')->toArray();
        if ($sortField && in_array($sortField, $sortableFields)) {
            // NULLS LAST siempre, en ambas direcciones: si no, Postgres pone los NULL
            // primero al ordenar descendente (comportamiento por defecto), y con columnas
            // mayoritariamente vacías (p.ej. un booleano poco usado) el resultado parece
            // "no hacer nada" porque la primera página sigue llena de valores en blanco.
            $sortFieldDef = $projectTable->listFields->firstWhere('name', $sortField);
            $refTable = $sortFieldDef && $sortFieldDef->isForeignKey()
                ? $sortFieldDef->getRefFullTable($project->slug)
                : '';

            if ($refTable !== '') {
                // Campo desplegable (FK): ordenar por el nombre visible de la tabla referenciada,
                // no por el id almacenado, mediante subconsulta correlacionada.
                $query->orderByRaw(
                    '(SELECT "nombre" FROM "' . $refTable . '" WHERE "' . $refTable . '"."id" = "' . $fullTable . '"."' . $sortField . '") ' . $sortDir . ' NULLS LAST'
                );
            } else {
                $query->orderByRaw('"' . $sortField . '" ' . $sortDir . ' NULLS LAST');
            }
        } else {
            $query->orderByDesc('id');
        }

        return $query;
    }

    // Aplica filtro control_user segun visibilidad del rol (personales/supervisados/todos)
    private function applyControlUserFilter($query, Project $project, string $fullTable): void
    {
        if (!Schema::hasColumn($fullTable, 'control_user')) return;

        $visibleIds = $this->resolveVisibleUserIds($project);
        if ($visibleIds === null) return;

        $fieldType = $this->getControlUserFieldType($project, $fullTable);

        if ($fieldType === 'desplegable') {
            $query->where(function ($q) use ($visibleIds) {
                $q->whereNull('control_user')
                  ->orWhereIn('control_user', $visibleIds);
            });
        } else {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $cast    = $isPgsql ? '::text' : '';
            $query->where(function ($q) use ($visibleIds, $cast) {
                $q->whereNull('control_user')
                  ->orWhereRaw("control_user{$cast} = '[]'");
                foreach ($visibleIds as $vid) {
                    $q->orWhereRaw("control_user{$cast} LIKE ?", ["%\"{$vid}\"%"]);
                }
            });
        }
    }

    // Ids de usuario visibles para el usuario actual: null = sin restriccion (ve todos),
    // array = propio id (+ equipo supervisado si el rol es 'supervisados')
    private function resolveVisibleUserIds(Project $project): ?array
    {
        $user = Auth::user();
        if (!$user || $user->isProjectAdmin($project)) return null;

        $projectUserId = $user->projectUserId($project);
        if (!$projectUserId) return null;

        $role = $this->getUserProjectRole($project, $projectUserId);
        if (!$role || ($role->todos_registros ?? null) === 'todos') return null;

        if (($role->todos_registros ?? null) === 'supervisados') {
            return RoleHierarchy::visibleUserIds(
                $project->slug . '_roles',
                $project->slug . '_usuarios',
                (int) $projectUserId,
                (int) $role->id
            );
        }

        return [(string) $projectUserId];
    }

    private function getControlUserFieldType(Project $project, string $fullTable): string
    {
        $tableName = substr($fullTable, strlen($project->slug . '_'));
        $field = $project->tables()
            ->where('name', $tableName)
            ->first()
            ?->fields()
            ->where('name', 'control_user')
            ->value('type');
        return $field ?? 'multiusuario';
    }

    // Comprueba si el usuario puede editar registros de esta tabla según su rol
    private function userCanEdit(Project $project, string $tableName): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        return $user->canEditTable($project, $tableName);
    }

    private function userCanSeeAllRecords(Project $project): bool
    {
        return $this->resolveVisibleUserIds($project) === null;
    }

    // Carga el registro de rol del usuario en {slug}_roles
    private function getUserProjectRole(Project $project, int $projectUserId): ?object
    {
        $usuariosTable = $project->slug . '_usuarios';
        $rolesTable    = $project->slug . '_roles';

        if (!Schema::hasTable($usuariosTable) || !Schema::hasTable($rolesTable)) return null;

        $usuario = DB::table($usuariosTable)->find($projectUserId);
        if (!$usuario || !$usuario->id_rol) return null;

        return DB::table($rolesTable)->find($usuario->id_rol);
    }

    // IDs de propiedades activas cuyo a3_code_historial o icnea_code_historial
    // comparte algun codigo con el de OTRA propiedad (posible duplicado a revisar).
    private function propiedadesConCodigoCompartido(): array
    {
        $rows = DB::select("
            WITH a3 AS (
                SELECT id, trim(c) AS codigo
                FROM vm_propiedades, unnest(string_to_array(a3_code_historial, ',')) AS c
                WHERE a3_code_historial IS NOT NULL AND trim(c) <> ''
            ), icnea AS (
                SELECT id, trim(c) AS codigo
                FROM vm_propiedades, unnest(string_to_array(icnea_code_historial, ',')) AS c
                WHERE icnea_code_historial IS NOT NULL AND trim(c) <> ''
            )
            SELECT DISTINCT p.id
            FROM vm_propiedades p
            WHERE p.deleted = 0 AND (
                EXISTS (SELECT 1 FROM a3 a JOIN a3 b ON a.codigo = b.codigo AND a.id <> b.id WHERE a.id = p.id)
                OR EXISTS (SELECT 1 FROM icnea a JOIN icnea b ON a.codigo = b.codigo AND a.id <> b.id WHERE a.id = p.id)
            )
        ");
        return array_column($rows, 'id');
    }

    private function ejercicioActualCuotas(): string
    {
        $hoy  = now();
        $anio = (int) $hoy->format('n') >= 7 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
        return $anio . '-' . ($anio + 1);
    }

    // Viviendas con alguna cuota pendiente (no demandada/anulada) cuya antigüedad desde fecha_emision
    // cumple 5 años dentro del PRÓXIMO ejercicio -- último momento para reclamarla antes de prescribir.
    private function viviendasProximasAPrescribir(): array
    {
        $anioActual = (int) substr($this->ejercicioActualCuotas(), 0, 4);
        $nextStart  = ($anioActual + 1) . '-07-01';
        $nextEnd    = ($anioActual + 2) . '-06-30';

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

    // Rango de fechas [desde, hasta] del ejercicio (formato "YYYY-YYYY", julio a junio).
    private function ejercicioRango(string $ejercicio): array
    {
        [$y1, $y2] = explode('-', $ejercicio);
        return [$y1 . '-07-01', $y2 . '-06-30'];
    }

    // Ejercicio (formato "YYYY-YYYY") en el que cae una fecha dada.
    private function ejercicioDeFecha($fecha): string
    {
        $d    = \Carbon\Carbon::parse($fecha);
        $anio = $d->month >= 7 ? $d->year : $d->year - 1;
        return $anio . '-' . ($anio + 1);
    }

    // Agrupa por "año de demanda" (ejercicio en que la cuota Pendiente más antigua de cada
    // vivienda cumple 5 años de antigüedad) las viviendas con deuda pendiente, mostrando para
    // cada una su deuda cobrable (fecha_emision dentro de los últimos 5 años desde el inicio
    // del ejercicio actual) y, si la hay, la deuda ya perdida (prescrita, fuera de esos 5 años).
    // Solo se incluyen años de demanda hasta el próximo ejercicio (pasados + actual + próximo).
    private function aDemandarPorAnio(string $ejercicioActual): string
    {
        $anioActual        = (int) substr($ejercicioActual, 0, 4);
        [$inicioActual]    = $this->ejercicioRango($ejercicioActual);
        $cutoff            = \Carbon\Carbon::parse($inicioActual)->subYears(5)->toDateString();

        $rows = DB::table('mb_cuotas as c')
            ->join('mb_viviendas as v', 'v.id', '=', 'c.id_viviendas')
            ->where('c.estado', 'Pendiente')
            ->where('c.pendiente', '>', 0)
            ->whereNotIn('c.tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS)
            ->where('v.deleted', 0)
            ->get(['c.id_viviendas', 'v.nombre', 'c.fecha_emision', 'c.pendiente']);

        $grupos = [];
        foreach ($rows->groupBy('id_viviendas') as $cuotas) {
            $nombre = $cuotas->first()->nombre;
            $oldest = $cuotas->min('fecha_emision');

            $ejercicioDemanda = $this->ejercicioDeFecha(\Carbon\Carbon::parse($oldest)->addYears(5));
            $anioDemanda      = (int) substr($ejercicioDemanda, 0, 4);
            if ($anioDemanda > $anioActual + 1) {
                continue; // aún no urgente
            }

            $recuperable = (float) $cuotas->filter(fn ($c) => $c->fecha_emision >= $cutoff)->sum('pendiente');
            $perdido     = (float) $cuotas->filter(fn ($c) => $c->fecha_emision < $cutoff)->sum('pendiente');

            $grupos[$ejercicioDemanda][] = ['nombre' => $nombre, 'recuperable' => $recuperable, 'perdido' => $perdido];
        }

        if (empty($grupos)) {
            return 'No hay viviendas próximas a demandar.';
        }

        ksort($grupos);

        $lineas = [];
        foreach ($grupos as $ejercicio => $viviendas) {
            usort($viviendas, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));
            $partes = array_map(function ($v) {
                $txt = number_format($v['recuperable'], 2, ',', '.') . '€';
                if ($v['perdido'] > 0) {
                    $txt .= ', perdido: ' . number_format($v['perdido'], 2, ',', '.') . '€';
                }
                return $v['nombre'] . ' (' . $txt . ')';
            }, $viviendas);
            $lineas[] = $ejercicio . ': ' . implode(', ', $partes);
        }

        return implode("\n", $lineas);
    }

    private function cuotasStats(string $ejercicioActual): array
    {
        $sinInformativos = fn() => DB::table('mb_cuotas')->whereNotIn('tipo_cuota', self::TIPOS_CUOTA_INFORMATIVOS);

        // Importe total emitido (suma de importe, sin filtrar por estado de cobro) para el ejercicio.
        $emitidoAnioSum = (float) $sinInformativos()->where('ejercicio', $ejercicioActual)->where('estado', '!=', 'Anulada')->sum('importe');

        $pendienteAnioSum = (float) $sinInformativos()->where('ejercicio', $ejercicioActual)->where('estado', '!=', 'Anulada')->where('pendiente', '>', 0)->sum('pendiente');

        $pendienteTotalSum = (float) $sinInformativos()->where('estado', '!=', 'Anulada')->where('pendiente', '>', 0)->sum('pendiente');

        $demandadoSum = (float) $sinInformativos()->where('estado', 'Demandada')->sum('pendiente');

        $viviendasIds   = $this->viviendasProximasAPrescribir();
        $aDemandarBase  = fn() => $sinInformativos()->whereIn('id_viviendas', $viviendasIds)->where('estado', 'Pendiente')->where('pendiente', '>', 0);
        $aDemandarSum   = (float) $aDemandarBase()->sum('pendiente');

        // Cobros: cuotas Pagadas con fecha_pago dentro del ejercicio seleccionado, separando
        // según si la cuota es del propio ejercicio o de uno anterior (deuda cobrada con retraso).
        $rangoActual  = $this->ejercicioRango($ejercicioActual);
        $anioActual   = (int) substr($ejercicioActual, 0, 4);

        // importe_cobrado no está fiablemente relleno en los lotes históricos (a menudo 0 aunque la
        // cuota esté Pagada), así que el importe realmente cobrado se calcula como importe - pendiente.
        $cobradoEjercicioSum = (float) $sinInformativos()
            ->where('ejercicio', $ejercicioActual)
            ->where('estado', 'Pagada')
            ->whereBetween('fecha_pago', $rangoActual)
            ->sum(DB::raw('importe - pendiente'));

        $cobradoAnterioresSum = (float) $sinInformativos()
            ->whereRaw('CAST(LEFT(ejercicio, 4) AS INTEGER) < ?', [$anioActual])
            ->where('estado', 'Pagada')
            ->whereBetween('fecha_pago', $rangoActual)
            ->sum(DB::raw('importe - pendiente'));

        $fmtImporte = fn($sum, $denomSum) => number_format($sum, 2, ',', '.') . ' € (' . ($denomSum > 0 ? round($sum / $denomSum * 100) : 0) . '%)';
        $fmtPlain   = fn($sum) => number_format($sum, 2, ',', '.') . ' €';

        return [
            'emitido_anio'        => $fmtPlain($emitidoAnioSum),
            'pendiente_anio'      => $fmtImporte($pendienteAnioSum, $emitidoAnioSum),
            'cobrado_ejercicio'   => $fmtImporte($cobradoEjercicioSum, $emitidoAnioSum),
            'cobrado_anteriores'  => $fmtPlain($cobradoAnterioresSum),
            'pendiente_total'     => $fmtPlain($pendienteTotalSum),
            'demandado_total'     => $fmtPlain($demandadoSum),
            'a_demandar'          => $fmtPlain($aDemandarSum),
        ];
    }
}
