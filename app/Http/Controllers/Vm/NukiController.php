<?php

namespace App\Http\Controllers\Vm;

use App\Console\Commands\VmSyncNukiCommand;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    // Orden de gravedad: lo que más pide atención, primero.
    public const ESTADO_BAJA        = 'baja';
    public const ESTADO_SIN_PROP    = 'sin_propiedad';
    public const ESTADO_SIN_DATO    = 'sin_dato';
    public const ESTADO_OK          = 'ok';

    private const RANGO = [
        self::ESTADO_BAJA     => 0,
        self::ESTADO_SIN_PROP => 1,
        self::ESTADO_SIN_DATO => 2,
        self::ESTADO_OK       => 3,
    ];

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
                'c.id_propiedades', 'p.nombre as propiedad', 'p.deleted as prop_deleted'
            )
            ->get();

        // Cuántas autorizaciones tiene cada cerradura y cuántas no caducan. Una sola consulta.
        $accesos = DB::table('vm_cerraduras_accesos')
            ->select('nuki_smartlock_id',
                DB::raw('count(*) as total'),
                DB::raw('sum(case when fecha_hasta is null then 1 else 0 end) as sin_caducidad'))
            ->groupBy('nuki_smartlock_id')
            ->get()
            ->keyBy('nuki_smartlock_id');

        $todas = $cerraduras->map(function ($c) use ($umbral, $accesos) {
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
                'accesos'       => (int) ($acc->total ?? 0),
                'sin_caducidad' => (int) ($acc->sin_caducidad ?? 0),
                'estado'        => $estado,
            ];
        });

        $tarjetas = $todas
            ->filter(function ($t) use ($q, $filtro) {
                if ($filtro === 'baja' && !$t->bateria_baja) return false;
                if ($filtro === 'sin_propiedad' && !$t->sin_propiedad) return false;
                if ($q !== '' && !str_contains(mb_strtolower($t->titulo . ' ' . $t->cerradura), mb_strtolower($q))) return false;
                return true;
            })
            ->sortBy(fn($t) => [self::RANGO[$t->estado], $t->bateria ?? 101, mb_strtolower((string) $t->titulo)])
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
        ]);
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

        return view('vm.nuki', [
            'project'   => $project,
            'cerradura' => $cerradura,
            'accesos'   => $accesos,
            'umbral'    => VmSyncNukiCommand::umbral(),
            'sinCaducidad' => $accesos->whereNull('fecha_hasta')->count(),
        ]);
    }
}
