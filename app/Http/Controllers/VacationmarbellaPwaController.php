<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VacationmarbellaPwaController extends Controller
{
    private string $app = 'vm';

    // Auth

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Autenticar contra admin_users (tabla de opland_pro)
        $authUser = DB::table('admin_users')
            ->where('email', $request->email)
            ->first();

        if (!$authUser || !\Illuminate\Support\Facades\Hash::check($request->password, $authUser->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        // Datos de perfil desde vm_usuarios (link por admin_user_id)
        $user = DB::table('vm_usuarios')
            ->where('admin_user_id', $authUser->id)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario sin acceso a la app'], 403);
        }

        $ttl       = $request->boolean('remember') ? 30 : 1;
        $token     = Str::random(64);
        $expiresAt = now()->addDays($ttl);

        DB::table('vm_pwa_tokens')->insert([
            'token'        => $token,
            'user_id'      => $user->id,
            'app'          => $this->app,
            'device'       => substr($request->header('User-Agent', ''), 0, 255),
            'expires_at'   => $expiresAt,
            'last_seen_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $rol = DB::table('vm_roles')->find($user->id_rol);

        return response()->json([
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => [
                'id'     => $user->id,
                'nombre' => $user->nombre,
                'email'  => $user->mail,
                'id_rol' => $user->id_rol,
                'rol'    => $rol ? $rol->nombre : null,
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
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $limpieza = DB::table("{$this->app}_tareas_limpieza as t")
            ->join("{$this->app}_propiedades as p", 'p.id', '=', 't.id_propiedades')
            ->join('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('t.fecha_planificada', $hoy)
            ->where('t.deleted', 0)
            ->select(
                't.id', 't.nombre', 't.descripcion', 't.comentario as comentarios',
                't.fecha_planificada as fecha_duedate', 't.fecha_finalizacion as completado_at',
                't.master_duraciones as id_duraciones', 'd.nombre as duracion', 'd.minutos as duracion_minutos',
                'p.id as inmueble_id', 'p.nombre as inmueble_nombre',
                'p.icnea_address as direccion', 'p.icnea_city as ciudad',
                'p.icnea_latitude as lat', 'p.icnea_longitude as lng',
                'p.file_foto as inmueble_foto',
                DB::raw('NULL as file_imagen1'), DB::raw('NULL as file_imagen2'),
                DB::raw('NULL as fecha_entrada'), DB::raw('NULL as fecha_salida'),
                DB::raw('NULL as nombre_cliente'), DB::raw('NULL as adultos'), DB::raw('NULL as ninyos'),
                DB::raw("'limpieza' as tipo")
            )
            ->get();

        $mantenimiento = DB::table("{$this->app}_tareas_mantenimiento as t")
            ->join("{$this->app}_propiedades as p", 'p.id', '=', 't.id_propiedades')
            ->join('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('t.fecha_planificada', $hoy)
            ->where('t.deleted', 0)
            ->select(
                't.id', 't.nombre', 't.descripcion', 't.comentario as comentarios',
                't.fecha_planificada as fecha_duedate', 't.fecha_finalizacion as completado_at',
                't.master_duraciones as id_duraciones', 'd.nombre as duracion', 'd.minutos as duracion_minutos',
                'p.id as inmueble_id', 'p.nombre as inmueble_nombre',
                'p.icnea_address as direccion', 'p.icnea_city as ciudad',
                'p.icnea_latitude as lat', 'p.icnea_longitude as lng',
                'p.file_foto as inmueble_foto',
                DB::raw('NULL as file_imagen1'), DB::raw('NULL as file_imagen2'),
                DB::raw('NULL as fecha_entrada'), DB::raw('NULL as fecha_salida'),
                DB::raw('NULL as nombre_cliente'), DB::raw('NULL as adultos'), DB::raw('NULL as ninyos'),
                DB::raw("'mantenimiento' as tipo")
            )
            ->get();

        $piscinas = DB::table("{$this->app}_tareas_piscinas as t")
            ->join("{$this->app}_propiedades as p", 'p.id', '=', 't.id_propiedades')
            ->join('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('t.fecha_planificada', $hoy)
            ->where('t.deleted', 0)
            ->select(
                't.id', 't.nombre', 't.descripcion', 't.comentario as comentarios',
                't.fecha_planificada as fecha_duedate', 't.fecha_finalizacion as completado_at',
                't.master_duraciones as id_duraciones', 'd.nombre as duracion', 'd.minutos as duracion_minutos',
                'p.id as inmueble_id', 'p.nombre as inmueble_nombre',
                'p.icnea_address as direccion', 'p.icnea_city as ciudad',
                'p.icnea_latitude as lat', 'p.icnea_longitude as lng',
                'p.file_foto as inmueble_foto',
                DB::raw('NULL as file_imagen1'), DB::raw('NULL as file_imagen2'),
                DB::raw('NULL as fecha_entrada'), DB::raw('NULL as fecha_salida'),
                DB::raw('NULL as nombre_cliente'), DB::raw('NULL as adultos'), DB::raw('NULL as ninyos'),
                DB::raw("'piscina' as tipo")
            )
            ->get();

        $tareas = $limpieza->merge($mantenimiento)->merge($piscinas)->sortBy('nombre')->values();

        return response()->json([
            'fecha'      => $hoy,
            'tareas'     => $tareas,
            'pendientes' => $tareas->whereNull('completado_at')->count(),
        ]);
    }

    public function completarTarea(Request $request, string $tipo, int $id)
    {
        $user = $this->authenticate($request);
        $request->validate(['id_duraciones' => 'required|integer']);

        $table = $this->resolveTable($tipo);
        $tarea = DB::table($table)
            ->where('id', $id)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('deleted', 0)
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $data = [
            'master_duraciones' => $request->id_duraciones,
            'fecha_finalizacion' => now()->toDateString(),
            'updateuser'         => $user->id,
            'updatedat'          => now(),
        ];

        if ($request->filled('comentarios')) {
            $data['comentario'] = $request->comentarios;
        }

        DB::table($table)->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }

    public function subirFoto(Request $request, string $tipo, int $id)
    {
        $user = $this->authenticate($request);
        $request->validate(['foto' => 'required|image|max:5120']);

        $table = $this->resolveTable($tipo);
        $tarea = DB::table($table)
            ->where('id', $id)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('deleted', 0)
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $path = $request->file('foto')->store('vacationmarbella/fotos_tareas', 'public');

        $fkCol = match ($tipo) {
            'limpieza'      => 'id_tareas_limpieza',
            'mantenimiento' => 'id_tareas_mantenimiento',
            'piscina'       => 'id_tareas_piscinas',
        };

        DB::table('vm_fotos')->insert([
            'nombre'     => 'Foto PWA',
            $fkCol       => $id,
            'file_foto'  => $path,
            'createuser' => $user->id,
            'createdat'  => now(),
            'updatedat'  => now(),
        ]);

        return response()->json(['ok' => true, 'path' => $path]);
    }

    public function duraciones()
    {
        $d = DB::table('master_duraciones')
            ->where('deleted', 0)
            ->orderBy('minutos')
            ->get(['id', 'nombre', 'minutos']);
        return response()->json($d);
    }

    // Fichaje

    public function fichajeHoy(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table("{$this->app}_fichaje")
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('fecha_fichaje', $hoy)
            ->first();

        return response()->json(['fichaje' => $fichaje]);
    }

    public function fichajeEntrada(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $existe = DB::table("{$this->app}_fichaje")
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('fecha_fichaje', $hoy)
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya has fichado entrada hoy'], 409);
        }

        DB::table("{$this->app}_fichaje")->insert([
            'fecha_fichaje' => $hoy,
            'control_user'  => json_encode([(string) $user->id]),
            'hora_inicio'   => now()->format('H:i:s'),
            'createuser'    => $user->id,
            'createdat'     => now(),
            'updatedat'     => now(),
        ]);

        return response()->json(['ok' => true, 'hora_inicio' => now()->format('H:i')]);
    }

    public function fichajeSalida(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table("{$this->app}_fichaje")
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('fecha_fichaje', $hoy)
            ->first();

        if (!$fichaje) {
            return response()->json(['error' => 'No has fichado entrada hoy'], 404);
        }
        if ($fichaje->hora_fin) {
            return response()->json(['error' => 'Ya has fichado salida hoy'], 409);
        }

        DB::table("{$this->app}_fichaje")
            ->where('id', $fichaje->id)
            ->update([
                'hora_fin'   => now()->format('H:i:s'),
                'updateuser' => $user->id,
                'updatedat'  => now(),
            ]);

        return response()->json(['ok' => true, 'hora_fin' => now()->format('H:i')]);
    }

    public function fichajePausa(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table("{$this->app}_fichaje")
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
            ->where('fecha_fichaje', $hoy)
            ->first();

        if (!$fichaje || !$fichaje->hora_inicio) {
            return response()->json(['error' => 'No has fichado entrada'], 404);
        }

        $update = ['updateuser' => $user->id, 'updatedat' => now()];

        if (!$fichaje->pausa_inicio) {
            $update['pausa_inicio'] = now()->format('H:i:s');
            $msg = 'Pausa iniciada';
        } elseif (!$fichaje->pausa_fin) {
            $update['pausa_fin'] = now()->format('H:i:s');
            $msg = 'Pausa finalizada';
        } else {
            return response()->json(['error' => 'La pausa ya esta registrada'], 409);
        }

        DB::table("{$this->app}_fichaje")->where('id', $fichaje->id)->update($update);

        return response()->json(['ok' => true, 'msg' => $msg]);
    }

    // Helpers

    private function authenticate(Request $request): object
    {
        $token = $this->bearerToken($request);

        if (!$token) {
            abort(401, 'Token requerido');
        }

        $row = DB::table('vm_pwa_tokens')
            ->where('token', $token)
            ->where('app', $this->app)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            abort(401, 'Token invalido o expirado');
        }

        DB::table('vm_pwa_tokens')->where('id', $row->id)->update(['last_seen_at' => now()]);

        $user = DB::table('vm_usuarios')
            ->where('id', $row->user_id)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            abort(401, 'Usuario no encontrado');
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
        return match ($tipo) {
            'limpieza'      => "{$this->app}_tareas_limpieza",
            'mantenimiento' => "{$this->app}_tareas_mantenimiento",
            'piscina'       => "{$this->app}_tareas_piscinas",
            default         => abort(400, 'Tipo de tarea invalido'),
        };
    }
}
