<?php

namespace App\Http\Controllers\Vm;

use App\Console\Commands\VmSyncNukiCommand;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

// Gestión de las cerraduras Nuki: una tarjeta por cerradura y, al pulsarla, su ficha con las
// personas que tienen acceso, su código y desde cuándo.
//
// Solo lee de vm_cerraduras y vm_cerraduras_accesos, que los comandos de las 06:45 y 06:50 dejan
// al día. Nada de aquí llama a Nuki ni cambia nada en Nuki.
//
// El acceso de lectura lo resuelve menu.access desde la entrada "Nuki" del menú (tabla virtual
// nuki_default), igual que dev-fianzas.
class NukiController extends Controller
{
    public const ESTADO_BAJA        = 'baja';
    public const ESTADO_SIN_PROP    = 'sin_propiedad';
    public const ESTADO_SIN_DATO    = 'sin_dato';
    public const ESTADO_OK          = 'ok';

    // Estados de vm_tareas_mantenimiento que siguen abiertas (la tarea aún no se ha hecho).
    public const ESTADOS_ABIERTOS   = ['Nueva', 'Planificada', 'Vencida'];

    public function listado(Request $request, Project $project)
    {
        $q      = trim((string) $request->input('q', ''));
        $filtro = (string) $request->input('filtro', '');
        $umbral = VmSyncNukiCommand::umbral();

        $cerraduras = DB::table('vm_cerraduras as c')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'c.id_propiedades')
            ->select(
                'c.id', 'c.nuki_smartlock_id', 'c.nombre as nombre_cerradura',
                'c.bateria', 'c.bateria_critica', 'c.leido_at',
                'c.ultimo_uso_at', 'c.ultimo_uso_quien',
                'c.id_propiedades', 'p.nombre as propiedad', 'p.deleted as prop_deleted'
            )
            ->get();

        // Cuántas autorizaciones tiene cada cerradura y cuántas no caducan. Una sola consulta.
        $accesos = DB::table('vm_cerraduras_accesos')
            ->select('nuki_smartlock_id',
                DB::raw('count(*) as total'),
                DB::raw('sum(case when fecha_hasta is null then 1 else 0 end) as sin_caducidad'),
                DB::raw('max(ultimo_uso_at) as ultimo_acceso'))
            ->groupBy('nuki_smartlock_id')
            ->get()
            ->keyBy('nuki_smartlock_id');

        // Tareas de cambio de batería por propiedad, para el icono de Breezeway de cada tarjeta.
        $propiedadesIds = $cerraduras->pluck('id_propiedades')->filter()->unique()->values()->all();
        $tareas = $this->tareasBateria($propiedadesIds);

        // Tareas de batería de cada propiedad en los últimos 30 días, todas las que haya.
        $tareasMes = DB::table('vm_tareas_mantenimiento')
            ->whereIn('id_propiedades', $propiedadesIds)
            ->whereNotNull('breezeway_task_id')
            ->whereRaw("nombre ~* 'nuki'")
            ->whereRaw("nombre ~* '(bater|pila)'")
            ->whereDate('fecha_planificada', '>=', now()->subDays(30)->toDateString())
            ->groupBy('id_propiedades')
            ->selectRaw('id_propiedades, count(*) as n')
            ->pluck('n', 'id_propiedades');

        $todas = $cerraduras->map(function ($c) use ($umbral, $accesos, $tareas, $tareasMes) {
            // "Propiedad activa" = vinculada y no borrada. Sin contrato ni fechas (decidido).
            $sinProp = $c->id_propiedades === null
                || $c->propiedad === null
                || ($c->prop_deleted !== null && (int) $c->prop_deleted !== 0);

            $bateriaBaja = $c->bateria !== null && (int) $c->bateria <= $umbral;

            // Si la batería es baja, gana el rojo sobre el amarillo. La vista muestra las dos
            // etiquetas cuando se cumplen a la vez.
            $estado = match (true) {
                $bateriaBaja         => self::ESTADO_BAJA,
                $sinProp             => self::ESTADO_SIN_PROP,
                $c->bateria === null => self::ESTADO_SIN_DATO,
                default              => self::ESTADO_OK,
            };

            $acc = $accesos->get($c->nuki_smartlock_id);

            return (object) [
                'id'            => $c->id,
                'smartlock_id'  => $c->nuki_smartlock_id,
                'titulo'        => $sinProp ? $c->nombre_cerradura : $c->propiedad,
                'cerradura'     => $c->nombre_cerradura,
                'sin_propiedad' => $sinProp,
                'bateria'       => $c->bateria,
                'bateria_baja'  => $bateriaBaja,
                'leido_at'      => $c->leido_at ? Carbon::parse($c->leido_at) : null,
                'ultimo_uso_at' => $c->ultimo_uso_at ? Carbon::parse($c->ultimo_uso_at) : null,
                'ultimo_uso_quien' => $c->ultimo_uso_quien,
                'ultimo_acceso'    => $acc?->ultimo_acceso ? Carbon::parse($acc->ultimo_acceso) : null,
                'tareas_mes'       => (int) ($tareasMes[$c->id_propiedades] ?? 0),
                'accesos'       => (int) ($acc->total ?? 0),
                'sin_caducidad' => (int) ($acc->sin_caducidad ?? 0),
                'estado'        => $estado,
                'tarea'         => $this->tareaVigente($tareas->get($c->id_propiedades)),
            ];
        });

