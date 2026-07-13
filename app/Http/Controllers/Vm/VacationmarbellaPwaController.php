<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class VacationmarbellaPwaController extends Controller
{
    // Auth

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $authUser = DB::table('admin_users')
            ->where('email', $request->email)
            ->first();

        if (!$authUser || !password_verify($request->password, $authUser->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $esAdmin = DB::table('admin_user_roles')
            ->where('user_id', $authUser->id)
            ->where('role', 'admin')
            ->exists();

        $user = DB::table('vm_usuarios')
            ->where('admin_user_id', $authUser->id)
            ->where('deleted', 0)
            ->first();

        if (!$user && !$esAdmin) {
            return response()->json(['error' => 'Usuario sin acceso a la app'], 403);
        }

        if ($user && in_array($user->acceso, ['web', 'sin acceso'])) {
            return response()->json(['error' => 'Sin acceso a la app'], 403);
        }

        $ttl       = $request->boolean('remember') ? 30 : 1;
        $token     = Str::random(64);
        $expiresAt = now()->addDays($ttl);

        DB::table('vm_pwa_tokens')->insert([
            'token'         => $token,
            'user_id'       => $user?->id,
            'admin_user_id' => $esAdmin ? $authUser->id : null,
            'app'           => 'vm',
            'device'        => substr($request->header('User-Agent', ''), 0, 255),
            'expires_at'    => $expiresAt,
            'last_seen_at'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $rol      = $user ? DB::table('vm_roles')->find($user->id_rol) : null;
        $contrato = $user ? DB::table('vm_contratos')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->first() : null;

        // Leer must_change_password del User de Laravel vinculado al admin_user
        $appUser = \App\Models\User::where('email', $authUser->email)->first();
        $debecambiarPassword = $appUser?->must_change_password ?? false;

        return response()->json([
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => [
                'id'                    => $user?->id,
                'nombre'                => $user?->nombre ?? $authUser->name ?? $authUser->email,
                'mail'                  => $user?->mail   ?? $authUser->email,
                'id_rol'                => $user?->id_rol,
                'rol'                   => $rol?->nombre,
                'horas_contrato'        => $contrato ? (float) $contrato->horas_semana : null,
                'is_admin'              => $esAdmin,
                'es_supervisor'         => $user && $user->id_rol
                    ? !empty($this->resolveRoleHierarchy($user->id_rol))
                    : false,
                'debe_cambiar_password' => (bool) $debecambiarPassword,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token) {
            DB::table('vm_pwa_tokens')->where('token', $token)->delete();
        }
        return response()->json(['ok' => true]);
    }

    // Tareas

    public function tareasHoy(Request $request)
    {
        $user  = $this->authenticate($request);
        $fecha = $request->query('fecha', now()->toDateString());
        $uid   = (string) $user->id;

        $visibleIds   = $this->getVisibleUserIds($user);
        $filterUserId = $request->query('usuario_id');
        $filterIds    = ($filterUserId && in_array((string) $filterUserId, $visibleIds))
            ? [(string) $filterUserId]
            : $visibleIds;

        $cols = [
            't.id', 't.nombre', 't.descripcion', 't.comentario',
            't.fecha_planificada', 't.tiempo', 't.control_user',
            'p.id as propiedad_id', 'p.nombre as propiedad_nombre',
            'p.icnea_address as direccion', 'p.icnea_city as ciudad',
            'p.icnea_latitude as lat', 'p.icnea_longitude as lng',
            'p.file_foto as propiedad_foto',
        ];

        $limpieza = DB::table('vm_tareas_limpieza as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $fecha)
            ->where(function ($q) use ($filterIds) {
                foreach ($filterIds as $fid) { $q->orWhereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$fid])]); }
            })
            ->select(array_merge($cols, [DB::raw("'limpieza' as tipo")]))
            ->get();

        $mantenimiento = DB::table('vm_tareas_mantenimiento as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $fecha)
            ->where(function ($q) use ($filterIds) {
                foreach ($filterIds as $fid) { $q->orWhereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$fid])]); }
            })
            ->select(array_merge($cols, [DB::raw("'mantenimiento' as tipo")]))
            ->get();

        $piscinas = DB::table('vm_tareas_piscinas as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $fecha)
            ->where(function ($q) use ($filterIds) {
                foreach ($filterIds as $fid) { $q->orWhereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$fid])]); }
            })
            ->select(array_merge($cols, [DB::raw("'piscina' as tipo")]))
            ->get();

        $tareas = $limpieza->merge($mantenimiento)->merge($piscinas)->sortBy('nombre')->values();

        $fichajeCerrado = false;
        if ($user->id) {
            $fichajeCerrado = DB::table('vm_fichaje')
                ->where('fecha_fichaje', $fecha)
                ->where('deleted', 0)
                ->where('control_user', $user->id)
                ->whereNotNull('hora_fin')
                ->exists();
        }

        // Adjuntar fotos, mis imputaciones y mi tiempo total a cada tarea
        $tareas = $tareas->map(function ($t) use ($user) {
            $col = match($t->tipo) {
                'limpieza'      => 'id_tareas_limpieza',
                'mantenimiento' => 'id_tareas_mantenimiento',
                'piscina'       => 'id_tareas_piscinas',
            };
            $fotos = DB::table('vm_fotos')
                ->where($col, $t->id)
                ->where('deleted', 0)
                ->get(['id', 'file_foto as path'])
                ->toArray();
            $t->fotos_detalle = $fotos;
            $t->fotos         = array_column($fotos, 'path');

            $misImputaciones = $user->id
                ? DB::table('vm_imputaciones')
                    ->where('tipo', $t->tipo)
                    ->where('id_tarea', $t->id)
                    ->where('id_usuario', $user->id)
                    ->orderByDesc('createdat')
                    ->get(['id', 'duracion', 'observacion', 'fecha_imputacion'])
                : collect();

            $t->mis_imputaciones = $misImputaciones->values();
            $totalMin            = $misImputaciones->sum('duracion');
            $t->mi_tiempo_total  = $totalMin > 0
                ? sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60)
                : null;

            // Tiempo imputado por cada asignado + puede_imputar
            $controlUserIds = array_map('intval', json_decode($t->control_user ?? '[]', true) ?? []);
            $t->puede_imputar = in_array((int) $user->id, $controlUserIds);

            if (!empty($controlUserIds)) {
                $nombres = DB::table('vm_usuarios')
                    ->whereIn('id', $controlUserIds)
                    ->pluck('nombre', 'id');

                $tiemposPorUsuario = DB::table('vm_imputaciones')
                    ->where('tipo', $t->tipo)
                    ->where('id_tarea', $t->id)
                    ->whereIn('id_usuario', $controlUserIds)
                    ->selectRaw('id_usuario, SUM(duracion) as total')
                    ->groupBy('id_usuario')
                    ->pluck('total', 'id_usuario');

                $t->control_user_info = collect($controlUserIds)->map(function ($uid) use ($nombres, $tiemposPorUsuario) {
                    return [
                        'id'             => $uid,
                        'nombre'         => $nombres[$uid] ?? 'Usuario #' . $uid,
                        'tiempo_minutos' => (int) ($tiemposPorUsuario[$uid] ?? 0),
                    ];
                })->values();
            } else {
                $t->control_user_info = [];
            }
            unset($t->control_user);

            return $t;
        });

        return response()->json([
            'fecha'           => $fecha,
            'tareas'          => $tareas,
            'fichaje_cerrado' => $fichajeCerrado,
        ]);
    }

    private function validarFechaImputacion(Request $request): string
    {
        $request->validate(['fecha_imputacion' => ['required', 'date']]);

        $hoy    = now()->toDateString();
        $limite = now()->subDays(2)->toDateString();
        $fecha  = $request->fecha_imputacion;

        if ($fecha < $limite || $fecha > $hoy) {
            abort(422, 'La fecha de imputación debe estar entre hoy y los 2 días anteriores');
        }

        return $fecha;
    }

    public function imputarTiempo(Request $request, string $tipo, int $id)
    {
        $user = $this->authenticate($request);
        $request->validate(['tiempo' => ['required', 'regex:/^\d+:\d{2}$/']]);
        $fechaImputacion = $this->validarFechaImputacion($request);

        $table = $this->resolveTable($tipo);
        $uid   = (string) $user->id;

        $tarea = DB::table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->first(['id', 'control_user']);

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $asignados = array_map('strval', json_decode($tarea->control_user ?? '[]', true) ?? []);
        if (!$uid || !in_array($uid, $asignados)) {
            return response()->json(['error' => 'No tienes permiso para imputar en esta tarea'], 403);
        }

        [$h, $m] = explode(':', $request->tiempo);
        $minutos = (int) $h * 60 + (int) $m;

        \App\Services\ImputacionesSync::insertar(
            $tipo === 'piscina' ? 'piscina' : $tipo,
            $id,
            $user->id,
            $minutos,
            $request->input('observacion'),
            $fechaImputacion,
            $request->input('lat') !== null ? (float) $request->input('lat') : null,
            $request->input('lng') !== null ? (float) $request->input('lng') : null
        );

        return response()->json(['ok' => true]);
    }

    public function editarImputacion(Request $request, string $tipo, int $id, int $imputacionId)
    {
        $user = $this->authenticate($request);
        $request->validate(['tiempo' => ['required', 'regex:/^\d+:\d{2}$/']]);

        $tipoLabel = $tipo === 'piscina' ? 'piscina' : $tipo;

        $imputacion = DB::table('vm_imputaciones')
            ->where('id', $imputacionId)
            ->where('tipo', $tipoLabel)
            ->where('id_tarea', $id)
            ->where('id_usuario', $user->id)
            ->first();

        if (!$imputacion) {
            return response()->json(['error' => 'Imputación no encontrada'], 404);
        }

        $limite = now()->subDays(2)->toDateString();
        if ($imputacion->fecha_imputacion < $limite) {
            return response()->json(['error' => 'No se puede editar una imputación de hace más de 2 días'], 403);
        }

        $fechaImputacion = $this->validarFechaImputacion($request);

        [$h, $m] = explode(':', $request->tiempo);
        $minutos = (int) $h * 60 + (int) $m;

        \App\Services\ImputacionesSync::actualizar(
            $imputacionId,
            $minutos,
            $request->input('observacion'),
            $fechaImputacion
        );

        return response()->json(['ok' => true]);
    }

    public function reportarTarea(Request $request, string $tipo, int $id)
    {
        $user = $this->authenticate($request);
        $request->validate([
            'descripcion' => 'required|string|max:2000',
            'fotos'       => 'required|array|min:1',
            'fotos.*'     => 'file|max:20480',
        ]);

        // Origen
        $tablaOrigen = $this->resolveTable($tipo);
        $tarea = DB::table($tablaOrigen)->where('id', $id)->where('deleted', 0)->first();
        if (!$tarea) return response()->json(['error' => 'Tarea no encontrada'], 404);

        // Destino: limpieza → mantenimiento y viceversa
        $tipoDestino  = $tipo === 'limpieza' ? 'mantenimiento' : 'limpieza';
        $tablaDestino = $this->resolveTable($tipoDestino);
        $nombreOrigen = $tipo === 'limpieza' ? 'Limpieza' : 'Mantenimiento';
        $descripcion  = $request->input('descripcion');

        $idNueva = DB::table($tablaDestino)->insertGetId([
            'nombre'           => 'Petición desde ' . $nombreOrigen,
            'id_propiedades'   => $tarea->id_propiedades,
            'fecha_planificada'=> now()->toDateString(),
            'descripcion'      => $descripcion,
            'control_user'     => null,
            'deleted'          => 0,
            'hidden'           => 0,
            'blocked'          => 0,
            'createuser'       => $user->admin_user_id,
            'createdat'        => now(),
        ]);

        // Fotos (una o varias)
        $destDir = storage_path('app/public/vm/fotos');
        if (!is_dir($destDir)) mkdir($destDir, 0775, true);

        foreach ($request->file('fotos') as $file) {
            $filename = uniqid() . '.jpg';
            $destPath = $destDir . '/' . $filename;
            $this->resizeImage($file->getPathname(), $destPath, 1280, 500 * 1024);
            $path = 'vm/fotos/' . $filename;

            $fotoData = ['nombre' => 'Foto reporte', 'file_foto' => $path, 'createuser' => $user->id, 'createdat' => now()];
            if ($tipoDestino === 'limpieza')      $fotoData['id_tareas_limpieza']      = $idNueva;
            if ($tipoDestino === 'mantenimiento') $fotoData['id_tareas_mantenimiento'] = $idNueva;
            DB::table('vm_fotos')->insert($fotoData);
        }

        return response()->json(['ok' => true]);
    }

    public function subirFoto(Request $request, string $tipo, int $id)
    {
        $user  = $this->authenticate($request);
        $request->validate(['foto' => 'required|file|max:20480']);

        $table = $this->resolveTable($tipo);
        $uid   = (string) $user->id;

        $tarea = DB::table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $file     = $request->file('foto');
        $filename = uniqid() . '.jpg';
        $destDir  = storage_path('app/public/vm/fotos');
        $destPath = $destDir . '/' . $filename;

        if (!is_dir($destDir)) mkdir($destDir, 0775, true);

        $this->resizeImage($file->getPathname(), $destPath, 1280, 500 * 1024);

        $path = 'vm/fotos/' . $filename;

        $fotoData = [
            'nombre'     => 'Foto PWA',
            'file_foto'  => $path,
            'createuser' => $user->id,
            'createdat'  => now(),
        ];

        if ($tipo === 'limpieza')      $fotoData['id_tareas_limpieza']      = $id;
        if ($tipo === 'mantenimiento') $fotoData['id_tareas_mantenimiento'] = $id;
        if ($tipo === 'piscina')       $fotoData['id_tareas_piscinas']      = $id;

        DB::table('vm_fotos')->insert($fotoData);

        return response()->json(['ok' => true, 'path' => $path]);
    }

    public function borrarFoto(Request $request, int $id)
    {
        $user = $this->authenticate($request);

        $foto = DB::table('vm_fotos')->where('id', $id)->where('deleted', 0)->first();

        if (!$foto) {
            return response()->json(['error' => 'Foto no encontrada'], 404);
        }

        // Verificar que la tarea pertenece al usuario
        $col   = $foto->id_tareas_limpieza      ? 'id_tareas_limpieza'
               : ($foto->id_tareas_mantenimiento ? 'id_tareas_mantenimiento' : 'id_tareas_piscinas');
        $tabla = $col === 'id_tareas_limpieza'      ? 'vm_tareas_limpieza'
               : ($col === 'id_tareas_mantenimiento' ? 'vm_tareas_mantenimiento' : 'vm_tareas_piscinas');
        $uid   = (string) $user->id;

        $ok = DB::table($tabla)
            ->where('id', $foto->$col)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->exists();

        if (!$ok) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }

        // Borrar archivo físico
        $physicalPath = storage_path('app/public/' . $foto->file_foto);
        if (file_exists($physicalPath)) {
            unlink($physicalPath);
        }

        DB::table('vm_fotos')->where('id', $id)->update(['deleted' => 1, 'updatedat' => now()]);

        return response()->json(['ok' => true]);
    }

    // Push notifications

    public function vapidPublicKey()
    {
        return response()->json(['key' => env('VAPID_PUBLIC_KEY')]);
    }

    public function me(Request $request)
    {
        $authUser = DB::table('admin_users')
            ->where('email', auth()->user()?->email ?? '')
            ->first();

        // Re-use the full authenticate flow to get the user object
        $row = DB::table('vm_pwa_tokens')
            ->where('token', $this->bearerToken($request))
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) abort(401, 'Token inválido');

        $esAdmin = $row->admin_user_id
            ? DB::table('admin_user_roles')->where('user_id', $row->admin_user_id)->where('role', 'admin')->exists()
            : false;

        if ($row->user_id) {
            $user = DB::table('vm_usuarios')->find($row->user_id);
        } else {
            $authU = DB::table('admin_users')->find($row->admin_user_id);
            $user  = $authU ? (object)['id' => null, 'nombre' => $authU->name ?? $authU->email, 'mail' => $authU->email, 'id_rol' => null] : null;
        }

        if (!$user) abort(401, 'Usuario no encontrado');

        $rol      = isset($user->id_rol) ? DB::table('vm_roles')->find($user->id_rol) : null;
        $contrato = $user->id ? DB::table('vm_contratos')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->first() : null;

        return response()->json([
            'id'             => $user->id ?? null,
            'nombre'         => $user->nombre ?? $user->name ?? '',
            'mail'           => $user->mail   ?? $user->email ?? '',
            'id_rol'         => $user->id_rol ?? null,
            'rol'            => $rol?->nombre,
            'horas_contrato' => $contrato ? (float) $contrato->horas_semana : null,
            'is_admin'       => $esAdmin,
        ]);
    }

    public function pushSubscribe(Request $request)
    {
        $user = $this->authenticate($request);
        if (!$user->id) return response()->json(['ok' => true]); // admin sin vm_usuarios
        $request->validate([
            'endpoint' => 'required|string',
            'p256dh'   => 'required|string',
            'auth'     => 'required|string',
        ]);

        DB::table('vm_push_subscriptions')->upsert([
            'id_usuario' => $user->id,
            'endpoint'   => $request->endpoint,
            'p256dh'     => $request->p256dh,
            'auth'       => $request->auth,
            'createdat'  => now(),
        ], ['id_usuario', 'endpoint'], ['p256dh', 'auth']);

        return response()->json(['ok' => true]);
    }

    public function pushUnsubscribe(Request $request)
    {
        $user = $this->authenticate($request);
        $request->validate(['endpoint' => 'required|string']);

        DB::table('vm_push_subscriptions')
            ->where('id_usuario', $user->id)
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function usuarios(Request $request)
    {
        $user = $this->authenticate($request);

        $esAdmin = DB::table('admin_user_roles')
            ->where('user_id', $user->admin_user_id)
            ->where('role', 'admin')
            ->exists();

        if ($esAdmin) {
            $usuarios = DB::table('vm_usuarios')
                ->where('deleted', 0)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'mail']);
        } else {
            $visibleIds = $this->getVisibleUserIds($user);
            $intIds     = array_map('intval', $visibleIds);
            $todos = DB::table('vm_usuarios')
                ->whereIn('id', $intIds)
                ->where('deleted', 0)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'mail']);
            $userId   = $user->id;
            $usuarios = $todos->sortBy(fn($u) => $u->id === $userId ? '0' : '1_' . $u->nombre)->values();
        }

        return response()->json($usuarios);
    }

    public function duraciones()
    {
        $d = DB::table('master_duraciones')->where('deleted', 0)->orderBy('minutos')->get();
        return response()->json($d);
    }

    // Fichaje

    public function fichajeHoy(Request $request)
    {
        $user  = $this->authenticate($request);
        $hoy   = now()->toDateString();
        $desde = now()->startOfMonth()->toDateString();

        $registros = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->whereBetween('fecha_fichaje', [$desde, $hoy])
            ->orderBy('fecha_fichaje', 'desc')
            ->get();

        $contrato = $user->id ? DB::table('vm_contratos')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->first() : null;

        // Ausencias del mes
        $ausencias = $user->id ? DB::table('vm_ausencias')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $desde)
            ->get(['tipo', 'fecha_inicio', 'fecha_fin']) : collect();

        // Horarios del mes (descansos y días especiales)
        $horarios = $user->id ? DB::table('vm_horarios')
            ->where('id_usuario', $user->id)
            ->whereBetween('fecha', [$desde, $hoy])
            ->get(['fecha', 'tipo']) : collect();

        // Suma de minutos imputados hoy (por fecha_imputacion, no por fecha_planificada de la tarea)
        $tareasHoyMin = $user->id
            ? (int) DB::table('vm_imputaciones')
                ->where('id_usuario', $user->id)
                ->where('fecha_imputacion', $hoy)
                ->sum('duracion')
            : 0;

        // Nº de tareas SIN ninguna imputación mía por día, para días anteriores (histórico)
        $pendientesPorDia = [];
        if ($user->id) {
            $ayer = now()->subDay()->toDateString();
            $tablasPorTipo = [
                'vm_tareas_limpieza'      => 'limpieza',
                'vm_tareas_mantenimiento' => 'mantenimiento',
                'vm_tareas_piscinas'      => 'piscina',
            ];

            foreach ($tablasPorTipo as $tabla => $tipoLabel) {
                $tareasDia = DB::table($tabla)
                    ->where('deleted', 0)
                    ->whereBetween('fecha_planificada', [$desde, $ayer])
                    ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
                    ->get(['id', 'fecha_planificada']);

                if ($tareasDia->isEmpty()) continue;

                $idsConImputacion = DB::table('vm_imputaciones')
                    ->where('tipo', $tipoLabel)
                    ->where('id_usuario', $user->id)
                    ->whereIn('id_tarea', $tareasDia->pluck('id'))
                    ->pluck('id_tarea')
                    ->unique();

                foreach ($tareasDia as $t) {
                    if (!$idsConImputacion->contains($t->id)) {
                        $pendientesPorDia[$t->fecha_planificada] = ($pendientesPorDia[$t->fecha_planificada] ?? 0) + 1;
                    }
                }
            }
        }

        $imputadoPorDia = $user->id
            ? DB::table('vm_imputaciones')
                ->where('id_usuario', $user->id)
                ->whereBetween('fecha_imputacion', [$desde, $hoy])
                ->selectRaw('fecha_imputacion, SUM(duracion) as total')
                ->groupBy('fecha_imputacion')
                ->pluck('total', 'fecha_imputacion')
                ->map(fn($v) => (int) $v)
                ->all()
            : [];

        return response()->json([
            'hoy'                => $hoy,
            'fichaje'            => $registros->firstWhere('fecha_fichaje', $hoy),
            'mes'                => $registros,
            'ausencias'          => $ausencias,
            'horarios'           => $horarios,
            'horas_contrato'     => $contrato ? (float) $contrato->horas_semana : null,
            'tareas_min'         => $tareasHoyMin,
            'pendientes_por_dia' => $pendientesPorDia,
            'imputado_por_dia'   => $imputadoPorDia,
        ]);
    }

    public function fichajeEntrada(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $existe = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya has fichado entrada hoy'], 409);
        }

        $hora   = now()->format('H:i:s');
        $nombre = now()->format('Y.m.d') . '_' . $user->nombre;
        DB::table('vm_fichaje')->insert([
            'fecha_fichaje'  => $hoy,
            'control_user'   => $user->id,
            'nombre'         => $nombre,
            'hora_inicio'    => $hora,
            'hora_ini_auto'  => $hora,
            'createuser'     => $user->id,
            'createdat'      => now(),
        ]);

        return response()->json(['ok' => true, 'hora_inicio' => now()->format('H:i')]);
    }

    public function fichajeSalida(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        if (!$fichaje) {
            return response()->json(['error' => 'No has fichado entrada hoy'], 404);
        }
        if ($fichaje->hora_fin) {
            return response()->json(['error' => 'Ya has fichado salida hoy'], 409);
        }

        $hora = now()->format('H:i:s');
        DB::table('vm_fichaje')
            ->where('id', $fichaje->id)
            ->update(['hora_fin' => $hora, 'hora_fin_auto' => $hora, 'updateuser' => $user->id, 'updatedat' => now()]);

        return response()->json(['ok' => true, 'hora_fin' => now()->format('H:i')]);
    }

    public function fichajePausa(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        if (!$fichaje || !$fichaje->hora_inicio) {
            return response()->json(['error' => 'No has fichado entrada'], 404);
        }

        $hora   = now()->format('H:i:s');
        $update = ['updateuser' => $user->id, 'updatedat' => now()];

        if (!$fichaje->pausa_inicio) {
            $update['pausa_inicio']   = $hora;
            $update['pausa_ini_auto'] = $hora;
            $msg = 'Pausa iniciada';
        } elseif (!$fichaje->pausa_fin) {
            $update['pausa_fin']      = $hora;
            $update['pausa_fin_auto'] = $hora;
            $msg = 'Pausa finalizada';
        } else {
            return response()->json(['error' => 'La pausa ya esta registrada'], 409);
        }

        DB::table('vm_fichaje')->where('id', $fichaje->id)->update($update);

        return response()->json(['ok' => true, 'msg' => $msg]);
    }

    public function fichajeEditar(Request $request)
    {
        $user  = $this->authenticate($request);
        $fecha = $request->input('fecha', now()->toDateString());

        // Máximo 2 días atrás
        if ($fecha < now()->subDays(2)->toDateString()) {
            return response()->json(['error' => 'Solo se pueden editar fichajes de los últimos 2 días'], 403);
        }

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $fecha)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        if (!$fichaje) {
            return response()->json(['error' => 'No hay fichaje para esa fecha'], 404);
        }

        $request->validate([
            'hora_inicio'  => 'nullable|date_format:H:i',
            'pausa_inicio' => 'nullable|date_format:H:i',
            'pausa_fin'    => 'nullable|date_format:H:i',
            'hora_fin'     => 'nullable|date_format:H:i',
            'observacion'  => 'nullable|string|max:500',
        ]);

        $update = ['updateuser' => $user->id, 'updatedat' => now()];
        foreach (['hora_inicio', 'pausa_inicio', 'pausa_fin', 'hora_fin'] as $campo) {
            if ($request->has($campo)) {
                $update[$campo] = $request->input($campo) ? $request->input($campo) . ':00' : null;
            }
        }
        if ($request->has('observacion')) {
            $update['observacion'] = $request->input('observacion') ?: null;
        }

        DB::table('vm_fichaje')->where('id', $fichaje->id)->update($update);

        return response()->json(['ok' => true]);
    }

    public function fichajeCrear(Request $request)
    {
        $user = $this->authenticate($request);

        $request->validate([
            'fecha'        => 'required|date',
            'hora_inicio'  => 'required|date_format:H:i',
            'hora_fin'     => 'nullable|date_format:H:i',
            'pausa_inicio' => 'nullable|date_format:H:i',
            'pausa_fin'    => 'nullable|date_format:H:i',
            'observacion'  => 'nullable|string|max:500',
        ]);

        $fecha = $request->input('fecha');

        if ($fecha < now()->subDays(2)->toDateString() || $fecha >= now()->toDateString()) {
            return response()->json(['error' => 'Solo se pueden crear fichajes de los 2 días anteriores'], 403);
        }

        $existe = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $fecha)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya existe un fichaje para ese día'], 409);
        }

        $nombre = \Carbon\Carbon::parse($fecha)->format('Y.m.d') . '_' . $user->nombre;

        DB::table('vm_fichaje')->insert([
            'fecha_fichaje' => $fecha,
            'control_user'  => $user->id,
            'nombre'        => $nombre,
            'hora_inicio'   => $request->input('hora_inicio') . ':00',
            'hora_fin'      => $request->input('hora_fin')     ? $request->input('hora_fin') . ':00'     : null,
            'pausa_inicio'  => $request->input('pausa_inicio') ? $request->input('pausa_inicio') . ':00' : null,
            'pausa_fin'     => $request->input('pausa_fin')    ? $request->input('pausa_fin') . ':00'    : null,
            'observacion'   => $request->input('observacion') ?: null,
            'createuser'    => $user->id,
            'createdat'     => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // Helpers

    public function crearTarea(Request $request)
    {
        $user = $this->authenticate($request);

        if (!$user->id) {
            abort(403, 'Solo usuarios del equipo pueden crear tareas');
        }

        $request->validate([
            'id_propiedades'    => 'required|integer',
            'fecha_finalizacion'=> 'required|date',
            'tiempo'            => ['required', 'regex:/^\d+:\d{2}$/'],
            'nombre'            => 'nullable|string|max:255',
        ]);

        $hoy        = now()->toDateString();
        $limite     = now()->subDays(2)->toDateString();
        $fecha      = $request->fecha_finalizacion;

        if ($fecha < $limite || $fecha > $hoy) {
            abort(422, 'La fecha debe estar entre hoy y los 2 días anteriores');
        }

        // Determinar tabla según rol del usuario
        $rol = DB::table('vm_roles')->where('id', $user->id_rol)->value('nombre');
        $tabla = str_contains(strtolower($rol ?? ''), 'mantenimiento')
            ? 'vm_tareas_mantenimiento'
            : 'vm_tareas_limpieza';

        $tipoLabel = $tabla === 'vm_tareas_mantenimiento' ? 'mantenimiento' : 'limpieza';
        $nombre    = $request->nombre
            ?: 'Tarea de ' . $tipoLabel;

        $idNueva = DB::table($tabla)->insertGetId([
            'nombre'            => $nombre,
            'id_propiedades'    => $request->id_propiedades,
            'fecha_planificada' => $fecha,
            'fecha_finalizacion'=> $fecha,
            'control_user'      => json_encode([(string) $user->id]),
            'createuser'        => $user->id,
            'createdat'         => now(),
            'deleted'           => 0,
            'hidden'            => 0,
            'blocked'           => 0,
        ]);

        [$h, $m] = explode(':', $request->tiempo);
        $minutos = (int) $h * 60 + (int) $m;

        \App\Services\ImputacionesSync::insertar(
            $tipoLabel,
            $idNueva,
            $user->id,
            $minutos,
            null,
            $fecha
        );

        return response()->json(['ok' => true]);
    }

    public function propiedades(Request $request)
    {
        $this->authenticate($request);

        $props = DB::table('vm_propiedades')
            ->where('deleted', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json($props);
    }

    public function agendaSemana(Request $request)
    {
        $user = $this->authenticate($request);

        $semana = $request->query('semana'); // YYYY-Www
        if ($semana && preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            $lunes = new \DateTime();
            $lunes->setISODate((int)$m[1], (int)$m[2]);
        } else {
            $lunes = new \DateTime();
            $lunes->setISODate((int)$lunes->format('o'), (int)$lunes->format('W'));
        }
        $domingo = clone $lunes;
        $domingo->modify('+6 days');

        $desde = $lunes->format('Y-m-d');
        $hasta = $domingo->format('Y-m-d');

        $horarios = DB::table('vm_horarios')
            ->where('id_usuario', $user->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')
            ->get(['fecha', 'tipo', 'hora_inicio', 'hora_fin']);

        return response()->json([
            'semana' => $lunes->format('o') . '-W' . $lunes->format('W'),
            'desde'  => $desde,
            'hasta'  => $hasta,
            'dias'   => $horarios,
        ]);
    }

    public function horarioEquipo(Request $request)
    {
        $user = $this->authenticate($request);

        $semana = $request->query('semana');
        if ($semana && preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            $lunes = new \DateTime();
            $lunes->setISODate((int)$m[1], (int)$m[2]);
        } else {
            $lunes = new \DateTime();
            $lunes->setISODate((int)$lunes->format('o'), (int)$lunes->format('W'));
        }
        $domingo = clone $lunes;
        $domingo->modify('+6 days');

        $desde = $lunes->format('Y-m-d');
        $hasta = $domingo->format('Y-m-d');

        $deptosFiltro = ['Operaciones', 'Recepción', 'Mantenimiento', 'Limpieza'];

        $usuarios = DB::table('vm_usuarios')
            ->where('deleted', 0)
            ->whereIn('departamento', $deptosFiltro)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'departamento']);

        $horarioRows = DB::table('vm_horarios')
            ->whereIn('id_usuario', $usuarios->pluck('id'))
            ->whereBetween('fecha', [$desde, $hasta])
            ->get(['id_usuario', 'fecha', 'tipo', 'hora_inicio', 'hora_fin']);

        $horarioMap = [];
        foreach ($horarioRows as $h) {
            $horarioMap[$h->id_usuario][$h->fecha] = $h;
        }

        $grupos = [];
        foreach ($deptosFiltro as $depto) {
            $miembros = $usuarios->where('departamento', $depto)->values();
            if ($miembros->isEmpty()) continue;
            $grupos[$depto] = [];
            foreach ($miembros as $u) {
                $dias = [];
                for ($i = 0; $i < 7; $i++) {
                    $fecha = (clone $lunes)->modify("+{$i} days")->format('Y-m-d');
                    $dias[$fecha] = $horarioMap[$u->id][$fecha] ?? null;
                }
                $grupos[$depto][] = [
                    'id'     => $u->id,
                    'nombre' => $u->nombre,
                    'dias'   => $dias,
                ];
            }
        }

        return response()->json([
            'semana' => $lunes->format('o') . '-W' . $lunes->format('W'),
            'desde'  => $desde,
            'hasta'  => $hasta,
            'grupos' => $grupos,
        ]);
    }

    private function resizeImage(string $src, string $dest, int $maxW, int $targetBytes): void
    {
        $info = @getimagesize($src);
        if (!$info) {
            copy($src, $dest);
            return;
        }

        [$origW, $origH, $type] = $info;

        $scale = min(1, $maxW / max($origW, $origH));
        $w     = (int) round($origW * $scale);
        $h     = (int) round($origH * $scale);

        $src_img = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => imagecreatefrompng($src),
            IMAGETYPE_WEBP => imagecreatefromwebp($src),
            default        => null,
        };

        if (!$src_img) {
            copy($src, $dest);
            return;
        }

        $dst_img = imagecreatetruecolor($w, $h);
        imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $w, $h, $origW, $origH);
        imagedestroy($src_img);

        // Reduce calidad hasta quedar bajo targetBytes
        foreach ([80, 65, 50, 35] as $quality) {
            ob_start();
            imagejpeg($dst_img, null, $quality);
            $data = ob_get_clean();
            if (strlen($data) <= $targetBytes || $quality === 35) {
                file_put_contents($dest, $data);
                break;
            }
        }

        imagedestroy($dst_img);
    }

    private function authenticate(Request $request): object
    {
        $token = $this->bearerToken($request);

        if (!$token) {
            abort(401, 'Token requerido');
        }

        $row = DB::table('vm_pwa_tokens')
            ->where('token', $token)
            ->where('app', 'vm')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            abort(401, 'Token invalido o expirado');
        }

        DB::table('vm_pwa_tokens')->where('id', $row->id)->update(['last_seen_at' => now()]);

        if ($row->user_id) {
            $user = DB::table('vm_usuarios')
                ->where('id', $row->user_id)
                ->where('deleted', 0)
                ->first();

            if (!$user) {
                abort(401, 'Usuario no encontrado');
            }
        } else {
            // Sesión de admin sin vm_usuarios
            $authUser = DB::table('admin_users')->where('id', $row->admin_user_id)->first();
            if (!$authUser) abort(401, 'Admin no encontrado');
            $user = (object) [
                'id'            => null,
                'admin_user_id' => $authUser->id,
                'nombre'        => $authUser->name ?? $authUser->email,
                'mail'          => $authUser->email,
                'id_rol'        => null,
            ];
        }

        $asUser = $request->query('as_user');
        if ($asUser && (int) $asUser !== $user->id) {
            $esAdmin = DB::table('admin_user_roles')
                ->where('user_id', $user->admin_user_id)
                ->where('role', 'admin')
                ->exists();

            if (!$esAdmin) {
                abort(403, 'Sin permiso para impersonar usuarios');
            }

            $impersonado = DB::table('vm_usuarios')
                ->where('id', (int) $asUser)
                ->where('deleted', 0)
                ->first();

            if (!$impersonado) {
                abort(404, 'Usuario no encontrado');
            }

            return $impersonado;
        }

        return $user;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    private function resolveTable(string $tipo): string
    {
        if ($tipo === 'limpieza')      return 'vm_tareas_limpieza';
        if ($tipo === 'mantenimiento') return 'vm_tareas_mantenimiento';
        if ($tipo === 'piscina')       return 'vm_tareas_piscinas';
        abort(400, 'Tipo de tarea invalido');
    }
    public function cambiarPassword(Request $request)
    {
        $user = $this->authenticate($request);

        $request->validate([
            'nueva_password'              => ['required', 'string', 'min:8', 'confirmed'],
            'nueva_password_confirmation' => ['required'],
        ]);

        // Obtener el User de Laravel vinculado
        $authUser = DB::table('admin_users')->find($user->admin_user_id);
        if (!$authUser) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $appUser = \App\Models\User::where('email', $authUser->email)->first();
        if (!$appUser) {
            return response()->json(['error' => 'Cuenta de acceso no encontrada'], 404);
        }

        $appUser->update([
            'password'             => \Illuminate\Support\Facades\Hash::make($request->nueva_password),
            'must_change_password' => false,
        ]);

        return response()->json(['ok' => true]);
    }

    // Jerarquia de roles

    private function resolveRoleHierarchy(int $startRoleId): array
    {
        $allRoles = DB::table('vm_roles')
            ->where('deleted', 0)
            ->get(['id', 'roles_supervisados'])
            ->keyBy('id');

        $root = $allRoles[$startRoleId] ?? null;
        if (!$root) return [];

        $directSubs = json_decode($root->roles_supervisados ?? '[]', true) ?? [];
        if (empty($directSubs)) return [];

        $visited = [];
        $toVisit = $directSubs;

        while (!empty($toVisit)) {
            $roleId = array_shift($toVisit);
            if (in_array($roleId, $visited)) continue;
            $visited[] = $roleId;
            $r = $allRoles[$roleId] ?? null;
            if (!$r) continue;
            $subs = json_decode($r->roles_supervisados ?? '[]', true) ?? [];
            foreach ($subs as $sub) {
                if (!in_array($sub, $visited)) $toVisit[] = $sub;
            }
        }

        return $visited;
    }

    private function getVisibleUserIds(object $user): array
    {
        $ids = $user->id ? [(string) $user->id] : [];
        if (!$user->id_rol) return $ids;

        $subRoleIds = $this->resolveRoleHierarchy($user->id_rol);
        if (empty($subRoleIds)) return $ids;

        $subUserIds = DB::table('vm_usuarios')
            ->whereIn('id_rol', $subRoleIds)
            ->where('deleted', 0)
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        return array_unique(array_merge($ids, $subUserIds));
    }

}