        $tarjetas = $todas
            ->filter(function ($t) use ($q, $filtro) {
                if ($filtro === 'baja' && !$t->bateria_baja) return false;
                if ($filtro === 'sin_propiedad' && !$t->sin_propiedad) return false;
                if ($q !== '' && !str_contains(mb_strtolower($t->titulo . ' ' . $t->cerradura), mb_strtolower($q))) return false;
                return true;
            })
            ->sortBy(fn($t) => mb_strtolower((string) $t->titulo))
            ->values();

        // Los contadores van sobre todas las cerraduras, no sobre lo filtrado.
        $resumen = [
            'total'         => $todas->count(),
            'bateria_baja'  => $todas->where('bateria_baja', true)->count(),
            'sin_propiedad' => $todas->where('sin_propiedad', true)->count(),
        ];

        return view('vm.nuki-list', [
            'project'  => $project,
            'tarjetas' => $tarjetas,
            'resumen'  => $resumen,
            'umbral'   => $umbral,
            'q'        => $q,
            'filtro'   => $filtro,
            'ultimaLectura' => $cerraduras->max('leido_at'),
        ]);
    }

    // Actualiza las baterías ahora mismo. Solo lee de Nuki (vm:sync-nuki). Las autorizaciones se
    // siguen actualizando a las 06:50: traerlas aquí llevaría más de lo que aguanta una petición web.
    public function sincronizar(Request $request, Project $project)
    {
        $codigo = Artisan::call('vm:sync-nuki');

        return redirect()->route('vm.nuki_list', $project->slug)->with('status', $codigo === 0
            ? 'Baterías de Nuki actualizadas.'
            : 'No se pudo actualizar Nuki. Revisa el log.');
    }

    public function show(Request $request, Project $project, int $id)
    {
        $cerradura = DB::table('vm_cerraduras as c')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'c.id_propiedades')
            ->where('c.id', $id)
            ->select('c.*', 'p.nombre as propiedad', 'p.deleted as prop_deleted')
            ->first();

        abort_if(!$cerradura, 404);

        $accesos = DB::table('vm_cerraduras_accesos')
            ->where('nuki_smartlock_id', $cerradura->nuki_smartlock_id)
            ->orderByRaw("case tipo when 'codigo' then 0 when 'teclado' then 1 when 'app' then 2 else 3 end")
            ->orderBy('nombre')
            ->get()
            ->map(function ($a) {
                // El código no se muestra en claro: la vista lo pone oculto y se revela al pulsar.
                try {
                    $a->codigo_claro = $a->codigo !== null ? Crypt::decryptString($a->codigo) : null;
                } catch (DecryptException) {
                    $a->codigo_claro = null;
                }
                $a->vigente = $a->habilitado
                    && ($a->fecha_desde === null || Carbon::parse($a->fecha_desde)->isPast())
                    && ($a->fecha_hasta === null || Carbon::parse($a->fecha_hasta)->isFuture());
                return $a;
            });

        // Histórico: todas las tareas de batería de la propiedad, la más reciente primero.
        $historico = ($this->tareasBateria($cerradura->id_propiedades ? [$cerradura->id_propiedades] : [])
                ->get($cerradura->id_propiedades) ?? collect())
            ->reverse()->values()
            ->map(fn($t) => $this->conUrl($t));

        return view('vm.nuki', [
            'project'   => $project,
            'historico' => $historico,
            'cerradura' => $cerradura,
            'accesos'   => $accesos,
            'umbral'    => VmSyncNukiCommand::umbral(),
            'sinCaducidad' => $accesos->whereNull('fecha_hasta')->count(),
        ]);
    }

    // Tareas de Breezeway cuyo nombre habla de la pila o batería del Nuki. Se buscan en las tareas
    // de mantenimiento de la propiedad, y solo las que tienen id de Breezeway, para poder enlazarlas.
    // Devuelve una colección agrupada por propiedad, de la más antigua a la más reciente.
    private function tareasBateria(array $propiedades)
    {
        if (!$propiedades) {
            return collect();
        }

        return DB::table('vm_tareas_mantenimiento')
            ->whereIn('id_propiedades', $propiedades)
            ->whereNotNull('breezeway_task_id')
            ->whereRaw("nombre ~* 'nuki'")
            ->whereRaw("nombre ~* '(bater|pila)'")
            ->orderBy('fecha_planificada')
            ->get(['id', 'breezeway_task_id', 'nombre', 'estado', 'fecha_planificada', 'fecha_finalizacion', 'id_propiedades'])
            ->groupBy('id_propiedades');
    }

    // La tarea abierta más próxima de la propiedad, o null si no hay ninguna.
    private function tareaVigente($tareas)
    {
        $abierta = $tareas?->first(fn($t) => in_array($t->estado, self::ESTADOS_ABIERTOS, true));

        return $abierta ? $this->conUrl($abierta) : null;
    }

    // Añade el enlace a la tarea en Breezeway y la fecha ya como objeto de fecha.
    private function conUrl($t)
    {
        $t->url = 'https://app.breezeway.io/task/' . $t->breezeway_task_id;
        $t->fecha = $t->fecha_planificada ? Carbon::parse($t->fecha_planificada) : null;

        return $t;
    }
}
